<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Policies\ObjectScope;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-410 (ROUTE_CONTRACTS RP-410; NAVIGATION_CONTRACTS UXP-410; TASK-024 scope
 * item 3): `product`, an internal key, the draft's first line when given (UXI-217, UXI-221);
 * `from_page`, the page that opened it for its way back and its "Batal" (UXI-275, UXI-276):
 * `stock` (UXP-401) or `history` (UXP-402). Any other parameter is refused.
 */
final class AdjustmentPageRequest extends RefusingRequest
{
    /** The openers of NAVIGATION_CONTRACTS UXP-410. */
    public const array OPENERS = ['stock', 'history'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'from_page' => ['sometimes', 'string', 'in:'.implode(',', self::OPENERS)],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['product', 'from_page'];
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
     * The product of the first line, or null.
     */
    public function product(): ?int
    {
        $product = $this->validatedInput()['product'] ?? null;

        return is_string($product) ? ObjectScope::key($product) : null;
    }

    /**
     * The page that opened this one, for its way back, or null.
     */
    public function opener(): ?string
    {
        $opener = $this->validatedInput()['from_page'] ?? null;

        return is_string($opener) ? $opener : null;
    }
}
