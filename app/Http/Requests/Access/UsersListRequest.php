<?php

namespace App\Http\Requests\Access;

use App\Http\Requests\Commands\RefusingRequest;

/**
 * The filters of RP-602 (ROUTE_CONTRACTS section 2; INTERACTION_CONTRACTS
 * section 3.2): `status` and `role`, each optional, as allowlists; an invalid
 * value is VALIDATION_FAILED.
 */
final class UsersListRequest extends RefusingRequest
{
    public const array STATUSES = ['active', 'deactivated', 'anonymized'];

    public const array ROLES = ['owner', 'cashier', 'operations_admin'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.implode(',', self::STATUSES)],
            'role' => ['sometimes', 'string', 'in:'.implode(',', self::ROLES)],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['status', 'role'];
    }

    public function status(): ?string
    {
        return self::filter($this->validatedInput()['status'] ?? null);
    }

    public function role(): ?string
    {
        return self::filter($this->validatedInput()['role'] ?? null);
    }

    private static function filter(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
