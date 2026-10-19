<?php

namespace Tests\Unit\Support\Numbers;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Limits;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Money, Quantity and Cost (ADR-006): the ranges of TECHNICAL_RULES section
 * 11.1 at their boundaries, the transport as strings, the reading of database
 * values, and UNKNOWN as a status (AC-003-01, AC-003-02).
 */
final class ValueObjectsTest extends TestCase
{
    public function test_money_ranges_are_checked_at_their_boundaries(): void
    {
        $this->assertSame('0', Money::of('0')->assertStored()->toCanonical());
        $this->assertSame(Limits::MONEY_MAX, Money::of(Limits::MONEY_MAX)->assertStored()->toCanonical());
        $this->assertSame('-999999999999', Money::of('-999999999999')->assertVariance()->toCanonical());
        $this->assertRefused(NumberOutOfRange::class, fn () => Money::of('1000000000000')->assertStored());
        $this->assertRefused(NumberOutOfRange::class, fn () => Money::of('-1')->assertStored());
        $this->assertRefused(NumberOutOfRange::class, fn () => Money::of('1000000000000')->assertVariance());
        $this->assertRefused(NumberOutOfRange::class, fn () => Money::of('-1000000000000')->assertVariance());
    }

    public function test_money_arithmetic_is_exact(): void
    {
        $tendered = Money::of('100000');
        $total = Money::sum(Money::of('75000'), Money::of('5000'));

        $this->assertSame('80000', $total->toCanonical());
        $this->assertSame('20000', $tendered->minus($total)->toCanonical());
        $this->assertSame('-20000', $total->minus($tendered)->toCanonical());
        $this->assertSame('20000', $total->minus($tendered)->negated()->toCanonical());
        $this->assertSame(1, $tendered->compareTo($total));
        $this->assertTrue($total->isEqualTo(Money::of('80000')));
        $this->assertTrue(Money::zero()->isZero());
        $this->assertTrue($total->isPositive());
        $this->assertTrue($total->negated()->isNegative());
        $this->assertSame('180000', $tendered->plus($total)->toCanonical());

        // An aggregate has no cap: it stays exact above the stored range.
        $this->assertSame('1999999999998', Money::of(Limits::MONEY_MAX)->plus(Money::of(Limits::MONEY_MAX))->toCanonical());
        $this->assertSame('12345678901234567890', Money::fromBigInteger(BigInteger::of('12345678901234567890'))->toCanonical());
    }

    public function test_money_is_read_from_a_bigint_column_without_a_float(): void
    {
        $this->assertSame('75000', Money::fromDatabase(75000)->toCanonical());
        $this->assertSame('-2000', Money::fromDatabase('-2000')->toCanonical());
        $this->assertSame(PHP_INT_MAX, Money::fromDatabase(PHP_INT_MAX)->toBigInteger()->toInt());
        $this->assertRefused(NonCanonicalNumber::class, fn () => Money::fromDatabase('1.5'));
    }

    public function test_quantity_ranges_are_checked_at_their_boundaries(): void
    {
        $this->assertSame('0.001', Quantity::of('0.001', 3)->assertLine()->toCanonical());
        $this->assertSame(Limits::QUANTITY_MAX, Quantity::of(Limits::QUANTITY_MAX, 3)->assertLine()->toCanonical());
        $this->assertSame('-99999999.999', Quantity::of('-99999999.999', 3)->assertMovement()->toCanonical());
        $this->assertSame(Limits::STOCK_MAX, Quantity::of(Limits::STOCK_MAX, 3)->assertStock()->toCanonical());
        $this->assertSame('0', Quantity::of('0', 0)->assertStock()->toCanonical());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('0', 3)->assertLine());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('-1', 0)->assertLine());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('100000000', 0)->assertLine());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('0', 0)->assertMovement());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('-100000000', 0)->assertMovement());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('-0.001', 3)->assertStock());
        $this->assertRefused(NumberOutOfRange::class, fn () => Quantity::of('10000000000', 0)->assertStock());
    }

    public function test_quantity_keeps_its_precision(): void
    {
        $this->assertSame(2, Quantity::of('1.5', 2)->precision());
        $this->assertSame('3.75', Quantity::of('1.5', 2)->plus(Quantity::of('2.25', 2))->toCanonical());
        $this->assertSame('-0.75', Quantity::of('1.5', 2)->minus(Quantity::of('2.25', 2))->toCanonical());
        $this->assertSame('-1.5', Quantity::of('1.5', 2)->negated()->toCanonical());
        $this->assertSame(-1, Quantity::of('1.5', 2)->compareTo(Quantity::of('2.25', 2)));
        $this->assertTrue(Quantity::of('0', 1)->isZero());
        $this->assertTrue(Quantity::of('-1', 1)->isNegative());
        $this->assertTrue(Quantity::of('1', 1)->isPositive());
        $this->assertRefused(InvalidArgumentException::class, fn () => Quantity::of('1', 2)->plus(Quantity::of('1', 3)));
        $this->assertRefused(InvalidArgumentException::class, fn () => Quantity::of('1', 4));
        $this->assertRefused(InvalidArgumentException::class, fn () => Quantity::of('1', -1));
    }

    public function test_quantity_is_read_from_a_numeric_column_at_the_products_precision(): void
    {
        $this->assertSame('1', Quantity::fromDatabase('1.000', 0)->toCanonical());
        $this->assertSame('1.5', Quantity::fromDatabase('1.500', 1)->toCanonical());
        $this->assertSame('-0.25', Quantity::fromDatabase('-0.250', 2)->toCanonical());
        $this->assertSame('1.5', Quantity::fromBigDecimal(BigDecimal::of('1.50000'), 1)->toCanonical());
        $this->assertRefused(NonCanonicalNumber::class, fn () => Quantity::fromDatabase('1.500', 0));
        $this->assertRefused(NonCanonicalNumber::class, fn () => Quantity::fromDatabase('NaN', 3));
        $this->assertRefused(NonCanonicalNumber::class, fn () => Quantity::fromBigDecimal(BigDecimal::of('0.0001'), 3));
    }

    public function test_cost_ranges_are_checked_and_unknown_passes_as_a_status(): void
    {
        $this->assertSame(Limits::COST_MAX, Cost::of(Limits::COST_MAX)->assertUnit()->toCanonical());
        $this->assertSame(Limits::VALUE_MAX, Cost::known(BigDecimal::of(Limits::VALUE_MAX))->assertValue()->toCanonical());
        $this->assertSame(Cost::UNKNOWN, Cost::unknown()->assertUnit()->assertValue()->toCanonical());
        $this->assertRefused(NumberOutOfRange::class, fn () => Cost::known(BigDecimal::of('1000000000000'))->assertUnit());
        $this->assertRefused(NumberOutOfRange::class, fn () => Cost::known(BigDecimal::of('1000000000000000'))->assertValue());
        $this->assertRefused(NumberOutOfRange::class, fn () => Cost::known(BigDecimal::of('-0.000001')));
        $this->assertRefused(NonCanonicalNumber::class, fn () => Cost::known(BigDecimal::of('0.0000001')));
        $this->assertRefused(NonCanonicalNumber::class, fn () => Cost::of('-1'));
    }

    public function test_unknown_is_never_zero_and_propagates(): void
    {
        $unknown = Cost::of(Cost::UNKNOWN);
        $known = Cost::of('12000');

        $this->assertFalse($unknown->isKnown());
        $this->assertNotSame('0', $unknown->toCanonical());
        $this->assertFalse($known->plus($unknown)->isKnown());
        $this->assertFalse($unknown->minus($known)->isKnown());
        $this->assertFalse(Cost::mulDiv($unknown, BigDecimal::one(), BigDecimal::of('3'))->isKnown());
        $this->assertRefused(LogicException::class, fn () => $unknown->amount());
        $this->assertRefused(LogicException::class, fn () => $known->compareTo($unknown));
        $this->assertRefused(NumberOutOfRange::class, fn () => Cost::of('1')->minus(Cost::of('2')));
        $this->assertSame('12001.5', $known->plus(Cost::of('1.5'))->toCanonical());
        $this->assertSame(1, $known->compareTo(Cost::of('1.5')));
    }

    public function test_cost_is_read_from_its_columns_without_a_float(): void
    {
        $this->assertSame('12.5', Cost::fromDatabase('12.500000')->toCanonical());
        $this->assertSame('12.5', Cost::fromDatabase('12.500000', 'KNOWN')->toCanonical());
        $this->assertSame(Cost::UNKNOWN, Cost::fromDatabase(null, 'UNKNOWN')->toCanonical());
        $this->assertRefused(InvalidArgumentException::class, fn () => Cost::fromDatabase('0.000000', 'UNKNOWN'));
        $this->assertRefused(InvalidArgumentException::class, fn () => Cost::fromDatabase(null, 'KNOWN'));
        $this->assertRefused(InvalidArgumentException::class, fn () => Cost::fromDatabase(null));
        $this->assertRefused(InvalidArgumentException::class, fn () => Cost::fromDatabase('UNKNOWN'));
    }

    public function test_every_value_travels_as_a_string_never_as_a_json_number(): void
    {
        $json = json_encode([
            'price' => Money::of('75000'),
            'variance' => Money::of('-2000'),
            'quantity' => Quantity::of('1.500', 3),
            'cost' => Cost::of('3333.333333'),
            'unknown' => Cost::unknown(),
        ], JSON_THROW_ON_ERROR);

        $this->assertSame('{"price":"75000","variance":"-2000","quantity":"1.5","cost":"3333.333333","unknown":"UNKNOWN"}', $json);
        $this->assertSame('75000', (string) Money::of('75000'));
        $this->assertSame('1.5', (string) Quantity::of('1.5', 1));
        $this->assertSame('UNKNOWN', (string) Cost::unknown());
    }

    /**
     * @param  class-string<\Throwable>  $exception
     * @param  callable(): mixed  $operation
     */
    private function assertRefused(string $exception, callable $operation): void
    {
        try {
            $operation();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);

            return;
        }

        $this->fail(sprintf('Expected %s.', $exception));
    }
}
