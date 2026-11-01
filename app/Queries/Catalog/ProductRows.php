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

    public static function query(): Builder
    {
        return DB::table('products', 'p')
            ->join('selling_price_revisions as r', 'r.id', '=', 'p.current_price_revision_id')
            ->select(['p.id', 'p.name', 'p.sku', 'p.base_unit', 'p.quantity_precision', 'p.active', 'p.photo_file_id', 'r.id as revision_id', 'r.amount']);
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

        if (! is_int($id) || ! is_string($name) || ! is_string($sku) || ! is_string($unit) || ! is_int($precision) || ! is_bool($active) || ! is_int($revision) || (! is_int($amount) && ! is_string($amount))) {
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
            // path (SEC-CH-06). TASK-018 sets the photo and serves its variant.
            is_int($photo) ? '/media/'.(string) $photo.'?variant=thumb' : null,
        );
    }
}
