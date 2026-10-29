<?php

namespace App\Queries\Access;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\DTO\Access\ShellIndicatorsData;
use App\Models\User;
use App\Queries\Commands\UnresolvedRequestCount;
use App\Support\Time\Clock;

/**
 * RD-03, the indicators of the shell for the signed-in user (ROUTE_CONTRACTS
 * RD-03; INTERACTION_CONTRACTS section 3.3; TASK-009 scope item 7): the
 * number of the user's own unresolved request receipts — for a holder of
 * CAP-34, which every role holds; null otherwise — and the as-of time. The
 * count is a read of the Commands module, on which Access already depends
 * (STACK_AND_MODULES section 2.1).
 */
final class ShellIndicators
{
    public function __construct(
        private readonly UnresolvedRequestCount $unresolved,
        private readonly Clock $clock,
    ) {}

    public function __invoke(User $user): ShellIndicatorsData
    {
        return new ShellIndicatorsData(
            unresolvedRequests: Capabilities::allows($user, Capability::Cap34) ? ($this->unresolved)($user) : null,
            asOf: $this->clock->now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        );
    }
}
