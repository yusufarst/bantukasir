<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\CreateStaffAccount;

/**
 * The FormRequest of RC-35 `user.create`: `displayName`, `email` and `roles`.
 * It expects no version.
 */
final class CreateStaffAccountRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return CreateStaffAccount::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [...self::detailRules(), ...self::roleRules()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [];
    }
}
