<?php

namespace Tests\Unit\Support\Numbers;

use App\Support\Formatting\Format;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A PHP float never becomes money, a quantity or a cost at run time (ADR-006
 * items 2 and 5, AC-003-01; TASK-003 adversarial findings A-11 and A-18). Like
 * the application's own files, this file does not declare strict_types, so
 * PHP's coercive typing applies to every call below: the factories refuse the
 * value themselves, instead of receiving it already turned into a string. The
 * static analysis reports each of these calls too (argument.type), which is
 * why each line tells it that the wrong type is the point of the test.
 */
final class NoFloatTest extends TestCase
{
    /**
     * @return array<string, array{callable(): mixed}>
     */
    public static function floatsIntoExactValues(): array
    {
        return [
            'Money::of(75000.0)' => [fn () => Money::of(75000.0)], // @phpstan-ignore argument.type
            'Money::of(75000)' => [fn () => Money::of(75000)], // @phpstan-ignore argument.type
            'Money::fromDatabase(75000.0)' => [fn () => Money::fromDatabase(75000.0)], // @phpstan-ignore argument.type
            'Money::fromDatabase(true)' => [fn () => Money::fromDatabase(true)], // @phpstan-ignore argument.type
            'Quantity::of(1.5, 1)' => [fn () => Quantity::of(1.5, 1)], // @phpstan-ignore argument.type
            'Quantity::of(2, 0)' => [fn () => Quantity::of(2, 0)], // @phpstan-ignore argument.type
            'Quantity::fromDatabase(0.25, 2)' => [fn () => Quantity::fromDatabase(0.25, 2)], // @phpstan-ignore argument.type
            'Cost::of(0.1)' => [fn () => Cost::of(0.1)], // @phpstan-ignore argument.type
            'Cost::fromDatabase(12000.5)' => [fn () => Cost::fromDatabase(12000.5)], // @phpstan-ignore argument.type
            'Cost::fromDatabase(12000.5, KNOWN)' => [fn () => Cost::fromDatabase(12000.5, 'KNOWN')], // @phpstan-ignore argument.type
            'CanonicalNumber::parse(0.5)' => [fn () => CanonicalNumber::parse(0.5, 6, false, 'x')], // @phpstan-ignore argument.type
            'Cost::of(json_decode 0.1)' => [fn () => Cost::of(json_decode('{"cost": 0.1}', true)['cost'])], // @phpstan-ignore argument.type
            'Quantity::of(1.5 + 0)' => [fn () => Quantity::of('1.5' + 0, 1)], // @phpstan-ignore argument.type
        ];
    }

    /**
     * @param  callable(): mixed  $compute
     */
    #[DataProvider('floatsIntoExactValues')]
    public function test_a_float_never_becomes_an_exact_value(callable $compute): void
    {
        try {
            $value = $compute();
        } catch (NonCanonicalNumber) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail(sprintf('A %s became an exact value: "%s"', 'non-string', is_object($value) && method_exists($value, 'toCanonical') ? $value->toCanonical() : get_debug_type($value)));
    }

    public function test_the_canonical_strings_and_database_values_still_pass(): void
    {
        $this->assertSame('75000', Money::of('75000')->toCanonical());
        $this->assertSame('9007199254740993', Money::fromDatabase(9007199254740993)->toCanonical());
        $this->assertSame('75000', Money::fromDatabase('75000')->toCanonical());
        $this->assertSame('1.5', Quantity::of('1.5', 1)->toCanonical());
        $this->assertSame('1.25', Quantity::fromDatabase('1.250', 2)->toCanonical());
        $this->assertSame('0.1', Cost::of('0.1')->toCanonical());
        $this->assertSame('UNKNOWN', Cost::fromDatabase(null, 'UNKNOWN')->toCanonical());
        $this->assertSame('12000.5', Cost::fromDatabase('12000.500000', 'KNOWN')->toCanonical());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function countsThatAreNotIntegers(): array
    {
        return ['1.5' => [1.5], '2.0' => [2.0], 'the string 5' => ['5'], 'null' => [null], 'true' => [true]];
    }

    #[DataProvider('countsThatAreNotIntegers')]
    public function test_a_count_is_never_truncated_from_another_type(mixed $count): void
    {
        try {
            $text = Format::count($count); // @phpstan-ignore argument.type
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail(sprintf('Format::count(%s) gave %s', json_encode($count), $text));
    }
}
