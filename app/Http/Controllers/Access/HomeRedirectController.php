<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Homes;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * RN-01 `GET /`, the home redirect (ROUTE_CONTRACTS section 2; NAVIGATION_CONTRACTS
 * NC-12; TASK-007): a signed-in account is sent with 302 to its role's home
 * (App\Domain\Access\Homes); a visitor without a sign-in session never reaches
 * it — the authentication of RT-04 sends it to RP-001. It reads and writes
 * nothing else.
 */
final class HomeRedirectController
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return redirect()->to(Homes::of($user));
    }
}
