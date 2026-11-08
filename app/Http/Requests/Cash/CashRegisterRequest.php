<?php

namespace App\Http\Requests\Cash;

use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest base of RC-33 and RC-34 (INTERACTION_CONTRACTS RC-33 and RC-34; TECHNICAL_RULES
 * sections 5 and 11.3; SCHEMA section 5.10; COPY_DECK section 16.1): `code` (1 to 32) and `label`
 * (1 to 100), each trimmed by the global middleware, valid UTF-8 and without a control character
 * (TR-VAL-05), and `active`, a JSON boolean, never a text or a number that looks like one
 * (TR-VAL-06). Whether a code is taken is the database's alone (SCH-14), so the Action answers it.
 */
abstract class CashRegisterRequest extends CommandRequest
{
    protected const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public const int CODE_MAX = 32;

    public const int LABEL_MAX = 100;

    /** The paths that hold a JSON whole number. */
    private const array WHOLE_NUMBERS = ['payload.registerId', 'expected.registerVersion'];

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            foreach (self::WHOLE_NUMBERS as $path) {
                $value = $this->input($path);

                if ($value !== null && ! is_int($value)) {
                    $validator->errors()->add('', '');
                }
            }

            if ($this->has('payload.active') && ! is_bool($this->input('payload.active'))) {
                $validator->errors()->add('payload.active', '');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function registerRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:'.self::PLAIN_TEXT],
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX, 'regex:'.self::PLAIN_TEXT],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'payload.code.string' => ['ux.validation.required'],
            'payload.code.max' => ['ux.validation.text_max', ['maks' => self::CODE_MAX]],
            'payload.label.string' => ['ux.validation.required'],
            'payload.label.max' => ['ux.validation.text_max', ['maks' => self::LABEL_MAX]],
            'payload.active.boolean' => ['ux.validation.choose_one'],
        ];
    }
}
