<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Access\Homes;
use App\Domain\Inventory\OnboardingState;
use App\DTO\Catalog\CategoryRowData;
use App\Models\User;
use App\Queries\Cash\OpenSessions;
use App\Queries\Catalog\CategoryRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-101, UXP-101 "Kasir", and RP-102, UXP-102 "Pembayaran" (ROUTE_CONTRACTS RP-101, RP-102; KEY_SCREENS
 * KS-03, KS-04; NAVIGATION_CONTRACTS UXP-101, UXP-102; TASK-029 scope items 1 and 2), for every holder of
 * CAP-21 in their own OPEN cash session (BR-SALE-02, BR-CAT-33): one page, `sales/Till`, whose view is
 * the till or its payment screen. The cart is a draft of the page, in its memory and its encrypted
 * history state, never on the server (BR-DOM-09); the payment view loaded without a cart returns to the
 * till in the browser. The props: the view; the session's header — its internal key, which the cart's
 * checkout names, its number and its register; whether the onboarding freeze is on (UXS-27); and the
 * active categories of the search's filter (UXI-069). The user's unresolved checkout receipts reach the
 * page through RD-04, which the command client reads when the page opens and again before the final
 * action (INTERACTION_CONTRACTS section 1.4). A user without an OPEN session is sent with 302 to Buka
 * sesi kas (RP-104), the Kasir destination without one (UXI-002).
 */
final class TillController
{
    /** The view of RP-101. */
    public const string TILL = 'till';

    /** The view of RP-102. */
    public const string PAYMENT = 'payment';

    public function show(Request $request, OpenSessions $sessions, OnboardingState $onboarding, CategoryRows $categories): Response|RedirectResponse
    {
        return $this->render($request, self::TILL, $sessions, $onboarding, $categories);
    }

    public function payment(Request $request, OpenSessions $sessions, OnboardingState $onboarding, CategoryRows $categories): Response|RedirectResponse
    {
        return $this->render($request, self::PAYMENT, $sessions, $onboarding, $categories);
    }

    private function render(Request $request, string $view, OpenSessions $sessions, OnboardingState $onboarding, CategoryRows $categories): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $session = $sessions->sessionOf($user->id);

        if ($session === null) {
            return redirect()->to(Homes::OPEN_SESSION);
        }

        return Inertia::render('sales/Till', [
            'view' => $view,
            'session' => $session,
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
            'categories' => array_map(static fn (CategoryRowData $row): array => $row->toArray(), $categories->choices()),
        ]);
    }
}
