<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Cost;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The current valuations, in the Costing module (SCHEMA section 5.8; ARC-21; ADR-008 sections
 * 4.2 and 7), the only writer of them (STK-12). A posting holds the product guard, locks the
 * row after the stock balance (TR §7.1 step 7) and publishes it in its transaction: the row
 * is inserted with the product's first movement and updated with each later one. It opens no
 * transaction of its own (TR-TX-01).
 */
final class InventoryValuations
{
    /**
     * The valuations of the products whose guards the caller holds, under their update locks,
     * ascending; a product with no movement has no row to lock.
     *
     * @param  list<int>  $productIds
     * @return array<int, ProductValuation>
     */
    public function lock(array $productIds): array
    {
        self::requireTransaction();

        $valuations = [];

        foreach ($productIds as $productId) {
            $valuations[$productId] = ProductValuation::none($productId);
        }

        $rows = DB::table('inventory_valuations')->whereIn('product_id', $productIds)->orderBy('product_id')->lock('for no key update')->get(['product_id', 'value_status', 'value', 'publication_no', 'last_sequence']);

        foreach ($rows as $row) {
            $values = (array) $row;
            $id = $values['product_id'] ?? null;
            $status = $values['value_status'] ?? null;
            $value = $values['value'] ?? null;
            $publication = $values['publication_no'] ?? null;
            $sequence = $values['last_sequence'] ?? null;

            if (! is_int($id) || ! is_string($status) || ! is_int($publication) || ! is_int($sequence)) {
                throw new LogicException('A valuation holds its columns (SCHEMA section 5.8).');
            }

            $valuations[$id] = new ProductValuation($id, Cost::fromDatabase(is_string($value) ? $value : null, $status), $publication, $sequence, true);
        }

        return $valuations;
    }

    /**
     * Publishes a product's valuation as the posting computed it. An UNKNOWN value has no
     * amount, and never reads as zero (BR-COST-03).
     */
    public function publish(ProductValuation $valuation, string $at): void
    {
        self::requireTransaction();

        $columns = [
            'value_status' => $valuation->value->isKnown() ? AcquisitionCost::KNOWN : AcquisitionCost::UNKNOWN,
            'value' => $valuation->value->isKnown() ? $valuation->value->toCanonical() : null,
            'publication_no' => $valuation->publicationNo,
            'last_sequence' => $valuation->lastSequence,
            'published_at' => $at,
        ];

        if (! $valuation->stored) {
            DB::table('inventory_valuations')->insert(['product_id' => $valuation->productId, ...$columns]);

            return;
        }

        $changed = DB::table('inventory_valuations')->where('product_id', $valuation->productId)->update($columns);

        if ($changed !== 1) {
            throw new LogicException('A locked valuation exists.');
        }
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A valuation is locked and published inside the transaction of its Action (TR-TX-01).');
        }
    }
}
