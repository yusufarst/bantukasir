<?php

namespace App\Http\Controllers\Inventory;

use App\DTO\Catalog\CategoryRowData;
use App\Http\Requests\Inventory\StockListRequest;
use App\Queries\Catalog\CategoryRows;
use App\Queries\Inventory\StockList;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-401, UXP-401 "Stok" (ROUTE_CONTRACTS RP-401; KS-08; BR-INV-40 to BR-INV-43; TASK-022
 * scope item 2): the stock attention of the active products with its filters, the categories
 * to filter by and the first page of RD-02, whose later refreshes the page reads itself
 * (INTERACTION_CONTRACTS section 3.3). Owner, OPERATIONS_ADMIN and the combined account, by the
 * route's gate of CAP-14; never a cost.
 */
final class StockController
{
    public function index(StockListRequest $request, StockList $stock, CategoryRows $categories): Response
    {
        $filters = $request->filters();

        return Inertia::render('inventory/Stock', [
            'filters' => ['q' => $filters['q'], 'state' => $filters['state'], 'category' => $filters['category'], 'perPage' => $filters['perPage']],
            'categories' => array_map(static fn (CategoryRowData $row): array => $row->toArray(), $categories->list()),
            'stock' => $stock($filters['q'], $filters['state'], $filters['category'], $filters['after'], $filters['perPage']),
        ]);
    }
}
