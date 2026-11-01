<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\RetireBarcode;

/**
 * The FormRequest of RC-29 `barcode.retire` (INTERACTION_CONTRACTS RC-29; BR-CAT-52;
 * TR-VAL-04): `mappingId` and `reasonClass`, `MISASSIGNMENT` or `OTHER`; no expected
 * version, since the mapping is judged active under its product's lock.
 */
final class RetireBarcodeRequest extends CatalogCommandRequest
{
    public function operation(): string
    {
        return RetireBarcode::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'mappingId' => ['required', 'integer', 'min:1'],
            'reasonClass' => ['required', 'string', 'in:MISASSIGNMENT,OTHER'],
        ];
    }
}
