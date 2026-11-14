<?php

namespace Tests\Unit\Cash;

use App\Domain\Cash\CashCount;
use App\Domain\Cash\DrawerRefunds;
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

        // The running figures, BANK_TRANSFER apart and never in expected cash, no drawer-funded refund; the
        // opening cash is no sale.
        $this->assertSame(
            ['openingCash' => '20000', 'saleCount' => 2, 'saleValue' => '150000', 'cashPayments' => '100000', 'transferPayments' => '50000', 'drawerRefunds' => '0', 'expectedCash' => '120000'],
            $sales->figures(Money::of('20000'), DrawerRefunds::of([])),
        );
    }

    public function test_the_session04_example_with_a_drawer_funded_refund_gives_expected_rp110000_and_a_variance_of_minus_rp2000(): void
    {
        // AC-034-06; BR-CASH-12, SALES_CASH section 6.1's example under D-035; G-05: a drawer-funded refund of
        // Rp10.000 comes out of the drawer, so expected cash is Rp110.000 and Rp108.000 counted is −Rp2.000;
        // each refund is kept separately, in its order, with its key for the report's sources.
        $sales = new SessionSales(2, Money::of('150000'), Money::of('100000'), Money::of('50000'), [1, 2], [3, 4]);
        $refunds = DrawerRefunds::of([
            ['id' => 9, 'saleNumber' => '20261111-000001', 'reference' => '20261111-000001/1', 'amount' => Money::of('6000'), 'recordedAt' => '2026-11-11T03:00:00.000000Z', 'owner' => 'Hendra'],
            ['id' => 11, 'saleNumber' => '20261111-000002', 'reference' => '20261111-000002/2', 'amount' => Money::of('4000'), 'recordedAt' => '2026-11-11T04:00:00.000000Z', 'owner' => 'Hendra'],
        ]);
        $count = CashCount::of(CashCount::expected(Money::of('20000'), $sales->cashApplied, $refunds->total), Money::of('108000'));

        $this->assertSame(['110000', '108000', '-2000'], [$count->expected->toCanonical(), $count->physical->toCanonical(), $count->variance->toCanonical()]);
        $this->assertSame(
            ['openingCash' => '20000', 'saleCount' => 2, 'saleValue' => '150000', 'cashPayments' => '100000', 'transferPayments' => '50000', 'drawerRefunds' => '10000', 'expectedCash' => '110000'],
            $sales->figures(Money::of('20000'), $refunds),
        );
        $this->assertSame([9, 11], $refunds->ids);
        $this->assertSame(['20261111-000001/1', '20261111-000002/2'], array_column($refunds->entries, 'reference'));
        $this->assertSame([['saleNumber' => '20261111-000001', 'amount' => '6000', 'recordedAt' => '2026-11-11T03:00:00.000000Z'], ['saleNumber' => '20261111-000002', 'amount' => '4000', 'recordedAt' => '2026-11-11T04:00:00.000000Z']], $refunds->previewRows());
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
