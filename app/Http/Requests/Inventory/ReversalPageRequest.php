<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;

/**
 * The parameters of RP-411 (ROUTE_CONTRACTS RP-411; NAVIGATION_CONTRACTS UXP-411; TASK-026 scope item
 * 4): `from_page`, the page that opened it, for its way back and its "Batal" (UXI-283, UXI-284):
 * `movement` (UXP-403), `receiving` (UXP-406) or `issue` (UXP-409). Any other parameter is refused.
 */
final class ReversalPageRequest extends RefusingRequest
{
    /** The openers of NAVIGATION_CONTRACTS UXP-411. */
    public const array OPENERS = ['movement', 'receiving', 'issue'];

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
