<?php

namespace Tests\Fixtures\StaticChecks\Php\Floats;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

// Compliant samples of the float rule (ADR-006 item 5, TASK-003 finding A-02):
// exact-value code as the kernel writes it — Brick Math for the values,
// integers for counts and positions, strict comparisons, string sorting and
// formats without a float conversion. The rule reports none of these lines.
// Never executed.
final class FloatControls
{
    /**
     * @param  list<string>  $keys
     * @param  array<string, string>  $texts
     */
    public function exact(string $canonical, string $other, int $units, int $scale, array $keys, array $texts): void
    {
        $sum = BigDecimal::of($canonical)->plus($other);
        $rounded = $sum->toScale(0, RoundingMode::HalfUp);
        $greater = BigDecimal::of($canonical)->isGreaterThan($other);
        $same = $canonical === $other;
        $different = $canonical !== $other;
        $order = strcmp($canonical, $other) <=> 0;
        $next = $units + 1;
        $previous = $units - 1;
        $double = $units * 2;
        $remainder = $units % 3;
        $negated = -$units;
        $quotient = intdiv($units, 2);
        $absolute = abs($units);
        $longer = strlen($canonical) > $scale;
        $within = $scale >= 0 && $scale <= 6;
        $units++;
        $units--;
        $units += 2;
        sort($keys, SORT_STRING);
        ksort($texts, SORT_STRING);
        $message = sprintf('The %s has %d decimals.', $canonical, $scale);
        $date = sprintf('%02d %s %04d', $units, $canonical, $scale);
        $count = count($keys);
        $found = in_array($canonical, $keys, true);
        $highest = max($units, $scale);
        $padded = str_pad($canonical, 6, '0', STR_PAD_LEFT);
    }
}
