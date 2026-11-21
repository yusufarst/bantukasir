<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Requests\Reporting\DailySummaryRequest;
use App\Queries\Reporting\DailySummary;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * RP-501, UXP-501 "Rekap harian" (ROUTE_CONTRACTS RP-501; NAVIGATION_CONTRACTS UXP-501; INTERACTION_INVENTORY
 * UXI-306 and UXI-307; INTERACTION_CONTRACTS section 3.4; BR-RPT-01 to BR-RPT-04; CAP-22, the Owner's alone;
 * TASK-041 scope item 2): the applied date, today in Asia/Jakarta at the request — the last date the date control
 * may choose — and the summary of DailySummary, read again by name with "Coba lagi". A read that fails is
 * `summary: null`, which the page shows as UXS-22, never zero (TR-TX-07); its cause goes to the log, never to the
 * page.
 */
final class DailyController
{
    /** The prop of the summary, reloaded by name (UXS-22, "Coba lagi"). */
    public const string SUMMARY = 'summary';

    public function show(DailySummaryRequest $request, DailySummary $summary, Clock $clock): Response
    {
        $date = $request->businessDate();

        return Inertia::render('reporting/Daily', [
            'date' => $date->toString(),
            'today' => BusinessDate::ofInstant($clock->now())->toString(),
            self::SUMMARY => static fn (): ?array => self::read($summary, $date),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(DailySummary $summary, BusinessDate $date): ?array
    {
        try {
            return $summary->read($date);
        } catch (Throwable $failure) {
            report($failure);

            return null;
        }
    }
}
