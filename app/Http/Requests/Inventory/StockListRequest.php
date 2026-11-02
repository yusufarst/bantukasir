<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Policies\ObjectScope;
use App\Queries\Inventory\StockList;
use Illuminate\Contracts\Validation\Validator;

/**
 * The query of RP-401 and RD-02 (ROUTE_CONTRACTS RP-401, RD-02, RT-08; INTERACTION_CONTRACTS
 * sections 3.2 and 3.3; TASK-022 scope items 2 and 3): `q`, up to 128 characters without a
 * control character; `state`, OUT, LOW or NORMAL; `category`, an internal key; `after`, the
 * opaque cursor of the last row; `perPage`, 1 to 100, 25 by default. Any other parameter is
 * refused, and so is any body: the read takes its parameters from the query string alone.
 */
final class StockListRequest extends RefusingRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:128', 'regex:'.self::PLAIN_TEXT],
            'state' => ['sometimes', 'string', 'in:'.implode(',', StockList::STATES)],
            'category' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'after' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_-]{1,400}$/D'],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['q', 'state', 'category', 'after', 'perPage'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'q.max' => ['ux.validation.text_max', ['maks' => 128]],
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
                if ($this->json()->all() !== [] || $this->request->all() !== [] || $this->files->all() !== []
                    || ($this->query->has('category') && ObjectScope::key($this->query('category')) === null)) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The query string's values, validated: a text absent is empty, a filter absent null.
     *
     * @return array{q: string, state: string|null, category: int|null, after: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $q = $validated['q'] ?? null;
        $state = $validated['state'] ?? null;
        $category = $validated['category'] ?? null;
        $after = $validated['after'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'q' => is_string($q) ? $q : '',
            'state' => is_string($state) ? $state : null,
            'category' => is_string($category) ? ObjectScope::key($category) : null,
            'after' => is_string($after) ? $after : null,
            'perPage' => is_string($perPage) ? (int) $perPage : StockList::PER_PAGE,
        ];
    }
}
