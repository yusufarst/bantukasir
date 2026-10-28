<?php

namespace App\Domain\Commands;

use App\Database\CommandPoints;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The command request receipts of ADR-009 section 1 (SCHEMA section 5.3,
 * `command_request_receipts`; guard G-25; D-089). Each write is a short
 * transaction of its own, never the command's:
 *
 * - record: on arrival, after the middleware and before the command runs —
 *   the actor, operation, intent, key, epoch and received time, never the
 *   payload; outcome PENDING.
 * - mark: after the command ends, by its own request, replacing only
 *   PENDING; a receipt settled meanwhile keeps its outcome and only gains an
 *   empty held code.
 * - settle: on a conclusive status check, every PENDING or HELD receipt of
 *   the operation and intent takes COMMITTED or NOT_COMMITTED with the
 *   settling identity and the checker, keeping its held code; the rows are
 *   locked in ascending order, and no session lock is taken. It runs in the
 *   caller's transaction: the fence's, or a short one of its own.
 * - holdStale: on an EPOCH_STALE answer, the intent's PENDING receipts of an
 *   earlier epoch become HELD with that code, in the caller's transaction.
 *
 * A COMMITTED, REJECTED or NOT_COMMITTED receipt never changes outcome.
 */
final class RequestReceipts
{
    public const string PENDING = 'PENDING';

    public const string COMMITTED = 'COMMITTED';

    public const string REJECTED = 'REJECTED';

    public const string HELD = 'HELD';

    public const string NOT_COMMITTED = 'NOT_COMMITTED';

    public function __construct(
        private readonly Clock $clock,
        private readonly CommandPoints $points,
    ) {}

    /**
     * Records the receipt of a request in a transaction of its own and returns
     * its key.
     */
    public function record(int $actorId, string $operation, string $sourceIntentId, string $idempotencyKey, string $recoveryEpoch, string $receivedAt): int
    {
        return DB::transaction(function () use ($actorId, $operation, $sourceIntentId, $idempotencyKey, $recoveryEpoch, $receivedAt): int {
            $id = DB::scalar(<<<'SQL'
                insert into command_request_receipts (actor_user_id, operation, source_intent_id, idempotency_key, recovery_epoch, received_at, outcome)
                values (?, ?, ?, ?, decode(?, 'hex'), ?, 'PENDING')
                returning id
                SQL, [$actorId, $operation, $sourceIntentId, $idempotencyKey, $recoveryEpoch, $receivedAt]);
            $this->points->afterOwnWrite('receipt');

            return is_int($id) ? $id : throw new InvalidArgumentException('The receipt has no key.');
        });
    }

    /**
     * Marks the outcome of the receipt's own request, in a transaction of its
     * own: only a PENDING receipt takes it; a receipt settled meanwhile keeps
     * its outcome and only fills an empty held code.
     */
    public function mark(int $receiptId, string $outcome, ?string $heldCode = null): void
    {
        if (! in_array($outcome, [self::COMMITTED, self::REJECTED, self::HELD, self::NOT_COMMITTED], true)) {
            throw new InvalidArgumentException(sprintf('%s is not an outcome of a request.', $outcome));
        }

        if (($outcome === self::HELD) !== ($heldCode !== null) || ($heldCode !== null && ! in_array($heldCode, [ErrorCode::IdempotencyConflict->value, ErrorCode::EpochStale->value], true))) {
            throw new InvalidArgumentException('A held request names why it is held, and only a held one.');
        }

        DB::transaction(function () use ($receiptId, $outcome, $heldCode): void {
            DB::update(
                "update command_request_receipts set outcome = ?, outcome_at = ?, held_code = coalesce(held_code, ?) where id = ? and outcome = 'PENDING'",
                [$outcome, $this->now(), $heldCode, $receiptId],
            );

            if ($heldCode !== null) {
                DB::update('update command_request_receipts set held_code = ? where id = ? and held_code is null', [$heldCode, $receiptId]);
            }

            $this->points->afterOwnWrite('marking');
        });
    }

    /**
     * Settles every PENDING or HELD receipt of the operation and intent, in
     * the caller's transaction, and returns how many it settled.
     */
    public function settle(string $operation, string $sourceIntentId, string $outcome, int $settledByCommandId, int $checkerId): int
    {
        if ($outcome !== self::COMMITTED && $outcome !== self::NOT_COMMITTED) {
            throw new InvalidArgumentException('A settlement is COMMITTED or NOT_COMMITTED (D-089).');
        }

        self::requireTransaction();

        $ids = $this->lockUnresolved($operation, $sourceIntentId, [self::PENDING, self::HELD]);

        if ($ids === []) {
            return 0;
        }

        $now = $this->now();

        foreach ($ids as $id) {
            DB::update(
                "update command_request_receipts set outcome = ?, outcome_at = ?, settled_by_command_id = ?, settled_by_user_id = ? where id = ? and outcome in ('PENDING', 'HELD')",
                [$outcome, $now, $settledByCommandId, $checkerId, $id],
            );
        }

        return count($ids);
    }

    /**
     * Holds the intent's PENDING receipts that carry an epoch other than the
     * current one, with the held code EPOCH_STALE, in the caller's
     * transaction (ADR-009 section 3).
     */
    public function holdStale(string $operation, string $sourceIntentId, ?string $currentEpoch): int
    {
        self::requireTransaction();

        $ids = array_map(
            static fn (object $row): int => is_int($row->id ?? null) ? $row->id : 0,
            DB::select(
                "select id from command_request_receipts where operation = ? and source_intent_id = ? and outcome = 'PENDING' and recovery_epoch is distinct from decode(?, 'hex') order by id for update",
                [$operation, $sourceIntentId, $currentEpoch ?? ''],
            ),
        );

        foreach ($ids as $id) {
            DB::update(
                "update command_request_receipts set outcome = 'HELD', outcome_at = ?, held_code = coalesce(held_code, 'EPOCH_STALE') where id = ? and outcome = 'PENDING'",
                [$this->now(), $id],
            );
        }

        return count($ids);
    }

    /**
     * The actors of the intent's receipts, whatever their outcome.
     *
     * @return list<int>
     */
    public function actorsOf(string $operation, string $sourceIntentId): array
    {
        $actors = [];

        foreach (DB::select('select distinct actor_user_id from command_request_receipts where operation = ? and source_intent_id = ? order by actor_user_id', [$operation, $sourceIntentId]) as $row) {
            if (is_int($row->actor_user_id ?? null)) {
                $actors[] = $row->actor_user_id;
            }
        }

        return $actors;
    }

    /**
     * The epochs, as hexadecimal, that the intent's receipts carried.
     *
     * @return list<string>
     */
    public function epochsOf(string $operation, string $sourceIntentId): array
    {
        $epochs = [];

        foreach (DB::select("select distinct encode(recovery_epoch, 'hex') as epoch from command_request_receipts where operation = ? and source_intent_id = ? order by 1", [$operation, $sourceIntentId]) as $row) {
            if (is_string($row->epoch ?? null)) {
                $epochs[] = $row->epoch;
            }
        }

        return $epochs;
    }

    /**
     * @param  list<string>  $outcomes
     * @return list<int>
     */
    private function lockUnresolved(string $operation, string $sourceIntentId, array $outcomes): array
    {
        $rows = DB::table('command_request_receipts')
            ->where('operation', $operation)
            ->where('source_intent_id', $sourceIntentId)
            ->whereIn('outcome', $outcomes)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        $ids = [];

        foreach ($rows as $id) {
            if (is_int($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new InvalidArgumentException('A settlement runs in the transaction of its check (D-089).');
        }
    }
}
