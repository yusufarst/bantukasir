<?php

namespace App\Domain\Sales\Corrections;

use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;

/**
 * One line of a correction as its Action resolved it against the Sale (INTERACTION_CONTRACTS RC-05 and
 * RC-06; BR-RFD-02 to BR-RFD-04): the request's index, the Sale line it corrects, its product's precision
 * and unit, the money refunded on it, and the quantities returned on it, saleable and not saleable, at
 * that precision. A refund without a return, a return without money and both together are each one line
 * (BR-RFD-04).
 */
final readonly class CorrectedLine
{
    public function __construct(
        public int $index,
        public SaleLineFacts $line,
        public int $precision,
        public string $unit,
        public Money $refund,
        public Quantity $saleable,
        public Quantity $notSaleable,
    ) {}

    /**
     * The quantity the Sale line issued: the bound of its returns (BR-RFD-03).
     */
    public function issued(): Quantity
    {
        return Quantity::fromDatabase($this->line->quantity, $this->precision);
    }

    /**
     * The quantity this line returns, saleable or not: both count against the line's quantity bound
     * (SCHEMA section 5.9).
     */
    public function returned(): Quantity
    {
        return $this->saleable->plus($this->notSaleable);
    }

    /**
     * Whether the line refunds money or returns goods; a line of zeros changes nothing.
     */
    public function moves(): bool
    {
        return $this->refund->isPositive() || $this->saleable->isPositive() || $this->notSaleable->isPositive();
    }
}
