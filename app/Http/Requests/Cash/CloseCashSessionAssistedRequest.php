<?php

namespace App\Http\Requests\Cash;

use App\Actions\Cash\CloseCashSessionAssisted;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Validation\MoneyRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-04 `cash_session.close_assisted` (INTERACTION_CONTRACTS RC-04; TECHNICAL_RULES
 * sections 5 and 11.3; BR-CASH-12, BR-CASH-18; COPY_DECK section 16.1): `sessionId`, a JSON whole
 * number, the session the close preview named; `physicalCash`, required — a blank amount is refused with
 * its own key, and `"0"` is valid — as a canonical string of whole rupiah (MoneyRule); `reason`, 1 to
 * 1,000 characters without a control character (TR-VAL-05); `expected.activityVersion`, the JSON whole
 * number of the close preview. Never expected cash, a total, a variance, a responsible user or a close
 * actor: the server derives them (TR-VAL-03), and the allowlist refuses them. Whether the session may be
 * closed here — another user's, OPEN — is the Action's, under the session's lock.
 */
final class CloseCashSessionAssistedRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return CloseCashSessionAssisted::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'sessionId' => ['required', 'integer', 'min:1'],
            'physicalCash' => ['required', new MoneyRule],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
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
            'payload.reason.required' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
    }
}
