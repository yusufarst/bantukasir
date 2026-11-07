<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Cost;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's cost corrections and completions, in the Costing module (SCHEMA section 5.8,
 * `cost_corrections`; D-034; BR-COST-20 to BR-COST-22; G-15), the only writer of them (STK-12). A
 * correction is only ever added: the effective value of an evidence is the new amount of its
 * correction with the highest number, or its original amount when it has none, read from these
 * rows and never stored a second time (SCH-01). Completing an UNKNOWN cost is a correction like any
 * other (BR-COST-22). The Action of RC-15 holds the product's guard while it reads the latest
 * correction and adds the next one, so that its old value is the effective value and its number the
 * next (G-15); the unique key refuses a second correction with the same number. It opens no
 * transaction of its own (TR-TX-01).
 */
final class CostCorrections
{
    /**
     * The cost source of an evidence, with its movement's place in the product's sequence, or null
     * when no evidence has this key. Evidence and movements are facts, so this needs no lock.
     */
    public function source(int $evidenceId): ?CostSource
    {
        $row = DB::table('acquisition_cost_evidence as e')
            ->join('stock_movements as m', 'm.id', '=', 'e.movement_id')
            ->where('e.id', $evidenceId)
            ->first(['e.id', 'e.product_id', 'e.movement_id', 'm.product_sequence', 'e.movement_type', 'e.cost_status', 'e.total_amount', 'e.zero_cost_note']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $product = $values['product_id'] ?? null;
        $movement = $values['movement_id'] ?? null;
        $sequence = $values['product_sequence'] ?? null;
        $type = $values['movement_type'] ?? null;
        $status = $values['cost_status'] ?? null;
        $amount = $values['total_amount'] ?? null;
        $note = $values['zero_cost_note'] ?? null;

        if (! is_int($id) || ! is_int($product) || ! is_int($movement) || ! is_int($sequence) || ! is_string($type) || ! is_string($status)
            || ($amount !== null && ! is_string($amount)) || ($note !== null && ! is_string($note))) {
            throw new LogicException('A cost evidence and its movement hold their columns (SCHEMA sections 5.7 and 5.8).');
        }

        return new CostSource($id, $product, $movement, $sequence, $type, Cost::fromDatabase($amount, $status), $note);
    }

    /**
     * The latest correction of an evidence, or null when it has none: what the effective value and
     * the next number are read from, under the product's guard (G-15).
     */
    public function latest(int $evidenceId): ?StoredCorrection
    {
        $row = DB::table('cost_corrections')->where('evidence_id', $evidenceId)->orderByDesc('correction_no')->first(['id', 'correction_no', 'new_amount', 'zero_cost_note']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $number = $values['correction_no'] ?? null;
        $amount = $values['new_amount'] ?? null;
        $note = $values['zero_cost_note'] ?? null;

        if (! is_int($id) || ! is_int($number) || ! is_string($amount) || ($note !== null && ! is_string($note))) {
            throw new LogicException('A cost correction holds its columns (SCHEMA section 5.8).');
        }

        return new StoredCorrection($id, $number, Cost::fromDatabase($amount), $note);
    }

    /**
     * The product of a correction, or null when no correction has this key.
     */
    public function productOf(int $correctionId): ?int
    {
        $product = DB::table('cost_corrections')->where('id', $correctionId)->value('product_id');

        return is_int($product) ? $product : null;
    }

    /**
     * Adds the correction of this number to the evidence whose product's guard the caller holds,
     * with the effective value before it, the new value — always KNOWN — and a zero's evidence or
     * reason, the reason and its actor's name frozen (SCHEMA section 3.5), and returns its key.
     */
    public function insert(CostSource $source, int $correctionNo, Cost $old, Cost $new, ?string $zeroNote, string $reason, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A cost correction is written in the transaction of its replay (BR-COST-25).');
        }

        if (! $new->isKnown()) {
            throw new LogicException('A correction always records a new value; UNKNOWN is a status, never a value (BR-COST-03).');
        }

        return DB::table('cost_corrections')->insertGetId([
            'evidence_id' => $source->evidenceId,
            'product_id' => $source->productId,
            'correction_no' => $correctionNo,
            'old_status' => $old->isKnown() ? AcquisitionCost::KNOWN : AcquisitionCost::UNKNOWN,
            'old_amount' => $old->isKnown() ? $old->toCanonical() : null,
            'new_amount' => $new->toCanonical(),
            'zero_cost_note' => $zeroNote,
            'reason' => $reason,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }
}
