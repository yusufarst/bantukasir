<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The controllable test clock of STK-31: it stands still at the instant it is
 * given and moves only when a test moves it (TS-08). A string without an
 * offset is read as UTC.
 */
final class TestClock implements Clock
{
    private CarbonImmutable $now;

    public function __construct(DateTimeInterface|string $at)
    {
        $this->now = self::utc($at);
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function set(DateTimeInterface|string $at): void
    {
        $this->now = self::utc($at);
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->now = $this->now->addSeconds($seconds);
    }

    private static function utc(DateTimeInterface|string $at): CarbonImmutable
    {
        return ($at instanceof DateTimeInterface ? CarbonImmutable::instance($at) : CarbonImmutable::parse($at, 'UTC'))->utc();
    }
}
