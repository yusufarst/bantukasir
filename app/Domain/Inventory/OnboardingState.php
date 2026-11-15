<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Onboarding\OnboardingStatus;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The installation's onboarding state (SCHEMA section 5.7; BR-INV-24; D-071, D-072), a service of the
 * Inventory module, which alone writes it. The bootstrap procedure inserts it NOT_STARTED (SEC-OP-01);
 * the onboarding commands of TASK-035 start and release it under its update lock, and the restore
 * procedures of TASK-037 hold it. Its presence key makes RELEASED possible only with the durable
 * release record of `onboarding_releases`, which this service writes with the release (RC-13), and
 * which, travelling with the business data, keeps onboarding final after a reinstallation, a server
 * migration or the restore of an earlier backup (G-26).
 */
final class OnboardingState
{
    public const string NOT_STARTED = 'NOT_STARTED';

    public const string ACTIVE = 'ACTIVE';

    public const string RELEASED = 'RELEASED';

    /** The kind of the release record that RC-13 writes (SCHEMA section 5.7). */
    private const string RELEASE = 'RELEASE';

    /** The kind of the release record that the restore reconciliation writes (SEC-OP-05; SCHEMA section 5.7). */
    private const string RESTORE_RECONCILIATION = 'RESTORE_RECONCILIATION';

    /** The Owner's outcomes of a restore hold (SCHEMA section 5.7; D-072). */
    public const string RELEASED_BEFORE_RESTORE = 'RELEASED_BEFORE_RESTORE';

    public const string NEVER_RELEASED = 'NEVER_RELEASED';

    /** A restore hold that waits for its reconciliation (D-072; G-26). */
    private const string UNRECONCILED_HOLD = 'exists (select 1 from onboarding_restore_holds h where not exists (select 1 from onboarding_restore_reconciliations r where r.hold_id = h.id))';

    /** The times of RP-412, in UTC as the pages read every instant. */
    private const string CHANGED_AT = "to_char(changed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as changed_at";

    private const string RELEASED_AT = "to_char(released_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as released_at";

    public function __construct(private readonly Clock $clock) {}

    /**
     * Inserts the state NOT_STARTED, at version 1, in the bootstrap's
     * transaction; a concurrent bootstrap waits on the primary key and then
     * fails (SCHEMA section 7; RACE-30).
     */
    public function insertInitial(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The onboarding state is inserted in the transaction of the bootstrap.');
        }

        DB::table('onboarding_state')->insert([
            'id' => 1,
            'state' => self::NOT_STARTED,
            'version' => 1,
            'changed_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
            'changed_by_user_id' => null,
        ]);
    }

    /**
     * The state, read without a lock, or null before the bootstrap.
     */
    public function current(): ?string
    {
        $state = DB::table('onboarding_state')->where('id', 1)->value('state');

        return is_string($state) ? $state : null;
    }

    /**
     * The state under the share lock of a posting (TR §7.1 step 2.3; SCHEMA section 7), or
     * null before the bootstrap: a start of onboarding waits for this lock, and a posting
     * that waited for a start reads the state that it committed (G-10; RACE-04).
     */
    public function lockShared(): ?string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The onboarding state is locked in the transaction of its command (TR-TX-01).');
        }

        $state = DB::table('onboarding_state')->where('id', 1)->sharedLock()->value('state');

        return is_string($state) ? $state : null;
    }

    /**
     * The status under the share lock of an opening command (TR §7.2, "Opening stock"; step 2.3),
     * with the hold and the release record read after it: the after-restore procedure sets a hold and
     * the release writes its record under the update lock of the same row, so neither changes while
     * this lock is held (SCH-31).
     */
    public function lockForEntry(): OnboardingStatus
    {
        self::requireTransaction();

        return $this->statusOf(DB::table('onboarding_state')->where('id', 1)->sharedLock()->first(['state', 'version']));
    }

    /**
     * The status under the update lock of a start or a release (TR §7.2, "Onboarding set or release";
     * step 2.3), which waits for every posting and opening command that holds the share lock, and which
     * they then wait for (RACE-04, RACE-32).
     */
    public function lockForChange(): OnboardingStatus
    {
        self::requireTransaction();

        return $this->statusOf(DB::table('onboarding_state')->where('id', 1)->lock('for no key update')->first(['state', 'version']));
    }

    /**
     * The status, read without a lock, for a page; null before the bootstrap.
     */
    public function status(): ?OnboardingStatus
    {
        $row = DB::table('onboarding_state')->where('id', 1)->first(['state', 'version']);

        return $row === null ? null : $this->statusOf($row);
    }

    /**
     * When the period started — the time of the state's last change while it is ACTIVE, the cutover
     * that the audit of the start records too — and when the release record was written; null where
     * there is none.
     *
     * @return array{changedAt: string|null, releasedAt: string|null}
     */
    public function times(): array
    {
        $changed = ((array) DB::table('onboarding_state')->where('id', 1)->selectRaw(self::CHANGED_AT)->first())['changed_at'] ?? null;
        $released = ((array) DB::table('onboarding_releases')->where('id', 1)->selectRaw(self::RELEASED_AT)->first())['released_at'] ?? null;

        return ['changedAt' => is_string($changed) ? $changed : null, 'releasedAt' => is_string($released) ? $released : null];
    }

    /**
     * RC-12: the state ACTIVE at the next version, from the version that the caller holds under the
     * update lock; the cutover is this time and actor (SCHEMA section 5.7).
     */
    public function start(OnboardingStatus $held, int $actorUserId, string $at): void
    {
        if (! $held->canStart()) {
            throw new LogicException('Onboarding starts only from NOT_STARTED, never released and not held (G-26).');
        }

        $this->change($held, self::ACTIVE, $actorUserId, $at);
    }

    /**
     * RC-13: the durable release record, then the state RELEASED at the next version; the deferred
     * presence key refuses a commit of RELEASED without the record (G-26).
     */
    public function release(OnboardingStatus $held, int $actorUserId, int $commandId, string $at): void
    {
        if (! $held->isOpen()) {
            throw new LogicException('Onboarding is released only from ACTIVE, never released and not held (G-26).');
        }

        DB::table('onboarding_releases')->insert([
            'id' => 1,
            'released_at' => $at,
            'kind' => self::RELEASE,
            'actor_user_id' => $actorUserId,
            'command_id' => $commandId,
        ]);

        $this->change($held, self::RELEASED, $actorUserId, $at);
    }

    /**
     * OP-04 (SEC-OP-04; SCHEMA section 5.7; D-072; G-26): when the restored state is not RELEASED, a
     * restore hold of that state with the epoch the same run set, and the version one on, from the status
     * that the caller holds under the update lock. The state, its last change and its author stay as the
     * restored data hold them, because the state did not change, so that RP-412 still reads when the
     * period began (TASK-035 R-10). Returns the hold's internal key, or null for a RELEASED state, which no
     * restore makes available again.
     */
    public function hold(OnboardingStatus $held, string $recoveryEpoch, string $at): ?int
    {
        self::requireTransaction();

        if ($held->state === self::RELEASED) {
            return null;
        }

        if (preg_match('/^(?:[0-9a-f]{2}){16,64}$/', $recoveryEpoch) !== 1) {
            throw new LogicException('A hold records the epoch of its after-restore run, as hexadecimal.');
        }

        $id = DB::table('onboarding_restore_holds')->insertGetId(['restored_state' => $held->state, 'recovery_epoch' => '\\x'.$recoveryEpoch, 'set_at' => $at]);
        $this->moveVersion($held);

        return is_int($id) ? $id : throw new LogicException('The database returned no key for the hold.');
    }

    /**
     * A restore hold as the restored data and the reconciliations hold it, read under the update lock
     * that the caller holds: the state it holds and whether the Owner's outcome is recorded; null when
     * there is no such hold.
     *
     * @return array{restoredState: string, reconciled: bool}|null
     */
    public function holdOf(int $holdId): ?array
    {
        self::requireTransaction();

        $state = DB::table('onboarding_restore_holds')->where('id', $holdId)->value('restored_state');

        if (! is_string($state)) {
            return null;
        }

        return ['restoredState' => $state, 'reconciled' => DB::table('onboarding_restore_reconciliations')->where('hold_id', $holdId)->exists()];
    }

    /**
     * OP-05 (SEC-OP-05; SCHEMA section 5.7; D-072): the Owner's outcome of one restore hold, under the
     * update lock that the caller holds (step 2.3). RELEASED_BEFORE_RESTORE writes, unless the data hold
     * it already, the release record of kind RESTORE_RECONCILIATION, the Owner its actor and no command,
     * and the state RELEASED at the next version, changed by the procedure; NEVER_RELEASED leaves the
     * state, which continues as the same period. The caller has refused NEVER_RELEASED for data that hold
     * a release record, which nothing undoes (G-26). Returns whether the state changed.
     */
    public function reconcileHold(OnboardingStatus $held, int $holdId, string $outcome, int $ownerId, string $note, string $at): bool
    {
        self::requireTransaction();

        if ($outcome !== self::RELEASED_BEFORE_RESTORE && $outcome !== self::NEVER_RELEASED) {
            throw new LogicException('A hold is decided released before the restore or never released (D-072).');
        }

        $released = $outcome === self::RELEASED_BEFORE_RESTORE && ! $held->released;

        if ($released) {
            DB::table('onboarding_releases')->insert([
                'id' => 1,
                'released_at' => $at,
                'kind' => self::RESTORE_RECONCILIATION,
                'actor_user_id' => $ownerId,
                'command_id' => null,
            ]);

            $changed = DB::table('onboarding_state')->where('id', 1)->where('version', $held->version)->update([
                'state' => self::RELEASED,
                'version' => $held->version + 1,
                'changed_at' => $at,
                'changed_by_user_id' => null,
            ]);

            if ($changed !== 1) {
                throw new LogicException('The locked onboarding state is at the version read under its lock.');
            }
        }

        DB::table('onboarding_restore_reconciliations')->insert([
            'hold_id' => $holdId,
            'outcome' => $outcome,
            'decided_by_user_id' => $ownerId,
            'operator_note' => $note,
            'recorded_at' => $at,
        ]);

        return $released;
    }

    /**
     * The update of the locked row to this state at the next version.
     */
    private function change(OnboardingStatus $held, string $state, int $actorUserId, string $at): void
    {
        self::requireTransaction();

        $changed = DB::table('onboarding_state')->where('id', 1)->where('version', $held->version)->update([
            'state' => $state,
            'version' => $held->version + 1,
            'changed_at' => $at,
            'changed_by_user_id' => $actorUserId,
        ]);

        if ($changed !== 1) {
            throw new LogicException('The locked onboarding state is at the version read under its lock.');
        }
    }

    /**
     * The locked row's version one on, its state, last change and author unchanged (a restore hold).
     */
    private function moveVersion(OnboardingStatus $held): void
    {
        $changed = DB::table('onboarding_state')->where('id', 1)->where('version', $held->version)->update(['version' => $held->version + 1]);

        if ($changed !== 1) {
            throw new LogicException('The locked onboarding state is at the version read under its lock.');
        }
    }

    /**
     * The status of a row of `onboarding_state`, with its hold and its release record.
     */
    private function statusOf(?object $row): OnboardingStatus
    {
        $values = $row === null ? [] : (array) $row;
        $state = $values['state'] ?? null;
        $version = $values['version'] ?? null;

        if (! is_string($state) || ! is_int($version)) {
            throw new LogicException('The bootstrap inserts the onboarding state (SEC-OP-01).');
        }

        $flags = (array) DB::selectOne('select '.self::UNRECONCILED_HOLD.' as held, exists (select 1 from onboarding_releases) as released');

        return new OnboardingStatus($state, $version, ($flags['held'] ?? null) === true, ($flags['released'] ?? null) === true);
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The onboarding state is locked in the transaction of its command (TR-TX-01).');
        }
    }
}
