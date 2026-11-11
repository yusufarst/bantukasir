<?php

namespace App\Actions\Cash;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Actions\Sales\CheckoutSale;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Cash\CashCount;
use App\Domain\Cash\Registers;
use App\Domain\Cash\SessionReports;
use App\Domain\Cash\SessionSales;
use App\Domain\Cash\Sessions;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileRevision;
use App\Domain\Sales\Sales;
use App\Models\User;
use App\Support\Numbers\Money;
use App\Support\Numbers\NumberOutOfRange;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * RC-03 `cash_session.close` (INTERACTION_CONTRACTS RC-03 and section 2.5; ROUTE_CONTRACTS section 6;
 * TECHNICAL_RULES section 7.2, "Close, own or Owner-assisted", RACE-08, RACE-09, RACE-35; SCHEMA
 * section 5.10; SALES_CASH BR-CASH-12 to BR-CASH-20; ADR-009 section 6; D-068, D-088, D-089; G-04,
 * G-05, G-16, G-17, G-19, G-23, G-27; CAP-21; WF-08; TASK-030 scope item 2). The responsible user
 * closes their own OPEN session with the physical cash they counted, against the activity version
 * of the close preview; nothing is locked while the cash is counted (BR-CASH-13). Steps 1, 2.1, 2.4
 * and 4, then 8:
 *
 * - the BusinessProfile pointer, share (step 2.4): the revision whose identity the report freezes
 *   (RACE-25), which the bootstrap wrote and no command removes (G-23);
 * - the session, FOR UPDATE (step 4; SCHEMA section 7), the same lock as a checkout's, only when the
 *   actor is its responsible user: another user's session, like one that does not exist, is
 *   NOT_FOUND (SEC-AZ-03; G-19);
 * - under that lock: the session OPEN, else SESSION_NOT_OPEN (RACE-08, RACE-09); its activity
 *   version the one reviewed, else RECOUNT_REQUIRED (BR-CASH-14); then the responsible user's
 *   `sale.checkout` receipts, read after the lock, any PENDING or HELD one refusing
 *   RECOUNT_REQUIRED (G-27, RACE-35; D-088), a settled one no longer counting (D-089);
 * - expected cash from the session's facts under the lock — the opening cash, the applied CASH of
 *   its Sales, which the Sales module's query gives (STK-10), and its drawer-funded refunds, none
 *   before TASK-034 pays one — and the variance (G-05; BR-CASH-12);
 * - at step 8, the time, then the session CLOSED, its close, its report, the audit event and the
 *   result, all or nothing (TR-TX-01): a failure at any of them leaves the session OPEN and nothing
 *   written (BR-CASH-17). There is no CLOSING state.
 *
 * A repeat of the same request answers the original result; any other close of the session finds it
 * CLOSED and is refused SESSION_NOT_OPEN, so a different count never overwrites (BR-CASH-17,
 * RACE-09). The close actor is the actor, never a field of the request (TR-VAL-03), and every name is
 * frozen from the rows the locks hold (G-16). The answer's location is RP-206, the session's report.
 *
 * Write steps (TEST_STRATEGY TS-21): the session's status, the close, the report, the audit event,
 * the result.
 */
final class CloseCashSession extends CommandAction
{
    public const string OPERATION = 'cash_session.close';

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    public const string RESULT_TYPE = 'cash_session_close';

    public function __construct(
        CommandServices $services,
        private readonly BusinessProfiles $profiles,
        private readonly Sessions $sessions,
        private readonly Registers $registers,
        private readonly SessionReports $reports,
        private readonly Sales $sales,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap21];
    }

    /**
     * The closed session, its number and its report's page for its responsible user, and for the
     * Owner, who sees every session (CAP-21 own, CAP-22; BR-CASH-21); nobody else (SEC-AZ-06).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $session = $result->ref === null ? null : DB::table('cash_sessions')->where('id', $result->ref)->first(['responsible_user_id', 'session_number']);
        $values = $session === null ? [] : (array) $session;
        $number = $values['session_number'] ?? null;
        $own = ($values['responsible_user_id'] ?? null) === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);

        if ($result->ref === null || ! is_string($number) || (! $own && ! Capabilities::allows($viewer, Capability::Cap22))) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'number' => $number, 'location' => Sessions::detailPath($result->ref)];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $sessionId = $payload['sessionId'] ?? null;
        $physical = $payload['physicalCash'] ?? null;
        $reviewed = $context->envelope->expected['activityVersion'] ?? null;

        if (! is_int($sessionId) || ! is_string($physical) || ! is_int($reviewed)) {
            throw new LogicException('The envelope was validated before the Action.');
        }

        $revision = $this->revisionInForce();
        $this->services->points->afterLock('2.4');

        $session = $this->sessions->lockForClose($sessionId, $actor->id);
        $this->services->points->afterLock('4');

        if ($session === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if (! $session->open) {
            throw new CommandRefused(ErrorCode::SessionNotOpen, messageKey: 'ux.state.session_closed.close');
        }

        // BR-CASH-14, RACE-08: a Sale or a drawer-funded refund after the preview needs a recount.
        if ($session->activityVersion !== $reviewed) {
            throw CommandRefused::of(ErrorCode::RecountRequired);
        }

        // G-27, RACE-35: read after the session's lock; a checkout whose receipt is written later
        // waits for this lock and then finds the session CLOSED.
        if ($this->services->receipts->unresolvedOf($actor->id, CheckoutSale::OPERATION) > 0) {
            throw new CommandRefused(ErrorCode::RecountRequired, messageKey: 'ux.state.recount.unresolved_checkouts');
        }

        $register = $this->registers->find($session->registerId) ?? throw new LogicException('A session names its register (SCHEMA section 5.10).');
        $sales = SessionSales::of($this->sales->ofSession($session->id));

        try {
            // The drawer-funded refunds of the session, none before TASK-034 pays one (D-035).
            $count = CashCount::of(CashCount::expected($session->openingCash, $sales->cashApplied, Money::zero()), Money::of($physical));
        } catch (NumberOutOfRange) {
            // TECHNICAL_RULES section 11.1: a computed amount above the range refuses the command.
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        // Step 8: the time of the close, its cutoff being the version counted against (BR-CASH-13).
        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');

        $this->sessions->close($session->id, $at);
        $this->written();

        $this->reports->record($session, $count, $actor->id, $actor->display_name, $at, $commandId);
        $this->written();

        $this->reports->freeze($session, ['code' => $register->code, 'label' => $register->label], $revision, $sales, $count, $actor->display_name, $at);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'CASH_SESSION_CLOSE',
            Actor::user($actor->id, $actor->display_name),
            [
                'number' => $session->number,
                'register' => $register->code,
                'cutoffVersion' => $session->activityVersion,
                'openingCash' => $session->openingCash->toCanonical(),
                'saleCount' => $sales->count,
                'cashApplied' => $sales->cashApplied->toCanonical(),
                'transferApplied' => $sales->transferApplied->toCanonical(),
                'expectedCash' => $count->expected->toCanonical(),
                'physicalCash' => $count->physical->toCanonical(),
                'variance' => $count->variance->toCanonical(),
            ],
            subject: ['type' => self::RESULT_TYPE, 'id' => $session->id],
            commandId: $commandId,
            cashSessionId: $session->id,
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $session->id, ['number' => $session->number]);
    }

    /**
     * The revision in force under the pointer's share lock, which the report freezes (RACE-25). The
     * bootstrap wrote the first, and no command removes one (G-23): without one a close is refused
     * before any fact (SCHEMA section 5.2).
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private function revisionInForce(): ProfileRevision
    {
        $id = $this->profiles->lockCurrentShared();
        $revision = $id === null ? null : $this->profiles->revision($id);

        if ($revision === null) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        return $revision;
    }
}
