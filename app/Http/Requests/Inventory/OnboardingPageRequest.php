<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Queries\Inventory\Keyset;
use App\Queries\Inventory\OnboardingChecklist;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-412 (ROUTE_CONTRACTS RP-412; INTERACTION_CONTRACTS section 3.2; TECHNICAL_RULES
 * section 11.4; UXI-287; TASK-035 scope item 5): `filter`, `counted` or `not_counted`, the checklist's
 * "Sudah dihitung" and "Belum dihitung"; `after` or `before`, never both, the opaque cursor of a page's
 * boundary row, since every list is paged (TR-PAGE-01 to TR-PAGE-03). Any other parameter is refused.
 */
final class OnboardingPageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'string', 'in:'.implode(',', OnboardingChecklist::FILTERS)],
            'after' => ['sometimes', 'string', Keyset::CURSOR],
            'before' => ['sometimes', 'string', Keyset::CURSOR],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['filter', 'after', 'before'];
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
            },
        ];
    }

    /**
     * The query string's values, validated: a parameter absent is null.
     *
     * @return array{filter: string|null, after: string|null, before: string|null}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $filter = $validated['filter'] ?? null;
        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;

        return [
            'filter' => is_string($filter) ? $filter : null,
            'after' => is_string($after) ? $after : null,
            'before' => is_string($before) ? $before : null,
        ];
    }
}
