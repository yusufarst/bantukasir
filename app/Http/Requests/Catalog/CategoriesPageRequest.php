<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Commands\RefusingRequest;

/**
 * The filter of RP-307 (ROUTE_CONTRACTS section 2; INTERACTION_CONTRACTS section 3.2):
 * `status`, optional, as an allowlist; an invalid value is VALIDATION_FAILED.
 */
final class CategoriesPageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['sometimes', 'string', 'in:active,inactive']];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['status'];
    }

    public function status(): ?string
    {
        $status = $this->validatedInput()['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
