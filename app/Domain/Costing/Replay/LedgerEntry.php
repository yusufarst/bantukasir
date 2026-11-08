<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * One movement of a product's logical ledger as the replay applies it (ADR-008 section 4.1): its
 * place in the product's sequence and its signed quantity; for an increase, the effective cost of
 * its evidence — the original amount, the new amount of its latest correction, or UNKNOWN; for an
 * issue, its allocation and that allocation's current projection, which the replay recomputes.
 */
final readonly class LedgerEntry
{
    private function __construct(
        public int $sequence,
        public Quantity $quantity,
        public ?Cost $cost,
        public ?int $allocationId,
        public ?Cost $projection,
    ) {}

    /**
     * An OPENING, a RECEIPT or a positive ADJUSTMENT with the effective cost of its evidence.
     */
    public static function increase(int $sequence, Quantity $quantity, Cost $cost): self
    {
        if (! $quantity->isPositive()) {
            throw new LogicException('An increase adds stock.');
        }

        return new self($sequence, $quantity, $cost, null, null);
    }

    /**
     * A SALE_ISSUE, a MANUAL_ISSUE or a negative ADJUSTMENT with its allocation and that
     * allocation's current projection, KNOWN or UNKNOWN.
     */
    public static function issue(int $sequence, Quantity $quantity, int $allocationId, Cost $projection): self
    {
        if (! $quantity->isNegative()) {
            throw new LogicException('An issue takes stock out.');
        }

        return new self($sequence, $quantity, null, $allocationId, $projection);
    }

    public function isIncrease(): bool
    {
        return $this->cost !== null;
    }
}
