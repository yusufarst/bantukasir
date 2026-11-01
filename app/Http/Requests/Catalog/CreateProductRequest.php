<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\CreateProduct;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-17 `product.create` (INTERACTION_CONTRACTS RC-17;
 * BR-CAT-05): the SKU, 1 to 64 characters, trimmed by the global middleware and
 * kept as typed; whether it is taken is the database's to say (SCH-14). `barcode`,
 * optional, is `token` and an optional `symbology`; an empty one names no token and
 * is refused. Its checks of RC-27 are the Action's under its locks.
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
        return [
            'sku' => ['required', 'string', 'max:64', 'regex:'.self::PLAIN_TEXT],
            ...$this->productRules(),
            'barcode' => ['nullable', 'array'],
            'barcode.token' => ['required_with:payload.barcode', ...self::tokenRules()],
            'barcode.symbology' => self::symbologyRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if ($this->input('payload.barcode') === []) {
                $validator->errors()->add('', '');
            }
        }];
    }
}
