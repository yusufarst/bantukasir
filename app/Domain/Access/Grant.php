<?php

namespace App\Domain\Access;

/**
 * What one cell of the permission matrix of SECURITY_MODEL section 6 grants:
 *
 * - Full: "Y", "Y, any", or "Y" with what the Owner sees in addition
 *   ("Y, with the reason and full correction history", "Y, with an explicit
 *   entry");
 * - Own: "Y, own" — the capability, on the objects of the actor's own scope
 *   only (SEC-AZ-03), which the Query or the FormRequest applies;
 * - Restricted: the narrower forms that the matrix spells out for a staff
 *   role — CAP-02 for a cashier ("in the till"), CAP-12 for Operations
 *   ("never the reason or history"), CAP-14 for a cashier ("only the stock
 *   needed at checkout"). A restricted cell never opens a gate by itself: the
 *   pages and reads of that capability are the other roles' (ROUTE_CONTRACTS
 *   RP-401: "stock pages: Owner, Ops, Combined"), and the Task that serves
 *   the narrow form names it on its own route;
 * - None: "N", refused by the server.
 */
enum Grant: string
{
    case Full = 'full';
    case Own = 'own';
    case Restricted = 'restricted';
    case None = 'none';

    /** Whether the cell opens a route gate of that capability (RT-05). */
    public function opensGate(): bool
    {
        return $this === self::Full || $this === self::Own;
    }

    /**
     * The stronger of two grants, for the union of a combined account's roles
     * (SEC-AZ-02): Full, then Own, then Restricted, then None.
     */
    public function union(self $other): self
    {
        foreach ([self::Full, self::Own, self::Restricted] as $grant) {
            if ($this === $grant || $other === $grant) {
                return $grant;
            }
        }

        return self::None;
    }
}
