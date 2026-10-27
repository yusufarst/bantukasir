<?php

namespace App\Http\Controllers\Access;

use App\Http\Auth\Responses;
use App\Models\User;
use App\Queries\Access\OwnAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-003, UXP-003 "Akun saya", and RP-004, UXP-004 "Ubah kata sandi"
 * (ROUTE_CONTRACTS section 2), both CAP-01 and the user's own account only.
 * Their history entries are encrypted, as every signed-in page's are
 * (SEC-CH-04; the route group's `history.encrypt`, TASK-007): the sign-in page
 * clears the key, so that after a sign-out neither page can be restored from
 * history.
 */
final class AccountController
{
    public function show(Request $request, OwnAccount $account): Response
    {
        return Inertia::render('access/Account', [
            'account' => $account(self::signedIn($request))->toArray(),
            'passwordChanged' => $request->session()->get(Responses::STATUS) === Responses::PASSWORD_CHANGED,
        ]);
    }

    public function password(Request $request): Response
    {
        self::signedIn($request);

        return Inertia::render('access/ChangePassword');
    }

    private static function signedIn(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
