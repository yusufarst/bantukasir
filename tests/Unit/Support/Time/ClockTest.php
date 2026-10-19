<?php

namespace Tests\Unit\Support\Time;

use App\Support\Time\Clock;
use App\Support\Time\SystemClock;
use App\Support\Time\TestClock;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The clock boundary of STK-31 (contract item 4): the application resolves
 * the system clock in UTC, which follows Carbon's own test time, and a test
 * can bind the controllable test clock in its place.
 */
final class ClockTest extends TestCase
{
    public function test_the_application_clock_is_the_system_clock_in_utc(): void
    {
        $clock = $this->app->make(Clock::class);

        $this->assertInstanceOf(SystemClock::class, $clock);
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_the_system_clock_follows_carbons_test_time(): void
    {
        CarbonImmutable::setTestNow('2026-10-18T16:59:59Z');

        try {
            $this->assertSame('2026-10-18T16:59:59+00:00', (new SystemClock)->now()->toIso8601String());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_a_test_binds_the_test_clock(): void
    {
        $this->app->instance(Clock::class, new TestClock('2026-10-18T17:00:00Z'));

        $this->assertSame('2026-10-18T17:00:00+00:00', $this->app->make(Clock::class)->now()->toIso8601String());
    }
}
