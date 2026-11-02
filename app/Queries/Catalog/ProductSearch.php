<?php

namespace App\Queries\Catalog;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RD-01, the shared Product Search (ROUTE_CONTRACTS RD-01; INTERACTION_CONTRACTS
 * section 3.1; BR-CAT-23 to BR-CAT-27; ARC-23; TASK-015 scope item 3, TASK-016 scope
 * item 4). The order: the product a token is active on, the token compared exactly;
 * the exact SKU, then the SKUs that start with the text, then the names that hold it
 * anywhere; within each, by name, then internal identifier, the stable total order of
 * TR-PAGE-01. Every comparison of a SKU or a name is without regard to letter case
 * through lower() in the database, the only authority for it (SCH-14), so that each
 * uses its index of SCHEMA section 11.
 *
 * Results are paged by the server: at most 100 per page and one row more read to
 * say whether another page exists; never a total, which would count every match
 * on each keystroke (TR-PAGE-05). Inactive products are in the results with their
 * active flag, read-only for the page (BR-CAT-27), unless `status` filters them.
 *
 * The mode `exact` is the lookup of a scanned or typed code: the product a token is
 * active on, else the product of that exact SKU; at most one product, marked active
 * or not, and no other filter (TASK-016). A retired token leads to no product.
 *
 * The context `correction` answers active products only (INTERACTION_CONTRACTS section 3.1;
 * TASK-020). In the mode `exact` that is judged on the code's own product, never in the
 * query, where it could pass over that product for another's SKU: a code whose product is
 * inactive answers none.
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

        if ($filters->exact && $filters->activeOnly) {
            $rows = array_values(array_filter($rows, static fn (object $row): bool => (((array) $row)['active'] ?? null) === true));
        }

        $perPage = $filters->exact ? 1 : $filters->perPage;
        $page = array_slice($rows, 0, $perPage);

        return [
            'rows' => array_map(static fn (object $row): array => ProductRows::row($row)->toArray(), $page),
            'page' => $filters->page,
            'perPage' => $perPage,
            'hasMore' => count($rows) > $perPage,
        ];
    }

    /**
     * The query of a page and one row more, in the order of BR-CAT-24 and
     * BR-CAT-25; public so that a test reads its plan (AC-015-03).
     */
    public function builder(SearchFilters $filters): Builder
    {
        if ($filters->exact) {
            return $this->exactBuilder($filters->q);
        }

        $query = ProductRows::query();

        if ($filters->q !== '') {
            $exact = $filters->q;
            $pattern = ProductRows::literal($filters->q);

            $query->where(static function (Builder $match) use ($exact, $pattern): void {
                $match->whereRaw('p.id = '.ProductRows::TOKEN_PRODUCT, [$exact])
                    ->orWhereRaw('lower(p.sku) = lower(?)', [$exact])
                    ->orWhereRaw("lower(p.sku) like lower(?) || '%' escape '!'", [$pattern])
                    ->orWhereRaw("lower(p.name) like '%' || lower(?) || '%' escape '!'", [$pattern]);
            });

            $query->orderByRaw('case when p.id = '.ProductRows::TOKEN_PRODUCT." then 0 when lower(p.sku) = lower(?) then 1 when lower(p.sku) like lower(?) || '%' escape '!' then 2 else 3 end", [$exact, $exact, $pattern]);
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

        if ($filters->activeOnly) {
            $query->where('p.active', true);
        }

        // RC-31's location: the products of one import (TASK-019).
        if ($filters->import !== null) {
            $query->whereIn('p.created_command_id', DB::table('csv_product_imports')->select('command_id')->where('id', $filters->import));
        }

        return $query->orderBy('p.name')->orderBy('p.id')
            ->offset(($filters->page - 1) * $filters->perPage)
            ->limit($filters->perPage + 1);
    }

    /**
     * The one product of a code, in one statement: its token's before its SKU's.
     */
    private function exactBuilder(string $code): Builder
    {
        return ProductRows::query()
            ->where(static function (Builder $match) use ($code): void {
                $match->whereRaw('p.id = '.ProductRows::TOKEN_PRODUCT, [$code])
                    ->orWhereRaw('lower(p.sku) = lower(?)', [$code]);
            })
            ->orderByRaw('case when p.id = '.ProductRows::TOKEN_PRODUCT.' then 0 else 1 end', [$code])
            ->limit(1);
    }
}
