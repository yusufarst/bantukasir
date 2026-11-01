<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\AddBarcode;

/**
 * The FormRequest of RC-27 `barcode.add` (INTERACTION_CONTRACTS RC-27; BR-CAT-17):
 * `productId`, `token` and an optional `symbology`; no expected version. Whether the
 * token is eligible is the Action's under its lock.
 */
final class AddBarcodeRequest extends CatalogCommandRequest
{
    public function operation(): string
    {
        return AddBarcode::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            ...self::productTargetRules(),
            'token' => ['required', ...self::tokenRules()],
            'symbology' => self::symbologyRules(),
        ];
    }
}
