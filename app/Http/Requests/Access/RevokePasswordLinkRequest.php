<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\RevokePasswordLink;

/**
 * The FormRequest of RC-42 `user.revoke_link`: `userId`.
 */
final class RevokePasswordLinkRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return RevokePasswordLink::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::targetRules();
    }
}
