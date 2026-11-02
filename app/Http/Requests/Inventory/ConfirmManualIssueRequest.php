<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ConfirmManualIssue;
use App\Domain\Inventory\ManualIssues;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\Quantity;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-08 `manual_issue.confirm` (INTERACTION_CONTRACTS RC-08; TECHNICAL_RULES
 * sections 5, 11.2 and 11.3; COPY_DECK section 16.1; BR-INV-19): `reasonCode`, one of the five of
 * BR-INV-19, mandatory; `note`, up to 1,000 characters, optional; `lines`, 1 to 200, each with its
 * `productId` and its `quantity`. A line takes no cost: its allocation is computed at commit
 * (ADR-008 section 2). `expected.products` names the version reviewed of each product of the
 * lines, once. A quantity is judged here at the largest precision, and against its product's only
 * under its lock (TR-VAL-08). An identifier and a version are JSON whole numbers (TR-VAL-06).
 */
final class ConfirmManualIssueRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return ConfirmManualIssue::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'reasonCode' => ['required', 'string', 'in:'.implode(',', ManualIssues::REASONS)],
            'note' => ['nullable', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.ConfirmManualIssue::MAX_LINES],
            'lines.*' => ['required', 'array'],
            'lines.*.productId' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', new QuantityRule(Quantity::MAX_PRECISION)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'products' => ['required', 'array', 'list'],
            'products.*' => ['required', 'array'],
            'products.*.productId' => ['required', 'integer', 'min:1'],
            'products.*.version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $lines = $this->input('payload.lines');

            if (! is_array($lines)) {
                return;
            }

            $products = [];

            foreach ($lines as $line) {
                $values = is_array($line) ? $line : [];
                $product = $values['productId'] ?? null;

                if (is_int($product)) {
                    $products[$product] = true;
                } elseif ($product !== null) {
                    $validator->errors()->add('', '');
                }
            }

            if (! self::namesEachOnce($this->input('expected.products'), $products)) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $keys = [
            'payload.reasonCode.string' => ['ux.validation.choose_one'],
            'payload.reasonCode.in' => ['ux.validation.choose_one'],
            'payload.note.max' => ['ux.validation.text_max', ['maks' => 1000]],
            'payload.lines.required' => ['ux.validation.lines_min'],
            'payload.lines.min' => ['ux.validation.lines_min'],
            'payload.lines.max' => ['ux.validation.lines_max', ['maks' => ConfirmManualIssue::MAX_LINES]],
            'payload.lines.*.productId.integer' => ['ux.validation.choose_one'],
            'payload.lines.*.productId.min' => ['ux.validation.choose_one'],
        ];
        $lines = $this->input('payload.lines');
        $rule = new QuantityRule(Quantity::MAX_PRECISION);

        foreach (is_array($lines) ? $lines : [] as $index => $line) {
            $values = is_array($line) ? $line : [];
            $path = 'payload.lines.'.(string) $index;
            $quantity = $rule->problem($values['quantity'] ?? null, $path.'.quantity');

            if ($quantity !== null) {
                $keys[$path.'.quantity.quantity_rule'] = ['ux.validation.'.$quantity, $quantity === 'quantity_decimals' ? ['desimal' => Format::count(Quantity::MAX_PRECISION)] : []];
            }
        }

        return $keys;
    }

    /**
     * Whether `expected.products` names exactly the products of the lines, each once, with a
     * version that is a JSON whole number.
     *
     * @param  array<int, true>  $products
     */
    private static function namesEachOnce(mixed $expected, array $products): bool
    {
        if (! is_array($expected) || count($expected) !== count($products)) {
            return false;
        }

        foreach ($expected as $entry) {
            $values = is_array($entry) ? $entry : [];
            $product = $values['productId'] ?? null;

            if (! is_int($product) || ! is_int($values['version'] ?? null) || ! isset($products[$product])) {
                return false;
            }

            unset($products[$product]);
        }

        return true;
    }
}
