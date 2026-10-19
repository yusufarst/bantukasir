<?php

namespace Tests\Unit\Support\Time;

use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\InvalidPeriod;
use App\Support\Time\Period;
use App\Support\Time\PeriodKind;
use App\Support\Time\SystemClock;
use App\Support\Time\TestClock;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Business dates and the five periods of BR-COST-37 as half-open UTC ranges
 * (BR-NUM-05, BR-NUM-06, R-10, R-11), against an independent oracle in
 * integer arithmetic — Asia/Jakarta is UTC+7 since 1964, and days come from
 * the civil calendar by Howard Hinnant's algorithm — with the test clock, at
 * random instants from 1970 to 2199 and at every boundary (the class-A
 * adversarial review of TASK-003, area 6). The test clock stands still and
 * accepts only absolute instants (finding A-19).
 */
final class PeriodOracleTest extends TestCase
{
    private const int DAY = 86400;

    private const int OFFSET = 7 * 3600;

    public function test_business_dates_and_the_five_periods_match_the_oracle(): void
    {
        mt_srand(20261020);

        for ($i = 0; $i < 4000; $i++) {
            $seconds = mt_rand(0, 7258118399);
            $micro = mt_rand(0, 3) === 0 ? 999999 : mt_rand(0, 999999);

            if ($i % 5 === 0) {
                // On or next to an Asia/Jakarta midnight.
                $seconds = intdiv($seconds, self::DAY) * self::DAY - self::OFFSET + [-1, 0, 1][$i % 3];
                $micro = [999999, 0, 0][$i % 3];
            }

            $clock = new TestClock(CarbonImmutable::createFromFormat('U.u', sprintf('%d.%06d', $seconds, $micro), 'UTC') ?: throw new LogicException('An instant.'));
            $now = $clock->now();
            $today = intdiv($seconds + self::OFFSET, self::DAY);
            [$y, $m] = self::civil($today);

            $this->assertSame(self::date($today), BusinessDate::ofInstant($now)->toString(), $now->toIso8601ZuluString('microsecond'));

            $this->assertPeriod(Period::of(PeriodKind::Today, $now), $today, $today, self::start($today), self::start($today + 1), $now);
            $this->assertPeriod(Period::of(PeriodKind::SevenDays, $now), $today - 6, $today, self::start($today - 6), self::start($today + 1), $now);

            $first = self::days($y, $m, 1);
            $thisMonth = Period::of(PeriodKind::ThisMonth, $now);
            $this->assertSame([self::date($first), self::date($today)], [$thisMonth->firstDate->toString(), $thisMonth->lastDate->toString()]);
            $this->assertSame(self::start($first), $thisMonth->start->getTimestamp());
            $this->assertTrue($thisMonth->end->eq($now), 'this_month ends at the snapshot');
            $this->assertFalse($thisMonth->contains($now), 'the snapshot itself is outside [start, now)');

            [$py, $pm] = $m === 1 ? [$y - 1, 12] : [$y, $m - 1];
            $previous = self::days($py, $pm, 1);
            $this->assertPeriod(Period::of(PeriodKind::LastMonth, $now), $previous, $first - 1, self::start($previous), self::start($first), null);

            $from = $today + mt_rand(-400, 3);
            $to = $from + mt_rand(-2, 370);
            $expected = match (true) {
                $from > $to => InvalidPeriod::START_AFTER_END,
                $to > $today => InvalidPeriod::END_AFTER_TODAY,
                $to - $from + 1 > 366 => InvalidPeriod::TOO_LONG,
                default => null,
            };

            try {
                $custom = Period::of(PeriodKind::Custom, $now, BusinessDate::fromString(self::date($from)), BusinessDate::fromString(self::date($to)));
                $this->assertNull($expected, sprintf('custom %s to %s at %s was accepted', self::date($from), self::date($to), $now->toIso8601ZuluString()));
                $this->assertPeriod($custom, $from, $to, self::start($from), self::start($to + 1), null);
            } catch (InvalidPeriod $refused) {
                $this->assertSame($expected, $refused->reason, sprintf('custom %s to %s at %s', self::date($from), self::date($to), $now->toIso8601ZuluString()));
            }
        }
    }

    public function test_the_named_boundaries(): void
    {
        $at = fn (string $instant): CarbonImmutable => (new TestClock($instant))->now();

        $this->assertSame('2026-10-18', BusinessDate::ofInstant($at('2026-10-18T16:59:59.999999Z'))->toString());
        $this->assertSame('2026-10-19', BusinessDate::ofInstant($at('2026-10-18T17:00:00Z'))->toString());
        $this->assertSame('2028-02-29', BusinessDate::ofInstant($at('2028-02-28T17:00:00Z'))->toString());
        $this->assertSame('2027-01-01', BusinessDate::ofInstant($at('2026-12-31T17:00:00Z'))->toString());

        $january = Period::of(PeriodKind::LastMonth, $at('2027-01-15T03:00:00Z'));
        $this->assertSame(['2026-12-01', '2026-12-31'], [$january->firstDate->toString(), $january->lastDate->toString()]);

        $march = Period::of(PeriodKind::LastMonth, $at('2028-03-01T00:00:00Z'));
        $this->assertSame(['2028-02-01', '2028-02-29'], [$march->firstDate->toString(), $march->lastDate->toString()]);

        $now = $at('2028-02-29T05:00:00Z');
        $this->assertSame('2027-03-01', Period::of(PeriodKind::Custom, $now, BusinessDate::fromString('2027-03-01'), BusinessDate::fromString('2028-02-29'))->firstDate->toString());
        $this->assertRefusedWith(InvalidPeriod::TOO_LONG, fn () => Period::of(PeriodKind::Custom, $now, BusinessDate::fromString('2027-02-28'), BusinessDate::fromString('2028-02-29')));
        $this->assertRefusedWith(InvalidPeriod::END_AFTER_TODAY, fn () => Period::of(PeriodKind::Custom, $at('2028-02-29T16:59:59.999999Z'), BusinessDate::fromString('2028-03-01'), BusinessDate::fromString('2028-03-01')));
        $this->assertSame('2028-03-01', Period::of(PeriodKind::Custom, $at('2028-02-29T17:00:00Z'), BusinessDate::fromString('2028-03-01'), BusinessDate::fromString('2028-03-01'))->lastDate->toString());
        $this->assertRefusedWith(InvalidPeriod::START_AFTER_END, fn () => Period::of(PeriodKind::Custom, $now, BusinessDate::fromString('2028-02-29'), BusinessDate::fromString('2028-02-28')));

        foreach (['2026-02-29', '2026-13-01', '2026-00-10', '2026-01-00', '2026-1-01', '+2026-01-01', ' 2026-01-01', "2026-01-01\n", '２０２６-01-01', '2026/01/01', '20260101', '0000-01-01', '2026-01-01T00:00:00Z'] as $bad) {
            try {
                BusinessDate::fromString($bad);
                $this->fail(json_encode($bad).' was accepted as a business date');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach ([[PeriodKind::Custom, null], [PeriodKind::Today, '2026-10-01'], [PeriodKind::Custom, '2026-10-01']] as [$kind, $from]) {
            try {
                Period::of($kind, $now, $from === null ? null : BusinessDate::fromString($from));
                $this->fail('a period with the wrong dates was accepted');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_clock_binding_and_the_test_clock(): void
    {
        $this->assertInstanceOf(SystemClock::class, app(Clock::class));
        CarbonImmutable::setTestNow('2026-10-18T16:59:59.999999Z');
        $this->assertSame('2026-10-18T16:59:59.999999Z', app(Clock::class)->now()->toIso8601ZuluString('microsecond'));
        $this->assertSame('UTC', app(Clock::class)->now()->getTimezone()->getName());
        CarbonImmutable::setTestNow();

        $clock = new TestClock('2026-10-18T16:59:59Z');
        $clock->advanceSeconds(1);
        $this->assertSame('2026-10-19', BusinessDate::ofInstant($clock->now())->toString());
        $this->app->instance(Clock::class, $clock);
        $this->assertSame($clock, app(Clock::class));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function instantsThatAreNotAbsolute(): array
    {
        return [
            'now' => ['now'],
            'tomorrow' => ['tomorrow'],
            'today' => ['today'],
            '+1 day' => ['+1 day'],
            'last monday' => ['last monday'],
            'noon' => ['noon'],
            'a date only' => ['2026-10-18'],
            'a date and time without an offset' => ['2026-10-18 16:59:59'],
            'a T without an offset' => ['2026-10-18T16:59:59'],
            'a time only' => ['16:59:59Z'],
            'a time zone name instead of an offset' => ['2026-10-18 16:59:59 Asia/Jakarta'],
            'a Unix timestamp' => ['@1729270799'],
            'an empty string' => [''],
            'a date with words' => ['18 October 2026 16:59:59Z'],
        ];
    }

    /**
     * A-19: a relative or local string would read the real clock or the
     * server's time zone once, so such a test clock would not be fixed.
     */
    #[DataProvider('instantsThatAreNotAbsolute')]
    public function test_the_test_clock_refuses_an_instant_that_is_not_absolute(string $instant): void
    {
        foreach ([fn () => new TestClock($instant), fn () => (new TestClock('2026-10-18T00:00:00Z'))->set($instant)] as $make) {
            try {
                $make();
                $this->fail(json_encode($instant).' was accepted by the test clock');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_test_clock_accepts_absolute_instants_with_z_or_an_offset(): void
    {
        foreach ([
            '2026-10-18T16:59:59Z' => '2026-10-18T16:59:59.000000Z',
            '2026-10-18T16:59:59.999999Z' => '2026-10-18T16:59:59.999999Z',
            '2026-10-18 16:59:59Z' => '2026-10-18T16:59:59.000000Z',
            '2026-10-18T23:59:59+07:00' => '2026-10-18T16:59:59.000000Z',
            '2026-10-18T23:59:59.5+0700' => '2026-10-18T16:59:59.500000Z',
            '2026-10-18T12:00:00-05:00' => '2026-10-18T17:00:00.000000Z',
            '2026-10-18T17:00Z' => '2026-10-18T17:00:00.000000Z',
        ] as $instant => $expected) {
            $this->assertSame($expected, (new TestClock($instant))->now()->toIso8601ZuluString('microsecond'), $instant);
        }

        $local = new DateTimeImmutable('2026-10-18 23:59:59', new DateTimeZone('Asia/Jakarta'));
        $this->assertSame('2026-10-18T16:59:59Z', (new TestClock($local))->now()->toIso8601ZuluString());
    }

    private function assertPeriod(Period $period, int $first, int $last, int $start, int $end, ?CarbonImmutable $now): void
    {
        $this->assertSame([self::date($first), self::date($last)], [$period->firstDate->toString(), $period->lastDate->toString()], $period->kind->value);
        $this->assertSame([$start, $end], [$period->start->getTimestamp(), $period->end->getTimestamp()], $period->kind->value);
        $this->assertSame(['UTC', 'UTC'], [$period->start->getTimezone()->getName(), $period->end->getTimezone()->getName()]);
        $this->assertSame(['000000', '000000'], [$period->start->format('u'), $period->end->format('u')]);
        $this->assertTrue($period->contains($period->start));
        $this->assertFalse($period->contains($period->end));
        $this->assertTrue($period->contains($period->end->subMicrosecond()));
        $this->assertFalse($period->contains($period->start->subMicrosecond()));

        if ($now !== null) {
            $this->assertTrue($period->contains($now));
        }
    }

    private function assertRefusedWith(string $reason, callable $compute): void
    {
        try {
            $compute();
            $this->fail("not refused ({$reason})");
        } catch (InvalidPeriod $refused) {
            $this->assertSame($reason, $refused->reason);
        }
    }

    private static function start(int $days): int
    {
        return $days * self::DAY - self::OFFSET;
    }

    /**
     * Days since 1970-01-01 of a civil date (Howard Hinnant, days_from_civil).
     */
    private static function days(int $y, int $m, int $d): int
    {
        $y -= $m <= 2 ? 1 : 0;
        $era = intdiv($y >= 0 ? $y : $y - 399, 400);
        $yoe = $y - $era * 400;
        $doy = intdiv(153 * ($m + ($m > 2 ? -3 : 9)) + 2, 5) + $d - 1;
        $doe = $yoe * 365 + intdiv($yoe, 4) - intdiv($yoe, 100) + $doy;

        return $era * 146097 + $doe - 719468;
    }

    /**
     * The civil date of a day number (Howard Hinnant, civil_from_days).
     *
     * @return array{int, int, int}
     */
    private static function civil(int $z): array
    {
        $z += 719468;
        $era = intdiv($z >= 0 ? $z : $z - 146096, 146097);
        $doe = $z - $era * 146097;
        $yoe = intdiv($doe - intdiv($doe, 1460) + intdiv($doe, 36524) - intdiv($doe, 146096), 365);
        $doy = $doe - (365 * $yoe + intdiv($yoe, 4) - intdiv($yoe, 100));
        $mp = intdiv(5 * $doy + 2, 153);
        $d = $doy - intdiv(153 * $mp + 2, 5) + 1;
        $m = $mp + ($mp < 10 ? 3 : -9);

        return [$yoe + $era * 400 + ($m <= 2 ? 1 : 0), $m, $d];
    }

    private static function date(int $days): string
    {
        [$y, $m, $d] = self::civil($days);

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
