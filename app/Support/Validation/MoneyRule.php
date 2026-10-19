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
        $parts = is_string($value) ? CanonicalNumber::split($value) : null;

        if (! is_string($value) || $parts === null || $parts['fraction'] !== '' || TrimmedInput::wasTrimmed($attribute)) {
            $fail(__('ux.validation.money'));
        } elseif ($parts['negative']) {
            $fail(__('ux.validation.money_min'));
        } elseif (CanonicalNumber::exceeds($value, Limits::MONEY_MAX)) {
            $fail(__('ux.validation.money_max'));
        }
    }
}
