<?php

namespace App\Domain\Sales\Corrections;

use App\Support\Numbers\Money;

/**
 * The running total of a Sale under its lock, the guard row of its corrections (SCHEMA sections 5.9 and
 * 7, `sale_correction_totals`; TR §7.1 step 5; BR-RFD-03): its total, which equals the applied payment
 * (BR-PAY-02, BR-PAY-03) and bounds its refunds, and the money refunded so far.
 */
final readonly class SaleTotals
{
    public function __construct(
        public int $saleId,
        public Money $saleTotal,
        public Money $refunded,
    ) {}

    /**
     * What may still be refunded of the Sale (BR-RFD-03).
     */
    public function remaining(): Money
    {
        return $this->saleTotal->minus($this->refunded);
    }
}
