<?php

namespace Tests\Unit\Support\Time;

use App\Support\Time\BusinessDate;
use App\Support\Time\TestClock;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The business date of BR-NUM-05 (AC-003-05): the Asia/Jakarta calendar date
 * of a UTC instant, across Asia/Jakarta midnight, with the test clock.
 */
final class BusinessDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function instants(): iterable
    {
        yield 'the last microsecond before Asia/Jakarta midnight' => ['2026-10-18T16:59:59.999999Z', '2026-10-18'];
        yield 'Asia/Jakarta midnight' => ['2026-10-18T17:00:00.000000Z', '2026-10-19'];
        yield 'UTC midnight is 07:00 in Asia/Jakarta' => ['2026-10-19T00:00:00Z', '2026-10-19'];
        yield 'the last second of a UTC day is the next Asia/Jakarta date' => ['2026-10-19T23:59:59Z', '2026-10-20'];
        yield 'the new year in Asia/Jakarta' => ['2026-12-31T17:00:00Z', '2027-01-01'];
        yield 'the last second of a year in Asia/Jakarta' => ['2026-12-31T16:59:59Z', '2026-12-31'];
        yield 'a leap day' => ['2028-02-28T17:00:00Z', '2028-02-29'];
        yield 'an instant given with another offset' => ['2026-10-19T06:30:00+07:00', '2026-10-19'];
    }

    #[DataProvider('instants')]
    public function test_the_business_date_of_an_instant_is_its_asia_jakarta_date(string $instant, string $date): void
    {
        $clock = new TestClock($instant);

        $this->assertSame($date, BusinessDate::ofInstant($clock->now())->toString());
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    public function test_the_day_starts_at_asia_jakarta_midnight_in_utc(): void
    {
        $date = BusinessDate::fromString('2026-10-19');

        $this->assertSame('2026-10-18T17:00:00+00:00', $date->startUtc()->toIso8601String());
        $this->assertSame('2026-10-19T17:00:00+00:00', $date->addDays(1)->startUtc()->toIso8601String());
    }

    public function test_the_test_clock_moves_only_when_moved(): void
    {
        $clock = new TestClock('2026-10-18T16:59:59Z');
        $first = $clock->now();

        $this->assertTrue($first->equalTo($clock->now()));
        $this->assertSame('2026-10-18', BusinessDate::ofInstant($first)->toString());

        $clock->advanceSeconds(1);

        $this->assertSame('2026-10-19', BusinessDate::ofInstant($clock->now())->toString());

        $clock->set('2026-12-31T17:00:00Z');

        $this->assertSame('2027-01-01', BusinessDate::ofInstant($clock->now())->toString());
    }

    public function test_dates_are_counted_and_compared(): void
    {
        $from = BusinessDate::fromString('2025-10-19');
        $to = BusinessDate::fromString('2026-10-19');

        $this->assertSame(365, $from->daysUntil($to));
        $this->assertSame(-365, $to->daysUntil($from));
        $this->assertSame(0, $to->daysUntil(BusinessDate::fromString('2026-10-19')));
        $this->assertTrue($to->isAfter($from));
        $this->assertTrue($from->isBefore($to));
        $this->assertSame(0, $to->compareTo(BusinessDate::fromString('2026-10-19')));
        $this->assertSame('2026-10-01', $to->firstOfMonth()->toString());
        $this->assertSame('2026-09-01', $to->firstOfPreviousMonth()->toString());
        $this->assertSame('2026-02-01', BusinessDate::fromString('2026-03-31')->firstOfPreviousMonth()->toString());
        $this->assertSame('2025-12-01', BusinessDate::fromString('2026-01-15')->firstOfPreviousMonth()->toString());
        $this->assertSame(1, $to->dayOfWeek());
        $this->assertSame([2026, 10, 19], [$to->year(), $to->month(), $to->day()]);
        $this->assertSame('"2026-10-19"', json_encode($to));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'a date that does not exist' => ['2026-02-30'];
        yield 'no leading zeros' => ['2026-1-5'];
        yield 'day first' => ['19-10-2026'];
        yield 'with a time' => ['2026-10-19T00:00:00'];
        yield 'with a trailing line break' => ["2026-10-19\n"];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidDates')]
    public function test_a_date_in_another_form_is_refused(string $date): void
    {
        $this->expectException(InvalidArgumentException::class);

        BusinessDate::fromString($date);
    }
}
