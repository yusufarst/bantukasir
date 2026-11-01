<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Commands\RefusingRequest;
use App\Policies\ObjectScope;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-301 (ROUTE_CONTRACTS section 2; INTERACTION_CONTRACTS sections
 * 3.1 and 3.2; TASK-017 scope item 1): `q`, `category`, `brand`, `status`, `page` and
 * `import`, RC-31's location (TASK-019), each optional and within RD-01's limits, as
 * allowlists; an invalid value is VALIDATION_FAILED. The results themselves come from
 * RD-01.
 */
final class ProductsPageRequest extends RefusingRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:128', 'regex:'.self::PLAIN_TEXT],
            'category' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
            'brand' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'page' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,5}$/D'],
            'import' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}$/D'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['q', 'category', 'brand', 'status', 'page', 'import'];
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
                if (($this->query->has('category') && ObjectScope::key($this->query('category')) === null)
                    || ($this->query->has('import') && ObjectScope::key($this->query('import')) === null)) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The filters as the page keeps them: a text absent is empty, a filter absent null.
     *
     * @return array{q: string, category: int|null, brand: string, status: string|null, page: int, import: int|null}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $q = $validated['q'] ?? null;
        $category = $validated['category'] ?? null;
        $brand = $validated['brand'] ?? null;
        $status = $validated['status'] ?? null;
        $page = $validated['page'] ?? null;
        $import = $validated['import'] ?? null;

        return [
            'q' => is_string($q) ? $q : '',
            'category' => is_string($category) ? ObjectScope::key($category) : null,
            'brand' => is_string($brand) ? $brand : '',
            'status' => is_string($status) ? $status : null,
            'page' => is_string($page) ? (int) $page : 1,
            'import' => is_string($import) ? ObjectScope::key($import) : null,
        ];
    }
}
