<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Requests\Inventory\HistoryPageRequest;
use App\Policies\NotFound;
use App\Queries\Inventory\StockHistory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-402, UXP-402 "Riwayat stok" (ROUTE_CONTRACTS RP-402; BR-INV-44; TASK-022 scope item 2): the
 * movements of the filters, keyset-paged on the server, the product the list is filtered to and
 * the page that opened it, for the way back (UXI-223). Operational history: no cost (CAP-14).
 */
final class HistoryController
{
    /**
     * @throws NotFound
     */
    public function index(HistoryPageRequest $request, StockHistory $history): Response
    {
        $filters = $request->filters();

        return Inertia::render('inventory/History', [
            'filters' => ['product' => $filters['product'], 'type' => $filters['type'], 'from' => $filters['from'], 'to' => $filters['to']],
            'product' => $filters['product'] === null ? null : $history->product($filters['product']),
            'history' => $history($filters),
            'opener' => $request->opener(),
        ]);
    }
}
