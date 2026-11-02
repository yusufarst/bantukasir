<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Inventory\MovementDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-403, UXP-403 "Detail mutasi stok" (ROUTE_CONTRACTS RP-403; BR-INV-44; CAP-14, CAP-20;
 * TASK-022 scope item 2): one movement with its source link by scope (UXI-224); its cost
 * evidence only for the Owner, who alone holds CAP-20 (UXI-225), and absent from every other
 * account's page. A movement that does not exist is NOT_FOUND.
 */
final class MovementsController
{
    /**
     * @throws NotFound
     */
    public function show(Request $request, string $movement, MovementDetail $detail): Response
    {
        $read = $detail->movement($movement);
        $viewer = $request->user();
        $props = ['movement' => $read];

        if ($viewer instanceof User && Capabilities::allows($viewer, Capability::Cap20)) {
            $props['costEvidence'] = $detail->evidence($read['id']);
        }

        return Inertia::render('inventory/Movement', $props);
    }
}
