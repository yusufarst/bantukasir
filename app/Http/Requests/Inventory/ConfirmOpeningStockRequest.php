<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ConfirmOpeningStock;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\Quantity;
use App\Support\Validation\CostRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-11 `opening_stock.confirm` (INTERACTION_CONTRACTS RC-11; TECHNICAL_RULES
 * sections 5, 11.2 and 11.3; COPY_DECK section 16.1; BR-INV-25, BR-INV-27): `rows`, 1 to 100, each with
 * its `productId`; its `quantity`, zero or more, "0" for a zero count; its `countReference`, 1 to 100,
 * required for a positive row and optional for a zero row; and, for a positive row alone, its `cost`, as
 * RC-07 takes it. A product is named at most once, else VALIDATION_FAILED at each row that repeats it
 * (INTERACTION_CONTRACTS section 1.6). `expected.onboardingVersion` is the onboarding version reviewed,
 * and `expected.products` names the version reviewed of each product of the rows, once. A quantity is
 * judged here at the largest precision, and against its product's only under its lock (TR-VAL-08). An
 * identifier and a version are JSON whole numbers (TR-VAL-06).
 */
final class ConfirmOpeningStockRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return ConfirmOpeningStock::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        $invalid = __('ux.validation.money');
        $tooLarge = __('ux.validation.money_max');

        return [
            'rows' => ['required', 'array', 'list', 'min:1', 'max:'.ConfirmOpeningStock::MAX_ROWS],
            'rows.*' => ['required', 'array'],
            'rows.*.productId' => ['required', 'integer', 'min:1'],
            'rows.*.quantity' => ['required', new QuantityRule(Quantity::MAX_PRECISION, true)],
            'rows.*.countReference' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'rows.*.cost' => ['nullable', 'array'],
            'rows.*.cost.status' => ['sometimes', 'string', 'in:KNOWN,UNKNOWN'],
            'rows.*.cost.amount' => ['nullable', new CostRule(is_string($invalid) ? $invalid : '', is_string($tooLarge) ? $tooLarge : '')],
            'rows.*.cost.zeroEvidence' => ['nullable', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'onboardingVersion' => ['required', 'integer', 'min:1'],
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
            $rows = $this->input('payload.rows');

            if (! is_int($this->input('expected.onboardingVersion'))) {
                $validator->errors()->add('', '');
            }

            if (! is_array($rows)) {
                return;
            }

            $products = [];

            foreach ($rows as $index => $row) {
                $values = is_array($row) ? $row : [];
                $product = $values['productId'] ?? null;
                $path = 'payload.rows.'.(string) $index;

                if (is_int($product)) {
                    $products[$product] = true;
                } elseif ($product !== null) {
                    $validator->errors()->add('', '');
                }

                $cost = $values['cost'] ?? null;
                $positive = ! CostShape::isZero($values['quantity'] ?? null);

                // A positive row carries its count reference and its cost, a zero row no cost
                // (BR-INV-25, BR-INV-27).
                if ($positive && ($values['countReference'] ?? null) === null) {
                    CostShape::fail($validator, $path.'.countReference', 'required');
                }

                if ($positive && $cost === null) {
                    CostShape::fail($validator, $path.'.cost', 'required');
                } elseif (! $positive && array_key_exists('cost', $values)) {
                    CostShape::fail($validator, $path.'.cost', 'prohibited');
                } elseif (is_array($cost)) {
                    CostShape::check($validator, $path.'.cost', $cost);
                }
            }

            if ($this->repeatedRows() !== [] || ! self::namesEachOnce($this->input('expected.products'), $products)) {
                $validator->errors()->add('', '');
            }
        }];
    }

    /**
     * A product named twice is refused at each row that repeats it (INTERACTION_CONTRACTS section 1.6);
     * every other failure is the base's.
     */
    protected function failedValidation(Validator $validator): never
    {
        $repeated = $this->repeatedRows();

        if ($repeated !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, lines: $repeated);
        }

        parent::failedValidation($validator);
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $keys = [
            'payload.rows.required' => ['ux.validation.lines_min'],
            'payload.rows.min' => ['ux.validation.lines_min'],
            'payload.rows.max' => ['ux.validation.lines_max', ['maks' => ConfirmOpeningStock::MAX_ROWS]],
            'payload.rows.*.productId.integer' => ['ux.validation.choose_one'],
            'payload.rows.*.productId.min' => ['ux.validation.choose_one'],
            'payload.rows.*.countReference.required' => ['ux.validation.required'],
            'payload.rows.*.countReference.max' => ['ux.validation.text_max', ['maks' => 100]],
            ...CostShape::keys('payload.rows.*.cost'),
        ];
        $rows = $this->input('payload.rows');
        $rule = new QuantityRule(Quantity::MAX_PRECISION, true);

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            $values = is_array($row) ? $row : [];
            $path = 'payload.rows.'.(string) $index;
            $quantity = $rule->problem($values['quantity'] ?? null, $path.'.quantity');
            $given = $values['cost'] ?? null;

            if ($quantity !== null) {
                $keys[$path.'.quantity.quantity_rule'] = ['ux.validation.'.$quantity, $quantity === 'quantity_decimals' ? ['desimal' => Format::count(Quantity::MAX_PRECISION)] : []];
            }

            $keys = [...$keys, ...CostShape::amountKey(is_array($given) ? ($given['amount'] ?? null) : null, $path.'.cost')];
        }

        return $keys;
    }

    /**
     * The rows that name a product an earlier row names, each by its index with its product
     * (INTERACTION_CONTRACTS section 1.6; BR-INV-26).
     *
     * @return array<int, array{productId: int}>
     */
    private function repeatedRows(): array
    {
        $rows = $this->input('payload.rows');
        $seen = [];
        $repeated = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
            $product = is_array($row) ? ($row['productId'] ?? null) : null;

            if (! is_int($product)) {
                continue;
            }

            if (isset($seen[$product])) {
                $repeated[$index] = ['productId' => $product];
            }

            $seen[$product] = true;
        }

        return $repeated;
    }

    /**
     * Whether `expected.products` names exactly the products of the rows, each once, with a version
     * that is a JSON whole number.
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
