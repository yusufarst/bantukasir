<?php

namespace App\Actions\Sales;

use App\Support\Numbers\Money;

/**
 * One line of RC-05's or RC-06's payload as its FormRequest validated it (INTERACTION_CONTRACTS RC-05
 * and RC-06): its index in the request, the Sale line it names, the money refunded on it, zero or more,
 * and the quantities returned on it, saleable and not saleable, zero or more, as canonical strings that
 * the Action reads at the product's precision.
 */
final readonly class CorrectionLine
{
    public function __construct(
        public int $index,
        public int $saleLineId,
        public Money $refund,
        public string $saleable,
        public string $notSaleable,
    ) {}
}
