<?php

namespace App\Http\Requests\Audit;

use App\Domain\Audit\AuditActions;
use App\Http\Requests\Commands\RefusingRequest;
use App\Http\Requests\Inventory\DateRange;
use App\Policies\ObjectScope;
use App\Queries\Audit\ActivityLog;
use App\Queries\Inventory\Keyset;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-612 (ROUTE_CONTRACTS RP-612, RT-08; INTERACTION_CONTRACTS section 9, UXI-377 and
 * UXI-380; TECHNICAL_RULES section 11.4; TASK-042 scope item 1), each an allowlist: `from` and `to`, days of
 * Asia/Jakarta, `from` never after `to`, refused at `to`; `user` and `product`, internal keys; `action`, one
 * of the codes of INTERACTION_CONTRACTS section 8; `session`, a cash session's number; `reference`, up to
 * 100 characters without a control character, matched exactly; `after` or `before`, never both, the opaque
 * cursor of a page's boundary row; `perPage`, 1 to 100, 25 by default. Any other parameter is refused.
 */
final class ActivityPageRequest extends RefusingRequest
{
    private const string DAY = 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';

    private const string KEY = 'regex:/^[1-9][0-9]{0,18}$/D';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', self::DAY, 'date'],
            'to' => ['sometimes', 'string', self::DAY, 'date'],
            'user' => ['sometimes', 'string', self::KEY],
            'action' => ['sometimes', 'string', 'in:'.implode(',', array_keys(AuditActions::LABELS))],
            'product' => ['sometimes', 'string', self::KEY],
            'session' => ['sometimes', 'string', 'regex:/^[0-9]{8}-[0-9]{6,19}$/D'],
            'reference' => ['sometimes', 'string', 'max:100', 'not_regex:/\p{Cc}/u'],
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
        return ['from', 'to', 'user', 'action', 'product', 'session', 'reference', 'after', 'before', 'perPage'];
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

                foreach (['user', 'product'] as $key) {
                    if ($this->query->has($key) && ObjectScope::key($this->query($key)) === null) {
                        $validator->errors()->add('', '');
                    }
                }

                DateRange::check($validator, $this->query('from'), $this->query('to'));
            },
        ];
    }

    /**
     * The query string's values, validated: a filter absent is null.
     *
     * @return array{from: string|null, to: string|null, user: int|null, action: string|null, product: int|null, session: string|null, reference: string|null, after: string|null, before: string|null, perPage: int}
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
            'user' => ObjectScope::key($text('user')),
            'action' => $text('action'),
            'product' => ObjectScope::key($text('product')),
            'session' => $text('session'),
            'reference' => $text('reference'),
            'after' => $text('after'),
            'before' => $text('before'),
            'perPage' => $perPage === null ? ActivityLog::PER_PAGE : (int) $perPage,
        ];
    }
}
