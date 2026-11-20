<?php

namespace App\Actions\Operations;

use App\Database\CommandPoints;
use App\Domain\Access\Accounts;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Operations\Restore\RestoreRefused;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * OP-04, after a restore (SECURITY_MODEL SEC-OP-04 and section 14; ROUTE_CONTRACTS OP-04; TECHNICAL_RULES
 * section 7.2, "After a restore"; BACKUP_RECOVERY RB-01 step 12 and RB-02 step 6; ADR-007 section 6;
 * D-072; RACE-12; TASK-037 scope item 2). An operator procedure, without an envelope (TR-CMD-07), run
 * before any user other than the Owner reaches the restored data, in one transaction:
 *
 * - the installation state under its update locks (step 2.3): the recovery epoch, then the onboarding
 *   state;
 * - a recovery epoch never issued before, 256 random bits, so that a request created under an earlier
 *   epoch — also one of an earlier restore of the same backup — is refused EPOCH_STALE before any fact;
 * - every sign-in session revoked;
 * - when the restored onboarding state is not RELEASED, an onboarding restore hold of that state with
 *   the new epoch, the onboarding version one on, and the audit event ONBOARDING_RESTORE_HOLD with the
 *   procedure as actor and the operator's note (INTERACTION_CONTRACTS section 8).
 *
 * The epoch and the sessions are recorded in the security log after the transaction — the run and each
 * account whose sessions were removed (SEC-LOG-03) — by the procedure's framework; INTERACTION_CONTRACTS
 * section 8 names no business audit event for them, so a restore of RELEASED data writes none.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the epoch, 2 the sessions, 3 the hold with the version, 4 its
 * audit event.
 */
final class FenceAfterRestore
{
    /** The procedure's name in the allowlist of SCHEMA section 3.4 (App\Domain\Audit\Actor). */
    public const string PROCESS = 'after_restore';

    public function __construct(
        private readonly RecoveryEpoch $epoch,
        private readonly OnboardingState $onboarding,
        private readonly Accounts $accounts,
        private readonly AuditLog $audit,
        private readonly CommandPoints $points,
        private readonly Clock $clock,
    ) {}

    /**
     * @return array{restoredState: string, holdId: int|null, sessions: array<int, int>}
     *
     * @throws RestoreRefused
     */
    public function __invoke(string $operatorNote): array
    {
        /** @var array{restoredState: string, holdId: int|null, sessions: array<int, int>} $result */
        $result = DB::transaction(fn (): array => $this->fence($operatorNote));

        return $result;
    }

    /**
     * @return array{restoredState: string, holdId: int|null, sessions: array<int, int>}
     *
     * @throws RestoreRefused
     */
    private function fence(string $operatorNote): array
    {
        if ($this->epoch->lockForReplacement() === null) {
            throw new RestoreRefused('The restored data hold no recovery epoch, so they are no installation of BantuKasir: nothing was changed.');
        }

        if ($this->onboarding->current() === null) {
            throw new RestoreRefused('The restored data hold no onboarding state, so they are no installation of BantuKasir: nothing was changed.');
        }

        $status = $this->onboarding->lockForChange();
        $this->points->afterLock('2.3');

        $epoch = $this->epoch->replace();
        $this->points->afterWrite(1);

        $sessions = $this->accounts->revokeEverySession();
        $this->points->afterWrite(2);

        $hold = $this->onboarding->hold($status, $epoch, $this->clock->now()->format('Y-m-d H:i:s.uP'));

        if ($hold !== null) {
            $this->points->afterWrite(3);

            $this->audit->record(
                AuditCategory::Business,
                'ONBOARDING_RESTORE_HOLD',
                Actor::operatorProcedure(self::PROCESS),
                ['holdId' => $hold, 'restoredState' => $status->state, 'version' => $status->version + 1],
                subject: ['type' => 'onboarding', 'id' => 1],
                operatorNote: $operatorNote,
            );
            $this->points->afterWrite(4);
        }

        $this->points->beforeCommit();

        return ['restoredState' => $status->state, 'holdId' => $hold, 'sessions' => $sessions];
    }
}
