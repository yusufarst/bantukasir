<?php

namespace App\Support\Validation;

use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Limits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An entered cost field of a FormRequest (TR-VAL-06, ADR-006 items 2 and 3):
 * a canonical non-negative string with at most six fractional digits, at most
 * COST_MAX (TECHNICAL_RULES section 11.1). A JSON number or a string that is
 * not canonical is refused, never repaired; an UNKNOWN cost is entered as its
 * own field, not through this one. COPY_DECK names no message for a cost field,
 * so the FormRequest passes its messages, translated from the keys that the
 * Task building it takes from COPY_DECK (TASK-003 evidence, the readings).
 */
final class CostRule implements ValidationRule
{
    public function __construct(
        private readonly string $invalidMessage,
        private readonly string $tooLargeMessage,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? CanonicalNumber::split($value) : null;

        if (! is_string($value) || $parts === null || $parts['negative'] || strlen($parts['fraction']) > Cost::SCALE) {
            $fail($this->invalidMessage);
        } elseif (CanonicalNumber::exceeds($value, Limits::COST_MAX)) {
            $fail($this->tooLargeMessage);
        }
    }
}
