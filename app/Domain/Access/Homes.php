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
 * (TRACEABILITY, RN-01). Until TASK-041 the Owner's home is Akun saya.
 * Whether the account holds an OPEN session is the Cash module's, which the
 * caller reads.
 */
final class Homes
{
    /** The path of RP-003, UXP-003 "Akun saya". */
    public const string ACCOUNT = '/account';

    /** The path of RP-401, UXP-401 "Stok" (TASK-022). */
    public const string STOCK = '/stock';

    /** The path of RP-104, UXP-104 "Buka sesi kas" (TASK-027). */
    public const string OPEN_SESSION = '/till/open-session';

    /** The path of RP-101, UXP-101 "Kasir" (TASK-029). */
    public const string TILL = '/till';

    /**
     * The path of the account's home: Kasir for a cashier or the combined
     * account with an OPEN session; Buka sesi kas for a cashier without one;
     * Stok for Operations and for the combined account without one; and Akun
     * saya for the Owner until Beranda exists.
     */
    public static function of(User $user, bool $holdsOpenSession): string
    {
        $roles = $user->roles();

        if (in_array(Role::SuperAdmin, $roles, true)) {
            return self::ACCOUNT;
        }

        if (in_array(Role::Cashier, $roles, true)) {
            if ($holdsOpenSession) {
                return self::TILL;
            }

            return in_array(Role::OperationsAdmin, $roles, true) ? self::STOCK : self::OPEN_SESSION;
        }

        return in_array(Role::OperationsAdmin, $roles, true) ? self::STOCK : self::ACCOUNT;
    }
}
