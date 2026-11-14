<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Cash\CashCount;
use App\Domain\Cash\DrawerRefunds;
use App\Domain\Sales\Corrections\CorrectedSale;
use App\Domain\Sales\Corrections\SaleCorrections;
use App\Domain\Sales\Sales;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Cash\OpenSessions;
use App\Queries\Sales\CorrectionRecords;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-203, UXP-203 "Pengembalian uang dan retur", and RP-204, UXP-204 "Pengembalian tercatat" (ROUTE_CONTRACTS
 * RP-203, RP-204; COPY_DECK sections 7.8 and 7.9; NAVIGATION_CONTRACTS UXP-203, UXP-204; SALES_CASH BR-RFD-02
 * to BR-RFD-07, BR-RFD-17; D-046, D-068; TASK-034 scope item 3), the Owner's alone (CAP-24, the gate). A Sale
 * that does not exist, like a correction that is not the Sale's, is NOT_FOUND (TR-ERR-03).
 *
 * RP-203: the Sale's number, its lines that something remains on to refund or to return, each with its
 * remaining money and quantity (BR-RFD-03), and every OPEN cash session with its register, its responsible
 * user and its expected cash now, from its facts as a close derives it (BR-RFD-07; G-05), the sources of a
 * drawer-funded refund. The figures are read without a lock: RC-05 checks the drawer again under the
 * session's lock (BR-RFD-15). RP-204: the committed correction — its refund's payout with, for a drawer-funded
 * refund, the session and register the cash is taken from (D-068), its lines and its reason.
 */
final class RefundController
{
    /**
     * @throws NotFound
     */
    public function create(string $sale, SaleCorrections $corrections, CorrectionRecords $records, OpenSessions $open, Sales $sales): Response
    {
        $found = self::sale($sale, $corrections);
        $lines = array_values(array_filter($records->lines($found->id), static fn (array $line): bool => Money::of($line['remainingAmount'])->isPositive() || Quantity::of($line['remainingQuantity'], $line['quantityPrecision'])->isPositive()));
        $sessions = [];

        foreach ($open->all() as $session) {
            $paid = DrawerRefunds::of($corrections->drawerRefundsOf($session['id']))->total;
            $sessions[] = [
                'id' => $session['id'],
                'number' => $session['number'],
                'registerCode' => $session['registerCode'],
                'registerLabel' => $session['registerLabel'],
                'responsible' => $session['responsible'],
                'expectedCash' => CashCount::due($session['openingCash'], $sales->ofSession($session['id'])['cash'], $paid)->toCanonical(),
            ];
        }

        return Inertia::render('sales/Refund', [
            'sale' => ['id' => $found->id, 'number' => $found->number],
            'lines' => $lines,
            'sessions' => $sessions,
        ]);
    }

    /**
     * @throws NotFound
     */
    public function show(string $sale, string $correction, SaleCorrections $corrections, CorrectionRecords $records): Response
    {
        $found = self::sale($sale, $corrections);
        $key = ObjectScope::key($correction);
        $recorded = $key === null ? [] : $records->corrections($found->id, $records->lines($found->id), $key);

        return Inertia::render('sales/RefundRecorded', [
            'sale' => ['id' => $found->id, 'number' => $found->number],
            'correction' => $recorded[0] ?? throw new NotFound,
        ]);
    }

    /**
     * The Sale of the path, which the Owner reaches whatever its session or cashier (CAP-22).
     *
     * @throws NotFound
     */
    private static function sale(string $identifier, SaleCorrections $corrections): CorrectedSale
    {
        $key = ObjectScope::key($identifier);

        return ($key === null ? null : $corrections->sale($key)) ?? throw new NotFound;
    }
}
