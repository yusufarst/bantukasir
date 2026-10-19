<?php

namespace Tests\Unit\Support\Numbers;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * The cost rounding of ADR-008 section 5 (AC-003-01): the exact product of the
 * factors, then one division to scale 6, half-up; the issue that takes stock
 * to zero takes the value exactly (BR-COST-11); the cumulative rule of partial
 * returns leaves no residue.
 */
final class CostArithmeticTest extends TestCase
{
    public function test_an_issue_allocation_is_rounded_once_to_six_decimals(): void
    {
        $value = Cost::of('100');

        $this->assertSame('33.333333', Cost::mulDiv($value, Quantity::of('1', 0), Quantity::of('3', 0))->toCanonical());
        $this->assertSame('66.666667', Cost::mulDiv($value, Quantity::of('2', 0), Quantity::of('3', 0))->toCanonical());
    }

    public function test_the_issue_that_empties_stock_takes_the_value_exactly(): void
    {
        $value = Cost::of('100.000001');

        $this->assertSame('100.000001', Cost::mulDiv($value, Quantity::of('3.5', 1), Quantity::of('3.5', 1))->toCanonical());
    }

    public function test_a_tie_at_the_sixth_decimal_rounds_up(): void
    {
        $this->assertSame('0.000001', Cost::mulDiv(Cost::of('0.000001'), BigDecimal::of('1'), BigDecimal::of('2'))->toCanonical());
        $this->assertSame('0', Cost::mulDiv(Cost::of('0.000001'), BigDecimal::of('1'), BigDecimal::of('3'))->toCanonical());
    }

    public function test_rounding_happens_once_not_per_step(): void
    {
        // round6(10 × 1 / 3) × 3 would be 9.999999; the exact product first,
        // then one division, gives 10.
        $this->assertSame('10', Cost::mulDiv(Cost::of('10'), BigDecimal::of('3'), BigDecimal::of('3'))->toCanonical());
        $this->assertSame('3.333333', Cost::quotient(BigDecimal::of('10'), BigDecimal::of('3'))->toCanonical());
    }

    public function test_the_cumulative_return_rule_restores_the_allocation_without_residue(): void
    {
        // An issue projection A = 10 over q = 3; three saleable returns of one.
        $allocation = Cost::of('10');
        $issued = BigDecimal::of('3');
        $restored = [];
        $previous = Cost::of('0');

        foreach (['1', '2', '3'] as $returned) {
            $cumulative = Cost::mulDiv($allocation, BigDecimal::of($returned), $issued);
            $restored[] = $cumulative->minus($previous)->toCanonical();
            $previous = $cumulative;
        }

        $this->assertSame(['3.333333', '3.333334', '3.333333'], $restored);
        $this->assertSame('10', $previous->toCanonical());
    }

    public function test_a_unit_cost_and_a_moving_weighted_average_divide_exact_sums_once(): void
    {
        // A line cost of Rp10.000 for 3 units.
        $this->assertSame('3333.333333', Cost::quotient(BigDecimal::of('10000'), BigDecimal::of('3'))->toCanonical());

        // Value 1000.5 for 2 units, then a receipt of 999.5 for 1 unit: the sum
        // is exact before the one division.
        $value = Cost::of('1000.5')->plus(Cost::of('999.5'));

        $this->assertSame('666.666667', Cost::quotient($value->amount(), BigDecimal::of('3'))->toCanonical());
    }
}
