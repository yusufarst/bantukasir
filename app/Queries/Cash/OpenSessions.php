<?php

namespace App\Queries\Cash;

use App\Domain\Cash\Sessions;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Who holds an OPEN cash session (SCHEMA section 5.10; BR-CASH-06), for the pages that depend on it:
 * the home of RN-01 (NAVIGATION_CONTRACTS NC-12), Buka sesi kas (RP-104), the till's header (RP-101,
 * RP-102) and the mark of UXP-602.
 * One OPEN session per user at most, so each list is bounded by the shop's accounts.
 */
final class OpenSessions
{
    /** The most holders read at once: the bound of the account list of RP-602 (UserAccounts). */
    public const int BOUND = 500;

    /**
     * Whether the user is responsible for an OPEN session.
     */
    public function heldBy(int $userId): bool
    {
        return DB::table('cash_sessions')->where('responsible_user_id', $userId)->where('status', Sessions::OPEN)->exists();
    }

    /**
     * The user's OPEN session as the till's header names it (COPY_DECK `sales.till.session`; ROUTE_CONTRACTS
     * RP-101; TASK-029): its internal key, which the cart's checkout names, its number and its register's
     * label; null when the user holds none.
     *
     * @return array{id: int, number: string, register: string}|null
     */
    public function sessionOf(int $userId): ?array
    {
        $row = DB::table('cash_sessions as s')
            ->join('cash_registers as r', 'r.id', '=', 's.register_id')
            ->where('s.responsible_user_id', $userId)
            ->where('s.status', Sessions::OPEN)
            ->first(['s.id', 's.session_number', 'r.label']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $number = $values['session_number'] ?? null;
        $label = $values['label'] ?? null;

        if (! is_int($id) || ! is_string($number) || ! is_string($label)) {
            throw new LogicException('An OPEN session holds its number and its register (SCHEMA section 5.10).');
        }

        return ['id' => $id, 'number' => $number, 'register' => $label];
    }

    /**
     * The internal keys of the users who are responsible for an OPEN session.
     *
     * @return list<int>
     */
    public function holders(): array
    {
        $holders = [];

        foreach (DB::table('cash_sessions')->where('status', Sessions::OPEN)->orderBy('responsible_user_id')->limit(self::BOUND)->pluck('responsible_user_id') as $id) {
            if (is_int($id)) {
                $holders[] = $id;
            }
        }

        return $holders;
    }
}
