<?php

namespace App\Support\Casts;

use App\Support\Numbers\Cost;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cost columns as Cost, with no float conversion (ADR-006 item 5). Without an
 * argument, the attribute is a `numeric` amount column that holds a KNOWN
 * cost. With the arguments `<amount column>,<status column>`, the attribute is
 * the pair of SCHEMA section 3.2 — `cost_status` KNOWN or UNKNOWN and its
 * nullable amount — and an UNKNOWN cost is written as its status and a null
 * amount, never as zero (BR-COST-03), for example
 * `'cost' => CostCast::class.':cost_amount,cost_status'`. Its database
 * round-trip is proven by TASK-004.
 *
 * @implements CastsAttributes<Cost, Cost>
 */
final class CostCast implements CastsAttributes
{
    public function __construct(
        private readonly ?string $amountColumn = null,
        private readonly ?string $statusColumn = null,
    ) {
        if (($amountColumn === null) !== ($statusColumn === null)) {
            throw new InvalidArgumentException('A cost cast names both its amount and its status column, or neither.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Cost
    {
        if ($this->amountColumn === null || $this->statusColumn === null) {
            return match (true) {
                $value === null => null,
                is_string($value) => Cost::fromDatabase($value),
                default => throw new InvalidArgumentException(sprintf('The cost column %s returned a %s (ADR-006 item 5).', $key, get_debug_type($value))),
            };
        }

        $amount = $attributes[$this->amountColumn] ?? null;
        $status = $attributes[$this->statusColumn] ?? null;

        if ($amount === null && $status === null) {
            return null;
        }

        if (($amount !== null && ! is_string($amount)) || ! is_string($status)) {
            throw new InvalidArgumentException(sprintf('The cost columns of %s returned a %s and a %s (ADR-006 item 5).', $key, get_debug_type($amount), get_debug_type($status)));
        }

        return Cost::fromDatabase($amount, $status);
    }

    /**
     * Any other value than Cost, a float above all, is refused at run time
     * too, whatever path filled the attribute.
     *
     * @param  mixed  $value  the value given to the attribute, refused unless it is the cast's value object
     * @param  array<string, mixed>  $attributes
     * @return string|array<string, string|null>|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|array|null
    {
        if ($value !== null && ! $value instanceof Cost) {
            throw new InvalidArgumentException(sprintf('The cost attribute %s accepts a Cost value only (ADR-006 item 5).', $key));
        }

        if ($this->amountColumn === null || $this->statusColumn === null) {
            return match (true) {
                $value === null => null,
                $value->isKnown() => $value->toCanonical(),
                default => throw new InvalidArgumentException(sprintf('The cost column %s has no status column, so it cannot hold UNKNOWN (BR-COST-03).', $key)),
            };
        }

        return match (true) {
            $value === null => [$this->amountColumn => null, $this->statusColumn => null],
            $value->isKnown() => [$this->amountColumn => $value->toCanonical(), $this->statusColumn => 'KNOWN'],
            default => [$this->amountColumn => null, $this->statusColumn => 'UNKNOWN'],
        };
    }
}
