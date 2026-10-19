<?php

namespace App\Support\Numbers;

use Brick\Math\BigDecimal;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact quantity in a product's base unit, on BigDecimal, with the
 * product's quantity precision of 0 to 3 decimals (ADR-006 item 1,
 * BR-NUM-02). It is signed, because a stock movement is; a line, a movement
 * and a balance are checked with assertLine(), assertMovement() and
 * assertStock() (TECHNICAL_RULES section 11.1). It travels as its canonical
 * decimal string in the shortest form, for example "1.5".
 */
final readonly class Quantity implements JsonSerializable, Stringable
{
    public const int MAX_PRECISION = 3;

    private function __construct(private BigDecimal $value, private int $precision) {}

    /**
     * A canonical decimal string with no more fractional digits, as written,
     * than the precision allows: "1.50" is accepted at precision 2 or 3, and
     * "1.0" is refused at precision 0. A value that is not a string is
     * refused at run time (TASK-003 finding A-11).
     *
     * @param  string  $canonical  any other type is refused at run time
     *
     * @throws NonCanonicalNumber
     */
    public static function of(mixed $canonical, int $precision): self
    {
        $precision = self::checkPrecision($precision);

        return new self(CanonicalNumber::parse($canonical, $precision, true, 'quantity')->toScale($precision), $precision);
    }

    /**
     * A value read from a `numeric(13,3)` column, which PostgreSQL returns
     * with three decimals ("1.000" at precision 0). Any other type than a
     * string is refused at run time (TASK-003 finding A-11).
     *
     * @param  string  $value  any other type is refused at run time
     *
     * @throws NonCanonicalNumber
     */
    public static function fromDatabase(mixed $value, int $precision): self
    {
        return self::fromBigDecimal(CanonicalNumber::parse($value, self::MAX_PRECISION, true, 'quantity'), $precision);
    }

    /**
     * An exact value whose significant decimals fit the precision; a value
     * with more is refused, never rounded.
     *
     * @throws NonCanonicalNumber
     */
    public static function fromBigDecimal(BigDecimal $value, int $precision): self
    {
        $precision = self::checkPrecision($precision);
        $stripped = $value->strippedOfTrailingZeros();

        if ($stripped->getScale() > $precision) {
            throw NonCanonicalNumber::excessScale('quantity');
        }

        return new self($stripped->toScale($precision), $precision);
    }

    public function precision(): int
    {
        return $this->precision;
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    public function plus(self $other): self
    {
        return new self($this->value->plus($other->value), $this->samePrecision($other));
    }

    public function minus(self $other): self
    {
        return new self($this->value->minus($other->value), $this->samePrecision($other));
    }

    public function negated(): self
    {
        return new self($this->value->negated(), $this->precision);
    }

    public function compareTo(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    /**
     * The quantity of one line: above 0, at most QUANTITY_MAX.
     *
     * @throws NumberOutOfRange
     */
    public function assertLine(): self
    {
        if (! $this->value->isPositive() || $this->value->isGreaterThan(Limits::QUANTITY_MAX)) {
            throw NumberOutOfRange::for('line quantity');
        }

        return $this;
    }

    /**
     * The signed quantity of one stock movement: not 0, at most QUANTITY_MAX
     * in magnitude (SCHEMA section 3.2).
     *
     * @throws NumberOutOfRange
     */
    public function assertMovement(): self
    {
        if ($this->value->isZero() || $this->value->abs()->isGreaterThan(Limits::QUANTITY_MAX)) {
            throw NumberOutOfRange::for('movement quantity');
        }

        return $this;
    }

    /**
     * A stock balance or a minimum stock: 0 to STOCK_MAX.
     *
     * @throws NumberOutOfRange
     */
    public function assertStock(): self
    {
        if ($this->value->isNegative() || $this->value->isGreaterThan(Limits::STOCK_MAX)) {
            throw NumberOutOfRange::for('stock quantity');
        }

        return $this;
    }

    public function toCanonical(): string
    {
        return CanonicalNumber::format($this->value);
    }

    public function jsonSerialize(): string
    {
        return $this->toCanonical();
    }

    public function __toString(): string
    {
        return $this->toCanonical();
    }

    /**
     * @return int<0, 3>
     */
    private static function checkPrecision(int $precision): int
    {
        if ($precision < 0 || $precision > self::MAX_PRECISION) {
            throw new InvalidArgumentException('A quantity precision is 0 to 3 decimals (BR-NUM-02).');
        }

        return $precision;
    }

    /**
     * Quantities are added only within one product, so at one precision.
     */
    private function samePrecision(self $other): int
    {
        if ($other->precision !== $this->precision) {
            throw new InvalidArgumentException('Quantities of different precisions are not added together.');
        }

        return $this->precision;
    }
}
