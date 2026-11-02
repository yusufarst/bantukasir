<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Queries\Inventory\ReceivingDocuments;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-404 (ROUTE_CONTRACTS RP-404; INTERACTION_CONTRACTS section 3.2;
 * TECHNICAL_RULES section 11.4; TASK-022 scope item 1): `from` and `to`, days of Asia/Jakarta,
 * `from` never after `to`; `q`, a reference or a product, up to 128 characters without a control
 * character; `after` or `before`, never both, the opaque cursor of a page's boundary row;
 * `perPage`, 1 to 100, 25 by default. Any other parameter is refused.
 */
final class ReceivingsPageRequest extends RefusingRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'to' => ['sometimes', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'q' => ['nullable', 'string', 'max:128', 'regex:'.self::PLAIN_TEXT],
            'after' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_-]{1,400}$/D'],
            'before' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_-]{1,400}$/D'],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['from', 'to', 'q', 'after', 'before', 'perPage'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'q.max' => ['ux.validation.text_max', ['maks' => 128]],
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

                DateRange::check($validator, $this->query('from'), $this->query('to'));
            },
        ];
    }

    /**
     * The query string's values, validated: a text absent is empty, a filter absent null.
     *
     * @return array{from: string|null, to: string|null, q: string, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $q = $validated['q'] ?? null;
        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'from' => is_string($from) ? $from : null,
            'to' => is_string($to) ? $to : null,
            'q' => is_string($q) ? $q : '',
            'after' => is_string($after) ? $after : null,
            'before' => is_string($before) ? $before : null,
            'perPage' => is_string($perPage) ? (int) $perPage : ReceivingDocuments::PER_PAGE,
        ];
    }
}
