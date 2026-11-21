<?php

namespace Tests\Feature\Reporting;

use App\Queries\Reporting\FinanceReport;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\PeriodKind;
use App\Support\Time\TestClock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The finance report's figures (AC-040-01 to AC-040-04; COSTING section 9, BR-COST-34 to BR-COST-39; SALES_CASH
 * BR-RPT-01; ADR-008 sections 5 and 6; SCHEMA section 5.8; TECHNICAL_RULES TR-TX-07; suites FIN03, FIN02; TASK-040
 * section 7, L2 report figures on fixtures with corrections and returns): Sales by their business date and refunds
 * and returns by their posting date; cost of goods sold from the allocation projections, its UNKNOWN rows counted
 * and never summed as zero; the margin only when cost is complete and net sales are above zero; corrected figures
 * after a cost correction, also for an earlier period; the periods as half-open Asia/Jakarta ranges; an active
 * mark keeping a period that ends after its cost source incomplete until it is cleared; and every figure from
 * one snapshot, whose time is the as-of time.
 */
final class FinanceReportTest extends DatabaseTestCase
{
    private TestClock $clock;

    private int $session = 0;

    private int $category = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 31 October 2026, 10.00 WIB.
        $this->clock = new TestClock('2026-10-31T03:00:00Z');
        $this->app->instance(Clock::class, $this->clock);
        TokoContoh::shop();
        $this->category = TokoContoh::category();
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
    }

    public function test_the_figures_follow_the_dates_the_projections_and_the_corrections(): void
    {
        $pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '10000']);
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '20000']);
        $penReceipt = TokoContoh::stock($pen, '10', '50000');
        $bookReceipt = TokoContoh::receive([['productId' => $book, 'quantity' => '10', 'cost' => ['status' => 'UNKNOWN']]]);

        // 31 October: 2 pens in cash, Rp20.000, their cost Rp10.000.
        $october = TokoContoh::sell('Rina', $this->session, [['productId' => $pen, 'quantity' => '2']]);

        // 2 November: 3 pens by bank transfer, Rp30.000 (cost Rp15.000); a book in cash, Rp20.000, its cost UNKNOWN.
        $this->clock->set('2026-11-02T03:00:00Z');
        $transfer = TokoContoh::sell('Rina', $this->session, [['productId' => $pen, 'quantity' => '3']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-B1']);
        $unknown = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '1']]);

        // 5 November, the posting date of three corrections (BR-COST-36): October's Sale refunded Rp10.000 by bank
        // transfer with one pen returned saleable (Rp5.000 of cost restored); Rp5.000 of the transfer Sale paid out
        // from the drawer, nothing returned; the book returned saleable without money, its cost UNKNOWN.
        $this->clock->set('2026-11-05T03:00:00Z');
        TokoContoh::correct($october, [['saleLineId' => TokoContoh::saleLinesOf($october)[0] ?? 0, 'refundAmount' => '10000', 'returnedSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-A1']);
        TokoContoh::refundFromDrawer($transfer, $this->session, [['saleLineId' => TokoContoh::saleLinesOf($transfer)[0] ?? 0, 'refundAmount' => '5000']]);
        TokoContoh::correct($unknown, [['saleLineId' => TokoContoh::saleLinesOf($unknown)[0] ?? 0, 'returnedSaleable' => '1']]);

        // 10 November, 10.00 WIB.
        $this->clock->set('2026-11-10T03:00:00Z');
        $month = $this->report(PeriodKind::ThisMonth);
        $this->assertSame('2026-11-10T03:00:00.000000Z', $month['asOf']);
        $this->assertSame(['kind' => 'this_month', 'from' => '2026-11-01', 'to' => '2026-11-10'], $month['period']);
        $this->assertSame(self::figures([
            'grossSales' => '50000', 'refunds' => '15000', 'netSales' => '35000', 'saleCount' => 2, 'paymentsCash' => '20000', 'paymentsTransfer' => '30000',
            'refundPayoutsCash' => '5000', 'refundPayoutsTransfer' => '10000', 'quantitySold' => '4.000', 'quantityReturned' => '2.000',
            // Only the known part, Rp15.000 of issues less Rp5.000 restored, and marked incomplete: the book's issue and its
            // return are UNKNOWN and never counted as zero (BR-COST-38, BR-DOM-08).
            'cogs' => '10000.000000', 'grossProfit' => '25000.000000', 'margin' => null,
        ]), $month['figures']);
        $this->assertSame(['complete' => false, 'unknownLines' => 2, 'markedProducts' => 0, 'lastCorrectionAt' => null], $month['completeness']);
        $this->assertSame(['0', '50000', '0', '0', '-15000', '0', '0', '0', '0', '0'], array_column($month['trend'], 'netSales'));
        $this->assertSame(['2026-11-01', '2026-11-10'], [$month['trend'][0]['date'] ?? null, $month['trend'][9]['date'] ?? null]);
        $this->assertFalse($month['empty']);

        // October has its Sale and none of the refunds posted in November; its cost is complete: margin 50,0 %.
        $last = $this->report(PeriodKind::LastMonth);
        $this->assertSame(['kind' => 'last_month', 'from' => '2026-10-01', 'to' => '2026-10-31'], $last['period']);
        $this->assertSame(self::figures([
            'grossSales' => '20000', 'netSales' => '20000', 'saleCount' => 1, 'paymentsCash' => '20000', 'quantitySold' => '2.000',
            'cogs' => '10000.000000', 'grossProfit' => '10000.000000', 'margin' => '50.0',
        ]), $last['figures']);
        $this->assertTrue($last['completeness']['complete']);
        $this->assertCount(31, $last['trend']);
        $this->assertSame('20000', $last['trend'][30]['netSales'] ?? null);

        // Today has nothing: empty, every figure zero, one point.
        $today = $this->report(PeriodKind::Today);
        $this->assertTrue($today['empty']);
        $this->assertSame(self::figures([]), $today['figures']);
        $this->assertSame([['date' => '2026-11-10', 'netSales' => '0']], $today['trend']);

        // 4 to 10 November: the refunds and returns alone, negative net sales, no margin.
        $week = $this->report(PeriodKind::SevenDays);
        $this->assertSame(['kind' => '7d', 'from' => '2026-11-04', 'to' => '2026-11-10'], $week['period']);
        $this->assertSame(self::figures([
            'refunds' => '15000', 'netSales' => '-15000', 'refundPayoutsCash' => '5000', 'refundPayoutsTransfer' => '10000', 'quantityReturned' => '2.000',
            'cogs' => '-5000.000000', 'grossProfit' => '-10000.000000',
        ]), $week['figures']);
        $this->assertSame(['complete' => false, 'unknownLines' => 1, 'markedProducts' => 0, 'lastCorrectionAt' => null], $week['completeness']);
        $this->assertFalse($week['empty']);

        // The book's cost completed at Rp100.000 for 10: its issue and its return are Rp10.000 each, the month is
        // complete and its margin 25.000 / 35.000 = 71,4 %; the week complete with net sales below zero, no margin.
        $this->clock->set('2026-11-10T04:00:00Z');
        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($bookReceipt)), '100000');
        $month = $this->report(PeriodKind::ThisMonth);
        $this->assertSame(['10000.000000', '25000.000000', '71.4'], [$month['figures']['cogs'], $month['figures']['grossProfit'], $month['figures']['margin']]);
        $this->assertSame(['complete' => true, 'unknownLines' => 0, 'markedProducts' => 0, 'lastCorrectionAt' => '2026-11-10T04:00:00.000000Z'], $month['completeness']);
        $week = $this->report(PeriodKind::SevenDays);
        $this->assertSame(['-15000.000000', '0.000000', null, true], [$week['figures']['cogs'], $week['figures']['grossProfit'], $week['figures']['margin'], $week['completeness']['complete']]);

        // The pens' cost corrected to Rp60.000 for 10 (BR-COST-30): October's Sale costs Rp12.000 and its margin is
        // 40,0 %, an earlier period corrected; November's transfer Sale Rp18.000 and the pen returned restores Rp6.000.
        $this->clock->set('2026-11-10T05:00:00Z');
        TokoContoh::correctCost(TokoContoh::evidenceOf(TokoContoh::movementOf($penReceipt)), '60000');
        $last = $this->report(PeriodKind::LastMonth);
        $this->assertSame(['12000.000000', '8000.000000', '40.0'], [$last['figures']['cogs'], $last['figures']['grossProfit'], $last['figures']['margin']]);
        $month = $this->report(PeriodKind::ThisMonth);
        $this->assertSame(['12000.000000', '23000.000000', '65.7'], [$month['figures']['cogs'], $month['figures']['grossProfit'], $month['figures']['margin']]);
        $this->assertSame('2026-11-10T05:00:00.000000Z', $month['completeness']['lastCorrectionAt']);

        // The sales and payment totals never moved with the cost (BR-COST-38).
        $this->assertSame(['50000', '15000', '35000', 2], [$month['figures']['grossSales'], $month['figures']['refunds'], $month['figures']['netSales'], $month['figures']['saleCount']]);
    }

    public function test_non_sale_losses_and_a_not_saleable_return_are_never_cost_of_goods_sold_and_a_loss_shows_its_margin(): void
    {
        // BR-COST-39, BR-COST-19, BR-COST-34 (TASK-040 review R-03, R-11): on 3 November a negative adjustment of one
        // glue (Rp8.000 of cost) and a manual issue of a product whose cost is UNKNOWN are not Sales: neither is cost of
        // goods sold, nor a line of unknown cost. Two glues sold at Rp5.000 cost 72.000 × 2 / 9 = Rp16.000, a gross loss
        // of Rp6.000 and a margin of −60,0 %; one of them returned not saleable adds to the quantity returned and
        // restores no cost.
        $this->clock->set('2026-11-03T03:00:00Z');
        $glue = TokoContoh::product('LEM-01', ['categoryId' => $this->category, 'name' => 'Lem Kertas', 'sellingPrice' => '5000']);
        $mystery = TokoContoh::product('MIS-01', ['categoryId' => $this->category, 'name' => 'Map Plastik', 'sellingPrice' => '3000']);
        TokoContoh::stock($glue, '10', '80000');
        TokoContoh::receive([['productId' => $mystery, 'quantity' => '5', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::adjust([['productId' => $glue, 'direction' => 'DECREASE', 'quantity' => '1']]);
        TokoContoh::issue([['productId' => $mystery, 'quantity' => '1']]);
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $glue, 'quantity' => '2']]);
        TokoContoh::correct($sale, [['saleLineId' => TokoContoh::saleLinesOf($sale)[0] ?? 0, 'returnedNotSaleable' => '1']]);

        $today = $this->report(PeriodKind::Today);
        $this->assertSame(self::figures([
            'grossSales' => '10000', 'netSales' => '10000', 'saleCount' => 1, 'paymentsCash' => '10000', 'quantitySold' => '2.000', 'quantityReturned' => '1.000',
            'cogs' => '16000.000000', 'grossProfit' => '-6000.000000', 'margin' => '-60.0',
        ]), $today['figures']);
        $this->assertSame(['complete' => true, 'unknownLines' => 0, 'markedProducts' => 0, 'lastCorrectionAt' => null], $today['completeness']);
    }

    public function test_the_periods_are_half_open_asia_jakarta_ranges(): void
    {
        $pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '10000']);
        TokoContoh::stock($pen, '10', '50000');

        // 23.59.59 WIB on 31 October, and 00.00.00 WIB on 1 November.
        $this->clock->set('2026-10-31T16:59:59Z');
        TokoContoh::sell('Rina', $this->session, [['productId' => $pen, 'quantity' => '1']]);
        $this->clock->set('2026-10-31T17:00:00Z');
        TokoContoh::sell('Rina', $this->session, [['productId' => $pen, 'quantity' => '2']]);

        $this->clock->set('2026-11-01T05:00:00Z');
        $this->assertSame(['20000', '2026-11-01', '2026-11-01'], $this->grossAndRange(PeriodKind::Today));
        $this->assertSame(['20000', '2026-11-01', '2026-11-01'], $this->grossAndRange(PeriodKind::ThisMonth));
        $this->assertSame(['10000', '2026-10-01', '2026-10-31'], $this->grossAndRange(PeriodKind::LastMonth));
        $this->assertSame(['30000', '2026-10-26', '2026-11-01'], $this->grossAndRange(PeriodKind::SevenDays));
        $this->assertSame(['10000', '2026-10-31', '2026-10-31'], $this->grossAndRange(PeriodKind::Custom, '2026-10-31', '2026-10-31'));
        $this->assertSame(['30000', '2025-11-01', '2026-11-01'], $this->grossAndRange(PeriodKind::Custom, '2025-11-01', '2026-11-01'));

        // The trend has one point for each day of the period, the custom range of 366 days included.
        $this->assertCount(366, $this->report(PeriodKind::Custom, '2025-11-01', '2026-11-01')['trend']);
    }

    public function test_an_active_mark_keeps_a_period_ending_after_its_source_incomplete_until_it_is_cleared(): void
    {
        // A receipt of 3 November and two Sales of it; its correction refused by the movement cap writes a mark
        // (BR-COST-27; SCHEMA section 5.8).
        $this->clock->set('2026-11-03T03:00:00Z');
        $glue = TokoContoh::product('LEM-01', ['categoryId' => $this->category, 'name' => 'Lem Kertas', 'sellingPrice' => '5000']);
        $evidence = TokoContoh::evidenceOf(TokoContoh::movementOf(TokoContoh::stock($glue, '10', '30000')));
        TokoContoh::sell('Rina', $this->session, [['productId' => $glue, 'quantity' => '2']]);
        TokoContoh::sell('Rina', $this->session, [['productId' => $glue, 'quantity' => '1']]);
        config(['costing.replay.movement_cap' => 1]);
        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $this->correction($evidence));

        // Every cost of the month is known, yet the month is incomplete and has no margin; the sales stand.
        $this->clock->set('2026-11-10T03:00:00Z');
        $month = $this->report(PeriodKind::ThisMonth);
        $this->assertSame(['complete' => false, 'unknownLines' => 0, 'markedProducts' => 1, 'lastCorrectionAt' => null], $month['completeness']);
        $this->assertSame(['15000', '9000.000000', null], [$month['figures']['grossSales'], $month['figures']['cogs'], $month['figures']['margin']]);

        // A period that ends before the marked source was recorded is not covered by it.
        $before = $this->report(PeriodKind::Custom, '2026-10-01', '2026-11-02');
        $this->assertSame(['complete' => true, 'unknownLines' => 0, 'markedProducts' => 0, 'lastCorrectionAt' => null], $before['completeness']);

        // A later successful correction of the same source, Rp45.000 for 10, clears the mark: the month is complete,
        // its Sales cost Rp9.000 and Rp4.500 and its margin is 1.500 / 15.000 = 10,0 %.
        config(['costing.replay.movement_cap' => 10000]);
        $this->assertSame('COMMITTED', $this->correction($evidence)['outcome'] ?? null);
        $month = $this->report(PeriodKind::ThisMonth);
        $this->assertSame([true, 0], [$month['completeness']['complete'], $month['completeness']['markedProducts']]);
        $this->assertSame(['13500.000000', '1500.000000', '10.0'], [$month['figures']['cogs'], $month['figures']['grossProfit'], $month['figures']['margin']]);
    }

    public function test_every_figure_comes_from_one_read_only_repeatable_read_snapshot(): void
    {
        // AC-040-01, TR-TX-07: a projection published by another session while the report reads is seen by no
        // statement of that report — its UNKNOWN row is still counted and summed as nothing — and by the next one.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '20000']);
        TokoContoh::receive([['productId' => $book, 'quantity' => '10', 'cost' => ['status' => 'UNKNOWN']]]);
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $book, 'quantity' => '1']]);
        $allocation = DB::table('issue_cost_allocations')->where('sale_line_id', TokoContoh::saleLinesOf($sale)[0] ?? 0)->value('id');
        $this->assertIsInt($allocation);

        $default = DB::connection()->getName();
        $settings = [];
        $published = false;
        DB::listen(function (QueryExecuted $query) use ($default, $allocation, &$settings, &$published): void {
            if ($query->connectionName !== $default || $published || ! str_contains($query->sql, 'join payments p')) {
                return;
            }

            $published = true;
            $settings = (array) DB::selectOne("select current_setting('transaction_isolation') as isolation, current_setting('transaction_read_only') as read_only");
            $this->migration()->table('issue_cost_allocation_projections')->where('allocation_id', $allocation)->update(['current_status' => 'KNOWN', 'current_amount' => '10000.000000']);
        });

        $during = $this->report(PeriodKind::Today);
        $this->assertTrue($published);
        $this->assertSame(['isolation' => 'repeatable read', 'read_only' => 'on'], $settings);
        $this->assertSame(['complete' => false, 'unknownLines' => 1], array_slice($during['completeness'], 0, 2));
        $this->assertSame('0.000000', $during['figures']['cogs']);
        $this->assertSame(0, DB::connection()->transactionLevel());

        $after = $this->report(PeriodKind::Today);
        $this->assertSame(['complete' => true, 'unknownLines' => 0], array_slice($after['completeness'], 0, 2));
        $this->assertSame('10000.000000', $after['figures']['cogs']);
    }

    /**
     * The report of a period, as RP-502 reads it.
     *
     * @return array{
     *     asOf: string,
     *     period: array{kind: string, from: string, to: string},
     *     figures: array{grossSales: string, refunds: string, netSales: string, saleCount: int, paymentsCash: string, paymentsTransfer: string, refundPayoutsCash: string, refundPayoutsTransfer: string, quantitySold: string, quantityReturned: string, cogs: string, grossProfit: string, margin: string|null},
     *     completeness: array{complete: bool, unknownLines: int, markedProducts: int, lastCorrectionAt: string|null},
     *     trend: list<array{date: string, netSales: string}>,
     *     empty: bool
     * }
     */
    private function report(PeriodKind $kind, ?string $from = null, ?string $to = null): array
    {
        return app(FinanceReport::class)->read($kind, $from === null ? null : BusinessDate::fromString($from), $to === null ? null : BusinessDate::fromString($to));
    }

    /**
     * Gross sales and the dates of a period.
     *
     * @return list<string>
     */
    private function grossAndRange(PeriodKind $kind, ?string $from = null, ?string $to = null): array
    {
        $report = $this->report($kind, $from, $to);

        return [$report['figures']['grossSales'], $report['period']['from'], $report['period']['to']];
    }

    /**
     * The figures of a report: every one zero but those named.
     *
     * @param  array<string, string|int|null>  $named
     * @return array<string, string|int|null>
     */
    private static function figures(array $named): array
    {
        return [...[
            'grossSales' => '0', 'refunds' => '0', 'netSales' => '0', 'saleCount' => 0, 'paymentsCash' => '0', 'paymentsTransfer' => '0',
            'refundPayoutsCash' => '0', 'refundPayoutsTransfer' => '0', 'quantitySold' => '0', 'quantityReturned' => '0',
            'cogs' => '0.000000', 'grossProfit' => '0.000000', 'margin' => null,
        ], ...$named];
    }

    /**
     * RC-15 of a source by the Owner at Rp45.000, its first correction reviewed: its outcome or the code of its refusal.
     *
     * @return array<string, mixed>
     */
    private function correction(int $evidence): array
    {
        return (new UserCommandScenario)->run(['operation' => 'cost_correction.confirm', 'actor' => TokoContoh::person('Hendra')['id'], 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => TokoContoh::recoveryEpoch(), 'expected' => ['correctionNo' => 0], 'payload' => ['evidenceId' => $evidence, 'newCost' => ['amount' => '45000'], 'reason' => 'Nota pemasok']]);
    }
}
