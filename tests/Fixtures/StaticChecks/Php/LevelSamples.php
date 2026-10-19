<?php

namespace Tests\Fixtures\StaticChecks;

// Negative samples of PHPStan at level 9 and of Larastan (TEST_STRATEGY
// section 6). Never executed.

function mixedArithmetic(mixed $value): int
{
    return $value + 1;
}

function envOutsideConfig(): mixed
{
    return env('APP_NAME');
}
