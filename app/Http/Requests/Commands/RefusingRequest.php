<?php

namespace App\Http\Requests\Commands;

use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The FormRequest base of the JSON routes of the command platform — the
 * commands, the status check RC-50 and the read RD-04 (TR-VAL-01, TR-VAL-07;
 * ROUTE_CONTRACTS RT-07; TASK-008 scope items 2 and 7). Its refusal is the
 * error body of RT-07, never Laravel's validation answer, so that no body
 * carries the framework's English summary (OPEN_ITEMS T-26):
 *
 * - every field that the request does not list is refused, not trimmed:
 *   allowed() lists the accepted paths, `*` standing for one list index;
 * - a failed rule is VALIDATION_FAILED, with `fields` mapping the request's
 *   path to the COPY_DECK keys of its failed rules (fieldKeys()) and their
 *   parameters; a rule without a key gives the path no entry;
 * - a refused authorization is FORBIDDEN.
 *
 * The validator formats no message of its own: every rule's message is
 * empty, so it looks up no translation.
 */
abstract class RefusingRequest extends FormRequest
{
    /** The built-in rules these requests use, whose messages are empty. */
    private const array RULES = [
        'required', 'present', 'array', 'list', 'string', 'integer', 'boolean', 'uuid', 'in', 'not_in',
        'regex', 'not_regex', 'max', 'min', 'between', 'size', 'digits', 'digits_between', 'distinct',
        'prohibited', 'same', 'different', 'filled', 'nullable', 'sometimes', 'required_with', 'required_without',
        'required_if', 'prohibited_if', 'gt', 'gte', 'lt', 'lte', 'file', 'mimes', 'mimetypes', 'date', 'email',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The accepted paths of the request, with `*` for one list index; a
     * path accepts everything below it only when it ends with `.**`.
     *
     * @return list<string>
     */
    abstract protected function allowed(): array;

    /**
     * The message key of a failed rule, by "path.rule" with `*` for an index,
     * with its parameters (COPY_DECK section 16). `*.required` is
     * `ux.validation.required` unless a request names its own.
     *
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [];
    }

    /**
     * Empty messages, so that the validator looks up no translation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_fill_keys(self::RULES, '');
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->unknownPaths() !== []) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * The paths of the input that allowed() does not list.
     *
     * @return list<string>
     */
    public function unknownPaths(): array
    {
        $patterns = $this->allowed();
        $unknown = [];

        foreach (self::paths($this->all()) as $path) {
            $known = false;

            foreach ($patterns as $pattern) {
                if (self::matches($pattern, $path)) {
                    $known = true;

                    break;
                }
            }

            if (! $known) {
                $unknown[] = $path;
            }
        }

        return $unknown;
    }

    protected function failedValidation(Validator $validator): never
    {
        $fields = [];
        $keys = $this->fieldKeys() + ['*.required' => ['ux.validation.required'], '*.present' => ['ux.validation.required']];

        foreach ($validator->failed() as $path => $rules) {
            foreach (array_keys($rules) as $rule) {
                $key = self::keyFor($keys, (string) $path, Str::snake(class_basename((string) $rule)));

                if ($key !== null) {
                    $fields[(string) $path][] = ['key' => $key[0], 'params' => $key[1] ?? []];
                }
            }
        }

        throw new CommandRefused(ErrorCode::ValidationFailed, fields: $fields);
    }

    protected function failedAuthorization(): never
    {
        throw new ActorNotAllowed;
    }

    /**
     * Every leaf path of the input, and the path of every empty list or
     * object, in dot notation.
     *
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    private static function paths(array $input, string $prefix = ''): array
    {
        $paths = [];

        foreach ($input as $key => $value) {
            $path = $prefix.$key;

            if (is_array($value) && $value !== []) {
                array_push($paths, ...self::paths($value, $path.'.'));
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private static function matches(string $pattern, string $path): bool
    {
        if (str_ends_with($pattern, '.**')) {
            $base = substr($pattern, 0, -3);

            return self::matches($base, $path) || preg_match('/^'.self::regex($base).'\./', $path) === 1;
        }

        return preg_match('/^'.self::regex($pattern).'$/', $path) === 1;
    }

    private static function regex(string $pattern): string
    {
        return str_replace('\*', '(?:0|[1-9][0-9]{0,5})', preg_quote($pattern, '/'));
    }

    /**
     * @param  array<string, array{0: string, 1?: array<string, string|int>}>  $keys
     * @return array{0: string, 1?: array<string, string|int>}|null
     */
    private static function keyFor(array $keys, string $path, string $rule): ?array
    {
        foreach ($keys as $selector => $key) {
            $dot = strrpos($selector, '.');

            if ($dot === false || substr($selector, $dot + 1) !== $rule) {
                continue;
            }

            $field = substr($selector, 0, $dot);

            if ($field === '*' || self::matches($field, $path)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The validated input as an array of the request's own values.
     *
     * @return array<string, mixed>
     */
    protected function validatedInput(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = Arr::wrap($this->validated());

        return $validated;
    }
}
