<?php

namespace App\Support\Casts;

use App\Support\Numbers\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A `numeric(13,3)` quantity column as Quantity, with no float conversion
 * (ADR-006 item 5): read from the string that PDO returns, written as the
 * canonical string. The argument is the precision: a digit from 0 to 3, or
 * the name of the attribute on the same row that holds the product's
 * precision (SCHEMA section 3.2), for example
 * `QuantityCast::class.':quantity_precision'`. Its database round-trip is
 * proven by TASK-004.
 *
 * @implements CastsAttributes<Quantity, Quantity>
 */
final class QuantityCast implements CastsAttributes
{
    public function __construct(private readonly string $precision) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        return match (true) {
            $value === null => null,
            is_string($value) => Quantity::fromDatabase($value, $this->precisionOf($attributes)),
            default => throw new InvalidArgumentException(sprintf('The quantity column %s returned a %s (ADR-006 item 5).', $key, get_debug_type($value))),
        };
    }

    /**
     * Any other value than Quantity, a float above all, is refused at run
     * time too, whatever path filled the attribute.
     *
     * @param  mixed  $value  the value given to the attribute, refused unless it is the cast's value object
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Quantity => $value->toCanonical(),
            default => throw new InvalidArgumentException(sprintf('The quantity attribute %s accepts a Quantity value only (ADR-006 item 5).', $key)),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function precisionOf(array $attributes): int
    {
        $precision = preg_match('/^[0-3]$/D', $this->precision) === 1 ? $this->precision : ($attributes[$this->precision] ?? null);

        return match (true) {
            is_int($precision) => $precision,
            is_string($precision) && preg_match('/^[0-3]$/D', $precision) === 1 => intval($precision),
            default => throw new InvalidArgumentException(sprintf('No quantity precision in %s (BR-NUM-02).', $this->precision)),
        };
    }
}
