<?php

namespace App\Queries\Access;

use App\Domain\Access\Role;
use App\DTO\Access\OwnAccountData;
use App\Models\User;

/**
 * The read of RP-003: the signed-in user's own account, by the identity of
 * the sign-in session only (SEC-AZ-05), as its DTO.
 */
final class OwnAccount
{
    public function __invoke(User $signedIn): OwnAccountData
    {
        $roles = $signedIn->roles();

        return new OwnAccountData(
            displayName: $signedIn->display_name,
            email: $signedIn->email,
            roles: array_map(static fn (Role $role): string => $role->value, $roles),
            owner: in_array(Role::SuperAdmin, $roles, true),
        );
    }
}
