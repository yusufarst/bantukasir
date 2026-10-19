<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;

/**
 * The application clock: the server's current instant in UTC (ARC-50). It
 * reads Carbon's clock, so a test that freezes Carbon's time freezes it too.
 */
final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
