<?php

namespace App\Http\Requests\Costing;

use App\Http\Requests\Commands\RefusingRequest;

/**
 * The parameters of RP-505 (ROUTE_CONTRACTS RP-505; NAVIGATION_CONTRACTS UXP-505; TASK-025 scope item
 * 6): `from_page`, the page that opened it, for "Kembali ke …" (UXI-325): `product` (UXP-302),
 * `worklist` (UXP-503) or `requests` (UXP-006, after a resolved cost correction). Any other parameter
 * is refused.
 */
final class CostHistoryPageRequest extends RefusingRequest
{
    /** The openers of NAVIGATION_CONTRACTS UXP-505. */
    public const array OPENERS = ['product', 'worklist', 'requests'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_page' => ['sometimes', 'string', 'in:'.implode(',', self::OPENERS)],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['from_page'];
    }

    /**
     * The page that opened this one, or null.
     */
    public function opener(): ?string
    {
        $opener = $this->validatedInput()['from_page'] ?? null;

        return is_string($opener) ? $opener : null;
    }
}
