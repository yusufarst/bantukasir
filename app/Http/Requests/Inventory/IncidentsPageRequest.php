<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Queries\Inventory\Incidents;
use App\Queries\Inventory\Keyset;

/**
 * The parameters of RP-414 (ROUTE_CONTRACTS RP-414; INTERACTION_CONTRACTS section 3.2; TECHNICAL_RULES
 * section 11.4; TASK-036 scope item 3): `status`, BLOCKED or REOPENED (UXI-301); `after`, the opaque
 * cursor of a page's last row; `perPage`, 1 to 100, 25 by default. Any other parameter is refused.
 */
final class IncidentsPageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.implode(',', Incidents::STATES)],
            'after' => ['sometimes', 'string', Keyset::CURSOR],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['status', 'after', 'perPage'];
    }

    /**
     * The query string's values, validated: a filter absent is null.
     *
     * @return array{status: string|null, after: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $status = $validated['status'] ?? null;
        $after = $validated['after'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'status' => is_string($status) ? $status : null,
            'after' => is_string($after) ? $after : null,
            'perPage' => is_string($perPage) ? (int) $perPage : Incidents::PER_PAGE,
        ];
    }
}
