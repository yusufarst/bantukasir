<?php

namespace Tests\Fixtures\StaticChecks\Php\Floats;

use Carbon\CarbonImmutable;

use function round as roundTo;

// Negative samples of the float rule (ADR-006 item 5, R-20, AC-003-03) from
// the class-A adversarial review of TASK-003, finding A-02: floating point in
// exact-value code that the rule let through. Each line ends with its tag;
// tests/Unit/StaticChecks/PhpstanRulesTest.php lists which tags are reported.
// B11, B16, B18 and B23 are not float operations. Never executed.
final class FloatBypassSamples
{
    public function bypasses(string $canonical, string $other, int $units): void
    {
        $sum = $canonical + 0; // B01 numeric-string arithmetic gives float|int
        $product = $canonical * $other; // B02 multiplication of canonical strings
        $difference = $canonical - $other; // B03 subtraction of canonical strings
        $greater = $canonical > $other; // B04 comparison of numeric strings through floats
        $equal = $canonical == $other; // B05 loose equality of numeric strings through floats
        $overflow = PHP_INT_MAX + $units; // B06 integer overflow gives a float
        $text = sprintf('%.2f', $canonical); // B07 %f formats through a float
        settype($canonical, 'float'); // B08 settype() makes a float
        $decoded = json_decode('{"cost": 0.1}', true); // B09 a float inside mixed
        $constant = M_PI; // B10 a float constant
        $function = 'floatval'; // B11 the name of a float function
        $called = $function($other); // B12 a float through a variable function
        $mapped = array_map('floatval', [$other]); // B13 floats inside an array
        $aliased = roundTo($units); // B14 round() under an alias
        $user = call_user_func('floatval', $other); // B15 call_user_func()
        $sorted = [$canonical, $other]; // B16
        sort($sorted); // B17 sort() compares numeric strings as numbers
        $increment = $other; // B18
        $increment++; // B19 '1.5'++ is 2.5
        $cast = (string) $sum; // B20 a float written as a string
        $max = max($canonical, $other); // B21 max() compares numeric strings as numbers
        $sumOf = array_sum([$canonical, $other]); // B22 array_sum() returns float|int
        $absolute = abs($units); // B23 control: abs() of an int is an int
        $seconds = CarbonImmutable::now()->diffInSeconds(); // B24 control: Carbon difference, a float
        $half = $units / 2; // B25 control: division with /
        $literal = 1.5; // B26 control: a float literal
    }

    /**
     * @param  numeric-string  $amount
     */
    public function narrowed(string $amount, string $input, int $quantity): void
    {
        $total = $amount * $quantity; // B27 arithmetic on a numeric-string
        $text = (string) ($amount + 0); // B28 a numeric-string sum written back as a string
        if (is_numeric($input)) {
            $doubled = $input * 2; // B29 arithmetic after is_numeric()
            $less = $input < $amount; // B30 comparison after is_numeric()
        }
    }

    /**
     * Neighbours of the same operations, beyond the review's samples.
     */
    public function neighbours(string $canonical, string $other, int $units): void
    {
        $canonical += 1; // B31 compound assignment on a string
        $modulo = $canonical % 2; // B32 modulo of a string
        $negated = -$canonical; // B33 unary minus of a string
        $spaceship = $canonical <=> $other; // B34 the spaceship operator on strings
        $notEqual = $canonical != $other; // B35 loose inequality of strings
        $atMost = $canonical <= $other; // B36 comparison of strings
        rsort($sorted); // B37 rsort() without a string flag
        $minimum = min([$canonical, $other]); // B38 min() of strings
        $printed = vsprintf('%e', [$canonical]); // B39 an exponent format through a float
        $format = '%.2f'; // B40
        $dynamic = sprintf($format, $units); // B41 a format that is not a literal
        $infinite = INF; // B42 a float constant
        $epsilon = PHP_FLOAT_EPSILON; // B43 a float constant
        $decremented = $other; // B44
        $decremented--; // B45 '1.5'-- is 0.5
        usort($sorted, fn ($a, $b) => $a - $b); // B46 a comparator that subtracts strings
        asort($sorted); // B47 asort() without a string flag
        $unique = array_unique([$canonical], SORT_NUMERIC); // B48 array_unique() comparing as numbers
    }
}
