<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Barcodes\BarcodeToken;
use App\Http\Requests\Commands\RefusingRequest;
use App\Support\Validation\BarcodeTokenRule;
use Illuminate\Contracts\Validation\Validator;
use LogicException;

/**
 * The query of RD-05 (ROUTE_CONTRACTS RD-05, RT-08; INTERACTION_CONTRACTS section 3.5; BR-CAT-17):
 * `token`, read as a token, which the global trimming leaves alone (bootstrap/app.php). Any
 * other parameter is refused, and so is any body.
 */
final class TokenLookupRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['token' => ['required', 'string', new BarcodeTokenRule]];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['token'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'token.string' => ['ux.validation.barcode_token'],
            'token.barcode_token_rule' => ['ux.validation.barcode_token'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->json()->all() !== [] || $this->request->all() !== [] || $this->files->all() !== []) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The token of the query string, validated and normalized.
     */
    public function token(): string
    {
        $token = $this->validatedInput()['token'] ?? null;

        if (! is_string($token) || ($this->query->all()['token'] ?? null) !== $token) {
            throw new LogicException('RD-05 reads the query string it validated, and nothing else.');
        }

        return BarcodeToken::normalized($token);
    }
}
