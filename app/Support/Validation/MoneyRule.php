<?php

namespace App\Support\Validation;

use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Limits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A money field of a FormRequest (TR-VAL-06, ADR-006 items 2 and 3): a
 * canonical string of whole rupiah from 0 to MONEY_MAX (TECHNICAL_RULES
 * section 11.1). A JSON number, a decimal, a separator, an exponent or any
 * other form that is not canonical is refused, never repaired; so is a value
 * that the global trimming changed (TrimmedInput). Whether the field may be
 * blank is the FormRequest's own `required` rule.
 */
final class MoneyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        match (self::problem($value, $attribute)) {
            'money' => $fail(__('ux.validation.money')),
            'money_min' => $fail(__('ux.validation.money_min')),
            'money_max' => $fail(__('ux.validation.money_max')),
            null => null,
        };
    }

    /**
     * The leaf of the key under `ux.validation` that the value fails with, or
     * null when it is valid, for the error body of ROUTE_CONTRACTS RT-07.
     *
     * @return 'money'|'money_min'|'money_max'|null
     */
    public static function problem(mixed $value, string $attribute): ?string
    {
        $parts = is_string($value) ? CanonicalNumber::split($value) : null;

        if (! is_string($value) || $parts === null || $parts['fraction'] !== '' || TrimmedInput::wasTrimmed($attribute)) {
            return 'money';
        }

        if ($parts['negative']) {
            return 'money_min';
        }

        return CanonicalNumber::exceeds($value, Limits::MONEY_MAX) ? 'money_max' : null;
    }
}
