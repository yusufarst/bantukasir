<?php

namespace Tests\Fixtures\StaticChecks\Php\Floats;

// Negative samples of the float rule (ADR-006 item 5), in a directory that the
// samples' configuration adds to the exact-value code. Never executed.

final class FloatSamples
{
    public float $price = 0;

    public const float RATE = 1;

    public function share(float $amount): int
    {
        return 0;
    }

    public function ratio(int $amount): float
    {
        return 0;
    }

    public function operations(int $amount): void
    {
        $literal = 1.5;
        $cast = (float) $amount;
        $half = $amount / 2;
        $square = $amount ** 2;
        $rounded = round($amount);
        $converted = \Brick\Math\BigDecimal::of('1.5')->toFloat();
        $days = \Carbon\CarbonImmutable::now()->diffInDays();
    }
}
