<?php

namespace App\Http\Requests\Costing;

use App\Http\Requests\Commands\RefusingRequest;

/**
 * The parameters of RP-504 (ROUTE_CONTRACTS RP-504; NAVIGATION_CONTRACTS UXP-504; TASK-025 scope item
 * 6): `from_page`, the page that opened it, for its way back, its "Batal" and where its success
 * leads (UXI-322, UXI-323): `worklist` (UXP-503), `history` (UXP-505), `receiving` (UXP-406) or
 * `movement` (UXP-403). Any other parameter is refused.
 */
final class CorrectionPageRequest extends RefusingRequest
{
    /** The openers of NAVIGATION_CONTRACTS UXP-504. */
    public const array OPENERS = ['worklist', 'history', 'receiving', 'movement'];

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
