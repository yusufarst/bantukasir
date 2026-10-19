<?php

namespace App\Support\Numbers;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use JsonSerializable;
use Stringable;

/**
 * Money in whole rupiah on BigInteger (ADR-006 item 1, BR-NUM-01). It travels
 * as its canonical integer string, never as a JSON number (ADR-006 item 2).
 * The value itself is unbounded, so that report aggregates are exact; a value
 * that is stored or entered is checked with assertStored() or assertVariance()
 * (TECHNICAL_RULES section 11.1).
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(private BigInteger $amount) {}

    /**
     * A canonical integer string, signed, for example "75000" or "-2000".
     *
     * @throws NonCanonicalNumber
     */
    public static function of(string $canonical): self
    {
        return new self(CanonicalNumber::parse($canonical, 0, true, 'money amount')->toBigInteger());
    }

    public static function zero(): self
    {
        return new self(BigInteger::zero());
    }

    public static function fromBigInteger(BigInteger $amount): self
    {
        return new self($amount);
    }

    /**
     * A value read from a `bigint` column: PDO returns an integer or a string,
     * never a float.
     *
     * @throws NonCanonicalNumber
     */
    public static function fromDatabase(int|string $value): self
    {
        return is_int($value) ? new self(BigInteger::of($value)) : self::of($value);
    }

    /**
     * A line total: quantity × unit price, rounded half-up once to whole
     * rupiah (ADR-006 item 4, BR-NUM-01), and refused when it is outside the
     * stored range (an overflow is refused, never truncated).
     *
     * @throws NumberOutOfRange
     */
    public static function lineTotal(Quantity $quantity, self $unitPrice): self
    {
        $exact = $quantity->toBigDecimal()->multipliedBy($unitPrice->amount);

        return (new self($exact->toScale(0, RoundingMode::HalfUp)->toBigInteger()))->assertStored();
    }

    /**
     * The exact sum, for example the Sale total of its line totals (BR-NUM-01).
     */
    public static function sum(self ...$amounts): self
    {
        $sum = BigInteger::zero();

        foreach ($amounts as $amount) {
            $sum = $sum->plus($amount->amount);
        }

        return new self($sum);
    }

    public function plus(self $other): self
    {
        return new self($this->amount->plus($other->amount));
    }

    public function minus(self $other): self
    {
        return new self($this->amount->minus($other->amount));
    }

    public function negated(): self
    {
        return new self($this->amount->negated());
    }

    public function compareTo(self $other): int
    {
        return $this->amount->compareTo($other->amount);
    }

    public function isEqualTo(self $other): bool
    {
        return $this->amount->isEqualTo($other->amount);
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function toBigInteger(): BigInteger
    {
        return $this->amount;
    }

    /**
     * A stored or entered amount: 0 to MONEY_MAX.
     *
     * @throws NumberOutOfRange
     */
    public function assertStored(): self
    {
        if ($this->amount->isNegative() || $this->amount->isGreaterThan(Limits::MONEY_MAX)) {
            throw NumberOutOfRange::for('money amount');
        }

        return $this;
    }

    /**
     * A cash variance: −MONEY_MAX to MONEY_MAX (BR-CASH-12).
     *
     * @throws NumberOutOfRange
     */
    public function assertVariance(): self
    {
        if ($this->amount->abs()->isGreaterThan(Limits::MONEY_MAX)) {
            throw NumberOutOfRange::for('cash variance');
        }

        return $this;
    }

    public function toCanonical(): string
    {
        return $this->amount->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toCanonical();
    }

    public function __toString(): string
    {
        return $this->toCanonical();
    }
}
