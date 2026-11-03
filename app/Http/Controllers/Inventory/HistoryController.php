<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\OnboardingState;
use App\Http\Requests\Inventory\HistoryPageRequest;
use App\Policies\NotFound;
use App\Queries\Inventory\StockHistory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-402, UXP-402 "Riwayat stok" (ROUTE_CONTRACTS RP-402; BR-INV-44; TASK-022 scope item 2): the
 * movements of the filters, keyset-paged on the server, the products the list is filtered to —
 * one, or those of an adjustment that RC-09 leads to (TASK-024) — and the page that opened it,
 * for the way back (UXI-223). It says whether the onboarding freeze stops an adjustment, for
 * "Sesuaikan stok" (UXI-221, UXS-27; TASK-024). Operational history: no cost (CAP-14).
 */
final class HistoryController
{
    /**
     * @throws NotFound
     */
    public function index(HistoryPageRequest $request, StockHistory $history, OnboardingState $onboarding): Response
    {
        $filters = $request->filters();

        return Inertia::render('inventory/History', [
            'filters' => ['products' => $filters['products'], 'type' => $filters['type'], 'from' => $filters['from'], 'to' => $filters['to']],
            'products' => $history->products($filters['products']),
            'history' => $history($filters),
            'opener' => $request->opener(),
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
        ]);
    }
}
