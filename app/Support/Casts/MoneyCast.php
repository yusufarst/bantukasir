<?php

namespace App\Support\Casts;

use App\Support\Numbers\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A `bigint` money column as Money, with no float conversion (ADR-006 item 5):
 * read from the integer or string that PDO returns, written as the canonical
 * string. Its database round-trip is proven by TASK-004.
 *
 * @implements CastsAttributes<Money, Money>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return match (true) {
            $value === null => null,
            is_int($value), is_string($value) => Money::fromDatabase($value),
            default => throw new InvalidArgumentException(sprintf('The money column %s returned a %s (ADR-006 item 5).', $key, get_debug_type($value))),
        };
    }

    /**
     * Any other value than Money, a float above all, is refused at run time
     * too, whatever path filled the attribute.
     *
     * @param  mixed  $value  the value given to the attribute, refused unless it is the cast's value object
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->toCanonical(),
            default => throw new InvalidArgumentException(sprintf('The money attribute %s accepts a Money value only (ADR-006 item 5).', $key)),
        };
    }
}
