<?php

namespace App\Http\Controllers\Costing;

use App\Http\Requests\Costing\CostHistoryPageRequest;
use App\Policies\NotFound;
use App\Queries\Costing\CostHistory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-505, UXP-505 "Riwayat biaya" (ROUTE_CONTRACTS RP-505; NAVIGATION_CONTRACTS UXP-505; BR-COST-06,
 * BR-COST-38; CAP-20, the Owner's alone; TASK-025 scope item 6): the product's cost sources,
 * corrections, average cost and inventory value, and the page that opened it; a product that does
 * not exist is NOT_FOUND (TR-ERR-03).
 */
final class CostHistoryController
{
    /**
     * @throws NotFound
     */
    public function show(CostHistoryPageRequest $request, string $product, CostHistory $history): Response
    {
        return Inertia::render('costing/CostHistory', [...$history->of($product), 'opener' => $request->opener()]);
    }
}
