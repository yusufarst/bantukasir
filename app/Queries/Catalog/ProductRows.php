<?php

namespace App\Queries\Catalog;

use App\DTO\Catalog\ProductRowData;
use App\Support\Numbers\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * What RD-01 and RD-06 share (ARC-23): the products with the price in force, each
 * row built into its DTO, and the search text made safe for LIKE. The text always
 * travels as a binding (TR-VAL-07), and a `%` or `_` in it matches only itself: a
 * user types text, never a pattern (THR-04).
 */
final class ProductRows
{
    /** The character that escapes LIKE's wildcards in every pattern here. */
    public const string ESCAPE = '!';

    /**
     * The product that a token is active on, compared exactly, in one statement with
     * the row that uses it: a correction changes the token's mapping in one
     * transaction, so a lookup sees its product before or after it, never none and
     * never both (SCHEMA section 5.6, RACE-18). It takes the token as its binding.
     */
    public const string TOKEN_PRODUCT = '(select am.product_id from barcode_tokens t join barcode_active_mappings am on am.token_id = t.id where t.token = ?)';

    /** Whether the product holds a barcode to print (UXI-163), by the index of its active mappings. */
    private const string ACTIVE_BARCODE = 'exists (select 1 from barcode_active_mappings active_barcode where active_barcode.product_id = p.id) as active_barcode';

    public static function query(): Builder
    {
        return DB::table('products', 'p')
            ->join('selling_price_revisions as r', 'r.id', '=', 'p.current_price_revision_id')
            ->select(['p.id', 'p.name', 'p.sku', 'p.base_unit', 'p.quantity_precision', 'p.active', 'p.photo_file_id', 'r.id as revision_id', 'r.amount'])
            ->selectRaw(self::ACTIVE_BARCODE);
    }

    /**
     * The text as a LIKE pattern that matches only itself.
     */
    public static function literal(string $text): string
    {
        return str_replace([self::ESCAPE, '%', '_'], [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'], $text);
    }

    public static function row(object $row): ProductRowData
    {
        $values = (array) $row;
        $id = $values['id'] ?? null;
        $name = $values['name'] ?? null;
        $sku = $values['sku'] ?? null;
        $unit = $values['base_unit'] ?? null;
        $precision = $values['quantity_precision'] ?? null;
        $active = $values['active'] ?? null;
        $photo = $values['photo_file_id'] ?? null;
        $revision = $values['revision_id'] ?? null;
        $amount = $values['amount'] ?? null;
        $activeBarcode = $values['active_barcode'] ?? null;

        if (! is_int($id) || ! is_string($name) || ! is_string($sku) || ! is_string($unit) || ! is_int($precision) || ! is_bool($active) || ! is_int($revision) || (! is_int($amount) && ! is_string($amount)) || ! is_bool($activeBarcode)) {
            throw new LogicException('A product row holds its columns (SCHEMA section 5.6).');
        }

        return new ProductRowData(
            $id,
            $name,
            $sku,
            $unit,
            $precision,
            Money::fromDatabase($amount)->toCanonical(),
            $revision,
            $active,
            // RD-07's thumbnail, by the file's internal key and never a storage
            // path (SEC-CH-06).
            is_int($photo) ? '/media/'.(string) $photo.'?variant=thumb' : null,
            $activeBarcode,
        );
    }
}
