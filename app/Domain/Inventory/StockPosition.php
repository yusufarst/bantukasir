<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;

/**
 * What a product's stock balance says under its lock (SCHEMA section 5.7, `stock_balances`;
 * BR-INV-03): the quantity on hand and the sequence of its last movement, or nothing before
 * its first movement, whose row does not exist yet. A posting computes each line's position
 * from the one before, before it writes anything, so that lines of one product are added
 * together before the check (BR-INV-07; G-02).
 */
final readonly class StockPosition
{
    public function __construct(
        public int $productId,
        public Quantity $onHand,
        public int $lastSequence,
        public bool $stored,
    ) {}

    /**
     * The position of a product with no movement: nothing on hand and no row (BR-CAT-03).
     */
    public static function none(int $productId, int $precision): self
    {
        return new self($productId, Quantity::of('0', $precision), 0, false);
    }

    /**
     * The position after one movement of this signed quantity, in the next place of the
     * product's sequence (BR-INV-02, BR-INV-05).
     *
     * @throws NumberOutOfRange below zero, which stock never is (BR-INV-04), or above its range
     */
    public function moved(Quantity $signed): self
    {
        return new self($this->productId, $this->onHand->plus($signed)->assertStock(), $this->lastSequence + 1, $this->stored);
    }
}
