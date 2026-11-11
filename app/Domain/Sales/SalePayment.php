<?php

namespace App\Domain\Sales;

use App\Support\Numbers\Money;

/**
 * The one payment of a Sale as PaymentMethods accepted it (SCHEMA section 5.9, `payments`;
 * BR-PAY-01 to BR-PAY-03): its method, the applied amount, which is the Sale total, and for CASH the
 * tendered amount and the change, for BANK_TRANSFER the optional reference.
 */
final readonly class SalePayment
{
    public function __construct(
        public string $method,
        public Money $applied,
        public ?Money $tendered,
        public ?Money $change,
        public ?string $reference,
    ) {}
}
