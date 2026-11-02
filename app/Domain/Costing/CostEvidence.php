<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The acquisition cost evidence of a stock increase, in the Costing module (SCHEMA section
 * 5.8; BR-COST-02 to BR-COST-05), which only this module writes (STK-12). It is the cost at
 * confirmation that D-047 shows on a receiving document, and is only ever added: a later
 * change of the cost is a correction (BR-COST-20). The posting Action calls it beside the
 * Inventory module, in its transaction (STK-11).
 */
final class CostEvidence
{
    /**
     * Adds the evidence of a movement of this product, type and quantity, which its key to
     * the movement holds, with its actor's name frozen (SCHEMA section 3.5), and returns its
     * internal key.
     */
    public function insert(int $movementId, int $productId, string $movementType, Quantity $quantity, AcquisitionCost $cost, ?string $reference, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Cost evidence is written in the transaction of its movement (BR-INV-08).');
        }

        return DB::table('acquisition_cost_evidence')->insertGetId([
            'movement_id' => $movementId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'quantity' => $quantity->toCanonical(),
            'cost_status' => $cost->status(),
            'total_amount' => $cost->amount(),
            'zero_cost_note' => $cost->zeroNote,
            'reference' => $reference,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }
}
