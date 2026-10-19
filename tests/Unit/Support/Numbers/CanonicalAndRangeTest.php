<?php

namespace Tests\Unit\Support\Numbers;

use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The kernel's boundaries, from the class-A adversarial review of TASK-003
 * (its section 5, areas 1 and 2, and finding A-13): every non-canonical form
 * of ADR-006 item 2 is refused by Money, Quantity and Cost; the ranges of
 * TECHNICAL_RULES section 11.1 hold at the boundary and one unit past it;
 * precisions 0 to 3; cost scale 6 and UNKNOWN; a division by zero is a kernel
 * refusal; line totals are rounded half-up once against an integer oracle.
 */
final class CanonicalAndRangeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function nonCanonical(): array
    {
        $inputs = [
            '', ' ', '-', '+', '.', '+0', '+1', '-0', '-00', '-0.0', '-0.000', '-0.000000', '00', '01', '-01', '00.5', '0.', '1.', '.5', '-.5',
            '1..5', '1.5.5', '--1', '1e3', '1E3', '1e-3', '7.5e4', '1e-6', '0x10', '0b10', '1_000', '1,000', '1,5', '1.000,5', "1'000",
            ' 1', '1 ', '1 000', "\t1", "1\t", "\n1", "1\n", "1\r", "1\0", "\u{00A0}1", "1\u{00A0}", "1\u{202F}000", "\u{200B}1", "1\u{200B}", "\u{FEFF}1",
            '１', '١٢', '۱۲', '१२', '๑๒', '𝟏𝟐', '¹', '½', 'Ⅻ', '1٫5', '−1', '－1', '‐1', 'NaN', 'INF', 'Infinity', 'null', 'true', 'Rp1',
            'unknown', ' UNKNOWN', 'UNKNOWN ', str_repeat('9', 10000),
        ];

        return array_combine(
            array_map(fn (string $s): string => json_encode(mb_substr($s, 0, 40), JSON_INVALID_UTF8_SUBSTITUTE) ?: '?', $inputs),
            array_map(fn (string $s): array => [$s], $inputs),
        );
    }

    #[DataProvider('nonCanonical')]
    public function test_every_non_canonical_form_is_refused_by_every_kind(string $input): void
    {
        foreach ([fn () => Money::of($input), fn () => Quantity::of($input, 3), fn () => Quantity::of($input, 0), fn () => Cost::of($input)] as $parse) {
            try {
                $parse();
                $this->fail(sprintf('%s was accepted', json_encode(mb_substr($input, 0, 40))));
            } catch (NonCanonicalNumber|NumberOutOfRange) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_ranges_of_technical_rules_11_1_at_the_boundary_and_one_past_it(): void
    {
        $this->assertSame('999999999999', Money::of('999999999999')->assertStored()->toCanonical());
        $this->assertSame('0', Money::of('0')->assertStored()->toCanonical());
        $this->assertRefused(fn () => Money::of('1000000000000')->assertStored());
        $this->assertRefused(fn () => Money::of('-1')->assertStored());
        $this->assertSame('-999999999999', Money::of('-999999999999')->assertVariance()->toCanonical());
        $this->assertRefused(fn () => Money::of('-1000000000000')->assertVariance());
        $this->assertRefused(fn () => Money::of('1000000000000')->assertVariance());

        $this->assertSame('99999999.999', Quantity::of('99999999.999', 3)->assertLine()->toCanonical());
        $this->assertSame('0.001', Quantity::of('0.001', 3)->assertLine()->toCanonical());
        $this->assertRefused(fn () => Quantity::of('100000000', 3)->assertLine());
        $this->assertRefused(fn () => Quantity::of('0', 3)->assertLine());
        $this->assertRefused(fn () => Quantity::of('-0.001', 3)->assertLine());
        $this->assertSame('-99999999.999', Quantity::of('-99999999.999', 3)->assertMovement()->toCanonical());
        $this->assertRefused(fn () => Quantity::of('-100000000', 0)->assertMovement());
        $this->assertSame('9999999999.999', Quantity::of('9999999999.999', 3)->assertStock()->toCanonical());
        $this->assertRefused(fn () => Quantity::of('10000000000', 0)->assertStock());
        $this->assertRefused(fn () => Quantity::of('-0.001', 3)->assertStock());

        $this->assertSame('999999999999.999999', Cost::of('999999999999.999999')->assertUnit()->toCanonical());
        $this->assertRefused(fn () => Cost::of('1000000000000')->assertUnit());
        $this->assertSame('999999999999999.999999', Cost::of('999999999999999.999999')->assertValue()->toCanonical());
        $this->assertRefused(fn () => Cost::of('1000000000000000')->assertValue());
        $this->assertSame('UNKNOWN', Cost::of('UNKNOWN')->assertUnit()->assertValue()->toCanonical());
    }

    public function test_the_precision_of_a_quantity_is_0_to_3_and_counts_the_decimals_as_written(): void
    {
        foreach ([0 => ['3', '3.0'], 1 => ['1.5', '1.50'], 2 => ['1.25', '1.250'], 3 => ['1.125', '1.1250']] as $precision => [$fits, $tooLong]) {
            $this->assertSame($fits, Quantity::of($fits, $precision)->toCanonical());
            $this->assertRefused(fn () => Quantity::of($tooLong, $precision));
        }

        $this->assertRefused(fn () => Quantity::of('1', -1));
        $this->assertRefused(fn () => Quantity::of('1', 4));
        $this->assertRefused(fn () => Quantity::fromBigDecimal(BigDecimal::of('1.5'), 0));
        $this->assertSame('1.5', Quantity::fromDatabase('1.500', 1)->toCanonical());
        $this->assertRefused(fn () => Quantity::fromDatabase('1.500', 0));
        $this->assertRefused(fn () => Quantity::of('1.5', 1)->plus(Quantity::of('1.5', 2)));
    }

    public function test_cost_is_scale_6_never_negative_and_unknown_is_never_zero(): void
    {
        $this->assertSame('0.000001', Cost::of('0.000001')->toCanonical());
        $this->assertRefused(fn () => Cost::of('0.0000001'));
        $this->assertRefused(fn () => Cost::of('-0.000001'));
        $this->assertRefused(fn () => Cost::known(BigDecimal::of('0.0000005')));
        $this->assertRefused(fn () => Cost::of('1')->minus(Cost::of('1.000001')));
        $this->assertFalse(Cost::of('UNKNOWN')->isKnown());
        $this->assertFalse(Cost::of('UNKNOWN')->plus(Cost::of('0'))->isKnown());
        $this->assertFalse(Cost::mulDiv(Cost::unknown(), BigDecimal::one(), BigDecimal::one())->isKnown());
        $this->assertRefused(fn () => Cost::unknown()->amount());
        $this->assertRefused(fn () => Cost::fromDatabase('0', 'UNKNOWN'));
        $this->assertRefused(fn () => Cost::fromDatabase(null, 'KNOWN'));
        $this->assertRefused(fn () => Cost::fromDatabase(null, null));
        $this->assertSame('"UNKNOWN"', json_encode(Cost::unknown()));
    }

    /**
     * A-13: a zero divisor is refused with a kernel exception (R-21), which
     * the command Tasks map to VALIDATION_FAILED, never with Brick's
     * DivisionByZeroException, which an unmapped handler would turn into a
     * server error.
     */
    public function test_a_division_by_zero_is_a_refusal_the_kernel_names(): void
    {
        foreach ([
            fn () => Cost::mulDiv(Cost::of('10'), Quantity::of('1', 0), Quantity::of('0', 0)),
            fn () => Cost::mulDiv(Cost::of('10'), BigDecimal::one(), BigDecimal::zero()),
            fn () => Cost::quotient(BigDecimal::of('10'), BigInteger::zero()),
            fn () => Cost::quotient(BigDecimal::of('0'), BigDecimal::of('0.000')),
        ] as $divide) {
            try {
                $divide();
                $this->fail('A division by zero was accepted.');
            } catch (Throwable $error) {
                $this->assertInstanceOf(NumberOutOfRange::class, $error, sprintf('A division by zero escapes as %s', $error::class));
            }
        }

        $this->assertFalse(Cost::mulDiv(Cost::unknown(), BigDecimal::one(), BigDecimal::zero())->isKnown(), 'UNKNOWN stays UNKNOWN, whatever the divisor');
    }

    public function test_line_totals_round_half_up_once_against_an_integer_oracle(): void
    {
        mt_srand(20261020);

        for ($i = 0; $i < 20000; $i++) {
            $precision = mt_rand(0, 3);
            $units = (string) mt_rand(1, 99999999999);
            $quantity = self::decimal($units, $precision);
            $price = (string) mt_rand(0, 999999999999);
            $product = BigInteger::of($units)->multipliedBy($price);
            $divisor = BigInteger::ten()->power($precision);
            [$quotient, $remainder] = [$product->quotient($divisor), $product->remainder($divisor)];
            $expected = $remainder->multipliedBy(2)->isGreaterThanOrEqualTo($divisor) ? $quotient->plus(1) : $quotient;

            try {
                $total = Money::lineTotal(Quantity::of($quantity, $precision), Money::of($price))->toCanonical();
                $this->assertSame((string) $expected, $total, "{$quantity} × {$price}");
            } catch (NumberOutOfRange) {
                $this->assertTrue($expected->isGreaterThan('999999999999'), "{$quantity} × {$price} refused at {$expected}");
            }
        }
    }

    public function test_no_double_rounding_and_ties_away_from_zero(): void
    {
        // 0.003 × 4833 = 14.499: rounding to two decimals first would give 15.
        $this->assertSame('14', Money::lineTotal(Quantity::of('0.003', 3), Money::of('4833'))->toCanonical());
        $this->assertSame('0', Money::lineTotal(Quantity::of('0.495', 3), Money::of('1'))->toCanonical());
        $this->assertSame('1', Money::lineTotal(Quantity::of('0.001', 3), Money::of('500'))->toCanonical());
        $this->assertSame('-11', CanonicalNumber::format(BigDecimal::of('-10.5')->toScale(0, RoundingMode::HalfUp)));
        $this->assertSame('0', CanonicalNumber::format(BigDecimal::of('-0.4')->toScale(0, RoundingMode::HalfUp)));
        $this->assertSame('1.000001', Cost::mulDiv(Cost::of('2.000001'), BigDecimal::of('1'), BigDecimal::of('2'))->toCanonical());
        $this->assertSame('0.000001', Cost::mulDiv(Cost::of('0.000001'), BigDecimal::of('1'), BigDecimal::of('2'))->toCanonical());
        $this->assertSame('999999999999999.999999', Cost::mulDiv(Cost::of('999999999999999.999999'), Quantity::of('99999999.999', 3), Quantity::of('99999999.999', 3))->toCanonical());
    }

    private function assertRefused(callable $compute): void
    {
        try {
            $compute();
            $this->fail('The value was accepted.');
        } catch (NonCanonicalNumber|NumberOutOfRange|InvalidArgumentException|LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    private static function decimal(string $units, int $precision): string
    {
        if ($precision === 0) {
            return $units;
        }

        $units = str_pad($units, $precision + 1, '0', STR_PAD_LEFT);

        return substr($units, 0, -$precision).'.'.substr($units, -$precision);
    }
}
