<?php

namespace App\Domain\Access;

use App\Models\User;

/**
 * The home of each role (NAVIGATION_CONTRACTS NC-12; INFORMATION_ARCHITECTURE
 * section 4; ROUTE_CONTRACTS RN-01): the Owner Beranda (RP-007); Operations
 * Stok (RP-401); a cashier Kasir (RP-101), which sends a cashier without an
 * OPEN session of their own to Buka sesi kas (RP-104); the combined account
 * Kasir with an OPEN session of its own, otherwise Stok.
 *
 * Until a role's home page exists, its home is Akun saya (RP-003), the one
 * page every role has (TASK-007 scope item 7; EXECUTION_CONTEXT EXC-52): the
 * Tasks that deliver the homes switch them here — TASK-022 Stok for
 * Operations and for the combined account without an OPEN session, TASK-027
 * Buka sesi kas for a cashier without one, TASK-029 Kasir for a cashier and
 * the combined account with one, TASK-041 Beranda for the Owner
 * (TRACEABILITY, RN-01). No cash session exists before TASK-027, so the
 * combined account has none of its own and its home is Stok.
 */
final class Homes
{
    /** The path of RP-003, UXP-003 "Akun saya". */
    public const string ACCOUNT = '/account';

    /** The path of RP-401, UXP-401 "Stok" (TASK-022). */
    public const string STOCK = '/stock';

    /**
     * The path of the account's home: Stok for an account that holds
     * OPERATIONS_ADMIN without SUPER_ADMIN — Operations and the combined
     * account — and Akun saya for the Owner and a cashier, until their homes
     * exist.
     */
    public static function of(User $user): string
    {
        $roles = $user->roles();

        if (in_array(Role::OperationsAdmin, $roles, true) && ! in_array(Role::SuperAdmin, $roles, true)) {
            return self::STOCK;
        }

        return self::ACCOUNT;
    }
}
