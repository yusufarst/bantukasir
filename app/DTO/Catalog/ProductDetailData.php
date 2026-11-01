<?php

namespace App\DTO\Catalog;

use App\DTO\PageData;

/**
 * A product as UXP-302 and UXP-304 show it (ROUTE_CONTRACTS RP-302, RP-304): its fields
 * with the name and state of its category, the price in force with its revision, the
 * version that RC-18 to RC-20 expect, and whether its unit and precision are locked
 * (BR-CAT-07). Never a cost, a valuation or a margin (BR-CAT-26).
 */
final readonly class ProductDetailData implements PageData
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public int $categoryId,
        public string $categoryName,
        public bool $categoryActive,
        public ?string $brand,
        public string $baseUnit,
        public int $quantityPrecision,
        public string $priceAmount,
        public int $priceRevisionId,
        public string $minimumStock,
        public bool $active,
        public int $version,
        public bool $unitLocked,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'categoryId' => $this->categoryId,
            'categoryName' => $this->categoryName,
            'categoryActive' => $this->categoryActive,
            'brand' => $this->brand,
            'baseUnit' => $this->baseUnit,
            'quantityPrecision' => $this->quantityPrecision,
            'price' => ['amount' => $this->priceAmount, 'revisionId' => $this->priceRevisionId],
            'minimumStock' => $this->minimumStock,
            'active' => $this->active,
            'version' => $this->version,
            'unitLocked' => $this->unitLocked,
        ];
    }
}
