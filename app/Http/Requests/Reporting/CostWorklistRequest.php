<?php

namespace App\Http\Requests\Reporting;

use App\Http\Requests\Commands\RefusingRequest;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Keyset;
use App\Queries\Reporting\CostWorklist;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-503 (ROUTE_CONTRACTS RP-503; INTERACTION_CONTRACTS section 3.2; TECHNICAL_RULES
 * section 11.4; TASK-025 scope item 6): `product`, an internal key, the filter of UXI-313; `after` or
 * `before`, never both, the opaque cursor of a page's boundary row; `perPage`, 1 to 100, 25 by
 * default. Any other parameter is refused.
 */
final class CostWorklistRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'after' => ['sometimes', 'string', Keyset::CURSOR],
            'before' => ['sometimes', 'string', Keyset::CURSOR],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['product', 'after', 'before', 'perPage'];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->query->has('after') && $this->query->has('before')) {
                    $validator->errors()->add('', '');
                }

                if ($this->query->has('product') && ObjectScope::key($this->query('product')) === null) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The query string's values, validated: a filter absent is null.
     *
     * @return array{product: int|null, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $product = $validated['product'] ?? null;
        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'product' => is_string($product) ? ObjectScope::key($product) : null,
            'after' => is_string($after) ? $after : null,
            'before' => is_string($before) ? $before : null,
            'perPage' => is_string($perPage) ? (int) $perPage : CostWorklist::PER_PAGE,
        ];
    }
}
