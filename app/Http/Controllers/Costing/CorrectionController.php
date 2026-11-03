<?php

namespace App\Http\Controllers\Costing;

use App\Http\Requests\Costing\CorrectionPageRequest;
use App\Policies\NotFound;
use App\Queries\Costing\CostSources;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-504, UXP-504 "Lengkapi biaya" or "Koreksi biaya" (ROUTE_CONTRACTS RP-504; NAVIGATION_CONTRACTS
 * UXP-504; BR-COST-20 to BR-COST-22; CAP-19, the Owner's alone; TASK-025 scope item 6): the cost
 * source with its value now and its history, for a draft in page memory only (NC-10), and the page
 * that opened it; a cost source that does not exist is NOT_FOUND (TR-ERR-03).
 */
final class CorrectionController
{
    /**
     * @throws NotFound
     */
    public function create(CorrectionPageRequest $request, string $evidence, CostSources $sources): Response
    {
        return Inertia::render('costing/Correction', [...$sources->detail($evidence), 'opener' => $request->opener()]);
    }
}
