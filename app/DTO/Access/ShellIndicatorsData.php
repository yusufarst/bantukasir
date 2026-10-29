<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * RD-03, the shell's indicators (ROUTE_CONTRACTS RD-03; INTERACTION_CONTRACTS
 * section 3.3): the number of the actor's own unresolved request receipts,
 * for a holder of CAP-34, and the as-of time of the read, in UTC. The other
 * indicators of RD-03 — the stock-attention count, open incidents and the
 * onboarding state — join this DTO with the Tasks that deliver them
 * (TASK-022, TASK-036, TASK-035). Nothing of another user and no business
 * data (SEC-CH-01, STK-21).
 */
final readonly class ShellIndicatorsData implements PageData
{
    public function __construct(
        public ?int $unresolvedRequests,
        public string $asOf,
    ) {}

    /**
     * @return array{unresolvedRequests: int|null, asOf: string}
     */
    public function toArray(): array
    {
        return [
            'unresolvedRequests' => $this->unresolvedRequests,
            'asOf' => $this->asOf,
        ];
    }
}
