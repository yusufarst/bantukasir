<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\WithdrawZeroCount;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-14 `onboarding.withdraw_zero_count` (INTERACTION_CONTRACTS RC-14; TECHNICAL_RULES
 * sections 5 and 11.3; D-087): `zeroCountId`, the zero count to withdraw, and `reason`, 1 to 1,000
 * characters; `expected.onboardingVersion`, the onboarding version reviewed. An identifier and a version
 * are JSON whole numbers (TR-VAL-06). Whether the zero count exists and may be withdrawn is the Action's
 * to answer, NOT_FOUND, and the rest under the locks.
 */
final class WithdrawZeroCountRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return WithdrawZeroCount::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'zeroCountId' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['onboardingVersion' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $zeroCount = $this->input('payload.zeroCountId');

            if (($zeroCount !== null && ! is_int($zeroCount)) || ! is_int($this->input('expected.onboardingVersion'))) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'payload.zeroCountId.integer' => ['ux.validation.choose_one'],
            'payload.zeroCountId.min' => ['ux.validation.choose_one'],
            'payload.reason.required' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
    }
}
