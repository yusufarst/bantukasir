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
 * The minimum stock of a product in a FormRequest (BR-CAT-11; SCHEMA section
 * 3.2; TR-VAL-06, ADR-006 items 2 and 3): a canonical string from 0 to STOCK_MAX
 * with no more fractional digits, as written, than the precision it is given. A
 * JSON number or a string that is not canonical is refused, never repaired; so
 * is a value that the global trimming changed (TrimmedInput). COPY_DECK section
 * 16.1 gives quantity_max, which names the largest quantity of a line, for a
 * value above the range too (TASK-015 evidence, the findings).
 */
final class MinimumStockRule implements ValidationRule
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
            'min_stock' => $fail(__('ux.validation.min_stock')),
            'quantity_whole' => $fail(__('ux.validation.quantity_whole')),
            'quantity_decimals' => $fail(__('ux.validation.quantity_decimals', ['desimal' => Format::count($this->precision)])),
            'quantity_max' => $fail(__('ux.validation.quantity_max')),
            null => null,
        };
    }

    /**
     * The leaf of the key under `ux.validation` that the value fails with, or
     * null when it is valid, for the error body of ROUTE_CONTRACTS RT-07.
     *
     * @return 'min_stock'|'quantity_whole'|'quantity_decimals'|'quantity_max'|null
     */
    public function problem(mixed $value, string $attribute): ?string
    {
        $parts = is_string($value) ? CanonicalNumber::split($value) : null;

        if (! is_string($value) || $parts === null || $parts['negative'] || TrimmedInput::wasTrimmed($attribute)) {
            return 'min_stock';
        }

        if (strlen($parts['fraction']) > $this->precision) {
            return $this->precision === 0 ? 'quantity_whole' : 'quantity_decimals';
        }

        return CanonicalNumber::exceeds($value, Limits::STOCK_MAX) ? 'quantity_max' : null;
    }
}
