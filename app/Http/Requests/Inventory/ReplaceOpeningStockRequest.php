<?php

namespace App\Http\Requests\Inventory;

use App\Actions\Inventory\ReplaceOpeningStock;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Formatting\Format;
use App\Support\Numbers\Quantity;
use App\Support\Validation\CostRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * The FormRequest of RC-48 `opening_stock.replace` (INTERACTION_CONTRACTS RC-48; TECHNICAL_RULES sections
 * 5 and 11.3; COPY_DECK section 16.1; BR-INV-48; D-087): `replacesLineId`, the opening line whose OPENING
 * was reversed; `quantity`, above zero; `countReference`, 1 to 100; `cost`, as RC-07 takes it; and
 * `reason`, 1 to 1,000 characters — one product per command, since the replacement is explicit.
 * `expected.onboardingVersion` and `expected.productVersion` are the versions reviewed. A quantity is
 * judged here at the largest precision, and against its product's only under its lock (TR-VAL-08). An
 * identifier and a version are JSON whole numbers (TR-VAL-06).
 */
final class ReplaceOpeningStockRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return ReplaceOpeningStock::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        $invalid = __('ux.validation.money');
        $tooLarge = __('ux.validation.money_max');

        return [
            'replacesLineId' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', new QuantityRule(Quantity::MAX_PRECISION)],
            'countReference' => ['required', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'cost' => ['required', 'array'],
            'cost.status' => ['sometimes', 'string', 'in:KNOWN,UNKNOWN'],
            'cost.amount' => ['nullable', new CostRule(is_string($invalid) ? $invalid : '', is_string($tooLarge) ? $tooLarge : '')],
            'cost.zeroEvidence' => ['nullable', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'onboardingVersion' => ['required', 'integer', 'min:1'],
            'productVersion' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            $line = $this->input('payload.replacesLineId');

            if (($line !== null && ! is_int($line)) || ! is_int($this->input('expected.onboardingVersion')) || ! is_int($this->input('expected.productVersion'))) {
                $validator->errors()->add('', '');
            }

            $cost = $this->input('payload.cost');

            if (is_array($cost)) {
                CostShape::check($validator, 'payload.cost', $cost);
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $quantity = (new QuantityRule(Quantity::MAX_PRECISION))->problem($this->input('payload.quantity'), 'payload.quantity');
        $cost = $this->input('payload.cost');
        $keys = [
            'payload.replacesLineId.integer' => ['ux.validation.choose_one'],
            'payload.replacesLineId.min' => ['ux.validation.choose_one'],
            'payload.countReference.required' => ['ux.validation.required'],
            'payload.countReference.max' => ['ux.validation.text_max', ['maks' => 100]],
            'payload.reason.required' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
            ...CostShape::keys('payload.cost'),
            ...CostShape::amountKey(is_array($cost) ? ($cost['amount'] ?? null) : null, 'payload.cost'),
        ];

        if ($quantity !== null) {
            $keys['payload.quantity.quantity_rule'] = ['ux.validation.'.$quantity, $quantity === 'quantity_decimals' ? ['desimal' => Format::count(Quantity::MAX_PRECISION)] : []];
        }

        return $keys;
    }
}
