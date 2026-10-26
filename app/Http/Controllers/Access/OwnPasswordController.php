<?php

namespace App\Http\Controllers\Access;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordUpdateResponse;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * RA-05, the change of one's own password (ROUTE_CONTRACTS section 4;
 * INTERACTION_CONTRACTS IC-12): Fortify's updater, App\Actions\Access\
 * UpdateOwnPassword, and its response. Fortify's own controller also deletes
 * the user's one-time link after the update, outside the transaction and the
 * account's lock and without an audit event; a change of one's own password is
 * not among the events that revoke a link (SECURITY_MODEL SEC-ACC-04), so this
 * controller leaves the link alone (TASK-006 review finding R-02).
 */
final class OwnPasswordController
{
    /**
     * @throws AuthenticationException
     */
    public function update(Request $request, UpdatesUserPasswords $updater): Responsable
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $updater->update($user, $request->all());

        return app(PasswordUpdateResponse::class);
    }
}
