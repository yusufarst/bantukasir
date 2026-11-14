<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Http\Requests\Sales\SalesPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Cash\CashRegisterRows;
use App\Queries\Sales\CorrectionRecords;
use App\Queries\Sales\SaleRecords;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-201, UXP-201 "Penjualan", and RP-202, UXP-202 "Detail penjualan" (ROUTE_CONTRACTS RP-201,
 * RP-202; COPY_DECK sections 7.6 and 7.7; NAVIGATION_CONTRACTS UXP-201, UXP-202; BR-DOC-06; REQ-SALE-12;
 * TASK-031 scope item 4), for a holder of CAP-21 on the Sales they made and for the Owner on every Sale
 * (CAP-22); OPERATIONS_ADMIN has no access (the gate).
 *
 * RP-201: one page of the Sales in scope with the filters and the page's size as they were read, which
 * the page keeps in its address when it pages (NC-10); for the Owner also the registers and the
 * people of the filters "Laci kas" and "Kasir" (UXI-114). RP-202: one Sale in scope, NOT_FOUND
 * otherwise (TR-ERR-03), with its lines, one per product, its payment and its session (UXI-119); its
 * reprint is RC-44 (UXI-117). For the Owner, its linked refunds and returns (UXI-120) and whether
 * anything remains to refund or return (UXI-118; TASK-034).
 */
final class SalesController
{
    public function index(SalesPageRequest $request, SaleRecords $sales, CashRegisterRows $registers): Response
    {
        $viewer = self::viewer($request);
        $filters = $request->filters();
        $owner = Capabilities::allows($viewer, Capability::Cap22);

        return Inertia::render('sales/Index', [
            'filters' => [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'number' => $filters['number'],
                'cashier' => $filters['cashier'],
                'register' => $filters['register'],
                'perPage' => $filters['perPage'],
            ],
            'sales' => $sales->list($viewer, $filters),
            // The Owner's filters (UXI-114); none for anyone else, who sees only their own Sales.
            'registers' => $owner ? array_map(static fn (array $register): array => ['id' => $register['id'], 'code' => $register['code'], 'label' => $register['label']], $registers->list()) : [],
            'cashiers' => $owner ? $sales->cashiers() : [],
        ]);
    }

    /**
     * @throws NotFound
     */
    public function show(Request $request, string $sale, SaleRecords $sales, CorrectionRecords $corrections): Response
    {
        $viewer = self::viewer($request);
        $props = $sales->detail($sale, $viewer);

        // The Owner's alone (TASK-034): the linked refunds and returns (UXI-120), and whether anything
        // remains to refund or return, for "Pengembalian uang dan retur" (UXI-118; BR-RFD-03).
        if (Capabilities::allows($viewer, Capability::Cap22) && Capabilities::allows($viewer, Capability::Cap24)) {
            $lines = $corrections->lines($props['sale']['id']);
            $props['corrections'] = $corrections->corrections($props['sale']['id'], $lines);
            $props['refundable'] = CorrectionRecords::anyRemaining($lines);
        }

        return Inertia::render('sales/Show', $props);
    }

    private static function viewer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
