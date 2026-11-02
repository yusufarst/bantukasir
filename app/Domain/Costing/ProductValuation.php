<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Cost;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;

/**
 * The current valuation of one product (SCHEMA section 5.8, `inventory_valuations`; ADR-008
 * sections 4.1 and 4.2): its value, KNOWN or UNKNOWN, the movement it reflects and its
 * publication number. The quantity is the stock balance's (SCH-01), so a posting gives it
 * here. A posting computes each state from the one before, before it writes anything.
 *
 * An increase of the moving weighted average (BR-COST-09): Q + q, and V + cost while both
 * are KNOWN; otherwise the value becomes UNKNOWN (BR-COST-13). Stock that reached zero starts
 * a new pool, V = 0 and KNOWN, so a whole KNOWN increase starts a known pool (BR-COST-15).
 * The amounts are exact sums: nothing is rounded here, and an average is one division of
 * these sums at the end of its computation (BR-COST-12; ADR-008 section 5).
 */
final readonly class ProductValuation
{
    public function __construct(
        public int $productId,
        public Cost $value,
        public int $publicationNo,
        public int $lastSequence,
        public bool $stored,
    ) {}

    /**
     * The empty prefix of the ledger: Q = 0, V = 0, KNOWN, and no row yet (ADR-008 section 4.1).
     */
    public static function none(int $productId): self
    {
        return new self($productId, Cost::of('0'), 0, 0, false);
    }

    /**
     * The state after an increase of the movement at this sequence, whose cost is KNOWN or
     * UNKNOWN, from the quantity on hand before it; one publication on (ADR-008 section 4.3).
     *
     * @throws NumberOutOfRange when the value would pass its range (TECHNICAL_RULES section 11.1)
     */
    public function increased(Quantity $quantityBefore, Cost $cost, int $sequence): self
    {
        $pool = $quantityBefore->isZero() ? Cost::of('0') : $this->value;

        return new self($this->productId, $pool->plus($cost)->assertValue(), $this->publicationNo + 1, $sequence, $this->stored);
    }
}
