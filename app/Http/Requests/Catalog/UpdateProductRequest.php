<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\UpdateProduct;

/**
 * The FormRequest of RC-18 `product.update` (INTERACTION_CONTRACTS RC-18):
 * `productId` and the fields of RC-17 except `sku` and `barcode`, against the
 * expected `productVersion`.
 */
final class UpdateProductRequest extends CatalogCommandRequest
{
    public function operation(): string
    {
        return UpdateProduct::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [...self::productTargetRules(), ...$this->productRules()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['productVersion' => ['required', 'integer', 'min:1']];
    }
}
