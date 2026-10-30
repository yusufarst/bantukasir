<?php

namespace App\Domain\Access;

use LogicException;

/**
 * The roles a staff account may hold, as the payload of RC-35 and RC-36 names
 * them (INTERACTION_CONTRACTS RC-35; D-032): `cashier`, `operationsAdmin`, or
 * both. SUPER_ADMIN is never among them (CAP-33).
 */
final class StaffRoles
{
    /** The payload's names, in the order of COPY_DECK section 3. */
    public const array NAMES = ['cashier', 'operationsAdmin'];

    /**
     * The roles a payload names, each once, in the order of COPY_DECK section 3.
     *
     * @param  array<array-key, mixed>  $names
     * @return non-empty-list<Role>
     */
    public static function fromNames(array $names): array
    {
        $roles = [];

        if (in_array('cashier', $names, true)) {
            $roles[] = Role::Cashier;
        }

        if (in_array('operationsAdmin', $names, true)) {
            $roles[] = Role::OperationsAdmin;
        }

        if ($roles === []) {
            throw new LogicException('A staff account holds at least one role (validated by its FormRequest).');
        }

        return $roles;
    }

    /**
     * The payload's names of these roles, for a fixture or a form.
     *
     * @param  list<Role>  $roles
     * @return list<string>
     */
    public static function namesOf(array $roles): array
    {
        $names = [];

        if (in_array(Role::Cashier, $roles, true)) {
            $names[] = 'cashier';
        }

        if (in_array(Role::OperationsAdmin, $roles, true)) {
            $names[] = 'operationsAdmin';
        }

        return $names;
    }

    /**
     * The columns of `users` that hold these staff roles.
     *
     * @param  list<Role>  $roles
     * @return array{role_cashier: bool, role_operations_admin: bool}
     */
    public static function columns(array $roles): array
    {
        return [
            'role_cashier' => in_array(Role::Cashier, $roles, true),
            'role_operations_admin' => in_array(Role::OperationsAdmin, $roles, true),
        ];
    }
}
