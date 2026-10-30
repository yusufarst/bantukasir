<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\UpdateStaffAccount;

/**
 * The FormRequest of RC-36 `user.update`: `userId`, `displayName`, `email` and
 * `roles`.
 */
final class UpdateStaffAccountRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return UpdateStaffAccount::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [...self::targetRules(), ...self::detailRules(), ...self::roleRules()];
    }
}
