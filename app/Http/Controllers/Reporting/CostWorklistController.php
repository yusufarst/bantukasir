<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Requests\Reporting\CostWorklistRequest;
use App\Policies\NotFound;
use App\Queries\Reporting\CostWorklist;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-503, UXP-503 "Biaya perlu dilengkapi" (ROUTE_CONTRACTS RP-503; NAVIGATION_CONTRACTS UXP-503;
 * BR-COST-27, BR-COST-38; CAP-20, the Owner's alone; TASK-025 scope item 6): the cost sources that are
 * UNKNOWN or marked, a page at a time, with the product of the filter named; a product that does not
 * exist is NOT_FOUND (TR-ERR-03).
 */
final class CostWorklistController
{
    /**
     * @throws NotFound
     */
    public function index(CostWorklistRequest $request, CostWorklist $worklist): Response
    {
        $filters = $request->filters();
        $product = $filters['product'] === null ? null : ($worklist->product($filters['product']) ?? throw new NotFound);

        return Inertia::render('reporting/CostWorklist', [
            'filters' => ['product' => $filters['product'], 'perPage' => $filters['perPage']],
            'product' => $product,
            'worklist' => $worklist->list($filters),
        ]);
    }
}
