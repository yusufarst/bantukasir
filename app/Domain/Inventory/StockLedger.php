<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\GuardedProduct;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The stock ledger and its synchronous balance, in the Inventory module (SCHEMA section
 * 5.7; BR-INV-02, BR-INV-03, BR-INV-05; ARC-20; G-03), the only writer of both (STK-12).
 * Every physical change is one movement, in the next place of its product's sequence, and
 * the balance changes in the same transaction: nothing else edits it. It opens no
 * transaction of its own: the Action that calls it owns the transaction and the locks
 * (TR-TX-01). It does not call Costing; the Action calls both (STK-11).
 */
final class StockLedger
{
    public const string RECEIPT = 'RECEIPT';

    public const string MANUAL_ISSUE = 'MANUAL_ISSUE';

    public const string ADJUSTMENT = 'ADJUSTMENT';

    /**
     * Whether the product has a movement, by the unique key of its sequence (BR-CAT-07).
     */
    public function hasMovement(int $productId): bool
    {
        return DB::table('stock_movements')->where('product_id', $productId)->exists();
    }

    /**
     * The positions of the products whose guards the caller holds, under the update locks of
     * their balances, ascending (TR §7.1 step 7); a product with no movement has no row to
     * lock, and its guard keeps every other posting from inserting one.
     *
     * @param  array<int, GuardedProduct>  $products  by key, in the order of the keys
     * @return array<int, StockPosition>
     */
    public function lockPositions(array $products): array
    {
        self::requireTransaction();

        $positions = [];

        foreach ($products as $product) {
            $positions[$product->id] = StockPosition::none($product->id, $product->quantityPrecision);
        }

        $rows = DB::table('stock_balances')->whereIn('product_id', array_keys($products))->orderBy('product_id')->lock('for no key update')->get(['product_id', 'on_hand', 'last_sequence']);

        foreach ($rows as $row) {
            $values = (array) $row;
            $id = $values['product_id'] ?? null;
            $onHand = $values['on_hand'] ?? null;
            $sequence = $values['last_sequence'] ?? null;

            if (! is_int($id) || ! is_string($onHand) || ! is_int($sequence)) {
                throw new LogicException('A stock balance holds its columns (SCHEMA section 5.7).');
            }

            $positions[$id] = new StockPosition($id, Quantity::fromDatabase($onHand, $products[$id]->quantityPrecision), $sequence, true);
        }

        return $positions;
    }

    /**
     * Writes a product's balance as the posting computed it: the first row with its first
     * movement, an update of the locked row afterwards.
     */
    public function writePosition(StockPosition $position, string $at): void
    {
        self::requireTransaction();

        if (! $position->stored) {
            DB::table('stock_balances')->insert([
                'product_id' => $position->productId,
                'on_hand' => $position->onHand->toCanonical(),
                'last_sequence' => $position->lastSequence,
                'updated_at' => $at,
            ]);

            return;
        }

        $changed = DB::table('stock_balances')->where('product_id', $position->productId)->update([
            'on_hand' => $position->onHand->toCanonical(),
            'last_sequence' => $position->lastSequence,
            'updated_at' => $at,
        ]);

        if ($changed !== 1) {
            throw new LogicException('A locked stock balance exists.');
        }
    }

    /**
     * Appends the RECEIPT of a receiving line, with the unit and precision of its guarded
     * product, and returns its internal key. Its evidence is Costing's, in this transaction:
     * the presence key refuses a commit without it (BR-INV-08).
     */
    public function appendReceipt(GuardedProduct $product, int $sequence, Quantity $quantity, int $receivingLineId, int $actorUserId, int $commandId, string $at): int
    {
        self::requireTransaction();

        return DB::table('stock_movements')->insertGetId([
            'product_id' => $product->id,
            'base_unit' => $product->baseUnit,
            'quantity_precision' => $product->quantityPrecision,
            'product_sequence' => $sequence,
            'movement_type' => self::RECEIPT,
            'quantity' => $quantity->toCanonical(),
            'receiving_line_id' => $receivingLineId,
            'line_quantity' => $quantity->toCanonical(),
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Appends the MANUAL_ISSUE of a manual issue line, the line's positive quantity taken out
     * of stock, with the unit and precision of its guarded product, and returns its internal
     * key. Its allocation is Costing's, in this transaction: the presence key refuses a commit
     * without it (BR-INV-08; ADR-008 section 2).
     */
    public function appendManualIssue(GuardedProduct $product, int $sequence, Quantity $quantity, int $manualIssueLineId, int $actorUserId, int $commandId, string $at): int
    {
        self::requireTransaction();

        if (! $quantity->isPositive()) {
            throw new LogicException('A manual issue line takes a positive quantity out of stock.');
        }

        return DB::table('stock_movements')->insertGetId([
            'product_id' => $product->id,
            'base_unit' => $product->baseUnit,
            'quantity_precision' => $product->quantityPrecision,
            'product_sequence' => $sequence,
            'movement_type' => self::MANUAL_ISSUE,
            'quantity' => $quantity->negated()->toCanonical(),
            'manual_issue_line_id' => $manualIssueLineId,
            'line_quantity' => $quantity->toCanonical(),
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Appends the ADJUSTMENT of an adjustment line, the line's signed quantity, with the unit and
     * precision of its guarded product, and returns its internal key. A positive one's cost
     * evidence, or a negative one's allocation, is Costing's, in this transaction: the presence
     * keys refuse a commit without it (BR-INV-08, BR-INV-32).
     */
    public function appendAdjustment(GuardedProduct $product, int $sequence, Quantity $signed, int $adjustmentLineId, int $actorUserId, int $commandId, string $at): int
    {
        self::requireTransaction();

        if ($signed->isZero()) {
            throw new LogicException('An adjustment line moves stock (SCHEMA section 5.7).');
        }

        return DB::table('stock_movements')->insertGetId([
            'product_id' => $product->id,
            'base_unit' => $product->baseUnit,
            'quantity_precision' => $product->quantityPrecision,
            'product_sequence' => $sequence,
            'movement_type' => self::ADJUSTMENT,
            'quantity' => $signed->toCanonical(),
            'adjustment_line_id' => $adjustmentLineId,
            'line_quantity' => $signed->toCanonical(),
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'command_id' => $commandId,
        ]);
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The stock ledger is locked and written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
