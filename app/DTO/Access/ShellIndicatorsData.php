<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * RD-03, the shell's indicators (ROUTE_CONTRACTS RD-03; INTERACTION_CONTRACTS
 * section 3.3): the number of the actor's own unresolved request receipts,
 * for a holder of CAP-34; the stock-attention count of the badge UXI-006, the
 * Habis and Menipis products, for a full holder of CAP-14 and null otherwise
 * (TASK-022); the onboarding state, NOT_STARTED, ACTIVE or RELEASED, for every
 * role, whose ACTIVE shows the banner UXS-27 on every page (UXI-030; TASK-035),
 * null before the bootstrap — both read from the Inventory module by the web
 * layer, since Access does not depend on it (STACK_AND_MODULES section 2.1);
 * and the as-of time of the read, in UTC. The open incidents join this DTO
 * with TASK-036. Nothing of another user and no business data beyond the
 * count and the state (SEC-CH-01, STK-21).
 */
final readonly class ShellIndicatorsData implements PageData
{
    public function __construct(
        public ?int $unresolvedRequests,
        public string $asOf,
        public ?int $stockAttention = null,
        public ?string $onboarding = null,
    ) {}

    /**
     * The same indicators with the stock-attention count.
     */
    public function withStockAttention(?int $count): self
    {
        return new self($this->unresolvedRequests, $this->asOf, $count, $this->onboarding);
    }

    /**
     * The same indicators with the onboarding state.
     */
    public function withOnboarding(?string $state): self
    {
        return new self($this->unresolvedRequests, $this->asOf, $this->stockAttention, $state);
    }

    /**
     * @return array{unresolvedRequests: int|null, stockAttention: int|null, onboarding: string|null, asOf: string}
     */
    public function toArray(): array
    {
        return [
            'unresolvedRequests' => $this->unresolvedRequests,
            'stockAttention' => $this->stockAttention,
            'onboarding' => $this->onboarding,
            'asOf' => $this->asOf,
        ];
    }
}
