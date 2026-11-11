<?php

namespace App\Http\Requests\Cash;

use App\Actions\Cash\CloseCashSession;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Validation\MoneyRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-03 `cash_session.close` (INTERACTION_CONTRACTS RC-03; TECHNICAL_RULES sections
 * 5 and 11.1; BR-CASH-12, BR-CASH-13; COPY_DECK section 16.1): `sessionId`, a JSON whole number, the
 * session the close preview named; `physicalCash`, required — a blank amount is refused with its own
 * key, and `"0"` is valid — as a canonical string of whole rupiah from 0 to the money limit
 * (MoneyRule); `expected.activityVersion`, the JSON whole number of the close preview, zero or more.
 * Never expected cash, a total, a variance, a number or a responsible user: the server derives them
 * (TR-VAL-03), and the allowlist refuses them, as it refuses a reason, which only RC-04 takes. The
 * session's scope — its responsible user is the actor — is the Action's, under the session's lock,
 * where another user's session answers as one that does not exist (SEC-AZ-03, TR-ERR-03).
 */
final class CloseCashSessionRequest extends CommandRequest
{
    public function operation(): string
    {
        return CloseCashSession::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'sessionId' => ['required', 'integer', 'min:1'],
            'physicalCash' => ['required', new MoneyRule],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'activityVersion' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $session = $this->input('payload.sessionId');
            $version = $this->input('expected.activityVersion');

            if (($session !== null && ! is_int($session)) || ($version !== null && ! is_int($version))) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $money = MoneyRule::problem($this->input('payload.physicalCash'), 'payload.physicalCash') ?? 'money';

        return [
            'payload.physicalCash.required' => ['ux.validation.money_blank'],
            'payload.physicalCash.money_rule' => ['ux.validation.'.$money],
        ];
    }
}
