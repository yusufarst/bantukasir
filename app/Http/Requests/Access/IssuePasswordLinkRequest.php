<?php

namespace App\Http\Requests\Access;

use App\Actions\Access\IssuePasswordLink;

/**
 * The FormRequest of RC-41 `user.issue_link`: `userId` and `kind`.
 */
final class IssuePasswordLinkRequest extends UserCommandRequest
{
    public function operation(): string
    {
        return IssuePasswordLink::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [...self::targetRules(), 'kind' => ['required', 'string', 'in:'.IssuePasswordLink::FIRST_PASSWORD.','.IssuePasswordLink::RESET]];
    }
}
