<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Stringable;

/**
 * A business date: an Asia/Jakarta calendar date computed by the server from
 * a UTC instant (BR-NUM-05, ARC-50). It travels as `YYYY-MM-DD`. Its day
 * starts at 00:00 in Asia/Jakarta, which startUtc() gives as a UTC instant.
 */
final readonly class BusinessDate implements JsonSerializable, Stringable
{
    public const string TIME_ZONE = 'Asia/Jakarta';

    private function __construct(private CarbonImmutable $midnight) {}

    /**
     * The Asia/Jakarta date of an instant.
     */
    public static function ofInstant(DateTimeInterface $instant): self
    {
        return new self(CarbonImmutable::instance($instant)->setTimezone(self::TIME_ZONE)->startOfDay());
    }

    /**
     * A date written `YYYY-MM-DD`; any other form, or a date that does not
     * exist, is refused.
     */
    public static function fromString(string $date): self
    {
        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts) !== 1
            || ! checkdate(intval($parts[2]), intval($parts[3]), intval($parts[1]))) {
            throw new InvalidArgumentException('A business date is written YYYY-MM-DD and exists in the calendar.');
        }

        return new self(new CarbonImmutable($date.' 00:00:00', self::TIME_ZONE));
    }

    /**
     * The instant at which this date starts, 00:00 in Asia/Jakarta, in UTC.
     */
    public function startUtc(): CarbonImmutable
    {
        return $this->midnight->utc();
    }

    public function addDays(int $days): self
    {
        return new self($this->midnight->addDays($days)->startOfDay());
    }

    public function firstOfMonth(): self
    {
        return new self($this->midnight->startOfMonth());
    }

    public function firstOfPreviousMonth(): self
    {
        return new self($this->midnight->startOfMonth()->subMonthNoOverflow());
    }

    /**
     * The number of days from this date to $other: 0 for the same date,
     * negative when $other is earlier.
     */
    public function daysUntil(self $other): int
    {
        $interval = $this->midnight->diff($other->midnight);

        if ($interval->days === false) {
            throw new LogicException('The days between two dates are always known.');
        }

        return $interval->invert === 1 ? -$interval->days : $interval->days;
    }

    /**
     * -1, 0 or 1: the dates compared as their `YYYY-MM-DD` strings, with
     * strcmp(), never PHP's numeric comparison of strings (TASK-003 finding
     * A-02).
     */
    public function compareTo(self $other): int
    {
        return strcmp($this->toString(), $other->toString()) <=> 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isBefore(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function year(): int
    {
        return intval($this->midnight->format('Y'));
    }

    /**
     * The month, 1 for January to 12 for December.
     */
    public function month(): int
    {
        return intval($this->midnight->format('n'));
    }

    public function day(): int
    {
        return intval($this->midnight->format('j'));
    }

    /**
     * The ISO day of the week, 1 for Monday to 7 for Sunday.
     */
    public function dayOfWeek(): int
    {
        return intval($this->midnight->format('N'));
    }

    public function toString(): string
    {
        return $this->midnight->format('Y-m-d');
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
