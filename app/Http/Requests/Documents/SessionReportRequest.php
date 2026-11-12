<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The payload of RC-45 and RC-46 (INTERACTION_CONTRACTS, "RC-44 to RC-47"; TECHNICAL_RULES section
 * 11): `sessionId`, a JSON whole number, and nothing else; no expected version, since the report is
 * immutable. Whether the session is CLOSED and in the actor's scope is the Action's, which answers
 * anything else NOT_FOUND (TR-ERR-03).
 */
abstract class SessionReportRequest extends CommandRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'sessionId' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $session = $this->input('payload.sessionId');

            if ($session !== null && ! is_int($session)) {
                $validator->errors()->add('', '');
            }
        }];
    }
}
