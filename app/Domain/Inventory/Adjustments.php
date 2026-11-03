<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The confirmed stock adjustments and their lines, in the Inventory module (SCHEMA section 5.7;
 * BR-INV-31 to BR-INV-33): the Owner's adjustment of stock to a count, with a reason, the count
 * reference and a reference. An adjustment exists only once it is confirmed, and is only ever
 * added: it never disguises or removes an earlier fact (BR-INV-33). A line holds a signed quantity
 * and no cost: a positive line's cost is the acquisition cost evidence of its movement, a negative
 * line's the issue allocation of its movement, both Costing's (BR-INV-32).
 */
final class Adjustments
{
    /**
     * Adds a confirmed adjustment, with its actor's name frozen (SCHEMA section 3.5), and returns
     * its internal key.
     */
    public function insertAdjustment(string $reason, string $countReference, string $reference, int $lineCount, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        self::requireTransaction();

        return DB::table('adjustments')->insertGetId([
            'reason' => $reason,
            'count_reference' => $countReference,
            'reference' => $reference,
            'line_count' => $lineCount,
            'confirmed_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds a line of this signed quantity, which must post its ADJUSTMENT in this transaction:
     * the deferred key refuses a commit without it. It returns its internal key.
     */
    public function insertLine(int $adjustmentId, int $lineNo, int $productId, Quantity $signed): int
    {
        self::requireTransaction();

        if ($signed->isZero()) {
            throw new LogicException('An adjustment line moves stock (SCHEMA section 5.7).');
        }

        return DB::table('adjustment_lines')->insertGetId([
            'adjustment_id' => $adjustmentId,
            'line_no' => $lineNo,
            'product_id' => $productId,
            'quantity' => $signed->toCanonical(),
        ]);
    }

    /**
     * The products of an adjustment's lines, each once, ascending: those that RP-402 is filtered
     * to after it (RC-09's location).
     *
     * @return list<int>
     */
    public function productsOf(int $adjustmentId): array
    {
        $ids = [];

        foreach (DB::table('adjustment_lines')->where('adjustment_id', $adjustmentId)->distinct()->orderBy('product_id')->pluck('product_id') as $id) {
            if (! is_int($id)) {
                throw new LogicException('An adjustment line names its product.');
            }

            $ids[] = $id;
        }

        return $ids;
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An adjustment is written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
