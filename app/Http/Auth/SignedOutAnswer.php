<?php

namespace App\Http\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer to a request without a sign-in session, or whose sign-in session
 * has just ended (UNAUTHENTICATED; UI_STATES UXS-09; NAVIGATION_CONTRACTS
 * NC-06; TECHNICAL_RULES section 4): JSON gets 401 with the error body of
 * ROUTE_CONTRACTS RT-07; a page gets the sign-in page with a full page load —
 * an Inertia visit by Inertia's location answer — so that no earlier page
 * stays in memory (SEC-CH-04), and the sign-in page shows the neutral note
 * that the sign-in session ended. A cash session is never touched (BR-CASH-11).
 */
final class SignedOutAnswer
{
    public static function for(Request $request): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse([
                'outcome' => 'REJECTED',
                'code' => 'UNAUTHENTICATED',
                'messageKey' => 'ux.state.signed_out',
                'params' => (object) [],
                'fields' => (object) [],
                'lines' => (object) [],
            ], 401);
        }

        if ($request->hasSession()) {
            $request->session()->flash(SignInSession::NOTICE, SignInSession::SIGNED_OUT);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        return redirect()->route('login');
    }
}
