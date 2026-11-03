<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Commands\RefusingRequest;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Keyset;
use App\Queries\Inventory\StockHistory;
use Illuminate\Contracts\Validation\Validator;

/**
 * The parameters of RP-402 (ROUTE_CONTRACTS RP-402; INTERACTION_CONTRACTS section 3.2;
 * TECHNICAL_RULES section 11.4; TASK-022 scope item 2): `product`, an internal key, or up to
 * 200 of them separated by commas, the products of an adjustment that RC-09 leads to (TASK-024),
 * each kept once; `type`, a
 * movement type of INVENTORY section 2; `from` and `to`, days of Asia/Jakarta, `from` never
 * after `to` (`ux.validation.date_order`); `after` or `before`, never both, the opaque cursor of
 * a page's boundary row; `perPage`, 1 to 100, 25 by default; `from_page`, the page that opened
 * this one for its way back (UXI-223): `stock`, `product` or `requests`. Any other parameter is
 * refused.
 */
final class HistoryPageRequest extends RefusingRequest
{
    /** The openers whose way back UXI-223 names (NAVIGATION_CONTRACTS UXP-402); UXP-415 joins with TASK-036. */
    public const array OPENERS = ['stock', 'product', 'requests'];

    /** The most products of the filter: those of one adjustment (TECHNICAL_RULES section 11.2). */
    public const int MAX_PRODUCTS = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{0,18}(?:,[1-9][0-9]{0,18}){0,'.(string) (self::MAX_PRODUCTS - 1).'}$/D'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', StockHistory::TYPES)],
            'from' => ['sometimes', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'to' => ['sometimes', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'after' => ['sometimes', 'string', Keyset::CURSOR],
            'before' => ['sometimes', 'string', Keyset::CURSOR],
            'perPage' => ['sometimes', 'string', 'regex:/^(?:[1-9][0-9]?|100)$/D'],
            'from_page' => ['sometimes', 'string', 'in:'.implode(',', self::OPENERS)],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowed(): array
    {
        return ['product', 'type', 'from', 'to', 'after', 'before', 'perPage', 'from_page'];
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
                if (($this->query->has('after') && $this->query->has('before'))
                    || ($this->query->has('product') && self::keysOf($this->query('product')) === null)) {
                    $validator->errors()->add('', '');
                }

                DateRange::check($validator, $this->query('from'), $this->query('to'));
            },
        ];
    }

    /**
     * The query string's values, validated.
     *
     * @return array{products: list<int>, type: string|null, from: string|null, to: string|null, after: string|null, before: string|null, perPage: int}
     */
    public function filters(): array
    {
        $validated = $this->validatedInput();
        $product = $validated['product'] ?? null;
        $type = $validated['type'] ?? null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $after = $validated['after'] ?? null;
        $before = $validated['before'] ?? null;
        $perPage = $validated['perPage'] ?? null;

        return [
            'products' => is_string($product) ? (self::keysOf($product) ?? []) : [],
            'type' => is_string($type) ? $type : null,
            'from' => is_string($from) ? $from : null,
            'to' => is_string($to) ? $to : null,
            'after' => is_string($after) ? $after : null,
            'before' => is_string($before) ? $before : null,
            'perPage' => is_string($perPage) ? (int) $perPage : StockHistory::PER_PAGE,
        ];
    }

    /**
     * The internal keys of a text of keys separated by commas, each once, ascending; null when
     * one of them is not a key.
     *
     * @return list<int>|null
     */
    private static function keysOf(mixed $text): ?array
    {
        if (! is_string($text)) {
            return null;
        }

        $keys = [];

        foreach (explode(',', $text) as $part) {
            $key = ObjectScope::key($part);

            if ($key === null) {
                return null;
            }

            $keys[$key] = $key;
        }

        sort($keys);

        return array_values($keys);
    }

    /**
     * The page that opened this one, for its way back, or null.
     */
    public function opener(): ?string
    {
        $opener = $this->validatedInput()['from_page'] ?? null;

        return is_string($opener) ? $opener : null;
    }
}
