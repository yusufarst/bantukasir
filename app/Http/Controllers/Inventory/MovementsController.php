<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Inventory\OnboardingState;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Inventory\MovementDetail;
use App\Queries\Inventory\Reversibility;
use App\Queries\Sales\SaleSources;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-403, UXP-403 "Detail mutasi stok" (ROUTE_CONTRACTS RP-403; BR-INV-44; CAP-14, CAP-17, CAP-20;
 * TASK-022 scope item 2, TASK-026 scope item 4): one movement with its source link by scope
 * (UXI-224) — a SALE_ISSUE's Sale read by the Sales module and composed here (TASK-031; OPEN_ITEMS T-46) —
 * and its reversal links — the REVERSAL that cancelled it, or the movement a REVERSAL
 * cancels (COPY_DECK section 9.4); its cost evidence only for the Owner, who alone holds CAP-20
 * (UXI-225), and absent from every other account's page; for the Owner, who alone holds CAP-17,
 * whether the onboarding freeze is on, for "Batalkan mutasi" (UXI-227). A movement that does not
 * exist is NOT_FOUND.
 */
final class MovementsController
{
    /**
     * @throws NotFound
     */
    public function show(Request $request, string $movement, MovementDetail $detail, Reversibility $reversibility, OnboardingState $onboarding, SaleSources $sales): Response
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            abort(401);
        }

        $read = $detail->movement($movement);
        $saleLine = $read['source'] === null ? $detail->saleLineOf($read['id']) : null;

        // A SALE_ISSUE's source, its Sale, read by the Sales module (UXI-224; TASK-031).
        if ($saleLine !== null) {
            $read['source'] = $sales->ofLine($saleLine, $viewer);
        }

        $props = ['movement' => [...$read, ...$reversibility->links($read['id'])]];

        if (Capabilities::allows($viewer, Capability::Cap20)) {
            $props['costEvidence'] = $detail->evidence($read['id']);
        }

        if (Capabilities::allows($viewer, Capability::Cap17)) {
            $props['postingFrozen'] = $onboarding->current() === OnboardingState::ACTIVE;
        }

        return Inertia::render('inventory/Movement', $props);
    }
}
