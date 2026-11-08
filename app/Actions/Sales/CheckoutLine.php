<?php

namespace App\Actions\Sales;

/**
 * A line of RC-01 as the Action reads it from an envelope that its FormRequest validated
 * (INTERACTION_CONTRACTS RC-01): the product, the quantity as its canonical text, which is judged
 * against the product's precision only under its lock (TR-VAL-08), and the price revision that the
 * cashier reviewed, from `expected.lines` at the same index. A line carries no price, total or cost:
 * the server takes none of them from the client (TR-VAL-03).
 */
final readonly class CheckoutLine
{
    public function __construct(
        public int $productId,
        public string $quantity,
        public int $priceRevisionId,
    ) {}
}
