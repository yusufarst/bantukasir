<?php

namespace App\Actions\Sales;

use App\Actions\Commands\CommandServices;
use App\Domain\Cash\CashCount;
use App\Domain\Cash\FundingSession;
use App\Domain\Cash\Registers;
use App\Domain\Cash\Sessions;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\Allocations\ReturnAllocations;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\PostingBlocks;
use App\Domain\Inventory\StockLedger;
use App\Domain\Sales\Corrections\CorrectedSale;
use App\Domain\Sales\Corrections\Payout;
use App\Domain\Sales\Corrections\SaleCorrections;
use App\Domain\Sales\Sales;
use App\Support\Formatting\Format;
use App\Support\Numbers\Money;
use LogicException;

/**
 * RC-05 `sale_correction.drawer_funded` (INTERACTION_CONTRACTS RC-05 and section 2.6; ROUTE_CONTRACTS section
 * 6; TECHNICAL_RULES section 7.2, "Drawer-funded refund or return", RACE-13 to RACE-15; SCHEMA G-04, G-05;
 * SALES_CASH BR-CASH-07, BR-CASH-10, BR-CASH-14, BR-RFD-05 to BR-RFD-09, BR-RFD-15 to BR-RFD-17; D-035, D-045,
 * D-046, D-068, D-087; CAP-24; WF-11; TASK-034 scope item 1): the Owner's CASH refund paid from the drawer of
 * the OPEN cash session that the Owner selects, with or without a return, on the correction engine
 * (CorrectionAction). Its own steps, beside the engine's:
 *
 * - step 4, between the onboarding state and the original Sale: the selected session under its update lock,
 *   FOR NO KEY UPDATE as a Sale's, whoever its responsible user is (BR-RFD-07); a session that does not exist
 *   is NOT_FOUND; a CLOSED one is refused SESSION_NOT_OPEN before any fact, with no cash taken (BR-RFD-09,
 *   RACE-13); the refund at most the session's expected cash, computed under that lock from its facts — the
 *   opening cash, the applied CASH of its Sales and the drawer-funded refunds already paid from it, which the
 *   Sales module's queries give — else DRAWER_CASH_INSUFFICIENT with the drawer and its expected cash, Rp0
 *   reachable and never below (BR-RFD-15, G-05, RACE-14). The source is never switched or split: the Owner
 *   may choose another session or the Owner's own funds (BR-RFD-16);
 * - under the Sale's lock, the payout: CASH from that drawer, with the reference `<Sale number>/<k>`, k the
 *   ordinal of this correction on the Sale (BR-RFD-05; D-087);
 * - after the correction's records, the session's activity version one on (BR-CASH-10, BR-RFD-08), so that a
 *   close counted before it is refused RECOUNT_REQUIRED (BR-CASH-14, RACE-13); the audit event names the
 *   session.
 *
 * No cash is handled under any lock: the cash leaves the drawer only after the commit, as RP-204 tells the
 * Owner (BR-RFD-17; D-046, D-068), and an uncertain refund is resolved by its status check, never replaced
 * (RACE-15). The session's expected cash, report and activity are changed only by this fact, never by
 * rewriting an earlier session or report (BR-RFD-07).
 *
 * Write steps (TEST_STRATEGY TS-21): the engine's, then the session's activity version, then the audit
 * event and the result.
 */
final class RecordDrawerFundedCorrection extends CorrectionAction
{
    public const string OPERATION = 'sale_correction.drawer_funded';

    /** The session locked in this attempt, set at step 4 and read by the later hooks. */
    private ?FundingSession $session = null;

    public function __construct(
        CommandServices $services,
        SaleCorrections $corrections,
        Products $products,
        OnboardingState $onboarding,
        PostingBlocks $blocks,
        StockLedger $ledger,
        InventoryValuations $valuations,
        IssueAllocations $issues,
        ReturnAllocations $returns,
        private readonly Sessions $sessions,
        private readonly Registers $registers,
        private readonly Sales $sales,
    ) {
        parent::__construct($services, $corrections, $products, $onboarding, $blocks, $ledger, $valuations, $issues, $returns);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    /**
     * Step 4: the selected session OPEN under its lock, and the refund within its expected cash.
     *
     * @throws CommandRefused NOT_FOUND, SESSION_NOT_OPEN or DRAWER_CASH_INSUFFICIENT
     */
    protected function beforeSale(CorrectionPayload $request, Money $refund): void
    {
        $this->session = null;
        $sessionId = $request->payout['sessionId'] ?? null;

        if (! is_int($sessionId)) {
            throw new LogicException('The payout of a drawer-funded refund was validated before the Action (RC-05).');
        }

        // RC-05 refunds money above zero; its FormRequest refuses it, and so does the Action, which every
        // caller reaches (ARC-30).
        if (! $refund->isPositive()) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        $session = $this->sessions->lockForRefund($sessionId);
        $this->services->points->afterLock('4');

        if ($session === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if (! $session->open) {
            throw new CommandRefused(ErrorCode::SessionNotOpen, messageKey: 'ux.state.session_closed.refund');
        }

        // G-05: from the session's facts under its lock, which every Sale and drawer-funded refund of the
        // session takes too (RACE-14).
        $cash = $this->sales->ofSession($session->id)['cash'];
        $paid = Money::sum(...array_map(static fn (array $paid): Money => $paid['amount'], $this->corrections->drawerRefundsOf($session->id)));
        $expected = CashCount::due($session->openingCash, $cash, $paid);

        if ($refund->compareTo($expected) > 0) {
            $register = $this->registers->find($session->registerId) ?? throw new LogicException('A session names its register (SCHEMA section 5.10).');

            throw new CommandRefused(ErrorCode::DrawerCashInsufficient, params: ['laci' => $register->label, 'uang' => Format::money($expected)]);
        }

        $this->session = $session;
    }

    /**
     * Under the Sale's lock: the payout in cash from the selected drawer, with the reference that the server
     * generates as `<Sale number>/<k>` (BR-RFD-05; D-087).
     */
    protected function payout(CorrectionPayload $request, CorrectedSale $sale, Money $refund): Payout
    {
        $session = $this->session ?? throw new LogicException('A drawer-funded refund holds its session (step 4).');

        return Payout::fromDrawer($session->id, $sale->number.'/'.(string) $this->corrections->nextOrdinal($sale->id));
    }

    /**
     * The session's activity version one on, by a relative increment under its lock (BR-CASH-10; SCH-33).
     */
    protected function afterRecords(string $at): void
    {
        $session = $this->session ?? throw new LogicException('A drawer-funded refund holds its session (step 4).');

        $this->sessions->recordActivity($session->id, $at);
        $this->written();
    }

    protected function auditSession(): ?int
    {
        return $this->session?->id;
    }
}
