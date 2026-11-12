<?php

namespace Tests\Unit\Cash;

use App\Domain\Cash\CashCount;
use App\Domain\Cash\SessionSales;
use App\Support\Numbers\Money;
use App\Support\Numbers\NumberOutOfRange;
use PHPUnit\Framework\TestCase;

/**
 * The cash arithmetic of a session, with no database (AC-030-01; SALES_CASH section 6.1, BR-CASH-12,
 * BR-CASH-13, BR-CASH-20; SCHEMA G-05; D-035, D-077; suites SESSION01 and FIN01; TASK-030 section 7,
 * L1): expected cash is the opening cash plus the applied CASH minus the drawer-funded refunds, never
 * the BANK_TRANSFER; the variance is the physical cash minus it, with its sign; every amount exact on
 * whole rupiah and within the range of a stored amount; the running figures of an OPEN session.
 */
final class CashCountTest extends TestCase
{
    public function test_the_example_of_ac_030_01_gives_expected_rp120000_and_a_variance_of_minus_rp2000(): void
    {
        // Opening Rp20.000, CASH Rp100.000, BANK_TRANSFER Rp50.000, Rp118.000 counted.
        $sales = new SessionSales(2, Money::of('150000'), Money::of('100000'), Money::of('50000'), [1, 2], [3, 4]);
        $expected = CashCount::expected(Money::of('20000'), $sales->cashApplied, Money::zero());
        $count = CashCount::of($expected, Money::of('118000'));

        $this->assertSame(['120000', '118000', '-2000'], [$count->expected->toCanonical(), $count->physical->toCanonical(), $count->variance->toCanonical()]);

        // The running figures, BANK_TRANSFER apart and never in expected cash, the refunds Rp0 until
        // TASK-034 pays one; the opening cash is no sale.
        $this->assertSame(
            ['openingCash' => '20000', 'saleCount' => 2, 'saleValue' => '150000', 'cashPayments' => '100000', 'transferPayments' => '50000', 'drawerRefunds' => '0', 'expectedCash' => '120000'],
            $sales->figures(Money::of('20000')),
        );
    }

    public function test_a_count_over_or_even_gives_its_variance_and_a_refund_lowers_expected_cash(): void
    {
        // BR-CASH-12: over is positive, even is zero; D-035: a drawer-funded refund comes out of the drawer.
        $this->assertSame('500', CashCount::of(Money::of('120000'), Money::of('120500'))->variance->toCanonical());
        $this->assertSame('0', CashCount::of(Money::of('0'), Money::of('0'))->variance->toCanonical());
        $this->assertSame('-120000', CashCount::of(Money::of('120000'), Money::zero())->variance->toCanonical());
        $this->assertSame('70000', CashCount::expected(Money::of('20000'), Money::of('100000'), Money::of('50000'))->toCanonical());
        $this->assertSame('-1', CashCount::due(Money::zero(), Money::zero(), Money::of('1'))->toCanonical());
    }

    public function test_an_amount_outside_the_range_of_a_stored_amount_is_refused(): void
    {
        // TECHNICAL_RULES section 11.1: expected cash or a count above Rp999.999.999.999 refuses the close.
        try {
            CashCount::expected(Money::of('999999999999'), Money::of('1'), Money::zero());
            $this->fail('Expected cash above the range is refused.');
        } catch (NumberOutOfRange) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(NumberOutOfRange::class);
        CashCount::of(Money::of('0'), Money::of('1000000000000'));
    }
}
