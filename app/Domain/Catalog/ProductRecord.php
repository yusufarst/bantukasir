<?php

namespace App\Domain\Catalog;

use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;

/**
 * A row of `products` as an Action reads it, with the amount of the selling
 * price in force (SCHEMA section 5.6).
 */
final readonly class ProductRecord
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public int $categoryId,
        public ?string $brand,
        public string $baseUnit,
        public int $quantityPrecision,
        public int $currentPriceRevisionId,
        public Money $sellingPrice,
        public Quantity $minimumStock,
        public bool $active,
        public int $version,
    ) {}

    /**
     * The names of RC-18's fields that differ from these values, in its
     * payload's order.
     *
     * @return list<string>
     */
    public function changedBy(ProductFields $fields): array
    {
        $changed = [];

        foreach ([
            'name' => $fields->name !== $this->name,
            'categoryId' => $fields->categoryId !== $this->categoryId,
            'brand' => $fields->brand !== $this->brand,
            'baseUnit' => $fields->baseUnit !== $this->baseUnit,
            'quantityPrecision' => $fields->quantityPrecision !== $this->quantityPrecision,
            'sellingPrice' => ! $fields->sellingPrice->isEqualTo($this->sellingPrice),
            'minimumStock' => $fields->minimumStock->compareTo($this->minimumStock) !== 0,
        ] as $field => $differs) {
            if ($differs) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * The values of the named fields as they stand, for the audit (BR-AUD-02),
     * since an edit leaves no other record of them.
     *
     * @param  list<string>  $names
     * @return array<string, string|int|null>
     */
    public function auditValues(array $names): array
    {
        return array_intersect_key([
            'name' => $this->name,
            'categoryId' => $this->categoryId,
            'brand' => $this->brand,
            'baseUnit' => $this->baseUnit,
            'quantityPrecision' => $this->quantityPrecision,
            'minimumStock' => $this->minimumStock->toCanonical(),
        ], array_flip($names));
    }

    /**
     * Whether the fields would change a column that the stock movements'
     * composite key references (BR-CAT-07; SCHEMA section 7).
     */
    public function unitChangedBy(ProductFields $fields): bool
    {
        return $fields->baseUnit !== $this->baseUnit || $fields->quantityPrecision !== $this->quantityPrecision;
    }
}
