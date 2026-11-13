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
use App\Domain\Cash\Sessions;
use App\Domain\Cash\SessionSales;
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
 * RC-04 `cash_session.close_assisted` (INTERACTION_CONTRACTS RC-04 and section 2.5; ROUTE_CONTRACTS
 * section 6; TECHNICAL_RULES section 7.2, "Close, own or Owner-assisted", RACE-09, RACE-28, RACE-35;
 * SCHEMA section 5.10; SALES_CASH BR-CASH-11, BR-CASH-16 to BR-CASH-20; D-069 (d), D-087, D-088, D-089;
 * G-05, G-16, G-17, G-19, G-23, G-27; CAP-23; WF-09; TASK-032 scope item 2). The Owner closes another
 * user's OPEN session — one whose responsible user was deactivated, or the outgoing Owner's after OP-03,
 * included (D-069 (d), D-087) — with the physical cash counted and a reason, against the activity version
 * of the close preview; the route asks for recent authentication first (password.confirm). The steps of
 * RC-03's family, 1, 2.1, 2.4, 4 and 8:
 *
 * - the BusinessProfile pointer, share (step 2.4), whose revision the report freezes (RACE-25, G-23);
 * - the session, FOR UPDATE (step 4), the same lock as a checkout's and an own close's, only when the
 *   actor is not its responsible user: the Owner's own session, like one that does not exist, is
 *   NOT_FOUND here, its close being RC-03's (G-19);
 * - under that lock: the session OPEN, else SESSION_NOT_OPEN (RACE-09); its activity version the one
 *   reviewed, else RECOUNT_REQUIRED (BR-CASH-14); then the responsible user's `sale.checkout` receipts,
 *   read after the lock, any PENDING or HELD one refusing RECOUNT_REQUIRED (G-27, RACE-35; D-088), a
 *   settled one — by the Owner's status check among others — no longer counting (D-089);
 * - expected cash from the session's facts under the lock, as an own close derives it (G-05; BR-CASH-12);
 * - at step 8, the time, then the session CLOSED, its close of kind OWNER_ASSISTED with the Owner as its
 *   actor and the reason, its report with the reason, the audit event CASH_SESSION_CLOSE_ASSISTED and the
 *   result, all or nothing (TR-TX-01; BR-CASH-17). The responsible user never changes (BR-CASH-18).
 *
 * A repeat of the same request answers the original result; any other close of the session, own or
 * assisted, finds it CLOSED and is refused SESSION_NOT_OPEN (RACE-09). The close actor and the reason's
 * author are the actor, never a field of the request (TR-VAL-03). The answer's location is RP-206.
 *
 * Write steps (TEST_STRATEGY TS-21): the session's status, the close, the report, the audit event, the
 * result.
 */
final class CloseCashSessionAssisted extends CommandAction
{
    public const string OPERATION = 'cash_session.close_assisted';

    /** The result's type and its subject in the audit, the same as an own close's (SCHEMA section 5.3). */
    public const string RESULT_TYPE = CloseCashSession::RESULT_TYPE;

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
        return [Capability::Cap23];
    }

    /**
     * The closed session, its number and its report's page, for the Owner, who sees every session
     * (CAP-22); nobody else (SEC-AZ-06).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $number = $result->ref === null ? null : $this->numberOf($result->ref);

        if ($result->ref === null || $number === null || ! Capabilities::allows($viewer, Capability::Cap22)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'number' => $number, 'location' => Sessions::detailPath($result->ref)];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $sessionId = $payload['sessionId'] ?? null;
        $physical = $payload['physicalCash'] ?? null;
        $reason = $payload['reason'] ?? null;
        $reviewed = $context->envelope->expected['activityVersion'] ?? null;

        if (! is_int($sessionId) || ! is_string($physical) || ! is_string($reason) || $reason === '' || ! is_int($reviewed)) {
            throw new LogicException('The envelope was validated before the Action.');
        }

        $revision = $this->revisionInForce();
        $this->services->points->afterLock('2.4');

        $session = $this->sessions->lockForAssistedClose($sessionId, $actor->id);
        $this->services->points->afterLock('4');

        if ($session === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if (! $session->open) {
            throw new CommandRefused(ErrorCode::SessionNotOpen, messageKey: 'ux.state.session_closed.close');
        }

        // BR-CASH-14: a Sale after the preview needs a recount.
        if ($session->activityVersion !== $reviewed) {
            throw CommandRefused::of(ErrorCode::RecountRequired);
        }

        // G-27, RACE-35: the responsible user's checkouts, read after the session's lock.
        if ($this->services->receipts->unresolvedOf($session->responsibleUserId, CheckoutSale::OPERATION) > 0) {
            throw new CommandRefused(ErrorCode::RecountRequired, messageKey: 'ux.state.recount.unresolved_checkouts');
        }

        $register = $this->registers->find($session->registerId) ?? throw new LogicException('A session names its register (SCHEMA section 5.10).');
        $sales = SessionSales::of($this->sales->ofSession($session->id));

        try {
            // The drawer-funded refunds of the session, none before TASK-034 pays one (D-035).
            $count = CashCount::of(CashCount::expected($session->openingCash, $sales->cashApplied, Money::zero()), Money::of($physical));
        } catch (NumberOutOfRange) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');

        $this->sessions->close($session->id, $at);
        $this->written();

        $this->reports->record($session, $count, $actor->id, $actor->display_name, $at, $commandId, $reason);
        $this->written();

        $this->reports->freeze($session, ['code' => $register->code, 'label' => $register->label], $revision, $sales, $count, $actor->display_name, $at, $reason);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'CASH_SESSION_CLOSE_ASSISTED',
            Actor::user($actor->id, $actor->display_name),
            [
                'number' => $session->number,
                'register' => $register->code,
                'responsible' => $session->responsibleDisplayName,
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
            reason: $reason,
            commandId: $commandId,
            cashSessionId: $session->id,
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $session->id, ['number' => $session->number]);
    }

    private function numberOf(int $sessionId): ?string
    {
        $number = DB::table('cash_sessions')->where('id', $sessionId)->value('session_number');

        return is_string($number) ? $number : null;
    }

    /**
     * The revision in force under the pointer's share lock, which the report freezes (RACE-25, G-23).
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
