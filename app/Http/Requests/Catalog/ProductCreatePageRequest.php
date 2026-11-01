<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Barcodes\BarcodeToken;
use App\Http\Requests\Commands\RefusingRequest;
use App\Support\Validation\BarcodeTokenRule;

/**
 * The parameter of RP-303 (ROUTE_CONTRACTS section 2; TASK-017 scope item 1): `code`,
 * the unknown code that a working page hands over, read as BR-CAT-17 reads a token.
 */
final class ProductCreatePageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['nullable', 'string', new BarcodeTokenRule]];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['code'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'code.string' => ['ux.validation.barcode_token'],
            'code.barcode_token_rule' => ['ux.validation.barcode_token'],
        ];
    }

    /**
     * The code as its token, or null without one.
     */
    public function code(): ?string
    {
        $code = $this->validatedInput()['code'] ?? null;

        return is_string($code) ? BarcodeToken::normalized($code) : null;
    }
}
