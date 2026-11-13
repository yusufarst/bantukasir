<?php

namespace App\Domain\Costing\Allocations;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;

/**
 * The SALE_ISSUE allocation of a Sale line as a saleable return links to it (ADR-008 section 3; D-061):
 * its internal key, its Sale line and product, the quantity it issued and its current projection, KNOWN
 * or UNKNOWN, from which the return restores its cost by the cumulative rule (ADR-008 section 5).
 */
final readonly class LinkedIssue
{
    public function __construct(
        public int $allocationId,
        public int $saleLineId,
        public int $productId,
        public Quantity $issued,
        public Cost $projection,
    ) {}
}
