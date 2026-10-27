<?php

namespace App\Domain\Access;

use App\Models\User;

/**
 * The capability map of SECURITY_MODEL section 6, encoded once (SEC-AZ-02;
 * TASK-007): what each of the three roles holds of each capability, as the
 * matrix's cell says it (Grant). A combined account — CASHIER and
 * OPERATIONS_ADMIN — holds exactly the union of the two columns and so never
 * an Owner-only capability; SUPER_ADMIN is held alone (BR-USR-02, the check
 * users_super_admin_alone), and an account that holds it is read by the
 * Owner's column only. CAP-33 is held by nobody: it is an operator procedure
 * (section 14).
 *
 * tests/Unit/Access/CapabilityMapTest.php reads the matrix from
 * SECURITY_MODEL section 6 and compares it with this map, cell by cell.
 */
final class Capabilities
{
    /**
     * The matrix, by capability: the Owner's, OPERATIONS_ADMIN's and
     * CASHIER's cell.
     *
     * @var array<string, array{Grant, Grant, Grant}>
     */
    private const array MATRIX = [
        'CAP-01' => [Grant::Full, Grant::Full, Grant::Full],
        'CAP-02' => [Grant::Full, Grant::Full, Grant::Restricted],
        'CAP-03' => [Grant::Full, Grant::Full, Grant::Full],
        'CAP-04' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-05' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-06' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-07' => [Grant::Full, Grant::None, Grant::None],
        'CAP-08' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-09' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-10' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-11' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-12' => [Grant::Full, Grant::Restricted, Grant::None],
        'CAP-13' => [Grant::Full, Grant::Full, Grant::None],
        'CAP-14' => [Grant::Full, Grant::Full, Grant::Restricted],
        'CAP-15' => [Grant::Full, Grant::None, Grant::None],
        'CAP-16' => [Grant::Full, Grant::None, Grant::None],
        'CAP-17' => [Grant::Full, Grant::None, Grant::None],
        'CAP-18' => [Grant::Full, Grant::None, Grant::None],
        'CAP-19' => [Grant::Full, Grant::None, Grant::None],
        'CAP-20' => [Grant::Full, Grant::None, Grant::None],
        'CAP-21' => [Grant::Own, Grant::None, Grant::Own],
        'CAP-22' => [Grant::Full, Grant::None, Grant::None],
        'CAP-23' => [Grant::Full, Grant::None, Grant::None],
        'CAP-24' => [Grant::Full, Grant::None, Grant::None],
        'CAP-25' => [Grant::Full, Grant::None, Grant::None],
        'CAP-26' => [Grant::Full, Grant::None, Grant::None],
        'CAP-27' => [Grant::Full, Grant::None, Grant::None],
        'CAP-28' => [Grant::Full, Grant::None, Grant::None],
        'CAP-29' => [Grant::Full, Grant::None, Grant::None],
        'CAP-30' => [Grant::Full, Grant::None, Grant::None],
        'CAP-31' => [Grant::Full, Grant::None, Grant::None],
        'CAP-32' => [Grant::Full, Grant::None, Grant::None],
        'CAP-33' => [Grant::None, Grant::None, Grant::None],
        'CAP-34' => [Grant::Full, Grant::Own, Grant::Own],
    ];

    /** One role's cell of the matrix. */
    public static function ofRole(Role $role, Capability $capability): Grant
    {
        $cells = self::MATRIX[$capability->value];

        return match ($role) {
            Role::SuperAdmin => $cells[0],
            Role::OperationsAdmin => $cells[1],
            Role::Cashier => $cells[2],
        };
    }

    /**
     * What an account with these roles holds of the capability: the Owner's
     * cell for an account that holds SUPER_ADMIN, otherwise the union of its
     * staff roles' cells; nothing without a role.
     *
     * @param  list<Role>  $roles
     */
    public static function ofRoles(array $roles, Capability $capability): Grant
    {
        if (in_array(Role::SuperAdmin, $roles, true)) {
            return self::ofRole(Role::SuperAdmin, $capability);
        }

        $grant = Grant::None;

        foreach ($roles as $role) {
            $grant = $grant->union(self::ofRole($role, $capability));
        }

        return $grant;
    }

    /** What the account holds of the capability, by its roles. */
    public static function of(User $user, Capability $capability): Grant
    {
        return self::ofRoles($user->roles(), $capability);
    }

    /**
     * Whether the account holds the capability so that it opens a gate of it:
     * fully or on its own objects (Grant::opensGate). Whether the account is
     * ACTIVE is the sign-in session's check (EnsureSignInSession) and, inside
     * a command, the actor guard's (ActorGuard).
     */
    public static function allows(User $user, Capability $capability): bool
    {
        return self::of($user, $capability)->opensGate();
    }

    /**
     * The capability flags of the shared props (SECURITY_MODEL SEC-CH-02):
     * the identifiers of the capabilities the account holds so that they open
     * their gates, in the order of section 6.
     *
     * @param  list<Role>  $roles
     * @return list<string>
     */
    public static function flags(array $roles): array
    {
        $flags = [];

        foreach (Capability::cases() as $capability) {
            if (self::ofRoles($roles, $capability)->opensGate()) {
                $flags[] = $capability->value;
            }
        }

        return $flags;
    }
}
