<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\CreateProduct;

/**
 * The FormRequest of RC-17 `product.create` (INTERACTION_CONTRACTS RC-17;
 * BR-CAT-05): the SKU, 1 to 64 characters, trimmed by the global middleware and
 * kept as typed; whether it is taken is the database's to say (SCH-14). `barcode`
 * joins the payload with TASK-016, and until then it is an unknown field.
 */
final class CreateProductRequest extends CatalogCommandRequest
{
    public function operation(): string
    {
        return CreateProduct::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return ['sku' => ['required', 'string', 'max:64', 'regex:'.self::PLAIN_TEXT], ...$this->productRules()];
    }
}
