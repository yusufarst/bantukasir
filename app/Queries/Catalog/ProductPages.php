<?php

namespace App\Queries\Catalog;

use App\Domain\Media\StoredFiles;
use App\DTO\Catalog\ProductDetailData;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The reads of UXP-302 and UXP-304 (ROUTE_CONTRACTS RP-302, RP-304; BR-CAT-13; TASK-017
 * scope item 1): one product with its category and the price in force, read in one
 * statement, so that a price change is seen whole; its latest price revisions; and its
 * barcodes, the active ones first. A product is the whole shop's, so a missing one is
 * the only NOT_FOUND. The two lists are bounded by the most that a page shows, and have
 * no paging control (INTERACTION_INVENTORY UXI-168).
 */
final class ProductPages
{
    /** The most revisions that UXP-302 lists. */
    public const int PRICE_HISTORY = 50;

    /** The most barcodes that UXP-302 lists. */
    public const int BARCODES = 100;

    /** The time of a revision, as the pages receive every instant: UTC with microseconds. */
    private const string REVISION_COLUMNS = 'amount, actor_display_name, to_char(created_at at time zone \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') as started_at';

    /**
     * @throws NotFound
     */
    public function detail(mixed $identifier): ProductDetailData
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('products', 'p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->join('selling_price_revisions as r', 'r.id', '=', 'p.current_price_revision_id')
            ->where('p.id', $key)
            ->first(['p.id', 'p.sku', 'p.name', 'p.category_id', 'c.name as category_name', 'c.active as category_active', 'p.brand', 'p.base_unit', 'p.quantity_precision', 'r.id as revision_id', 'r.amount', 'p.minimum_stock', 'p.active', 'p.version', 'p.photo_file_id']);

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $sku = $values['sku'] ?? null;
        $name = $values['name'] ?? null;
        $category = $values['category_id'] ?? null;
        $categoryName = $values['category_name'] ?? null;
        $categoryActive = $values['category_active'] ?? null;
        $brand = $values['brand'] ?? null;
        $unit = $values['base_unit'] ?? null;
        $precision = $values['quantity_precision'] ?? null;
        $revision = $values['revision_id'] ?? null;
        $amount = $values['amount'] ?? null;
        $minimum = $values['minimum_stock'] ?? null;
        $active = $values['active'] ?? null;
        $version = $values['version'] ?? null;
        $photo = $values['photo_file_id'] ?? null;

        if (! is_int($id) || ! is_string($sku) || ! is_string($name) || ! is_int($category) || ! is_string($categoryName) || ! is_bool($categoryActive)
            || ! is_string($unit) || ! is_int($precision) || ! is_int($revision) || (! is_int($amount) && ! is_string($amount)) || ! is_string($minimum) || ! is_bool($active) || ! is_int($version)) {
            throw new LogicException('A product row holds its columns (SCHEMA section 5.6).');
        }

        return new ProductDetailData(
            $id,
            $sku,
            $name,
            $category,
            $categoryName,
            $categoryActive,
            is_string($brand) ? $brand : null,
            $unit,
            $precision,
            Money::fromDatabase($amount)->toCanonical(),
            $revision,
            Quantity::fromDatabase($minimum, $precision)->toCanonical(),
            $active,
            $version,
            // BR-CAT-07 is Inventory's to say, and the Catalog does not read it (STK-13): the
            // pages that show the lock ask Queries\Inventory\ProductStock (withUnitLocked).
            false,
            is_int($photo) ? StoredFiles::deliveryPath($photo) : null,
        );
    }

    /**
     * The product's active tokens, for UXP-306 and whether its labels can be printed
     * (BR-CAT-35; G-09): each with the mapping that RC-47 names.
     *
     * @return list<array{mappingId: int, token: string}>
     */
    public function activeBarcodes(int $productId): array
    {
        $rows = DB::table('barcode_active_mappings', 'a')
            ->join('barcode_tokens as t', 't.id', '=', 'a.token_id')
            ->where('a.product_id', $productId)
            ->orderBy('a.mapping_id')
            ->limit(self::BARCODES)
            ->get(['a.mapping_id', 't.token']);
        $barcodes = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $mapping = $values['mapping_id'] ?? null;
            $token = $values['token'] ?? null;

            if (! is_int($mapping) || ! is_string($token)) {
                throw new LogicException('An active mapping holds its columns (SCHEMA section 5.6).');
            }

            $barcodes[] = ['mappingId' => $mapping, 'token' => $token];
        }

        return $barcodes;
    }

    /**
     * The product's price revisions, the newest first (BR-CAT-13).
     *
     * @return list<array{amount: string, startedAt: string, actorDisplayName: string}>
     */
    public function priceHistory(int $productId): array
    {
        $rows = DB::table('selling_price_revisions')
            ->selectRaw(self::REVISION_COLUMNS)
            ->where('product_id', $productId)
            ->orderByDesc('id')
            ->limit(self::PRICE_HISTORY)
            ->get();
        $history = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $amount = $values['amount'] ?? null;
            $startedAt = $values['started_at'] ?? null;
            $actor = $values['actor_display_name'] ?? null;

            if ((! is_int($amount) && ! is_string($amount)) || ! is_string($startedAt) || ! is_string($actor)) {
                throw new LogicException('A price revision holds its columns (SCHEMA section 5.6).');
            }

            $history[] = ['amount' => Money::fromDatabase($amount)->toCanonical(), 'startedAt' => $startedAt, 'actorDisplayName' => $actor];
        }

        return $history;
    }

    /**
     * The product's barcodes: each mapping with its token, its kind, whether it is
     * active, and the reason class of its retirement.
     *
     * @return list<array{mappingId: int, token: string, kind: string, active: bool, reasonClass: string|null}>
     */
    public function barcodes(int $productId): array
    {
        $rows = DB::table('barcode_mappings', 'm')
            ->join('barcode_tokens as t', 't.id', '=', 'm.token_id')
            ->leftJoin('barcode_active_mappings as a', 'a.mapping_id', '=', 'm.id')
            ->leftJoin('barcode_mapping_retirements as x', 'x.mapping_id', '=', 'm.id')
            ->where('m.product_id', $productId)
            ->orderByRaw('a.mapping_id is null')
            ->orderBy('m.id')
            ->limit(self::BARCODES)
            ->get(['m.id', 't.token', 't.kind', 'a.mapping_id as active_mapping', 'x.reason_class']);
        $barcodes = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $mapping = $values['id'] ?? null;
            $token = $values['token'] ?? null;
            $kind = $values['kind'] ?? null;
            $reason = $values['reason_class'] ?? null;

            if (! is_int($mapping) || ! is_string($token) || ! is_string($kind)) {
                throw new LogicException('A barcode mapping holds its columns (SCHEMA section 5.6).');
            }

            $barcodes[] = ['mappingId' => $mapping, 'token' => $token, 'kind' => $kind, 'active' => ($values['active_mapping'] ?? null) !== null, 'reasonClass' => is_string($reason) ? $reason : null];
        }

        return $barcodes;
    }
}
