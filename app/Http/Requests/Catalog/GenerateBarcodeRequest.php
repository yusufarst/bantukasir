<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\GenerateBarcode;

/**
 * The FormRequest of RC-28 `barcode.generate` (INTERACTION_CONTRACTS RC-28):
 * `productId`; no expected version.
 */
final class GenerateBarcodeRequest extends CatalogCommandRequest
{
    public function operation(): string
    {
        return GenerateBarcode::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::productTargetRules();
    }
}
