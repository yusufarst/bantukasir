<?php

namespace App\Domain\Sales\Corrections;

use App\Support\Numbers\Money;

/**
 * A line of the corrected Sale as it was committed (SCHEMA section 5.9, `sale_lines`): its internal key,
 * its product, its line total, the money bound of its refunds, and the quantity it issued, the quantity
 * bound of its returns, as the column holds it — read at the product's precision by its reader
 * (BR-RFD-03). A fact read without a lock (SCH-31).
 */
final readonly class SaleLineFacts
{
    public function __construct(
        public int $id,
        public int $productId,
        public Money $lineTotal,
        public string $quantity,
    ) {}
}
