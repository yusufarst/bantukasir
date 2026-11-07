<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\Quantity;

/**
 * A movement as a reversal reads it (SCHEMA section 5.7; BR-INV-34 to BR-INV-36): its key, its
 * product, its type, its signed quantity and its place in the product's sequence, the replay's start
 * position for its reversal (ADR-008 section 4.3). A fact: it never changes, so a command may read it
 * without a lock to choose the product's guard (SCH-31).
 */
final readonly class ReversalSource
{
    /** The types that a reversal may cancel (BR-INV-34, BR-INV-36). */
    public const array ELIGIBLE = [StockLedger::OPENING, StockLedger::RECEIPT, StockLedger::MANUAL_ISSUE];

    public function __construct(
        public int $movementId,
        public int $productId,
        public string $movementType,
        public Quantity $quantity,
        public int $productSequence,
    ) {}

    /**
     * Whether a reversal may cancel this movement's type: an OPENING, a RECEIPT or a MANUAL_ISSUE,
     * never a SALE_ISSUE, a RETURN_RECEIPT, an ADJUSTMENT or a REVERSAL (BR-INV-36).
     */
    public function eligible(): bool
    {
        return in_array($this->movementType, self::ELIGIBLE, true);
    }

    /**
     * The exact inverse of the source's quantity (BR-INV-34).
     */
    public function inverse(): Quantity
    {
        return $this->quantity->negated();
    }
}
