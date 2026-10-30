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

    /**
     * RP-005, UXP-005 "Ubah nama dan email" (CAP-27; TASK-011 scope item 2): the
     * Owner's own display name and email, with the version the Owner reviews.
     */
    public function details(Request $request): Response
    {
        $user = self::signedIn($request);

        return Inertia::render('access/OwnerDetails', [
            'account' => ['displayName' => $user->display_name, 'email' => $user->email, 'version' => $user->version],
        ]);
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
