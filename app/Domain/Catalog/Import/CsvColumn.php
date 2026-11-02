<?php

namespace App\Domain\Catalog\Import;

use LogicException;

/**
 * The columns of the product import file, in the order of DOCUMENT_CONTRACTS DC-04,
 * each named by its header of COPY_DECK section 8.9. The value is the field of DC-04.
 */
enum CsvColumn: string
{
    case Sku = 'sku';
    case Name = 'name';
    case Category = 'category';
    case Brand = 'brand';
    case BaseUnit = 'baseUnit';
    case QuantityPrecision = 'quantityPrecision';
    case SellingPrice = 'sellingPrice';
    case MinimumStock = 'minimumStock';
    case Barcode = 'barcode';

    public function header(): string
    {
        $text = match ($this) {
            self::Sku => __('catalog.csv_import.column.sku'),
            self::Name => __('catalog.csv_import.column.name'),
            self::Category => __('catalog.csv_import.column.category'),
            self::Brand => __('catalog.csv_import.column.brand'),
            self::BaseUnit => __('catalog.csv_import.column.base_unit'),
            self::QuantityPrecision => __('catalog.csv_import.column.quantity_precision'),
            self::SellingPrice => __('catalog.csv_import.column.selling_price'),
            self::MinimumStock => __('catalog.csv_import.column.minimum_stock'),
            self::Barcode => __('catalog.csv_import.column.barcode'),
        };

        return is_string($text) ? $text : throw new LogicException('A column header is one text of COPY_DECK section 8.9.');
    }

    public function required(): bool
    {
        return ! in_array($this, [self::Brand, self::MinimumStock, self::Barcode], true);
    }
}
