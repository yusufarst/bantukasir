<?php

namespace App\Http\Requests\Access;

use App\Domain\Access\StaffRoles;
use App\Http\Requests\Commands\CommandRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest base of RC-35 to RC-42 (INTERACTION_CONTRACTS "RC-35 to
 * RC-42"; TECHNICAL_RULES section 11.3, TR-VAL-05; TASK-011 scope item 1): the
 * rules of the fields they share and their COPY_DECK keys. Every operation
 * except RC-35 expects `userVersion`, which each request names.
 */
abstract class UserCommandRequest extends CommandRequest
{
    /** Valid UTF-8 without a control character, a line break included (TR-VAL-05). */
    protected const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /** The domain of the placeholder of an anonymized account, never a person's (SEC-ACC-09). */
    private const string RESERVED_EMAIL = '/@users\.invalid\z/i';

    /**
     * @return array<string, mixed>
     */
    protected static function detailRules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'regex:'.self::PLAIN_TEXT, 'not_regex:'.self::RESERVED_EMAIL],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function roleRules(): array
    {
        return [
            'roles' => ['required', 'array', 'list', 'min:1', 'max:'.count(StaffRoles::NAMES)],
            'roles.*' => ['required', 'string', 'distinct', 'in:'.implode(',', StaffRoles::NAMES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function targetRules(): array
    {
        return ['userId' => ['required', 'integer', 'min:1']];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['userVersion' => ['required', 'integer', 'min:1']];
    }

    /**
     * An identifier and a version are JSON whole numbers, never a text that
     * looks like one: the `integer` rule alone accepts "5", which the Action
     * would never read as this account (TR-VAL-06).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            foreach (['payload.userId', 'expected.userVersion'] as $path) {
                $value = $this->input($path);

                if ($value !== null && ! is_int($value)) {
                    $validator->errors()->add('', '');
                }
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'payload.displayName.max' => ['ux.validation.text_max', ['maks' => 100]],
            'payload.displayName.string' => ['ux.validation.required'],
            'payload.email.max' => ['ux.validation.text_max', ['maks' => 254]],
            'payload.email.email' => ['ux.validation.email'],
            'payload.email.not_regex' => ['ux.validation.email'],
            'payload.email.string' => ['ux.validation.required'],
            'payload.roles.array' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
            'payload.reason.string' => ['ux.validation.required'],
        ];
    }
}
