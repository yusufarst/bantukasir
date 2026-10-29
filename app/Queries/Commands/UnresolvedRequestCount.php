<?php

namespace App\Queries\Commands;

use App\Domain\Commands\Operations;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The number of the actor's own request receipts that are not resolved —
 * PENDING or HELD — for the shell's indicators (ROUTE_CONTRACTS RD-03;
 * INTERACTION_CONTRACTS sections 1.4 and 3.3; TASK-009 scope item 7). Print,
 * reprint and export requests are left out: they are exempt from the block
 * of NC-13, have no panel and are repeated, never resolved (COPY_DECK
 * section 14.1; UI_STATES section 4), so the client never lists them and
 * the count agrees with what UXP-006 shows (TASK-009 evidence, the
 * readings). One indexed count of SCHEMA section 11; never a payload.
 */
final class UnresolvedRequestCount
{
    public function __invoke(User $actor): int
    {
        $exempt = array_keys(array_filter(Operations::FAMILIES, static fn (string $family): bool => $family === Operations::EXEMPT_FAMILY));

        return DB::table('command_request_receipts')
            ->where('actor_user_id', $actor->id)
            ->whereIn('outcome', ['PENDING', 'HELD'])
            ->whereNotIn('operation', $exempt)
            ->count();
    }
}
