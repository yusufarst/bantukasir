<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ConfirmReversal;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-10 `reversal.confirm` (INTERACTION_CONTRACTS RC-10; TECHNICAL_RULES sections
 * 5 and 11.3; BR-INV-34): `movementId`, the movement to reverse, a JSON whole number, and `reason`, 1
 * to 1,000 characters. The Owner never enters a quantity: the reversal is the source's exact inverse,
 * and nothing else is accepted. No expected value: "not yet reversed" is checked under the locks.
 * Whether the movement exists and may be reversed is the Action's to answer, NOT_FOUND, and the rest
 * under the product's guard.
 */
final class ConfirmReversalRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return ConfirmReversal::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'movementId' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            // An identifier is a JSON whole number, never a text (TR-VAL-06).
            $value = $this->input('payload.movementId');

            if ($value !== null && ! is_int($value)) {
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
            'payload.movementId.integer' => ['ux.validation.choose_one'],
            'payload.movementId.min' => ['ux.validation.choose_one'],
            'payload.reason.required' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
    }
}
