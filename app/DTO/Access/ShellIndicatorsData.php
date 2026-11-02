<?php

namespace App\DTO\Access;

use App\DTO\PageData;

/**
 * RD-03, the shell's indicators (ROUTE_CONTRACTS RD-03; INTERACTION_CONTRACTS
 * section 3.3): the number of the actor's own unresolved request receipts,
 * for a holder of CAP-34; the stock-attention count of the badge UXI-006, the
 * Habis and Menipis products, for a full holder of CAP-14 and null otherwise
 * (TASK-022), which the web layer reads from the Inventory module, since Access
 * does not depend on it (STACK_AND_MODULES section 2.1); and the as-of time of
 * the read, in UTC. The other indicators of RD-03 — open incidents and the
 * onboarding state — join this DTO with the Tasks that deliver them (TASK-036,
 * TASK-035). Nothing of another user and no business data beyond the count
 * (SEC-CH-01, STK-21).
 */
final readonly class ShellIndicatorsData implements PageData
{
    public function __construct(
        public ?int $unresolvedRequests,
        public string $asOf,
        public ?int $stockAttention = null,
    ) {}

    /**
     * The same indicators with the stock-attention count.
     */
    public function withStockAttention(?int $count): self
    {
        return new self($this->unresolvedRequests, $this->asOf, $count);
    }

    /**
     * @return array{unresolvedRequests: int|null, stockAttention: int|null, asOf: string}
     */
    public function toArray(): array
    {
        return [
            'unresolvedRequests' => $this->unresolvedRequests,
            'stockAttention' => $this->stockAttention,
            'asOf' => $this->asOf,
        ];
    }
}
