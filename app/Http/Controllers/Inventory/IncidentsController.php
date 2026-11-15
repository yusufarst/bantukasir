<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\IncidentsPageRequest;
use App\Policies\NotFound;
use App\Queries\Inventory\Incidents;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-414 and RP-415, the reconciliation incidents (ROUTE_CONTRACTS RP-414, RP-415; INFORMATION_ARCHITECTURE
 * UXP-414, UXP-415; BR-INV-45, BR-INV-46; WF-27; CAP-18; TASK-036 scope item 3), the Owner's alone. RP-414
 * lists the posting blocks with their state, filtered by state (UXI-301); RP-415 shows one, with what did
 * not match and the blocked product, and offers "Buka kembali mutasi" (UXI-304, RC-16) while it is open.
 */
final class IncidentsController
{
    public function index(IncidentsPageRequest $request, Incidents $incidents): Response
    {
        $filters = $request->filters();

        return Inertia::render('inventory/Incidents', [
            'filters' => ['status' => $filters['status']],
            'incidents' => $incidents->list($filters),
        ]);
    }

    /**
     * @throws NotFound
     */
    public function show(string $block, Incidents $incidents): Response
    {
        return Inertia::render('inventory/Incident', ['incident' => $incidents->detail($block)]);
    }
}
