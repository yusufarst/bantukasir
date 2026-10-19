<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * The controllable test clock of STK-31: it stands still at the instant it is
 * given and moves only when a test moves it (TS-08). A string is an absolute
 * instant, a date and a time with `Z` or an offset; a relative or local string
 * such as "now", "tomorrow" or "2026-10-18 16:59:59" would read the real clock
 * or the server's time zone once, so it is refused (TASK-003 finding A-19,
 * reading R-39).
 */
final class TestClock implements Clock
{
    private const string ABSOLUTE = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}[T ][0-9]{2}:[0-9]{2}(?::[0-9]{2}(?:\.[0-9]{1,6})?)?(?:Z|[+-][0-9]{2}:?[0-9]{2})$/D';

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
        if ($at instanceof DateTimeInterface) {
            return CarbonImmutable::instance($at)->utc();
        }

        if (preg_match(self::ABSOLUTE, $at) !== 1) {
            throw new InvalidArgumentException('A test clock takes an absolute instant: a date and a time with Z or an offset.');
        }

        return CarbonImmutable::parse($at)->utc();
    }
}
