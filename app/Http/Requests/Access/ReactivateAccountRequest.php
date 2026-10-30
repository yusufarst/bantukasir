<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\ReactivateAccount;

/**
 * The FormRequest of RC-39 `user.reactivate`: `userId`.
 */
final class ReactivateAccountRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return ReactivateAccount::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::targetRules();
    }
}
