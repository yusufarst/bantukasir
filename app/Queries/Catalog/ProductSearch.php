<?php

namespace App\Queries\Catalog;

use Illuminate\Database\Query\Builder;

/**
 * RD-01, the shared Product Search (ROUTE_CONTRACTS RD-01; INTERACTION_CONTRACTS
 * section 3.1; BR-CAT-23 to BR-CAT-27; ARC-23; TASK-015 scope item 3). The order:
 * the exact SKU, then the SKUs that start with the text, then the names that hold
 * it anywhere; within each, by name, then internal identifier, the stable total
 * order of TR-PAGE-01. Every comparison is without regard to letter case through
 * lower() in the database, the only authority for it (SCH-14), so that each uses
 * its index of SCHEMA section 11. TASK-016 adds the exact barcode before them.
 *
 * Results are paged by the server: at most 100 per page and one row more read to
 * say whether another page exists; never a total, which would count every match
 * on each keystroke (TR-PAGE-05). Inactive products are in the results with their
 * active flag, read-only for the page (BR-CAT-27), unless `status` filters them.
 */
final class ProductSearch
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /**
     * One page of results.
     *
     * @return array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool}
     */
    public function __invoke(SearchFilters $filters): array
    {
        $rows = $this->builder($filters)->get()->all();
        $page = array_slice($rows, 0, $filters->perPage);

        return [
            'rows' => array_values(array_map(static fn (object $row): array => ProductRows::row($row)->toArray(), $page)),
            'page' => $filters->page,
            'perPage' => $filters->perPage,
            'hasMore' => count($rows) > $filters->perPage,
        ];
    }

    /**
     * The query of a page and one row more, in the order of BR-CAT-24 and
     * BR-CAT-25; public so that a test reads its plan (AC-015-03).
     */
    public function builder(SearchFilters $filters): Builder
    {
        $query = ProductRows::query();

        if ($filters->q !== '') {
            $exact = $filters->q;
            $pattern = ProductRows::literal($filters->q);

            $query->where(static function (Builder $match) use ($exact, $pattern): void {
                $match->whereRaw('lower(p.sku) = lower(?)', [$exact])
                    ->orWhereRaw("lower(p.sku) like lower(?) || '%' escape '!'", [$pattern])
                    ->orWhereRaw("lower(p.name) like '%' || lower(?) || '%' escape '!'", [$pattern]);
            });

            $query->orderByRaw("case when lower(p.sku) = lower(?) then 1 when lower(p.sku) like lower(?) || '%' escape '!' then 2 else 3 end", [$exact, $pattern]);
        }

        if ($filters->category !== null) {
            $query->where('p.category_id', $filters->category);
        }

        if ($filters->brand !== null) {
            $query->whereRaw('lower(p.brand) = lower(?)', [$filters->brand]);
        }

        if ($filters->status !== null) {
            $query->where('p.active', $filters->status === 'active');
        }

        return $query->orderBy('p.name')->orderBy('p.id')
            ->offset(($filters->page - 1) * $filters->perPage)
            ->limit($filters->perPage + 1);
    }
}
