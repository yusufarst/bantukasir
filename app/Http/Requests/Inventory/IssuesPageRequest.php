<?php

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\ManualIssues;
use App\Http\Requests\Commands\RefusingRequest;
use App\Queries\Inventory\Keyset;
use App\Queries\Inventory\ManualIssueRecords;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-407 (ROUTE_CONTRACTS RP-407; INTERACTION_CONTRACTS section 3.2;
 * TECHNICAL_RULES section 11.4; TASK-023 scope item 4): `from` and `to`, days of Asia/Jakarta,
 * `from` never after `to`; `reason`, one of the five of BR-INV-19; `after` or `before`, never
 * both, the opaque cursor of a page's boundary row; `perPage`, 1 to 100, 25 by default. Any other
 * parameter is refused.
 */
final class IssuesPageRequest extends RefusingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'to' => ['sometimes', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'reason' => ['sometimes', 'string', 'in:'.implode(',', ManualIssues::REASONS)],
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
        return ['from', 'to', 'reason', 'after', 'before', 'perPage'];
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

                DateRange::check($validator, $this->query('from'), $this->query('to'));
            },
        ];
    }

    /**
     * The query string's values, validated: a filter absent is null.
     *
     * @return array{from: string|null, to: string|null, reason: string|null, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $reason = $validated['reason'] ?? null;
        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'from' => is_string($from) ? $from : null,
            'to' => is_string($to) ? $to : null,
            'reason' => is_string($reason) ? $reason : null,
            'after' => is_string($after) ? $after : null,
            'before' => is_string($before) ? $before : null,
            'perPage' => is_string($perPage) ? (int) $perPage : ManualIssueRecords::PER_PAGE,
        ];
    }
}
