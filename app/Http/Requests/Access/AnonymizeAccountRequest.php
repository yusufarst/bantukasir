<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\AnonymizeAccount;

/**
 * The FormRequest of RC-40 `user.anonymize`: `userId` and `reason` (1 to 1,000).
 */
final class AnonymizeAccountRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return AnonymizeAccount::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [...self::targetRules(), 'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT]];
    }
}
