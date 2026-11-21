<?php

namespace App\Http\Requests\Audit;

use App\Domain\Audit\SecurityEvent;
use App\Http\Requests\Commands\RefusingRequest;
use App\Http\Requests\Inventory\DateRange;
use App\Policies\ObjectScope;
use App\Queries\Audit\SecurityLogEntries;
use App\Queries\Inventory\Keyset;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-613 (ROUTE_CONTRACTS RP-613, RT-08; INTERACTION_CONTRACTS section 9, UXI-382 and
 * UXI-384; TECHNICAL_RULES section 11.4; TASK-042 scope item 2), each an allowlist: `from` and `to`, days of
 * Asia/Jakarta, `from` never after `to`, refused at `to`; `type`, one of the nine event types of SCHEMA
 * section 5.4; `user`, an internal key; `after` or `before`, never both, the opaque cursor of a page's
 * boundary row; `perPage`, 1 to 100, 25 by default. Any other parameter is refused.
 */
final class SecurityLogPageRequest extends RefusingRequest
{
    private const string DAY = 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', self::DAY, 'date'],
            'to' => ['sometimes', 'string', self::DAY, 'date'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', array_map(static fn (SecurityEvent $event): string => $event->value, SecurityEvent::cases()))],
            'user' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'after' => ['sometimes', 'string', Keyset::CURSOR],
            'before' => ['sometimes', 'string', Keyset::CURSOR],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['from', 'to', 'type', 'user', 'after', 'before', 'perPage'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'to.prohibited' => ['ux.validation.date_order'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->query->has('after') && $this->query->has('before')) {
                    $validator->errors()->add('', '');
                }

                if ($this->query->has('user') && ObjectScope::key($this->query('user')) === null) {
                    $validator->errors()->add('', '');
                }

                DateRange::check($validator, $this->query('from'), $this->query('to'));
            },
        ];
    }

    /**
     * The query string's values, validated: a filter absent is null.
     *
     * @return array{from: string|null, to: string|null, type: string|null, user: int|null, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $text = static function (string $name) use ($validated): ?string {
            $value = $validated[$name] ?? null;

            return is_string($value) && $value !== '' ? $value : null;
        };
        $perPage = $text('perPage');

        return [
            'from' => $text('from'),
            'to' => $text('to'),
            'type' => $text('type'),
            'user' => ObjectScope::key($text('user')),
            'after' => $text('after'),
            'before' => $text('before'),
            'perPage' => $perPage === null ? SecurityLogEntries::PER_PAGE : (int) $perPage,
        ];
    }
}
