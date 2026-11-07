<?php

namespace App\Queries\Cash;

use App\Domain\Cash\Sessions;
use Illuminate\Support\Facades\DB;

/**
 * Who holds an OPEN cash session (SCHEMA section 5.10; BR-CASH-06), for the pages that depend on it:
 * the home of RN-01 (NAVIGATION_CONTRACTS NC-12), Buka sesi kas (RP-104) and the mark of UXP-602.
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
