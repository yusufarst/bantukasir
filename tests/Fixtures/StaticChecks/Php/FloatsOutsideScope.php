<?php

namespace Tests\Fixtures\StaticChecks;

// Compliant sample of the float rule: outside the exact-value code, floats are
// not refused. Never executed.

function floatsOutsideExactValueCode(float $amount): float
{
    return round($amount / 2.5, 1);
}
