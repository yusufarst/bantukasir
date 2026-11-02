<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ConfirmReceiving;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Quantity;
use App\Support\Validation\CostRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as FrameworkValidator;

/**
 * The FormRequest of RC-07 `receiving.confirm` (INTERACTION_CONTRACTS RC-07; TECHNICAL_RULES
 * sections 5, 11.2 and 11.3; COPY_DECK section 16.1): `reference`, up to 100 characters;
 * `documentDate`, a date of the calendar, which moves no fact; `lines`, 1 to 500, each with
 * its `productId`, its `quantity` and its `cost`, whose `status` is KNOWN with an `amount`, or
 * UNKNOWN with nothing, because UNKNOWN is never zero; an amount of zero takes its
 * `zeroEvidence`, and no other cost does (BR-COST-03, BR-COST-04). `expected.products` names
 * the version reviewed of each product of the lines, once. A quantity is judged here at the
 * largest precision, and against its product's only under its lock (TR-VAL-08). An identifier
 * and a version are JSON whole numbers (TR-VAL-06).
 *
 * COPY_DECK has no message of its own for a cost: an amount reads the texts of an amount of
 * rupiah, as TASK-003 read them (`CanonicalNumberInputTest`).
 */
final class ConfirmReceivingRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return ConfirmReceiving::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        $invalid = __('ux.validation.money');
        $tooLarge = __('ux.validation.money_max');

        return [
            'reference' => ['nullable', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'documentDate' => ['nullable', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date'],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.ConfirmReceiving::MAX_LINES],
            'lines.*' => ['required', 'array'],
            'lines.*.productId' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', new QuantityRule(Quantity::MAX_PRECISION)],
            'lines.*.cost' => ['required', 'array'],
            'lines.*.cost.status' => ['required', 'string', 'in:KNOWN,UNKNOWN'],
            'lines.*.cost.amount' => ['nullable', new CostRule(is_string($invalid) ? $invalid : '', is_string($tooLarge) ? $tooLarge : '')],
            'lines.*.cost.zeroEvidence' => ['nullable', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
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

            foreach ($lines as $index => $line) {
                $values = is_array($line) ? $line : [];
                $product = $values['productId'] ?? null;

                if (is_int($product)) {
                    $products[$product] = true;
                } elseif ($product !== null) {
                    $validator->errors()->add('', '');
                }

                $cost = $values['cost'] ?? null;

                if (is_array($cost)) {
                    self::checkCost($validator, 'payload.lines.'.(string) $index.'.cost', $cost);
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
            'payload.reference.max' => ['ux.validation.text_max', ['maks' => 100]],
            'payload.lines.required' => ['ux.validation.lines_min'],
            'payload.lines.min' => ['ux.validation.lines_min'],
            'payload.lines.max' => ['ux.validation.lines_max', ['maks' => ConfirmReceiving::MAX_LINES]],
            'payload.lines.*.productId.integer' => ['ux.validation.choose_one'],
            'payload.lines.*.productId.min' => ['ux.validation.choose_one'],
            'payload.lines.*.cost.status.string' => ['ux.validation.choose_one'],
            'payload.lines.*.cost.status.in' => ['ux.validation.choose_one'],
            'payload.lines.*.cost.zeroEvidence.required' => ['ux.validation.zero_evidence'],
            'payload.lines.*.cost.zeroEvidence.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
        $lines = $this->input('payload.lines');
        $rule = new QuantityRule(Quantity::MAX_PRECISION);

        foreach (is_array($lines) ? $lines : [] as $index => $line) {
            $values = is_array($line) ? $line : [];
            $path = 'payload.lines.'.(string) $index;
            $quantity = $rule->problem($values['quantity'] ?? null, $path.'.quantity');
            $given = $values['cost'] ?? null;
            $amount = CostRule::problem(is_array($given) ? ($given['amount'] ?? null) : null, $path.'.cost.amount');

            if ($quantity !== null) {
                $keys[$path.'.quantity.quantity_rule'] = ['ux.validation.'.$quantity, $quantity === 'quantity_decimals' ? ['desimal' => Format::count(Quantity::MAX_PRECISION)] : []];
            }

            if ($amount !== null) {
                $keys[$path.'.cost.amount.cost_rule'] = [$amount === 'too_large' ? 'ux.validation.money_max' : 'ux.validation.money'];
            }
        }

        return $keys;
    }

    /**
     * What a cost holds besides its status: KNOWN takes an amount and UNKNOWN nothing; an
     * amount of zero takes its evidence or reason, and no other takes one.
     *
     * @param  array<array-key, mixed>  $cost
     */
    private static function checkCost(Validator $validator, string $path, array $cost): void
    {
        $status = $cost['status'] ?? null;
        $amount = $cost['amount'] ?? null;
        $evidence = $cost['zeroEvidence'] ?? null;

        if ($status === 'UNKNOWN') {
            if ($amount !== null) {
                self::fail($validator, $path.'.amount', 'prohibited');
            }

            if ($evidence !== null) {
                self::fail($validator, $path.'.zeroEvidence', 'prohibited');
            }

            return;
        }

        if ($status !== 'KNOWN') {
            return;
        }

        if ($amount === null) {
            self::fail($validator, $path.'.amount', 'required');

            return;
        }

        $parts = is_string($amount) ? CanonicalNumber::split($amount) : null;

        if ($parts === null) {
            return;
        }

        $zero = $parts['integer'] === '0' && trim($parts['fraction'], '0') === '';

        if ($zero && $evidence === null) {
            self::fail($validator, $path.'.zeroEvidence', 'required');
        } elseif (! $zero && $evidence !== null) {
            self::fail($validator, $path.'.zeroEvidence', 'prohibited');
        }
    }

    /**
     * A failed rule at this path, read by the keys of fieldKeys().
     */
    private static function fail(Validator $validator, string $path, string $rule): void
    {
        if ($validator instanceof FrameworkValidator) {
            $validator->addFailure($path, $rule);

            return;
        }

        $validator->errors()->add('', '');
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
