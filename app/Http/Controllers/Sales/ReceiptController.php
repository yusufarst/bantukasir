<?php

namespace App\Http\Controllers\Sales;

use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Sales\Receipts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-103, UXP-103 "Struk" (ROUTE_CONTRACTS RP-103; KEY_SCREENS KS-05; NAVIGATION_CONTRACTS UXP-103;
 * TASK-029 scope item 3): the committed receipt from its snapshot, with no further confirmation
 * (BR-SALE-14), for the Sale's own cashier and the Owner; anyone else, and a Sale that does not exist,
 * NOT_FOUND (SEC-AZ-06, TR-ERR-03). The page is the result of a checkout and of "Lihat hasil" after a
 * resolution (UXI-056); its print controls are TASK-031's.
 */
final class ReceiptController
{
    /**
     * @throws NotFound
     */
    public function show(Request $request, string $sale, Receipts $receipts): Response
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            abort(401);
        }

        return Inertia::render('sales/Receipt', $receipts->forViewer($sale, $viewer));
    }
}
