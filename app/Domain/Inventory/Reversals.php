<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's reversals, in the Inventory module (SCHEMA section 5.7, `reversals`; BR-INV-34 to
 * BR-INV-37), the only writer of them (STK-12). A reversal is the full, exact inverse of one OPENING,
 * RECEIPT or MANUAL_ISSUE, at most once per source — the unique key of its source refuses a second
 * (BR-INV-35; RACE-10) — and is only ever added: the source and its REVERSAL both stay in the ledger,
 * and the valuation cancels the pair logically (BR-COST-32). It opens no transaction of its own: the
 * Action owns the transaction and the locks (TR-TX-01).
 */
final class Reversals
{
    /**
     * A movement as a reversal reads it, without a lock — a fact (SCH-31) — or null when it does not
     * exist.
     */
    public function source(int $movementId): ?ReversalSource
    {
        $row = DB::table('stock_movements')->where('id', $movementId)->first(['id', 'product_id', 'movement_type', 'quantity', 'quantity_precision', 'product_sequence']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $product = $values['product_id'] ?? null;
        $type = $values['movement_type'] ?? null;
        $quantity = $values['quantity'] ?? null;
        $precision = $values['quantity_precision'] ?? null;
        $sequence = $values['product_sequence'] ?? null;

        if (! is_int($product) || ! is_string($type) || ! is_string($quantity) || ! is_int($precision) || ! is_int($sequence)) {
            throw new LogicException('A movement holds its columns (SCHEMA section 5.7).');
        }

        return new ReversalSource($movementId, $product, $type, Quantity::fromDatabase($quantity, $precision), $sequence);
    }

    /**
     * Whether the movement has its reversal, read under its product's guard, which every reversal of
     * it holds (RACE-10).
     */
    public function isReversed(int $movementId): bool
    {
        return $this->of($movementId) !== null;
    }

    /**
     * The reversal of the movement, or null: read under its product's guard, which every reversal of
     * it holds (RACE-10).
     */
    public function of(int $movementId): ?int
    {
        $reversal = DB::table('reversals')->where('original_movement_id', $movementId)->value('id');

        return is_int($reversal) ? $reversal : null;
    }

    /**
     * Adds the reversal of this source, with its actor's name frozen (SCHEMA section 3.5), and returns
     * its internal key. Its REVERSAL must post in this transaction: the deferred key refuses a commit
     * without it.
     */
    public function insert(ReversalSource $source, string $reason, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A reversal is written inside the transaction of its Action (TR-TX-01).');
        }

        if (! $source->eligible()) {
            throw new LogicException('Only an OPENING, a RECEIPT or a MANUAL_ISSUE is reversed (BR-INV-36).');
        }

        return DB::table('reversals')->insertGetId([
            'original_movement_id' => $source->movementId,
            'product_id' => $source->productId,
            'original_type' => $source->movementType,
            'original_quantity' => $source->quantity->toCanonical(),
            'inverse_quantity' => $source->inverse()->toCanonical(),
            'reason' => $reason,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * The REVERSAL movement of a committed reversal: RC-10's location is its RP-403.
     */
    public function movementOf(int $reversalId): ?int
    {
        $movement = DB::table('stock_movements')->where('reversal_id', $reversalId)->value('id');

        return is_int($movement) ? $movement : null;
    }
}
