<?php

namespace App\Domain\Catalog;

use App\Support\Numbers\Money;

/**
 * A product as a Sale holds it under its update lock (TECHNICAL_RULES section 7.2, "Sale
 * checkout", step 6, the product guard): what every stock posting holds of it, with the SKU and the
 * name that the receipt freezes and the price in force, its revision and that revision's amount,
 * which the Sale applies only as the cashier reviewed it (BR-SALE-05; D-036).
 */
final readonly class SaleProduct
{
    public function __construct(
        public GuardedProduct $guarded,
        public string $sku,
        public string $name,
        public int $priceRevisionId,
        public Money $price,
    ) {}
}
