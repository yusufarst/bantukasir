<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Bind;

/**
 * The clock boundary of STK-31: the application's current instant, in UTC
 * (ARC-50, BR-NUM-05). The application resolves SystemClock; a test binds a
 * TestClock to control time for business dates, periods and every other time
 * rule (TEST_STRATEGY section 3, TS-08).
 */
#[Bind(SystemClock::class)]
interface Clock
{
    public function now(): CarbonImmutable;
}
