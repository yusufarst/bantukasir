<?php

namespace App\Domain\Sales;

use App\Domain\Catalog\SaleProduct;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;

/**
 * A line of a Sale as checkout priced it under the product guard (SCHEMA section 5.9, `sale_lines`;
 * BR-SALE-05, BR-SALE-06): its number in the order of the cart, its product with the price in force
 * as the cashier reviewed it, its quantity within the product's precision, and its total, quantity ×
 * unit price rounded half-up once to whole rupiah.
 */
final readonly class PricedLine
{
    public function __construct(
        public int $lineNo,
        public SaleProduct $product,
        public Quantity $quantity,
        public Money $lineTotal,
    ) {}

    /**
     * The line as the receipt freezes it, every value a string (SCHEMA sections 3.8 and 5.9,
     * `receipt_snapshots.lines`; DOCUMENT_CONTRACTS DC-01): its number, SKU, product name, unit,
     * quantity, unit price and line total.
     *
     * @return array{lineNo: string, sku: string, name: string, unit: string, quantity: string, unitPrice: string, lineTotal: string}
     */
    public function frozen(): array
    {
        return [
            'lineNo' => (string) $this->lineNo,
            'sku' => $this->product->sku,
            'name' => $this->product->name,
            'unit' => $this->product->guarded->baseUnit,
            'quantity' => $this->quantity->toCanonical(),
            'unitPrice' => $this->product->price->toCanonical(),
            'lineTotal' => $this->lineTotal->toCanonical(),
        ];
    }
}
