<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\DeactivateAccount;

/**
 * The FormRequest of RC-38 `user.deactivate`: `userId`.
 */
final class DeactivateAccountRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return DeactivateAccount::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::targetRules();
    }
}
