<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\StockListRequest;
use App\Queries\Inventory\StockList;
use Illuminate\Http\JsonResponse;

/**
 * RD-02, `GET /data/stock` (ROUTE_CONTRACTS section 3; INTERACTION_CONTRACTS section 3.3;
 * BR-INV-41; TASK-022 scope item 3): the rows of RP-401 with their state, the numbers of Habis
 * and Menipis products and the as-of time, from one statement. The page reads it when it opens,
 * at least every 30 seconds while visible, on focus and after a command, as background requests
 * (RT-10), and for "Muat lebih banyak". It writes nothing.
 */
final class StockDataController
{
    public function __invoke(StockListRequest $request, StockList $stock): JsonResponse
    {
        $filters = $request->filters();

        return new JsonResponse($stock($filters['q'], $filters['state'], $filters['category'], $filters['after'], $filters['perPage']), 200);
    }
}
