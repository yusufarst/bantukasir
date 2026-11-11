<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Sales\CheckoutSale;
use App\Domain\Access\Homes;
use App\Domain\Cash\SessionSales;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Sales\Sales;
use App\Models\User;
use App\Queries\Cash\SessionRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-105, UXP-105 "Tutup sesi kas" (ROUTE_CONTRACTS RP-105; KEY_SCREENS KS-07; NAVIGATION_CONTRACTS
 * UXP-105; INTERACTION_CONTRACTS RC-03 and section 2.5; BR-CASH-13 step 1; TASK-030 scope item 3), for
 * every holder of CAP-21 on their own OPEN session: the session's header, and the close preview as a
 * deferred prop, which the page reloads on RECOUNT_REQUIRED — the session, the activity version that
 * the close compares, the running figures with expected cash (G-05), the drawer-funded refunds' total
 * among them (Rp0 until TASK-034 pays one and lists them, UXI-101), and how many of the user's
 * checkouts the server holds unresolved (G-27). The version is read before the Sales module's totals (STK-10): a Sale between the
 * two reads is counted in the figures but not in the version, so its close is refused
 * RECOUNT_REQUIRED, never recorded against figures the user did not review. No lock is held while the
 * cash is counted (BR-CASH-13). Without an OPEN session of the user's own, 302 to RP-101, which leads
 * on to Buka sesi kas.
 */
final class CloseSessionController
{
    /** The deferred prop of the close preview, reloaded by name (RP-105). */
    public const string PREVIEW = 'preview';

    public function show(Request $request, SessionRecords $sessions, Sales $sales, RequestReceipts $receipts): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $session = $sessions->openOf($user->id);

        if ($session === null) {
            return redirect()->to(Homes::TILL);
        }

        return Inertia::render('cash/CloseSession', [
            'session' => ['id' => $session['id'], 'number' => $session['number'], 'registerCode' => $session['registerCode'], 'registerLabel' => $session['registerLabel']],
            self::PREVIEW => Inertia::defer(static fn (): ?array => self::preview($user, $session['id'], $sessions, $sales, $receipts)),
        ]);
    }

    /**
     * The close preview of the user's OPEN session, read again when the page asks for it; null when
     * that session is no longer OPEN — closed meanwhile, by the user elsewhere or by the Owner — which
     * the page says (UXS-30).
     *
     * @return array{sessionId: int, activityVersion: int, figures: array{openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string}, unresolvedCheckouts: int}|null
     */
    private static function preview(User $user, int $sessionId, SessionRecords $sessions, Sales $sales, RequestReceipts $receipts): ?array
    {
        $session = $sessions->openOf($user->id);

        if ($session === null || $session['id'] !== $sessionId) {
            return null;
        }

        return [
            'sessionId' => $session['id'],
            'activityVersion' => $session['activityVersion'],
            'figures' => SessionSales::of($sales->ofSession($session['id']))->figures($session['openingCash']),
            'unresolvedCheckouts' => $receipts->unresolvedOf($user->id, CheckoutSale::OPERATION),
        ];
    }
}
