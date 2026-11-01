<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Commands\RefusingRequest;
use Illuminate\Contracts\Validation\Validator;
use LogicException;

/**
 * The query of RD-06 (ROUTE_CONTRACTS RD-06, RT-08; INTERACTION_CONTRACTS section
 * 3.5; TECHNICAL_RULES section 11.3; TASK-015 scope item 4): `sku` and `name`, at
 * least one, each within its field's limit and without a control character.
 * `barcode` joins with TASK-016, and until then it is an unknown parameter. Any
 * other parameter is refused, and so is any body.
 */
final class SearchFirstRequest extends RefusingRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:64', 'regex:'.self::PLAIN_TEXT],
            'name' => ['nullable', 'string', 'max:200', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['sku', 'name'];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        return [
            'sku.max' => ['ux.validation.sku'],
            'sku.regex' => ['ux.validation.sku'],
            'name.max' => ['ux.validation.text_max', ['maks' => 200]],
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
                    || (! is_string($this->query('sku')) && ! is_string($this->query('name')))) {
                    $validator->errors()->add('', '');
                }
            },
        ];
    }

    /**
     * The query string's values, validated.
     *
     * @return array{sku: string|null, name: string|null}
     */
    public function checks(): array
    {
        $validated = $this->validatedInput();

        foreach ($validated as $name => $value) {
            if (($this->query->all()[$name] ?? null) !== $value) {
                throw new LogicException('RD-06 reads the query string it validated, and nothing else.');
            }
        }

        $sku = $validated['sku'] ?? null;
        $name = $validated['name'] ?? null;

        return ['sku' => is_string($sku) ? $sku : null, 'name' => is_string($name) ? $name : null];
    }
}
