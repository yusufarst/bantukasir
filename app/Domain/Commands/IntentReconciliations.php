<?php

namespace App\Domain\Commands;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's reconciliation of a request reported from an earlier epoch (ADR-009 section 3; SCHEMA
 * section 5.3, `stale_intent_reports` and `intent_reconciliations`; D-073), which the restore
 * reconciliation OP-05 records (TASK-037): its one outcome is NOT_COMMITTED in the restored data, with a
 * fence of the report's operation and intent in the current epoch, so that the intent can never commit
 * afterwards. A report is reconciled at most once: the reconciliation's key is the report's.
 */
final class IntentReconciliations
{
    public const string NOT_COMMITTED = 'NOT_COMMITTED';

    public function __construct(private readonly Clock $clock) {}

    /**
     * The report of this key — its operation, intent, actor and reported epoch as hexadecimal — and
     * whether it is reconciled; null when there is no such report.
     *
     * @return array{operation: string, intent: string, actorUserId: int, epoch: string, reconciled: bool}|null
     */
    public function report(int $reportId): ?array
    {
        $row = DB::selectOne(
            "select operation, source_intent_id::text as intent, actor_user_id, encode(reported_epoch, 'hex') as epoch,
                    exists (select 1 from intent_reconciliations r where r.report_id = s.id) as reconciled
             from stale_intent_reports s where id = ?",
            [$reportId],
        );

        if ($row === null) {
            return null;
        }

        $values = (array) $row;

        if (! is_string($values['operation'] ?? null) || ! is_string($values['intent'] ?? null) || ! is_int($values['actor_user_id'] ?? null) || ! is_string($values['epoch'] ?? null)) {
            throw new LogicException('A stale-intent report names its operation, intent, actor and epoch.');
        }

        return ['operation' => $values['operation'], 'intent' => $values['intent'], 'actorUserId' => $values['actor_user_id'], 'epoch' => $values['epoch'], 'reconciled' => ($values['reconciled'] ?? null) === true];
    }

    /**
     * Records the reconciliation of the report with its fence and the Owner who decided, in the
     * caller's transaction, which also wrote the fence and settles the intent's receipts.
     */
    public function record(int $reportId, int $fenceId, int $ownerId, string $operatorNote): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A reconciliation is written in the transaction of its fence (ADR-009 section 3).');
        }

        DB::table('intent_reconciliations')->insert([
            'report_id' => $reportId,
            'outcome' => self::NOT_COMMITTED,
            'fence_command_id' => $fenceId,
            'decided_by_user_id' => $ownerId,
            'operator_note' => $operatorNote,
            'recorded_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
