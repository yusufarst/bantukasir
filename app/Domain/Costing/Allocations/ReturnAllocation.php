<?php

namespace App\Domain\Costing\Allocations;

use App\Domain\Costing\ProductValuation;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * The return allocation of one saleable return line, as a correction computes it at commit (ADR-008
 * sections 3, 4.1 and 5; COSTING BR-COST-17 to BR-COST-19; D-061; G-13): the cost tied to the original
 * sale issue for the quantity returned, by the cumulative rule, from the linked issue projection in force
 * at that moment, and the valuation after the RETURN_RECEIPT, an increase like any other (BR-COST-09).
 *
 * The cumulative rule: for an issue projection of amount A over the issued quantity q, the k-th saleable
 * return restores round6(A × R(k) / q) − round6(A × R(k−1) / q), where R(k−1) is the quantity that the
 * earlier saleable returns linked to it took back and R(k) that plus this return's. The sum restored
 * after k returns is round6(A × R(k) / q): several partial returns leave no residue, the total never
 * passes A, and when the whole quantity has come back the total is exactly A. A not-saleable return
 * restores nothing and has no return allocation (BR-COST-19); while A is UNKNOWN the restored cost is
 * UNKNOWN, never zero, and the valuation becomes UNKNOWN (BR-COST-13). The replay recomputes it from the
 * same link with the same rule (ValuationReplay).
 */
final readonly class ReturnAllocation
{
    private function __construct(
        public Cost $amount,
        public int $basedOnPublicationNo,
        public ProductValuation $valuation,
    ) {}

    /**
     * The cost that a saleable return of $returned restores, after earlier saleable returns of
     * $returnedBefore, of an issue of $issued whose projection is $issue. All three quantities are of
     * the product's precision.
     *
     * @throws NumberOutOfRange when the returns would pass the issued quantity, which the running total
     *                          of the line refuses first (BR-RFD-03; ADR-008 section 3), or a cost its range
     */
    public static function restored(Cost $issue, Quantity $issued, Quantity $returnedBefore, Quantity $returned): Cost
    {
        if (! $issued->isPositive() || ! $returned->isPositive() || $returnedBefore->isNegative()) {
            throw new LogicException('A saleable return takes back a positive quantity of an issue of a positive quantity.');
        }

        $after = $returnedBefore->plus($returned);

        if ($after->compareTo($issued) > 0) {
            throw NumberOutOfRange::for('returned quantity');
        }

        if (! $issue->isKnown()) {
            return Cost::unknown();
        }

        return Cost::mulDiv($issue, $after, $issued)->minus(Cost::mulDiv($issue, $returnedBefore, $issued))->assertValue();
    }

    /**
     * The allocation of a saleable return of $returned at this sequence of the product, linked to an
     * issue of $issued whose projection is $issue, after earlier saleable returns of $returnedBefore,
     * from the valuation and the quantity on hand before it; and the valuation after it, one publication
     * on (ADR-008 sections 4.2 and 4.3). The valuation it was computed from is the cost-evidence state it
     * depended on, as for an issue allocation (SCHEMA section 5.8).
     *
     * @throws NumberOutOfRange
     */
    public static function compute(Cost $issue, Quantity $issued, Quantity $returnedBefore, Quantity $returned, ProductValuation $before, Quantity $onHand, int $sequence): self
    {
        if (! $before->stored || $before->publicationNo < 1) {
            throw new LogicException('A product that a Sale issued has a published valuation (ADR-008 section 4.2).');
        }

        $amount = self::restored($issue, $issued, $returnedBefore, $returned);

        return new self($amount, $before->publicationNo, $before->increased($onHand, $amount, $sequence));
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
