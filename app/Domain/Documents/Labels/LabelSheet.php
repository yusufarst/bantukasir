<?php

namespace App\Domain\Documents\Labels;

use App\DTO\PageData;

/**
 * What a barcode label prints (DOCUMENT_CONTRACTS DC-05; BR-CAT-35): the product's name
 * and SKU, the code that the print request recorded, and how many labels it asked for.
 * Never a cost, a price or anything else.
 */
final readonly class LabelSheet implements PageData
{
    public function __construct(
        public string $name,
        public string $sku,
        public string $code,
        public int $count,
    ) {}

    /**
     * @return array{name: string, sku: string, code: string, count: int}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'sku' => $this->sku, 'code' => $this->code, 'count' => $this->count];
    }
}
