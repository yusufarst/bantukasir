<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A report period of BR-COST-37 as a half-open UTC range [start, end) of
 * Asia/Jakarta dates (BR-NUM-06), computed by pure functions of the instant
 * "now" that the caller takes from the Clock, which is the report's snapshot
 * time:
 *
 * - today: today, [today 00:00, tomorrow 00:00);
 * - 7d: today and the six dates before;
 * - this_month: from the first of this month until now, [first 00:00, now);
 * - last_month: the whole previous calendar month;
 * - custom: the chosen dates, inclusive, ending at the start of the day after
 *   the last one, and refused when TECHNICAL_RULES section 11.4 refuses it.
 */
final readonly class Period
{
    public const int MAX_DAYS = 366;

    private function __construct(
        public PeriodKind $kind,
        public BusinessDate $firstDate,
        public BusinessDate $lastDate,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * @throws InvalidPeriod
     */
    public static function of(PeriodKind $kind, DateTimeInterface $now, ?BusinessDate $from = null, ?BusinessDate $to = null): self
    {
        if ($kind === PeriodKind::Custom && $from !== null && $to !== null) {
            return self::custom($from, $to, $now);
        }

        if ($kind === PeriodKind::Custom || $from !== null || $to !== null) {
            throw new InvalidArgumentException('Only a custom period has dates, and it has both.');
        }

        return match ($kind) {
            PeriodKind::Today => self::today($now),
            PeriodKind::SevenDays => self::sevenDays($now),
            PeriodKind::ThisMonth => self::thisMonth($now),
            PeriodKind::LastMonth => self::lastMonth($now),
        };
    }

    public static function today(DateTimeInterface $now): self
    {
        $today = BusinessDate::ofInstant($now);

        return self::dates(PeriodKind::Today, $today, $today);
    }

    public static function sevenDays(DateTimeInterface $now): self
    {
        $today = BusinessDate::ofInstant($now);

        return self::dates(PeriodKind::SevenDays, $today->addDays(-6), $today);
    }

    public static function thisMonth(DateTimeInterface $now): self
    {
        $today = BusinessDate::ofInstant($now);
        $first = $today->firstOfMonth();

        return new self(PeriodKind::ThisMonth, $first, $today, $first->startUtc(), CarbonImmutable::instance($now)->utc());
    }

    public static function lastMonth(DateTimeInterface $now): self
    {
        $firstOfThisMonth = BusinessDate::ofInstant($now)->firstOfMonth();

        return self::dates(PeriodKind::LastMonth, $firstOfThisMonth->firstOfPreviousMonth(), $firstOfThisMonth->addDays(-1));
    }

    /**
     * @throws InvalidPeriod
     */
    public static function custom(BusinessDate $from, BusinessDate $to, DateTimeInterface $now): self
    {
        if ($from->isAfter($to)) {
            throw InvalidPeriod::startAfterEnd();
        }

        if ($to->isAfter(BusinessDate::ofInstant($now))) {
            throw InvalidPeriod::endAfterToday();
        }

        if ($from->daysUntil($to) + 1 > self::MAX_DAYS) {
            throw InvalidPeriod::tooLong();
        }

        return self::dates(PeriodKind::Custom, $from, $to);
    }

    /**
     * Whether an instant falls in the period: start ≤ instant < end.
     */
    public function contains(DateTimeInterface $instant): bool
    {
        $instant = CarbonImmutable::instance($instant);

        return $instant->greaterThanOrEqualTo($this->start) && $instant->lessThan($this->end);
    }

    private static function dates(PeriodKind $kind, BusinessDate $first, BusinessDate $last): self
    {
        return new self($kind, $first, $last, $first->startUtc(), $last->addDays(1)->startUtc());
    }
}
