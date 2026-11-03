<?php

namespace App\Domain\Costing\Allocations;

use App\Domain\Costing\ProductValuation;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * The issue-cost allocation of one issue movement, as an ordinary posting computes it at commit
 * (ADR-008 sections 2, 4.1, 4.2 and 5; COSTING BR-COST-10 to BR-COST-13, BR-COST-15; INVENTORY
 * BR-INV-21; G-13), from the product's current valuation, which is the state immediately before
 * the issue:
 *
 * - while the valuation is UNKNOWN, the allocation is UNKNOWN, never zero (BR-COST-13);
 * - the issue that takes the quantity to zero takes the whole remaining value, so that no
 *   rounding residue is left (BR-COST-11);
 * - any other issue of q units out of Q takes round6(V × q / Q), the exact product divided once
 *   and rounded half-up to six decimals (BR-COST-10, BR-COST-12), and V falls by that amount.
 *
 * Whenever the quantity reaches zero the next state starts a new pool, V = 0 and KNOWN (ADR-008
 * section 4.1; BR-COST-15), so the valuation after such an issue is a known zero. The same rule
 * serves SALE_ISSUE, MANUAL_ISSUE and a negative ADJUSTMENT (BR-COST-10); only a SALE_ISSUE's
 * allocation enters cost of goods sold, which this class neither knows nor records.
 */
final readonly class IssueAllocation
{
    private function __construct(
        public Cost $amount,
        public int $basedOnPublicationNo,
        public ProductValuation $valuation,
    ) {}

    /**
     * The allocation of an issue of this positive quantity, at this sequence of the product,
     * from the valuation and the quantity on hand before it, and the valuation after it, one
     * publication on (ADR-008 section 4.3). The caller has checked that the stock suffices
     * (G-02).
     *
     * @throws NumberOutOfRange when the allocation would pass its range (TECHNICAL_RULES section 11.1)
     */
    public static function compute(ProductValuation $before, Quantity $onHand, Quantity $issued, int $sequence): self
    {
        if (! $issued->isPositive() || $issued->compareTo($onHand) > 0) {
            throw new LogicException('An issue takes a positive quantity of at most the stock on hand (G-02).');
        }

        if (! $before->stored || $before->publicationNo < 1) {
            throw new LogicException('A product with stock on hand has a published valuation (ADR-008 section 4.2).');
        }

        $toZero = $issued->compareTo($onHand) === 0;

        if (! $before->value->isKnown()) {
            $amount = Cost::unknown();
            $after = $toZero ? Cost::of('0') : Cost::unknown();
        } elseif ($toZero) {
            $amount = $before->value;
            $after = Cost::of('0');
        } else {
            $amount = Cost::mulDiv($before->value, $issued, $onHand)->assertValue();
            $after = $before->value->minus($amount);
        }

        return new self(
            $amount,
            $before->publicationNo,
            new ProductValuation($before->productId, $after->assertValue(), $before->publicationNo + 1, $sequence, $before->stored),
        );
    }

    /**
     * The status column of SCHEMA section 3.2.
     */
    public function status(): string
    {
        return $this->amount->isKnown() ? 'KNOWN' : 'UNKNOWN';
    }

    /**
     * The amount column of SCHEMA section 3.2: null for UNKNOWN, never zero.
     */
    public function canonicalAmount(): ?string
    {
        return $this->amount->isKnown() ? $this->amount->toCanonical() : null;
    }
}
