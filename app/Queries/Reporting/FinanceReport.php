<?php

namespace App\Queries\Reporting;

use App\Domain\Sales\PaymentMethods;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Cost;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\Period;
use App\Support\Time\PeriodKind;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * RP-502, "Laporan keuangan" (ROUTE_CONTRACTS RP-502; INTERACTION_CONTRACTS section 3.4; COSTING section 9,
 * BR-COST-34 to BR-COST-41; SALES_CASH BR-RPT-01; ARC-24; ADR-008 sections 5 and 6; TECHNICAL_RULES TR-TX-07;
 * CAP-20; TASK-040 scope item 1): the Owner's finance report of one period, read in one read-only REPEATABLE
 * READ transaction of its own. Its first statement takes the snapshot, and the clock read after it is the
 * as-of time, from which the period is computed (Period, BR-COST-37), so that every figure, the trend and the
 * completeness come from the same committed state.
 *
 * - Sales, their payments, their quantities and their issue-cost allocations by each Sale's business date;
 *   refunds, their payouts and returns by the correction's posting date (BR-COST-36, BR-RPT-01).
 * - Cost from the allocation projections, never the facts recorded at commit (ADR-008 section 6): the issue
 *   projections of the period's Sale lines less the return projections of its saleable returns
 *   (BR-COST-35). Each cost total is summed in the same statement as the count of its rows whose cost is
 *   not KNOWN, so that a sum that skipped an UNKNOWN row never passes as complete (BR-DOM-08).
 * - Complete only when no such row is counted and no product carries an active "reporting incomplete" mark
 *   whose cost source was recorded before the period's end (SCHEMA section 5.8, BR-COST-27). The margin, a
 *   ratio rounded once to one decimal (ADR-008 section 5), only when complete and net sales are above zero;
 *   never net profit (BR-COST-41).
 * - The trend: one point for every business day of the period, net sales of that day.
 *
 * Opening cash, variance, manual issues and adjustments are never read here (BR-COST-39).
 */
final class FinanceReport
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{
     *     asOf: string,
     *     period: array{kind: string, from: string, to: string},
     *     figures: array{grossSales: string, refunds: string, netSales: string, saleCount: int, paymentsCash: string, paymentsTransfer: string, refundPayoutsCash: string, refundPayoutsTransfer: string, quantitySold: string, quantityReturned: string, cogs: string, grossProfit: string, margin: string|null},
     *     completeness: array{complete: bool, unknownLines: int, markedProducts: int, lastCorrectionAt: string|null},
     *     trend: list<array{date: string, netSales: string}>,
     *     empty: bool
     * }
     */
    public function read(PeriodKind $kind, ?BusinessDate $from = null, ?BusinessDate $to = null): array
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('A report is a read-only transaction of its own (TR-TX-07).');
        }

        $connection->beginTransaction();

        try {
            $connection->statement('set transaction isolation level repeatable read, read only');
            // The first query takes the snapshot of the transaction; the clock read after it is the as-of time.
            $connection->select('select 1');
            $now = $this->clock->now()->utc();

            return $this->figures($connection, Period::of($kind, $now, $from, $to), $now);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    /**
     * @return array{
     *     asOf: string,
     *     period: array{kind: string, from: string, to: string},
     *     figures: array{grossSales: string, refunds: string, netSales: string, saleCount: int, paymentsCash: string, paymentsTransfer: string, refundPayoutsCash: string, refundPayoutsTransfer: string, quantitySold: string, quantityReturned: string, cogs: string, grossProfit: string, margin: string|null},
     *     completeness: array{complete: bool, unknownLines: int, markedProducts: int, lastCorrectionAt: string|null},
     *     trend: list<array{date: string, netSales: string}>,
     *     empty: bool
     * }
     */
    private function figures(ConnectionInterface $connection, Period $period, CarbonImmutable $now): array
    {
        $first = $period->firstDate->toString();
        $last = $period->lastDate->toString();

        $sales = self::row($connection->select(<<<'SQL'
            select count(*) as sale_count,
                   coalesce(sum(s.total_amount), 0)::text as gross_sales,
                   coalesce(sum(p.applied_amount) filter (where p.method = ?), 0)::text as payments_cash,
                   coalesce(sum(p.applied_amount) filter (where p.method = ?), 0)::text as payments_transfer
            from sales s
            join payments p on p.sale_id = s.id
            where s.business_date between ?::date and ?::date
            SQL, [PaymentMethods::CASH, PaymentMethods::BANK_TRANSFER, $first, $last]));

        // Every Sale line with its SALE_ISSUE projection; a line without one counts as not KNOWN.
        $issues = self::row($connection->select(<<<'SQL'
            select coalesce(sum(l.quantity), 0)::text as quantity_sold,
                   coalesce(sum(j.current_amount) filter (where j.current_status = 'KNOWN'), 0)::text as issue_cost,
                   count(*) filter (where j.current_status is distinct from 'KNOWN') as unknown_lines
            from sales s
            join sale_lines l on l.sale_id = s.id
            left join issue_cost_allocations a on a.sale_line_id = l.id and a.issue_type = 'SALE_ISSUE'
            left join issue_cost_allocation_projections j on j.allocation_id = a.id
            where s.business_date between ?::date and ?::date
            SQL, [$first, $last]));

        $refunds = self::row($connection->select(<<<'SQL'
            select (select count(*) from sale_corrections k where k.business_date between ?::date and ?::date) as corrections,
                   coalesce(sum(r.amount), 0)::text as refunds,
                   coalesce(sum(r.amount) filter (where r.payout_method = ?), 0)::text as refund_payouts_cash,
                   coalesce(sum(r.amount) filter (where r.payout_method = ?), 0)::text as refund_payouts_transfer
            from sale_corrections c
            join refunds r on r.correction_id = c.id
            where c.business_date between ?::date and ?::date
            SQL, [$first, $last, PaymentMethods::CASH, PaymentMethods::BANK_TRANSFER, $first, $last]));

        // Every return line; a saleable one with its return projection, which a saleable line without one
        // counts as not KNOWN. A not-saleable return restores no cost (BR-COST-19).
        $returns = self::row($connection->select(<<<'SQL'
            select coalesce(sum(l.quantity), 0)::text as quantity_returned,
                   coalesce(sum(j.current_amount) filter (where l.condition = 'SALEABLE' and j.current_status = 'KNOWN'), 0)::text as return_cost,
                   count(*) filter (where l.condition = 'SALEABLE' and j.current_status is distinct from 'KNOWN') as unknown_lines
            from sale_corrections c
            join return_lines l on l.correction_id = c.id
            left join return_cost_allocations a on a.return_line_id = l.id
            left join return_cost_allocation_projections j on j.allocation_id = a.id
            where c.business_date between ?::date and ?::date
            SQL, [$first, $last]));

        // The products whose reporting an active mark keeps incomplete for a period that ends after the
        // marked cost source was recorded (SCHEMA section 5.8).
        $marks = self::row($connection->select(<<<'SQL'
            select count(distinct k.product_id) as marked_products
            from cost_incomplete_marks k
            join stock_movements m on m.id = k.evidence_movement_id
            where m.recorded_at < ?::timestamptz
              and not exists (select 1 from cost_incomplete_mark_clearances x where x.mark_id = k.id)
            SQL, [$period->end->utc()->format('Y-m-d H:i:s.uP')]));

        $correction = self::row($connection->select(<<<'SQL'
            select to_char(max(recorded_at) at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') as last_correction_at
            from cost_corrections
            SQL));

        $trend = [];

        foreach ($connection->select(<<<'SQL'
            select to_char(d.day, 'YYYY-MM-DD') as day,
                   (coalesce(g.amount, 0) - coalesce(f.amount, 0))::text as net_sales
            from (select ?::date + i as day from generate_series(0, ?::int) as i) d
            left join (select s.business_date, sum(s.total_amount) as amount
                       from sales s
                       where s.business_date between ?::date and ?::date
                       group by s.business_date) g on g.business_date = d.day
            left join (select c.business_date, sum(r.amount) as amount
                       from sale_corrections c
                       join refunds r on r.correction_id = c.id
                       where c.business_date between ?::date and ?::date
                       group by c.business_date) f on f.business_date = d.day
            order by d.day
            SQL, [$first, $period->firstDate->daysUntil($period->lastDate), $first, $last, $first, $last]) as $day) {
            $values = (array) $day;
            $trend[] = ['date' => Read::text($values, 'day'), 'netSales' => Read::text($values, 'net_sales')];
        }

        $gross = BigDecimal::of(Read::text($sales, 'gross_sales'));
        $refunded = BigDecimal::of(Read::text($refunds, 'refunds'));
        $net = $gross->minus($refunded);
        $cogs = BigDecimal::of(Read::text($issues, 'issue_cost'))->minus(BigDecimal::of(Read::text($returns, 'return_cost')))->toScale(Cost::SCALE);
        $profit = $net->toScale(Cost::SCALE)->minus($cogs);
        $unknownLines = Read::int($issues, 'unknown_lines') + Read::int($returns, 'unknown_lines');
        $marked = Read::int($marks, 'marked_products');
        $complete = $unknownLines === 0 && $marked === 0;
        $saleCount = Read::int($sales, 'sale_count');

        return [
            'asOf' => $now->format('Y-m-d\TH:i:s.u\Z'),
            'period' => ['kind' => $period->kind->value, 'from' => $first, 'to' => $last],
            'figures' => [
                'grossSales' => (string) $gross,
                'refunds' => (string) $refunded,
                'netSales' => (string) $net,
                'saleCount' => $saleCount,
                'paymentsCash' => Read::text($sales, 'payments_cash'),
                'paymentsTransfer' => Read::text($sales, 'payments_transfer'),
                'refundPayoutsCash' => Read::text($refunds, 'refund_payouts_cash'),
                'refundPayoutsTransfer' => Read::text($refunds, 'refund_payouts_transfer'),
                'quantitySold' => Read::text($issues, 'quantity_sold'),
                'quantityReturned' => Read::text($returns, 'quantity_returned'),
                'cogs' => (string) $cogs,
                'grossProfit' => (string) $profit,
                // Gross profit over net sales, in percent, rounded half-up once to one decimal.
                'margin' => $complete && $net->isPositive() ? (string) $profit->multipliedBy(100)->dividedBy($net, 1, RoundingMode::HalfUp) : null,
            ],
            'completeness' => [
                'complete' => $complete,
                'unknownLines' => $unknownLines,
                'markedProducts' => $marked,
                'lastCorrectionAt' => Read::nullableText($correction, 'last_correction_at'),
            ],
            'trend' => $trend,
            'empty' => $saleCount === 0 && Read::int($refunds, 'corrections') === 0,
        ];
    }

    /**
     * The one row of an aggregate statement.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array<array-key, mixed>
     */
    private static function row(array $rows): array
    {
        $row = $rows[0] ?? null;

        return is_object($row) ? (array) $row : throw new LogicException('An aggregate statement gives one row.');
    }
}
