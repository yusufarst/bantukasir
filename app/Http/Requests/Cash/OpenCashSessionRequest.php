<?php

namespace App\Http\Requests\Cash;

use App\Actions\Cash\OpenCashSession;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Validation\MoneyRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-02 `cash_session.open` (INTERACTION_CONTRACTS RC-02; TECHNICAL_RULES sections
 * 5 and 11.1; BR-CASH-04; COPY_DECK section 16.1): `registerId`, a JSON whole number, and
 * `openingCash`, required — a blank amount is refused with its own key, and `"0"` is valid — as a
 * canonical string of whole rupiah from 0 to the money limit (MoneyRule). No expected version, and
 * never a responsible user, a number or a date (TR-VAL-03): the allowlist refuses them. A register's
 * scope is the whole shop's, so a missing one is the Action's NOT_FOUND under its lock.
 */
final class OpenCashSessionRequest extends CommandRequest
{
    public function operation(): string
    {
        return OpenCashSession::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'registerId' => ['required', 'integer', 'min:1'],
            'openingCash' => ['required', new MoneyRule],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $register = $this->input('payload.registerId');

            if ($register !== null && ! is_int($register)) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $money = MoneyRule::problem($this->input('payload.openingCash'), 'payload.openingCash') ?? 'money';

        return [
            'payload.registerId.required' => ['ux.validation.choose_one'],
            'payload.registerId.integer' => ['ux.validation.choose_one'],
            'payload.registerId.min' => ['ux.validation.choose_one'],
            'payload.openingCash.required' => ['ux.validation.money_blank'],
            'payload.openingCash.money_rule' => ['ux.validation.'.$money],
        ];
    }
}
