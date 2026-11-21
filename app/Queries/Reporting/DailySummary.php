<?php

namespace App\Queries\Reporting;

use App\Domain\Cash\Sessions;
use App\Queries\Inventory\Column as Read;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\InvalidPeriod;
use App\Support\Time\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * RP-501, "Rekap harian" (ROUTE_CONTRACTS RP-501; INTERACTION_CONTRACTS section 3.4; SALES_CASH section 9,
 * BR-RPT-01 to BR-RPT-04; COSTING section 9; TECHNICAL_RULES TR-TX-07; CAP-22; TASK-041 scope item 2): the
 * Owner's summary of one Asia/Jakarta business day, read in one read-only REPEATABLE READ transaction of its own.
 * Its first statement takes the snapshot; the clock read after it is the as-of time, and a date after today at
 * that time is refused (InvalidPeriod), so that nothing is read for it.
 *
 * - The figures are the finance report's of that one day, computed once from the source records in the same
 *   snapshot (FinanceReport::within): Sales, their payments, quantities and issue-cost allocations by each Sale's
 *   business date; refunds and returns by their posting date; cost of goods sold and gross profit from the latest
 *   cost evidence. No session snapshot total is added on top, and opening cash and variance are never sales or
 *   profit (BR-RPT-01).
 * - The sessions, each listed once with its own variance (BR-RPT-02): a CLOSED session on the date of its close,
 *   with its full span from its opening — the day before, for one that crossed midnight — and its register as its
 *   report froze it; an OPEN session in the active section, until it closes, when it was opened before the end of
 *   the date. A session's variance is its own count's and is never added to a figure. Each list is bounded.
 */
final class DailySummary
{
    /** The most sessions of either list; a shop's day holds far fewer (TASK-048 measures the budgets). */
    public const int SESSIONS_BOUND = 500;

    private const string OPENED_AT = "to_char(s.opened_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as opened_at";

    private const string CLOSED_AT = "to_char(c.closed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as closed_at";

    private const string INSTANT_FORMAT = 'Y-m-d H:i:s.uP';

    public function __construct(
        private readonly Clock $clock,
        private readonly FinanceReport $report,
    ) {}

    /**
     * @return array{
     *     asOf: string,
     *     date: string,
     *     figures: array{grossSales: string, refunds: string, netSales: string, saleCount: int, paymentsCash: string, paymentsTransfer: string, refundPayoutsCash: string, refundPayoutsTransfer: string, quantitySold: string, quantityReturned: string, cogs: string, grossProfit: string, margin: string|null},
     *     completeness: array{complete: bool, unknownLines: int, markedProducts: int, lastCorrectionAt: string|null},
     *     empty: bool,
     *     sessions: list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>,
     *     active: list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>
     * }
     *
     * @throws InvalidPeriod a date after today at the snapshot
     */
    public function read(BusinessDate $date): array
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
            $day = Period::custom($date, $date, $now);
            $report = $this->report->within($connection, $day, $now);

            return [
                'asOf' => $report['asOf'],
                'date' => $date->toString(),
                'figures' => $report['figures'],
                'completeness' => $report['completeness'],
                'empty' => $report['empty'],
                'sessions' => self::closedOn($connection, $day, $now),
                'active' => self::openBefore($connection, $day, $now),
            ];
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    /**
     * The sessions whose close falls on the day, by opening time, each with its count's variance.
     *
     * @return list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>
     */
    private static function closedOn(ConnectionInterface $connection, Period $day, CarbonImmutable $now): array
    {
        $openedAt = self::OPENED_AT;
        $closedAt = self::CLOSED_AT;

        return self::rows($connection->select(<<<SQL
            select s.id, s.session_number, s.status, s.responsible_display_name,
                   coalesce(rep.register_code, r.code) as register_code, coalesce(rep.register_label, r.label) as register_label,
                   c.variance::text as variance, {$closedAt}, {$openedAt}
            from cash_session_closes c
            join cash_sessions s on s.id = c.session_id
            join cash_registers r on r.id = s.register_id
            left join cash_session_reports rep on rep.session_id = s.id
            where c.closed_at >= ?::timestamptz and c.closed_at < ?::timestamptz
            order by s.opened_at, s.id
            limit ?
            SQL, [$day->start->utc()->format(self::INSTANT_FORMAT), $day->end->utc()->format(self::INSTANT_FORMAT), self::SESSIONS_BOUND]), $now);
    }

    /**
     * The sessions still OPEN at the snapshot that were opened before the end of the day, by opening time.
     *
     * @return list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>
     */
    private static function openBefore(ConnectionInterface $connection, Period $day, CarbonImmutable $now): array
    {
        $openedAt = self::OPENED_AT;

        return self::rows($connection->select(<<<SQL
            select s.id, s.session_number, s.status, s.responsible_display_name, r.code as register_code, r.label as register_label,
                   null::text as variance, null::text as closed_at, {$openedAt}
            from cash_sessions s
            join cash_registers r on r.id = s.register_id
            where s.status = ? and s.opened_at < ?::timestamptz
            order by s.opened_at, s.id
            limit ?
            SQL, [Sessions::OPEN, $day->end->utc()->format(self::INSTANT_FORMAT), self::SESSIONS_BOUND]), $now);
    }

    /**
     * The rows of a list; a session crosses midnight when its opening and its close — for an OPEN one, the
     * snapshot — fall on different Asia/Jakarta business dates.
     *
     * @param  array<array-key, mixed>  $rows
     * @return list<array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null, crossesMidnight: bool}>
     */
    private static function rows(array $rows, CarbonImmutable $now): array
    {
        $sessions = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $openedAt = Read::text($values, 'opened_at');
            $closedAt = Read::nullableText($values, 'closed_at');
            $until = $closedAt === null ? $now : CarbonImmutable::parse($closedAt);
            $sessions[] = [
                'id' => Read::int($values, 'id'),
                'number' => Read::text($values, 'session_number'),
                'status' => Read::text($values, 'status'),
                'registerCode' => Read::text($values, 'register_code'),
                'registerLabel' => Read::text($values, 'register_label'),
                'responsible' => Read::text($values, 'responsible_display_name'),
                'openedAt' => $openedAt,
                'closedAt' => $closedAt,
                'variance' => Read::nullableText($values, 'variance'),
                'crossesMidnight' => BusinessDate::ofInstant(CarbonImmutable::parse($openedAt))->compareTo(BusinessDate::ofInstant($until)) !== 0,
            ];
        }

        return $sessions;
    }
}
