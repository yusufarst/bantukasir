<?php

namespace App\DTO\Catalog;

use App\DTO\PageData;

/**
 * A product in a result of RD-01 or RD-06 (INTERACTION_CONTRACTS section 3.1;
 * BR-CAT-26): what every role that sees a result sees — identity, name, SKU, base
 * unit and precision, the current price with its revision, the active flag and the
 * thumbnail's path, and whether it holds an active barcode, for UXI-163. Never a cost, a
 * valuation or a margin. RD-01 adds the stock the role may see (Queries\Inventory\ProductStock),
 * and in the context `receiving` the product's version, which RC-07 expects as reviewed
 * (TASK-022): withVersion().
 */
final readonly class ProductRowData implements PageData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public string $baseUnit,
        public int $quantityPrecision,
        public string $priceAmount,
        public int $priceRevisionId,
        public bool $active,
        public ?string $thumbnail,
        public bool $activeBarcode,
        public int $version,
    ) {}

    /**
     * The row with the product's version, for the context `receiving` (INTERACTION_CONTRACTS
     * RC-07, `expected.products`).
     *
     * @return array{id: int, name: string, sku: string, baseUnit: string, quantityPrecision: int, price: array{amount: string, revisionId: int}, active: bool, thumbnail: string|null, activeBarcode: bool, version: int}
     */
    public function withVersion(): array
    {
        return [...$this->toArray(), 'version' => $this->version];
    }

    /**
     * @return array{id: int, name: string, sku: string, baseUnit: string, quantityPrecision: int, price: array{amount: string, revisionId: int}, active: bool, thumbnail: string|null, activeBarcode: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'baseUnit' => $this->baseUnit,
            'quantityPrecision' => $this->quantityPrecision,
            'price' => ['amount' => $this->priceAmount, 'revisionId' => $this->priceRevisionId],
            'active' => $this->active,
            'thumbnail' => $this->thumbnail,
            'activeBarcode' => $this->activeBarcode,
        ];
    }
}
