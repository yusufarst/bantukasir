<?php

namespace App\Http\Requests\Costing;

use App\Actions\Costing\CorrectCost;
use App\Http\Requests\Commands\CommandRequest;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Validation\CostRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as FrameworkValidator;

/**
 * The FormRequest of RC-15 `cost_correction.confirm` (INTERACTION_CONTRACTS RC-15; TECHNICAL_RULES
 * sections 5, 11.1 and 11.3; COPY_DECK section 16.1; BR-COST-02 to BR-COST-04, BR-COST-20):
 * `evidenceId`, the cost source, a JSON whole number; `newCost.amount`, the new total, KNOWN — a
 * correction always records a value, never UNKNOWN (SCHEMA section 5.8) — and `newCost.zeroEvidence`
 * with an amount of zero, and only then (BR-COST-04); `reason`, 1 to 1,000 characters.
 * `expected.correctionNo` is the number of the source's latest correction as reviewed, 0 for none.
 * Whether the source exists is the Action's to answer, NOT_FOUND, under the product's guard.
 */
final class CorrectCostRequest extends CommandRequest
{
    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    public function operation(): string
    {
        return CorrectCost::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        $invalid = __('ux.validation.money');
        $tooLarge = __('ux.validation.money_max');

        return [
            'evidenceId' => ['required', 'integer', 'min:1'],
            'newCost' => ['required', 'array'],
            'newCost.amount' => ['required', new CostRule(is_string($invalid) ? $invalid : '', is_string($tooLarge) ? $tooLarge : '')],
            'newCost.zeroEvidence' => ['nullable', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return [
            'correctionNo' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            // An identifier and a number reviewed are JSON whole numbers, never texts (TR-VAL-06).
            foreach (['payload.evidenceId', 'expected.correctionNo'] as $path) {
                $value = $this->input($path);

                if ($value !== null && ! is_int($value)) {
                    $validator->errors()->add('', '');
                }
            }

            $amount = $this->input('payload.newCost.amount');
            $evidence = $this->input('payload.newCost.zeroEvidence');
            $parts = is_string($amount) ? CanonicalNumber::split($amount) : null;

            if ($parts === null) {
                return;
            }

            $zero = $parts['integer'] === '0' && trim($parts['fraction'], '0') === '';

            // An explicit zero carries its evidence or reason, and no other value does (BR-COST-04).
            if ($zero && $evidence === null) {
                self::fail($validator, 'payload.newCost.zeroEvidence', 'required');
            } elseif (! $zero && $evidence !== null) {
                self::fail($validator, 'payload.newCost.zeroEvidence', 'prohibited');
            }
        }];
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    protected function fieldKeys(): array
    {
        $keys = [
            'payload.evidenceId.integer' => ['ux.validation.choose_one'],
            'payload.evidenceId.min' => ['ux.validation.choose_one'],
            'payload.newCost.zeroEvidence.required' => ['ux.validation.zero_evidence'],
            'payload.newCost.zeroEvidence.max' => ['ux.validation.text_max', ['maks' => 1000]],
            'payload.reason.required' => ['ux.validation.required'],
            'payload.reason.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
        $amount = CostRule::problem($this->input('payload.newCost.amount'), 'payload.newCost.amount');

        if ($amount !== null && $this->input('payload.newCost.amount') !== null) {
            $keys['payload.newCost.amount.cost_rule'] = [$amount === 'too_large' ? 'ux.validation.money_max' : 'ux.validation.money'];
        }

        return $keys;
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
}
