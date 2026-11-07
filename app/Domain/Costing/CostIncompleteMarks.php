<?php

namespace App\Domain\Costing;

use App\Domain\Audit\Actor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The marks that a cost source's reporting is incomplete, and their clearances, in the Costing
 * module (SCHEMA section 5.8, `cost_incomplete_marks` and `cost_incomplete_mark_clearances`; D-034,
 * D-063; BR-COST-27; G-21), the only writer of them (STK-12). A mark is recorded once per refused
 * intent, after a cost correction or completion was refused REPLAY_REJECTED, in a transaction of
 * its own; it is active while it has no clearance, and it is cleared only by a later successful
 * correction of the same cost source, a reversal of its movement (TASK-026) or the cost recovery
 * procedure (TASK-036) — never by editing: both tables are only ever added to. Every writer holds
 * the product's guard. It opens no transaction of its own (TR-TX-01).
 */
final class CostIncompleteMarks
{
    public const string MOVEMENT_CAP = 'MOVEMENT_CAP';

    public const string TIME_BUDGET = 'TIME_BUDGET';

    public const string UNSAFE_HISTORY = 'UNSAFE_HISTORY';

    public const string LATER_CORRECTION = 'LATER_CORRECTION';

    /**
     * Whether the refused intent already has its mark, so that a repeat of it writes none.
     */
    public function existsFor(string $operation, string $sourceIntentId): bool
    {
        return DB::table('cost_incomplete_marks')->where('rejected_operation', $operation)->where('rejected_source_intent_id', $sourceIntentId)->exists();
    }

    /**
     * Adds the mark of a refused correction of this cost source, with the cause, the number of
     * corrections the refused attempt saw and the Owner who attempted it, and returns its key.
     */
    public function insert(CostSource $source, string $operation, string $sourceIntentId, string $rejectionCode, int $correctionsSeen, int $actorUserId, string $actorDisplayName, string $at): int
    {
        self::requireTransaction();

        if (! in_array($rejectionCode, [self::MOVEMENT_CAP, self::TIME_BUDGET, self::UNSAFE_HISTORY], true)) {
            throw new LogicException('A mark names the cause of the refusal (SCHEMA section 5.8).');
        }

        return DB::table('cost_incomplete_marks')->insertGetId([
            'evidence_id' => $source->evidenceId,
            'product_id' => $source->productId,
            'evidence_movement_id' => $source->movementId,
            'rejected_operation' => $operation,
            'rejected_source_intent_id' => $sourceIntentId,
            'rejection_code' => $rejectionCode,
            'corrections_seen' => $correctionsSeen,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
        ]);
    }

    /**
     * The active marks of an evidence — those without a clearance — in ascending order.
     *
     * @return list<int>
     */
    public function activeOf(int $evidenceId): array
    {
        $marks = [];

        foreach (self::active()->where('k.evidence_id', $evidenceId)->orderBy('k.id')->pluck('k.id') as $mark) {
            if (! is_int($mark)) {
                throw new LogicException('A mark has its key.');
            }

            $marks[] = $mark;
        }

        return $marks;
    }

    /**
     * Clears these marks of the cost source by its later correction, in the transaction that wrote
     * that correction or in the mark's own, with that transaction's actor and command, if any.
     *
     * @param  list<int>  $markIds
     */
    public function clearByCorrection(array $markIds, CostSource $source, int $correctionId, Actor $actor, ?int $commandId, string $at): void
    {
        self::requireTransaction();

        foreach ($markIds as $mark) {
            DB::table('cost_incomplete_mark_clearances')->insert([
                'mark_id' => $mark,
                'evidence_id' => $source->evidenceId,
                'evidence_movement_id' => $source->movementId,
                'clearing_kind' => self::LATER_CORRECTION,
                'clearing_correction_id' => $correctionId,
                'cleared_at' => $at,
                'actor_kind' => $actor->kind,
                'actor_user_id' => $actor->userId,
                'actor_process' => $actor->process,
                'actor_display_name' => $actor->displayName,
                'command_id' => $commandId,
            ]);
        }
    }

    /**
     * The marks without a clearance, as `k`.
     */
    private static function active(): Builder
    {
        return DB::table('cost_incomplete_marks', 'k')->whereNotExists(static function (Builder $cleared): void {
            $cleared->from('cost_incomplete_mark_clearances', 'x')->whereColumn('x.mark_id', 'k.id');
        });
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A mark and its clearance are written inside a transaction that holds the product\'s guard (SCHEMA section 5.8).');
        }
    }
}
