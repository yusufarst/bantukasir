<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Http\Requests\Commands\RefusingRequest;
use App\Models\User;
use App\Policies\ObjectScope;
use App\Queries\Catalog\ProductSearch;
use App\Queries\Catalog\SearchFilters;
use Illuminate\Contracts\Validation\Validator;
use LogicException;

/**
 * The query of RD-01 (ROUTE_CONTRACTS RD-01, RT-08; INTERACTION_CONTRACTS section
 * 3.1; TECHNICAL_RULES sections 11.3 and 11.4; TASK-015 scope item 3): `context`,
 * required, one of the contexts of the contract, each open only to its capability
 * and answering FORBIDDEN until its Task adds it; `q`, up to 128 characters without
 * a control character; `mode`, only `search` until TASK-016 adds `exact`; `category`,
 * `brand`, `status`, `page` and `perPage`. Any other parameter is refused, and so
 * is any body: the read takes its parameters from the query string alone (TASK-008
 * review finding R-01).
 *
 * The client's sequence number travels in the header SEQUENCE_HEADER, which the
 * answer repeats, so that a page applies only the answer to its newest request
 * (INTERACTION_CONTRACTS section 3.1; TR-PAGE-04).
 */
final class ProductSearchRequest extends RefusingRequest
{
    /** The header of INTERACTION_CONTRACTS section 3.1. */
    public const string SEQUENCE_HEADER = 'X-Search-Sequence';

    /** Every context of INTERACTION_CONTRACTS section 3.1 and ROUTE_CONTRACTS RD-01. */
    public const array CONTEXTS = ['till', 'catalog', 'receiving', 'issue', 'adjustment', 'opening', 'stock', 'correction'];

    /**
     * The contexts already provided, each with its capability; every later Task
     * adds its own here (TRACEABILITY section 7).
     *
     * @var array<string, Capability>
     */
    private const array PROVIDED = ['catalog' => Capability::Cap04];

    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function authorize(): bool
    {
        $context = $this->query('context');

        // A context that is missing or unknown is the rules' to refuse.
        if (! is_string($context) || ! in_array($context, self::CONTEXTS, true)) {
            return true;
        }

        $capability = self::PROVIDED[$context] ?? null;
        $user = $this->user();

        return $capability !== null && $user instanceof User && Capabilities::allows($user, $capability);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'context' => ['required', 'string', 'in:'.implode(',', self::CONTEXTS)],
            'q' => ['nullable', 'string', 'max:128', 'regex:'.self::PLAIN_TEXT],
            'mode' => ['sometimes', 'string', 'in:search'],
            'category' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'brand' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'page' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,5}$/D'],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['context', 'q', 'mode', 'category', 'brand', 'status', 'page', 'perPage'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'q.max' => ['ux.validation.text_max', ['maks' => 128]],
            'brand.max' => ['ux.validation.text_max', ['maks' => 100]],
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
                $sequence = $this->sequence();

                if ($this->json()->all() !== [] || $this->request->all() !== [] || $this->files->all() !== []
                    || ($this->query->has('category') && ObjectScope::key($this->query('category')) === null)
                    || ($sequence !== null && preg_match('/^[0-9]{1,16}$/D', $sequence) !== 1)) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The query string's values, validated.
     */
    public function filters(): SearchFilters
    {
        $validated = $this->validatedInput();

        foreach ($validated as $name => $value) {
            if (($this->query->all()[$name] ?? null) !== $value) {
                throw new LogicException('RD-01 reads the query string it validated, and nothing else.');
            }
        }

        $q = $validated['q'] ?? null;
        $category = $validated['category'] ?? null;
        $brand = $validated['brand'] ?? null;
        $status = $validated['status'] ?? null;
        $page = $validated['page'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return new SearchFilters(
            is_string($q) ? $q : '',
            is_string($category) ? ObjectScope::key($category) : null,
            is_string($brand) ? $brand : null,
            $status === 'active' || $status === 'inactive' ? $status : null,
            is_string($page) ? (int) $page : 1,
            is_string($perPage) ? (int) $perPage : ProductSearch::PER_PAGE,
        );
    }

    /**
     * The client's sequence number, which after() validated.
     */
    public function sequence(): ?string
    {
        return $this->headers->get(self::SEQUENCE_HEADER);
    }
}
