<?php

namespace App\Support\Numbers;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Stringable;

/**
 * A cost value: KNOWN, an exact decimal with scale 6 on BigDecimal, never
 * negative; or UNKNOWN, a status and never zero (ADR-006 item 1, BR-NUM-03,
 * BR-COST-03). It travels as its canonical decimal string in the shortest form
 * or as the marker "UNKNOWN", never as a JSON number. UNKNOWN propagates
 * through the arithmetic. Every value computed by multiplication and division
 * is rounded once, half-up, to six decimals at the end of its computation
 * (ADR-008 section 5); sums and differences are exact.
 */
final readonly class Cost implements JsonSerializable, Stringable
{
    public const int SCALE = 6;

    /**
     * The explicit UNKNOWN marker of ADR-006 item 1 in requests and responses.
     */
    public const string UNKNOWN = 'UNKNOWN';

    private function __construct(private ?BigDecimal $amount) {}

    /**
     * A canonical non-negative decimal string with at most six fractional
     * digits, or the marker "UNKNOWN".
     *
     * @throws NonCanonicalNumber
     */
    public static function of(string $canonical): self
    {
        if ($canonical === self::UNKNOWN) {
            return self::unknown();
        }

        return new self(CanonicalNumber::parse($canonical, self::SCALE, false, 'cost')->toScale(self::SCALE));
    }

    /**
     * An exact non-negative value whose significant decimals fit scale 6; a
     * value with more is refused, never rounded.
     *
     * @throws NonCanonicalNumber
     * @throws NumberOutOfRange
     */
    public static function known(BigDecimal $amount): self
    {
        $stripped = $amount->strippedOfTrailingZeros();

        if ($stripped->getScale() > self::SCALE) {
            throw NonCanonicalNumber::excessScale('cost');
        }

        if ($stripped->isNegative()) {
            throw NumberOutOfRange::for('cost');
        }

        return new self($stripped->toScale(self::SCALE));
    }

    public static function unknown(): self
    {
        return new self(null);
    }

    /**
     * A value read from the columns of SCHEMA section 3.2: an amount column
     * alone, which holds a KNOWN value, or a status column (KNOWN or UNKNOWN)
     * with its nullable amount column.
     *
     * @throws NonCanonicalNumber
     */
    public static function fromDatabase(?string $amount, ?string $status = null): self
    {
        if ($status === 'UNKNOWN' && $amount === null) {
            return self::unknown();
        }

        if (($status === null || $status === 'KNOWN') && $amount !== null) {
            return self::known(CanonicalNumber::parse($amount, self::SCALE, false, 'cost'));
        }

        throw new InvalidArgumentException('A cost status and amount disagree (SCHEMA section 3.2).');
    }

    /**
     * The exact dividend divided once, rounded half-up to six decimals
     * (ADR-008 section 5), for example a unit cost or a moving weighted
     * average from exact sums.
     *
     * @throws NumberOutOfRange
     */
    public static function quotient(BigNumber $dividend, BigNumber $divisor): self
    {
        return self::known($dividend->toBigDecimal()->dividedBy($divisor, self::SCALE, RoundingMode::HalfUp));
    }

    /**
     * round6(value × multiplier / divisor): the exact product, then one
     * division (ADR-008 section 5) — the issue allocation round6(V × q / Q) and
     * the cumulative return rule round6(A × R(k) / q). UNKNOWN stays UNKNOWN.
     *
     * @throws NumberOutOfRange
     */
    public static function mulDiv(self $value, BigDecimal|Quantity $multiplier, BigDecimal|Quantity $divisor): self
    {
        if ($value->amount === null) {
            return self::unknown();
        }

        $multiplier = $multiplier instanceof Quantity ? $multiplier->toBigDecimal() : $multiplier;
        $divisor = $divisor instanceof Quantity ? $divisor->toBigDecimal() : $divisor;

        return self::quotient($value->amount->multipliedBy($multiplier), $divisor);
    }

    public function isKnown(): bool
    {
        return $this->amount !== null;
    }

    /**
     * The exact amount of a KNOWN cost; asking an UNKNOWN cost for its amount
     * is a defect, never a zero.
     */
    public function amount(): BigDecimal
    {
        return $this->amount ?? throw new LogicException('An UNKNOWN cost has no amount (BR-COST-03).');
    }

    public function plus(self $other): self
    {
        if ($this->amount === null || $other->amount === null) {
            return self::unknown();
        }

        return new self($this->amount->plus($other->amount));
    }

    /**
     * @throws NumberOutOfRange when the difference would be negative
     */
    public function minus(self $other): self
    {
        if ($this->amount === null || $other->amount === null) {
            return self::unknown();
        }

        return self::known($this->amount->minus($other->amount));
    }

    public function compareTo(self $other): int
    {
        return $this->amount()->compareTo($other->amount());
    }

    /**
     * The acquisition cost of one evidence, a unit cost or an average cost:
     * 0 to COST_MAX, or UNKNOWN.
     *
     * @throws NumberOutOfRange
     */
    public function assertUnit(): self
    {
        return $this->assertAtMost(Limits::COST_MAX, 'unit cost');
    }

    /**
     * An issue or return allocation or an inventory value: 0 to VALUE_MAX,
     * or UNKNOWN.
     *
     * @throws NumberOutOfRange
     */
    public function assertValue(): self
    {
        return $this->assertAtMost(Limits::VALUE_MAX, 'cost value');
    }

    public function toCanonical(): string
    {
        return $this->amount === null ? self::UNKNOWN : CanonicalNumber::format($this->amount);
    }

    public function jsonSerialize(): string
    {
        return $this->toCanonical();
    }

    public function __toString(): string
    {
        return $this->toCanonical();
    }

    private function assertAtMost(string $maximum, string $kind): self
    {
        if ($this->amount !== null && $this->amount->isGreaterThan($maximum)) {
            throw NumberOutOfRange::for($kind);
        }

        return $this;
    }
}
