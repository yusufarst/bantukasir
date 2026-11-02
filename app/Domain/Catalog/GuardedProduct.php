<?php

namespace App\Domain\Catalog;

/**
 * A product as a stock posting holds it under its update lock (TECHNICAL_RULES section
 * 7.1 step 6, the product guard): what the posting checks, and the unit and precision
 * that its movements copy through their composite key (SCHEMA section 5.7).
 */
final readonly class GuardedProduct
{
    public function __construct(
        public int $id,
        public string $baseUnit,
        public int $quantityPrecision,
        public bool $active,
        public int $version,
    ) {}
}
