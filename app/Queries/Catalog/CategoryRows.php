<?php

namespace App\Queries\Catalog;

use App\DTO\Catalog\CategoryRowData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The categories for the catalogue pages (ROUTE_CONTRACTS RP-301, RP-303, RP-304,
 * RP-307; BR-CAT-09): by name, then internal identifier. RP-307 takes no paging
 * parameter, so the list is bounded instead; a shop's categories stay far below the
 * bound.
 */
final class CategoryRows
{
    public const int LIST_BOUND = 500;

    /**
     * Every category, or those of one status.
     *
     * @param  'active'|'inactive'|string|null  $status
     * @return list<CategoryRowData>
     */
    public function list(?string $status = null): array
    {
        $query = self::query();

        if ($status !== null) {
            $query->where('active', $status === 'active');
        }

        return self::rows($query);
    }

    /**
     * What a product form offers: the active categories, and the one the product
     * already has, which stays assignable to it (BR-CAT-09; RC-18).
     *
     * @return list<CategoryRowData>
     */
    public function choices(?int $keep = null): array
    {
        return self::rows(self::query()->where(static function (Builder $match) use ($keep): void {
            $match->where('active', true);

            if ($keep !== null) {
                $match->orWhere('id', $keep);
            }
        }));
    }

    private static function query(): Builder
    {
        return DB::table('categories')->orderBy('name')->orderBy('id')->limit(self::LIST_BOUND);
    }

    /**
     * @return list<CategoryRowData>
     */
    private static function rows(Builder $query): array
    {
        $rows = [];

        foreach ($query->get(['id', 'name', 'active', 'version']) as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $name = $values['name'] ?? null;
            $active = $values['active'] ?? null;
            $version = $values['version'] ?? null;

            if (! is_int($id) || ! is_string($name) || ! is_bool($active) || ! is_int($version)) {
                throw new LogicException('A category row holds its columns (SCHEMA section 5.6).');
            }

            $rows[] = new CategoryRowData($id, $name, $active, $version);
        }

        return $rows;
    }
}
