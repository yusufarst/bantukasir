<?php

namespace App\Domain\Sales\Corrections;

use App\Support\Numbers\Money;

/**
 * The running totals of one Sale line under the original Sale's lock (SCHEMA section 5.9,
 * `sale_line_correction_totals`; BR-RFD-03): the money refunded and the quantity returned so far, saleable
 * or not, as the column holds it. A line without its row has neither yet.
 */
final readonly class LineTotals
{
    public function __construct(
        public int $saleLineId,
        public Money $refunded,
        public string $returned,
    ) {}
}
