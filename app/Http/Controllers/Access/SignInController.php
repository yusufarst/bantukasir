<?php

namespace App\Http\Controllers\Access;

use App\Http\Auth\SignInSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-001, UXP-001 "Masuk" (ROUTE_CONTRACTS section 2; KEY_SCREENS KS-01): the
 * sign-in form, for a visitor without a sign-in session; a signed-in visitor
 * is sent to RN-01 by the guest middleware. It reveals neither whether an
 * account exists nor its state and shows no business data
 * (INFORMATION_ARCHITECTURE section 3). Its only prop is the note that a
 * sign-in session ended (UXS-09). The page clears the browser history's
 * encryption key, so that no page of a signed-out user can be restored from
 * history (SEC-AUTH-10, SEC-CH-04).
 */
final class SignInController
{
    public function show(Request $request): Response
    {
        $notice = $request->session()->get(SignInSession::NOTICE);

        Inertia::clearHistory();

        return Inertia::render('access/SignIn', [
            'notice' => $notice === SignInSession::SIGNED_OUT ? SignInSession::SIGNED_OUT : null,
        ]);
    }
}
