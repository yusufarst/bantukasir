<?php

namespace App\Http\Requests\Inventory;

use App\Support\Numbers\CanonicalNumber;
use App\Support\Validation\CostRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Validator as FrameworkValidator;

/**
 * The shape of a `cost` of an opening row or a replacement (INTERACTION_CONTRACTS RC-07, `cost`, which
 * RC-11 and RC-48 take as it is; BR-COST-03, BR-COST-04), checked after its rules: KNOWN takes an amount
 * and UNKNOWN nothing, because UNKNOWN is never zero; an amount of zero takes its `zeroEvidence`, and no
 * other cost does. Each failure is a rule at its path, read by the keys of the FormRequest. The Owner
 * alone records opening stock (CAP-15), so an explicit zero is always his.
 */
final class CostShape
{
    /**
     * The message keys of the cost's fields below the given path (COPY_DECK section 16.1); a cost or a
     * part of it that no page sends — a cost on a zero row, UNKNOWN with an amount — has none.
     *
     * @return array<string, array{0: string, 1?: array<string, string|int>}>
     */
    public static function keys(string $path): array
    {
        return [
            $path.'.required' => ['ux.validation.required'],
            $path.'.status.required' => ['ux.validation.choose_one'],
            $path.'.status.string' => ['ux.validation.choose_one'],
            $path.'.status.in' => ['ux.validation.choose_one'],
            $path.'.amount.required' => ['ux.validation.required'],
            $path.'.zeroEvidence.required' => ['ux.validation.zero_evidence'],
            $path.'.zeroEvidence.max' => ['ux.validation.text_max', ['maks' => 1000]],
        ];
    }

    /**
     * The key of the amount's own rule, when its value fails it.
     *
     * @return array<string, array{0: string}>
     */
    public static function amountKey(mixed $amount, string $path): array
    {
        $problem = CostRule::problem($amount, $path.'.amount');

        return $problem === null ? [] : [$path.'.amount.cost_rule' => [$problem === 'too_large' ? 'ux.validation.money_max' : 'ux.validation.money']];
    }

    /**
     * What a cost holds besides its status: KNOWN takes an amount and UNKNOWN nothing; an amount of zero
     * takes its evidence or reason, and no other takes one.
     *
     * @param  array<array-key, mixed>  $cost
     */
    public static function check(Validator $validator, string $path, array $cost): void
    {
        $status = $cost['status'] ?? null;
        $amount = $cost['amount'] ?? null;
        $evidence = $cost['zeroEvidence'] ?? null;

        if ($status === null) {
            self::fail($validator, $path.'.status', 'required');

            return;
        }

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
     * Whether a quantity's text is zero, "0" or with zero decimals; a text that is not a canonical
     * number is not.
     */
    public static function isZero(mixed $quantity): bool
    {
        $parts = is_string($quantity) ? CanonicalNumber::split($quantity) : null;

        return $parts !== null && ! $parts['negative'] && trim($parts['integer'].$parts['fraction'], '0') === '';
    }

    /**
     * A failed rule at this path, read by the keys of the FormRequest.
     */
    public static function fail(Validator $validator, string $path, string $rule): void
    {
        if ($validator instanceof FrameworkValidator) {
            $validator->addFailure($path, $rule);

            return;
        }

        $validator->errors()->add('', '');
    }
}
