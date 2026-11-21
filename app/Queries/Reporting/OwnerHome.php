<?php

namespace App\Queries\Reporting;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Domain\Cash\Sessions;
use App\Domain\Inventory\OnboardingState;
use App\Models\User;
use App\Queries\Inventory\Column as Read;
use App\Queries\Inventory\Incidents;
use App\Queries\Inventory\StockAttention;
use App\Queries\Operations\SystemStatus;
use App\Support\Time\PeriodKind;
use Illuminate\Support\Facades\DB;

/**
 * The panels of RP-007, UXP-007 "Beranda" (ROUTE_CONTRACTS RP-007; KEY_SCREENS KS-02; NAVIGATION_CONTRACTS
 * UXP-007; INTERACTION_INVENTORY UXI-059 to UXI-065; COPY_DECK section 6.8; INFORMATION_ARCHITECTURE UXP-007;
 * OPERATIONS_RUNBOOK section 5; TASK-041 scope item 1), each read on its own so that each fails on its own:
 *
 * - Today: the four figures that KS-02 shows, from the finance report of today in its own snapshot, with its as-of
 *   time (FinanceReport, BR-RPT-01): net sales, the number of Sales, cash and bank-transfer payments. No cost.
 * - The open cash sessions, by opening time, each with its register, responsible user and opening time; bounded.
 * - The attention items, each only for a holder of its capability and each by the one definition of the page it
 *   leads to: the Habis and Menipis products of the stock badge (StockAttention; UXI-061, CAP-14 in full), the
 *   cost sources of the worklist (CostWorklist; UXI-062, CAP-20), the open reconciliation incidents (UXI-063,
 *   CAP-18), the system status with an item failed or unverified (SystemStatus::attention; UXI-064, CAP-32) and
 *   the onboarding freeze while onboarding is ACTIVE (UXI-065, CAP-15). A read of any of them that fails fails the
 *   panel, never a missing item or a zero (BR-DOM-08).
 *
 * It reads only: no lock, no write (STK-12).
 */
final class OwnerHome
{
    /** The most open sessions read: one per register and per user at most (BR-CASH-06), the bound of OpenSessions. */
    public const int OPEN_SESSIONS_BOUND = 500;

    private const string OPENED_AT = "to_char(s.opened_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as opened_at";

    public function __construct(
        private readonly FinanceReport $report,
        private readonly CostWorklist $worklist,
        private readonly Incidents $incidents,
        private readonly SystemStatus $status,
        private readonly OnboardingState $onboarding,
    ) {}

    /**
     * @return array{asOf: string, netSales: string, saleCount: int, paymentsCash: string, paymentsTransfer: string}
     */
    public function today(): array
    {
        $report = $this->report->read(PeriodKind::Today);
        $figures = $report['figures'];

        return [
            'asOf' => $report['asOf'],
            'netSales' => $figures['netSales'],
            'saleCount' => $figures['saleCount'],
            'paymentsCash' => $figures['paymentsCash'],
            'paymentsTransfer' => $figures['paymentsTransfer'],
        ];
    }

    /**
     * @return list<array{id: int, registerLabel: string, responsible: string, openedAt: string}>
     */
    public function openSessions(): array
    {
        $sessions = [];

        foreach (DB::table('cash_sessions as s')
            ->join('cash_registers as r', 'r.id', '=', 's.register_id')
            ->where('s.status', Sessions::OPEN)
            ->select(['s.id', 'r.label', 's.responsible_display_name'])
            ->selectRaw(self::OPENED_AT)
            ->orderBy('s.opened_at')
            ->orderBy('s.id')
            ->limit(self::OPEN_SESSIONS_BOUND)
            ->get() as $row) {
            $values = (array) $row;
            $sessions[] = [
                'id' => Read::int($values, 'id'),
                'registerLabel' => Read::text($values, 'label'),
                'responsible' => Read::text($values, 'responsible_display_name'),
                'openedAt' => Read::text($values, 'opened_at'),
            ];
        }

        return $sessions;
    }

    /**
     * Each item's evidence, null for an item whose capability the viewer does not hold.
     *
     * @return array{restock: array{out: int, low: int}|null, costs: int|null, incidents: int|null, status: bool|null, onboarding: bool|null}
     */
    public function attention(User $viewer): array
    {
        $restock = null;

        if (Capabilities::of($viewer, Capability::Cap14) === Grant::Full) {
            $counts = (array) StockAttention::counts()->first();
            $restock = ['out' => Read::int($counts, 'out_count'), 'low' => Read::int($counts, 'low_count')];
        }

        return [
            'restock' => $restock,
            'costs' => Capabilities::allows($viewer, Capability::Cap20) ? $this->worklist->count() : null,
            'incidents' => Capabilities::allows($viewer, Capability::Cap18) ? $this->incidents->openCount() : null,
            'status' => Capabilities::allows($viewer, Capability::Cap32) ? $this->status->attention() : null,
            'onboarding' => Capabilities::allows($viewer, Capability::Cap15) ? $this->onboarding->current() === OnboardingState::ACTIVE : null,
        ];
    }
}
