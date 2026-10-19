<?php

namespace Tests\Unit\Support\Time;

use App\Support\Time\BusinessDate;
use App\Support\Time\InvalidPeriod;
use App\Support\Time\Period;
use App\Support\Time\PeriodKind;
use App\Support\Time\TestClock;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The five report periods of BR-COST-37 as half-open UTC ranges of
 * Asia/Jakarta dates (BR-NUM-06, AC-003-05), computed from the test clock,
 * with the custom-range refusals of TECHNICAL_RULES section 11.4.
 */
final class PeriodTest extends TestCase
{
    /**
     * now (UTC), period, custom dates, then the first and last dates and the
     * UTC range [start, end).
     *
     * @return iterable<string, array{string, PeriodKind, ?string, ?string, string, string, string, string}>
     */
    public static function periods(): iterable
    {
        $noon = '2026-10-19T05:00:00Z';

        yield 'today' => [$noon, PeriodKind::Today, null, null, '2026-10-19', '2026-10-19', '2026-10-18T17:00:00Z', '2026-10-19T17:00:00Z'];
        yield 'today at Asia/Jakarta midnight' => ['2026-10-18T17:00:00Z', PeriodKind::Today, null, null, '2026-10-19', '2026-10-19', '2026-10-18T17:00:00Z', '2026-10-19T17:00:00Z'];
        yield 'today one second before Asia/Jakarta midnight' => ['2026-10-18T16:59:59Z', PeriodKind::Today, null, null, '2026-10-18', '2026-10-18', '2026-10-17T17:00:00Z', '2026-10-18T17:00:00Z'];
        yield '7d: today and the six dates before' => [$noon, PeriodKind::SevenDays, null, null, '2026-10-13', '2026-10-19', '2026-10-12T17:00:00Z', '2026-10-19T17:00:00Z'];
        yield '7d across a month' => ['2026-10-02T05:00:00Z', PeriodKind::SevenDays, null, null, '2026-09-26', '2026-10-02', '2026-09-25T17:00:00Z', '2026-10-02T17:00:00Z'];
        yield 'this_month until now' => [$noon, PeriodKind::ThisMonth, null, null, '2026-10-01', '2026-10-19', '2026-09-30T17:00:00Z', '2026-10-19T05:00:00Z'];
        yield 'this_month half an hour into it' => ['2026-10-31T17:30:00Z', PeriodKind::ThisMonth, null, null, '2026-11-01', '2026-11-01', '2026-10-31T17:00:00Z', '2026-10-31T17:30:00Z'];
        yield 'last_month' => [$noon, PeriodKind::LastMonth, null, null, '2026-09-01', '2026-09-30', '2026-08-31T17:00:00Z', '2026-09-30T17:00:00Z'];
        yield 'last_month across a year' => ['2026-01-10T00:00:00Z', PeriodKind::LastMonth, null, null, '2025-12-01', '2025-12-31', '2025-11-30T17:00:00Z', '2025-12-31T17:00:00Z'];
        yield 'last_month is February' => ['2026-03-31T05:00:00Z', PeriodKind::LastMonth, null, null, '2026-02-01', '2026-02-28', '2026-01-31T17:00:00Z', '2026-02-28T17:00:00Z'];
        yield 'last_month half an hour after Asia/Jakarta midnight' => ['2026-02-28T17:30:00Z', PeriodKind::LastMonth, null, null, '2026-02-01', '2026-02-28', '2026-01-31T17:00:00Z', '2026-02-28T17:00:00Z'];
        yield 'custom, inclusive dates' => [$noon, PeriodKind::Custom, '2026-10-01', '2026-10-19', '2026-10-01', '2026-10-19', '2026-09-30T17:00:00Z', '2026-10-19T17:00:00Z'];
        yield 'custom, one date' => [$noon, PeriodKind::Custom, '2026-10-05', '2026-10-05', '2026-10-05', '2026-10-05', '2026-10-04T17:00:00Z', '2026-10-05T17:00:00Z'];
        yield 'custom, 366 days' => [$noon, PeriodKind::Custom, '2025-10-19', '2026-10-19', '2025-10-19', '2026-10-19', '2025-10-18T17:00:00Z', '2026-10-19T17:00:00Z'];
        yield 'custom, ending today just after Asia/Jakarta midnight' => ['2026-10-18T17:00:00Z', PeriodKind::Custom, '2026-10-19', '2026-10-19', '2026-10-19', '2026-10-19', '2026-10-18T17:00:00Z', '2026-10-19T17:00:00Z'];
    }

    #[DataProvider('periods')]
    public function test_a_period_is_a_half_open_utc_range_of_asia_jakarta_dates(string $now, PeriodKind $kind, ?string $from, ?string $to, string $first, string $last, string $start, string $end): void
    {
        $clock = new TestClock($now);
        $period = Period::of($kind, $clock->now(), $from === null ? null : BusinessDate::fromString($from), $to === null ? null : BusinessDate::fromString($to));

        $this->assertSame($kind, $period->kind);
        $this->assertSame([$first, $last], [$period->firstDate->toString(), $period->lastDate->toString()]);
        $this->assertSame([$start, $end], [$period->start->format('Y-m-d\TH:i:s\Z'), $period->end->format('Y-m-d\TH:i:s\Z')]);
        $this->assertSame(['UTC', 'UTC'], [$period->start->getTimezone()->getName(), $period->end->getTimezone()->getName()]);
    }

    public function test_the_start_is_inside_and_the_end_is_outside(): void
    {
        $period = Period::today((new TestClock('2026-10-19T05:00:00Z'))->now());

        $this->assertTrue($period->contains(new TestClock('2026-10-18T17:00:00Z')->now()));
        $this->assertTrue($period->contains(new TestClock('2026-10-19T16:59:59.999999Z')->now()));
        $this->assertFalse($period->contains(new TestClock('2026-10-19T17:00:00Z')->now()));
        $this->assertFalse($period->contains(new TestClock('2026-10-18T16:59:59.999999Z')->now()));
    }

    public function test_consecutive_days_meet_without_a_gap_or_an_overlap(): void
    {
        $clock = new TestClock('2026-10-18T05:00:00Z');
        $yesterday = Period::today($clock->now());
        $clock->advanceSeconds(86400);
        $today = Period::today($clock->now());

        $this->assertTrue($yesterday->end->equalTo($today->start));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function refusedCustomPeriods(): iterable
    {
        $noon = '2026-10-19T05:00:00Z';

        yield 'the start after the end' => [$noon, '2026-10-10', '2026-10-09', InvalidPeriod::START_AFTER_END];
        yield 'the end after today' => [$noon, '2026-10-19', '2026-10-20', InvalidPeriod::END_AFTER_TODAY];
        yield 'the end after today, one second before Asia/Jakarta midnight' => ['2026-10-18T16:59:59Z', '2026-10-19', '2026-10-19', InvalidPeriod::END_AFTER_TODAY];
        yield '367 days' => [$noon, '2025-10-18', '2026-10-19', InvalidPeriod::TOO_LONG];
    }

    #[DataProvider('refusedCustomPeriods')]
    public function test_a_custom_period_is_refused_as_technical_rules_section_11_4_says(string $now, string $from, string $to, string $reason): void
    {
        try {
            Period::custom(BusinessDate::fromString($from), BusinessDate::fromString($to), (new TestClock($now))->now());
        } catch (InvalidPeriod $refusal) {
            $this->assertSame($reason, $refusal->reason);

            return;
        }

        $this->fail('The custom period was not refused.');
    }

    public function test_only_a_custom_period_has_dates_and_it_has_both(): void
    {
        $now = (new TestClock('2026-10-19T05:00:00Z'))->now();
        $date = BusinessDate::fromString('2026-10-01');

        foreach ([[PeriodKind::Custom, $date, null], [PeriodKind::Custom, null, null], [PeriodKind::Today, $date, $date]] as [$kind, $from, $to]) {
            try {
                Period::of($kind, $now, $from, $to);
                $this->fail('The period was not refused.');
            } catch (InvalidArgumentException $refusal) {
                $this->assertNotInstanceOf(InvalidPeriod::class, $refusal);
            }
        }

        $this->assertSame(PeriodKind::SevenDays, PeriodKind::from('7d'));
        $this->assertSame(['today', '7d', 'this_month', 'last_month', 'custom'], array_map(fn (PeriodKind $kind): string => $kind->value, PeriodKind::cases()));
    }
}
