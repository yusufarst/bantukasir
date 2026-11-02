<?php

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\ProductFields;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * One data row, its cells read as the values of DOCUMENT_CONTRACTS DC-04, and every error
 * of the row: each a message key of COPY_DECK section 16.3 with its parameters. A value
 * that its cell does not give is null, or the empty text, and an error says why.
 */
final class CsvRow
{
    /** @var list<array{key: string, params: array<string, string|int>}> */
    public array $errors = [];

    public ?int $categoryId = null;

    public function __construct(
        public readonly int $number,
        public readonly string $sku,
        public readonly string $name,
        public readonly string $category,
        public readonly ?string $brand,
        public readonly string $baseUnit,
        public readonly ?int $quantityPrecision,
        public readonly string $sellingPriceText,
        public readonly ?Money $sellingPrice,
        public readonly ?Quantity $minimumStock,
        public readonly ?string $barcode,
    ) {}

    /**
     * @param  array<string, string|int>  $params
     */
    public function addError(string $key, array $params = []): void
    {
        $this->errors[] = ['key' => $key, 'params' => $params];
    }

    /**
     * The fields of a row without errors, for the catalogue's services.
     */
    public function fields(): ProductFields
    {
        if ($this->errors !== [] || $this->categoryId === null || $this->quantityPrecision === null || $this->sellingPrice === null || $this->minimumStock === null) {
            throw new LogicException('Only a row without errors becomes a product (BR-CAT-38).');
        }

        return new ProductFields($this->name, $this->categoryId, $this->brand, $this->baseUnit, $this->quantityPrecision, $this->sellingPrice, $this->minimumStock);
    }
}
