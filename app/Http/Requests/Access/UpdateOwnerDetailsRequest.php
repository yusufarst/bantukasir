<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\UpdateOwnerDetails;

/**
 * The FormRequest of RC-37 `owner.update_details`: `displayName` and `email`;
 * the account is always the Owner's own, from the sign-in session (SEC-AZ-05).
 */
final class UpdateOwnerDetailsRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return UpdateOwnerDetails::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::detailRules();
    }
}
