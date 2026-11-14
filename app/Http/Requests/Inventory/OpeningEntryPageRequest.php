<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Policies\ObjectScope;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-413 (ROUTE_CONTRACTS RP-413; NAVIGATION_CONTRACTS UXP-413; TASK-035 scope item 5):
 * `product`, an internal key, the product that "Catat ulang stok awal" names for its replacement (UXI-228,
 * UXI-291), which the draft starts with as a search result would. Any other parameter is refused.
 */
final class OpeningEntryPageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['product'];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->query->has('product') && ObjectScope::key($this->query('product')) === null) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The product the draft starts with, or null.
     */
    public function product(): ?int
    {
        $product = $this->validatedInput()['product'] ?? null;

        return is_string($product) ? ObjectScope::key($product) : null;
    }
}
