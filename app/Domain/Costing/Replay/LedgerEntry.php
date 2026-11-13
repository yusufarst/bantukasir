<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * One movement of a product's logical ledger as the replay applies it (ADR-008 section 4.1): its
 * place in the product's sequence and its signed quantity; for an increase, the effective cost of
 * its evidence — the original amount, the new amount of its latest correction, or UNKNOWN; for an
 * issue, its allocation and that allocation's current projection, which the replay recomputes; for a
 * RETURN_RECEIPT, its return allocation, that allocation's current projection and the link to its
 * SALE_ISSUE allocation, from which the replay recomputes it (ADR-008 section 3; TASK-033).
 */
final readonly class LedgerEntry
{
    private function __construct(
        public int $sequence,
        public Quantity $quantity,
        public ?Cost $cost,
        public ?int $allocationId,
        public ?Cost $projection,
        public ?ReturnLink $link = null,
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

    /**
     * A RETURN_RECEIPT with its return allocation, that allocation's current projection, KNOWN or
     * UNKNOWN, and its link to the SALE_ISSUE allocation whose cost it restores.
     */
    public static function returnReceipt(int $sequence, Quantity $quantity, int $allocationId, Cost $projection, ReturnLink $link): self
    {
        if (! $quantity->isPositive()) {
            throw new LogicException('A saleable return adds stock.');
        }

        return new self($sequence, $quantity, null, $allocationId, $projection, $link);
    }

    /**
     * Whether the movement adds stock: an increase with its evidence, or a RETURN_RECEIPT.
     */
    public function isIncrease(): bool
    {
        return $this->cost !== null || $this->link !== null;
    }

    public function isReturn(): bool
    {
        return $this->link !== null;
    }
}
