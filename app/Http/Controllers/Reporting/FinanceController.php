<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Requests\Reporting\FinanceReportRequest;
use App\Queries\Reporting\FinanceReport;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\PeriodKind;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * RP-502, UXP-502 "Laporan keuangan" (ROUTE_CONTRACTS RP-502; NAVIGATION_CONTRACTS UXP-502; INTERACTION_INVENTORY
 * UXI-308 to UXI-312; INTERACTION_CONTRACTS section 3.4; KEY_SCREENS KS-10; BR-COST-34 to BR-COST-38; CAP-20, the
 * Owner's alone; TASK-040 scope item 2): the applied period, today in Asia/Jakarta at the request — the last date
 * the custom range may end on — and the report of FinanceReport, read again by name with "Coba lagi". A read that
 * fails is `report: null`, which the page shows as UXS-22, never zero (TR-TX-07); its cause goes to the log, never
 * to the page.
 */
final class FinanceController
{
    /** The prop of the report, reloaded by name (UXS-22, "Coba lagi"). */
    public const string REPORT = 'report';

    public function show(FinanceReportRequest $request, FinanceReport $report, Clock $clock): Response
    {
        $period = $request->period();

        return Inertia::render('reporting/Finance', [
            'period' => ['kind' => $period['kind']->value, 'from' => $period['from']?->toString(), 'to' => $period['to']?->toString()],
            'today' => BusinessDate::ofInstant($clock->now())->toString(),
            self::REPORT => static fn (): ?array => self::read($report, $period['kind'], $period['from'], $period['to']),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(FinanceReport $report, PeriodKind $kind, ?BusinessDate $from, ?BusinessDate $to): ?array
    {
        try {
            return $report->read($kind, $from, $to);
        } catch (Throwable $failure) {
            report($failure);

            return null;
        }
    }
}
