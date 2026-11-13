<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Sales\CheckoutSale;
use App\Domain\Cash\Sessions;
use App\Domain\Cash\SessionSales;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Sales\Sales;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Cash\SessionRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;

/**
 * RP-207, UXP-207 "Tutup sesi oleh Pemilik" (ROUTE_CONTRACTS RP-207; NAVIGATION_CONTRACTS UXP-207;
 * INTERACTION_CONTRACTS RC-04 and section 2.5; INTERACTION_INVENTORY UXI-149; BR-CASH-13 step 1,
 * BR-CASH-16, BR-CASH-18; D-087; TASK-032 scope item 3), for the Owner (CAP-23) on another user's OPEN
 * session: the session's header with its responsible user, and the close preview as a deferred prop,
 * which the page reloads after RECOUNT_REQUIRED and after the Owner's status check — the activity version
 * that the close compares, the running figures with expected cash (G-05), and the responsible user's
 * unresolved checkouts, each by its intent, for "Cek status" (CAP-34; ADR-009 section 4). The version is
 * read before the Sales module's totals (STK-10), as RP-105 reads it, so that a Sale between the two
 * reads makes the close refused RECOUNT_REQUIRED. No lock is held while the cash is counted. A session
 * that does not exist is NOT_FOUND; one CLOSED, or the Owner's own, 302 to RP-206; the page's own read
 * of its preview then answers a null preview, so that the page says the session closed meanwhile (UXS-30).
 */
final class AssistedCloseController
{
    /** The deferred prop of the close preview, reloaded by name (RP-207). */
    public const string PREVIEW = 'preview';

    /** The checkouts the preview lists at most, as RD-04 pages them (INTERACTION_CONTRACTS section 3.6). */
    public const int UNRESOLVED_BOUND = 100;

    /** The page's component, which the page's own partial reload names. */
    private const string COMPONENT = 'cash/AssistedClose';

    /**
     * @throws NotFound
     */
    public function show(Request $request, string $session, SessionRecords $sessions, Sales $sales, RequestReceipts $receipts): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $found = $sessions->forAssistedClose($session, $user);
        $closable = $found['open'] && $found['responsibleUserId'] !== $user->id;

        if (! $closable && $request->header(Header::PARTIAL_COMPONENT) !== self::COMPONENT) {
            return redirect()->to(Sessions::detailPath($found['id']));
        }

        return Inertia::render(self::COMPONENT, [
            // The header, which a partial reload of the preview never reads.
            'session' => static fn (): array => [
                'id' => $found['id'],
                'number' => $found['number'],
                'registerCode' => $found['registerCode'],
                'registerLabel' => $found['registerLabel'],
                'responsible' => $found['responsible'],
            ],
            self::PREVIEW => Inertia::defer(static fn (): ?array => self::preview($user, $found['id'], $sessions, $sales, $receipts)),
        ]);
    }

    /**
     * The close preview of the session, read again when the page asks for it; null once it is no longer
     * an OPEN session of another user — closed meanwhile, by its responsible user or by the Owner
     * elsewhere — which the page says (UXS-30).
     *
     * @return array{sessionId: int, activityVersion: int, figures: array{openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string}, unresolvedCheckouts: list<array{sourceIntentId: string, receivedAt: string}>}|null
     *
     * @throws NotFound
     */
    private static function preview(User $user, int $sessionId, SessionRecords $sessions, Sales $sales, RequestReceipts $receipts): ?array
    {
        $session = $sessions->forAssistedClose((string) $sessionId, $user);

        if (! $session['open'] || $session['responsibleUserId'] === $user->id) {
            return null;
        }

        return [
            'sessionId' => $session['id'],
            'activityVersion' => $session['activityVersion'],
            'figures' => SessionSales::of($sales->ofSession($session['id']))->figures($session['openingCash']),
            'unresolvedCheckouts' => $receipts->unresolvedIntentsOf($session['responsibleUserId'], CheckoutSale::OPERATION, self::UNRESOLVED_BOUND),
        ];
    }
}
