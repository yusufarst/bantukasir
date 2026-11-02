<?php

namespace App\Support\Validation;

use App\Support\Formatting\Format;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Limits;
use App\Support\Numbers\Quantity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * The quantity field of a line or movement in a FormRequest (TR-VAL-06,
 * ADR-006 items 2 and 3): a canonical string above 0, at most QUANTITY_MAX
 * (TECHNICAL_RULES section 11.1), with no more fractional digits, as written,
 * than the precision it is given (BR-NUM-02). A JSON number or a string that
 * is not canonical is refused, never repaired; so is a value that the global
 * trimming changed (TrimmedInput). The Action checks the product's own
 * precision again inside its transaction (TR-VAL-08).
 */
final class QuantityRule implements ValidationRule
{
    public function __construct(private readonly int $precision)
    {
        if ($precision < 0 || $precision > Quantity::MAX_PRECISION) {
            throw new InvalidArgumentException('A quantity precision is 0 to 3 decimals (BR-NUM-02).');
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        match ($this->problem($value, $attribute)) {
            'quantity_positive' => $fail(__('ux.validation.quantity_positive')),
            'quantity_whole' => $fail(__('ux.validation.quantity_whole')),
            'quantity_decimals' => $fail(__('ux.validation.quantity_decimals', ['desimal' => Format::count($this->precision)])),
            'quantity_max' => $fail(__('ux.validation.quantity_max')),
            null => null,
        };
    }

    /**
     * The leaf of the key under `ux.validation` that the value fails with, or null when it
     * is valid, for the error body of ROUTE_CONTRACTS RT-07.
     *
     * @return 'quantity_positive'|'quantity_whole'|'quantity_decimals'|'quantity_max'|null
     */
    public function problem(mixed $value, string $attribute): ?string
    {
        $parts = is_string($value) ? CanonicalNumber::split($value) : null;

        if (! is_string($value) || $parts === null || $parts['negative'] || trim($parts['integer'].$parts['fraction'], '0') === '' || TrimmedInput::wasTrimmed($attribute)) {
            return 'quantity_positive';
        }

        if (strlen($parts['fraction']) > $this->precision) {
            return $this->precision === 0 ? 'quantity_whole' : 'quantity_decimals';
        }

        return CanonicalNumber::exceeds($value, Limits::QUANTITY_MAX) ? 'quantity_max' : null;
    }
}
