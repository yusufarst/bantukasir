<?php

namespace Tests\Feature\Reporting;

use App\Queries\Reporting\DailySummary;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\InvalidPeriod;
use App\Support\Time\TestClock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The daily summary across midnight (AC-041-01, AC-041-02; SALES_CASH BR-RPT-01 to BR-RPT-03; COSTING BR-COST-36;
 * INTERACTION_CONTRACTS section 3.4; TECHNICAL_RULES TR-TX-07; suite SESSION09; TASK-041 section 7, L2 daily summary
 * fixtures across midnight): two Sales of one session at 23.59 and 00.01 Asia/Jakarta fall on different business dates;
 * a refund falls on its posting date, not its Sale's; a session that crosses midnight is listed once, on its close
 * date, with its full span and its own variance; a return to work the same day is a separate session; a session still
 * open is listed apart until it closes; no session total, opening cash or variance is ever added to a figure; every
 * figure and session comes from one read-only REPEATABLE READ snapshot; a date after today is refused with nothing read.
 */
final class DailySummaryTest extends DatabaseTestCase
{
    private TestClock $clock;

    private int $pen = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 31 October 2026, 20.00 WIB.
        $this->clock = new TestClock('2026-10-31T13:00:00Z');
        $this->app->instance(Clock::class, $this->clock);
        TokoContoh::shop();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'name' => 'Pulpen Hitam', 'sellingPrice' => '10000']);
        TokoContoh::stock($this->pen, '10', '50000');
    }

    public function test_a_day_counts_its_own_sales_and_refunds_and_lists_each_session_once(): void
    {
        $first = TokoContoh::register('LACI-1', 'Laci 1');
        $second = TokoContoh::register('LACI-2', 'Laci 2');

        // 20.00 WIB on 31 October: Rina opens her session with Rp100.000 of opening cash.
        $night = TokoContoh::openSession('Rina', $first, '100000');

        // AC-041-02: 23.59 WIB on 31 October, one pen in cash; 00.01 WIB on 1 November, two pens by bank transfer.
        $this->clock->set('2026-10-31T16:59:00Z');
        $late = TokoContoh::sell('Rina', $night, [['productId' => $this->pen, 'quantity' => '1']]);
        $this->clock->set('2026-10-31T17:01:00Z');
        TokoContoh::sell('Rina', $night, [['productId' => $this->pen, 'quantity' => '2']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-01']);
        $this->assertSame(['2026-10-31', '2026-11-01'], DB::table('sales')->orderBy('id')->pluck('business_date')->all());

        // 09.00 WIB on 1 November: her session closes Rp2.000 short — it crossed midnight.
        $this->clock->set('2026-11-01T02:00:00Z');
        TokoContoh::closeSession($night, '108000');

        // 10.00 WIB: the Owner opens a session and sells one pen in cash; 11.30 to 11.45 WIB Rina works again the same day,
        // a session of its own; 11.00 WIB the 23.59 Sale is refunded Rp5.000 by bank transfer, its pen returned saleable.
        $this->clock->set('2026-11-01T03:00:00Z');
        $owner = TokoContoh::openSession('Hendra', $second, '50000');
        TokoContoh::sell('Hendra', $owner, [['productId' => $this->pen, 'quantity' => '1']]);
        $this->clock->set('2026-11-01T04:00:00Z');
        TokoContoh::correct($late, [['saleLineId' => TokoContoh::saleLinesOf($late)[0] ?? 0, 'refundAmount' => '5000', 'returnedSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-R1']);
        $this->clock->set('2026-11-01T04:30:00Z');
        $again = TokoContoh::openSession('Rina', $first, '20000');
        $this->clock->set('2026-11-01T04:45:00Z');
        TokoContoh::closeSession($again);

        // 12.00 WIB on 1 November.
        $this->clock->set('2026-11-01T05:00:00Z');

        // 31 October holds the 23.59 Sale alone, and no session: the one that crossed midnight closed on 1 November.
        $october = $this->summary('2026-10-31');
        $this->assertSame('2026-11-01T05:00:00.000000Z', $october['asOf']);
        $this->assertSame('2026-10-31', $october['date']);
        $this->assertSame(self::figures([
            'grossSales' => '10000', 'netSales' => '10000', 'saleCount' => 1, 'paymentsCash' => '10000', 'quantitySold' => '1.000',
            'cogs' => '5000.000000', 'grossProfit' => '5000.000000', 'margin' => '50.0',
        ]), $october['figures']);
        $this->assertSame([[], []], [$october['sessions'], $october['active']]);
        $this->assertFalse($october['empty']);

        // 1 November: the 00.01 Sale and the Owner's, the refund of the 23.59 Sale on its posting date; no opening cash,
        // session total or variance in any figure (BR-RPT-01): two Sales, Rp30.000, not three.
        $november = $this->summary('2026-11-01');
        $this->assertSame(self::figures([
            'grossSales' => '30000', 'refunds' => '5000', 'netSales' => '25000', 'saleCount' => 2, 'paymentsCash' => '10000', 'paymentsTransfer' => '20000',
            'refundPayoutsTransfer' => '5000', 'quantitySold' => '3.000', 'quantityReturned' => '1.000',
            'cogs' => '10000.000000', 'grossProfit' => '15000.000000', 'margin' => '60.0',
        ]), $november['figures']);
        $this->assertSame(['complete' => true, 'unknownLines' => 0, 'markedProducts' => 0, 'lastCorrectionAt' => null], $november['completeness']);

        // AC-041-01, BR-RPT-02: the night session once, with its full span from 31 October and its own variance; Rina's
        // return to work a session of its own; the Owner's, still open, apart.
        $this->assertSame([
            self::session($night, 'CLOSED', 'LACI-1', 'Laci 1', 'Rina', '2026-10-31T13:00:00.000000Z', '2026-11-01T02:00:00.000000Z', '-2000', true),
            self::session($again, 'CLOSED', 'LACI-1', 'Laci 1', 'Rina', '2026-11-01T04:30:00.000000Z', '2026-11-01T04:45:00.000000Z', '0', false),
        ], $november['sessions']);
        $this->assertSame([self::session($owner, 'OPEN', 'LACI-2', 'Laci 2', 'Hendra', '2026-11-01T03:00:00.000000Z', null, null, false)], $november['active']);

        // 2 November, 10.00 WIB: nothing sold yet; the Owner's session, opened the day before, is still open, once,
        // marked as crossing midnight; the day before keeps its figures.
        $this->clock->set('2026-11-02T03:00:00Z');
        $next = $this->summary('2026-11-02');
        $this->assertTrue($next['empty']);
        $this->assertSame(self::figures([]), $next['figures']);
        $this->assertSame([], $next['sessions']);
        $this->assertSame([self::session($owner, 'OPEN', 'LACI-2', 'Laci 2', 'Hendra', '2026-11-01T03:00:00.000000Z', null, null, true)], $next['active']);
        $this->assertSame('25000', $this->summary('2026-11-01')['figures']['netSales']);
    }

    public function test_the_summary_is_one_read_only_snapshot_and_a_date_after_today_reads_nothing(): void
    {
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        TokoContoh::sell('Rina', $session, [['productId' => $this->pen, 'quantity' => '1']]);

        // The figures and the sessions are read inside one read-only REPEATABLE READ transaction (TR-TX-07).
        $default = DB::connection()->getName();
        $settings = [];
        $seen = false;
        DB::listen(static function (QueryExecuted $query) use ($default, &$settings, &$seen): void {
            if ($query->connectionName !== $default || $seen || ! str_contains($query->sql, 'join payments p')) {
                return;
            }

            $seen = true;
            $settings = (array) DB::selectOne("select current_setting('transaction_isolation') as isolation, current_setting('transaction_read_only') as read_only");
        });

        $during = $this->summary('2026-10-31');
        $this->assertTrue($seen);
        $this->assertSame(['isolation' => 'repeatable read', 'read_only' => 'on'], $settings);
        $this->assertSame([$session], array_column($during['active'], 'id'));
        $this->assertSame(0, DB::connection()->transactionLevel());

        // 1 November is after today at 20.00 WIB on 31 October: refused, and no figure is read.
        $reads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$reads): void {
            if (str_contains($query->sql, 'from sales s') || str_contains($query->sql, 'cash_sessions')) {
                $reads++;
            }
        });

        try {
            $this->summary('2026-11-01');
            $this->fail('A date after today is refused.');
        } catch (InvalidPeriod) {
            $this->assertSame(0, $reads);
            $this->assertSame(0, DB::connection()->transactionLevel());
        }
    }

    /**
     * The summary of a date, as RP-501 reads it.
     *
     * @return array{
     *     asOf: string,
     *     date: string,
     *     figures: array{grossSales: string, refunds: string, netSales: string, saleCount: int, paymentsCash: string, paymentsTransfer: string, refundPayoutsCash: string, refundPayoutsTransfer: string, quantitySold: string, quantityReturned: string, cogs: string, grossProfit: string, margin: string|null},
     *     completeness: array{complete: bool, unknownLines: int, markedProducts: int, lastCorrectionAt: string|null},
     *     empty: bool,
     *     sessions: list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>,
     *     active: list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>
     * }
     */
    private function summary(string $date): array
    {
        return app(DailySummary::class)->read(BusinessDate::fromString($date));
    }

    /**
     * A session as the summary lists it.
     *
     * @return array<string, mixed>
     */
    private static function session(int $id, string $status, string $code, string $label, string $responsible, string $openedAt, ?string $closedAt, ?string $variance, bool $crossesMidnight): array
    {
        $number = DB::table('cash_sessions')->where('id', $id)->value('session_number');

        return [
            'id' => $id,
            'number' => is_string($number) ? $number : '',
            'status' => $status,
            'registerCode' => $code,
            'registerLabel' => $label,
            'responsible' => $responsible,
            'openedAt' => $openedAt,
            'closedAt' => $closedAt,
            'variance' => $variance,
            'crossesMidnight' => $crossesMidnight,
        ];
    }

    /**
     * The figures of a summary: every one zero but those named.
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
}
