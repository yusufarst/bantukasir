<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Database\SqlState;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\GuardedProduct;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\Allocations\ReturnAllocations;
use App\Domain\Costing\CostIncompleteMarks;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Costing\ProductValuation;
use App\Domain\Costing\Replay\ProductReplay;
use App\Domain\Costing\Replay\ReplayBudget;
use App\Domain\Costing\Replay\ReplayRefused;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\PostingBlocks;
use App\Domain\Inventory\PostingGuard;
use App\Domain\Inventory\Reversals;
use App\Domain\Inventory\ReversalSource;
use App\Domain\Inventory\StockLedger;
use App\Domain\Inventory\StockPosition;
use App\Models\User;
use App\Support\Numbers\NumberOutOfRange;
use LogicException;
use Throwable;

/**
 * RC-10 `reversal.confirm` (INTERACTION_CONTRACTS RC-10 and section 1.5; ROUTE_CONTRACTS section 6;
 * TECHNICAL_RULES section 7.2, "Reversal", section 9 and TR-TIME-01; ADR-008 sections 4.1 and 4.3;
 * SCHEMA sections 5.7 and 5.8; BR-INV-34 to BR-INV-37; BR-COST-27, BR-COST-32, BR-COST-33; G-07,
 * G-11, G-21; CAP-17; WF-23). The Owner alone, with recent authentication, which the route requires,
 * reverses one OPENING, RECEIPT or MANUAL_ISSUE with a reason: one REVERSAL that is its exact inverse —
 * the Owner never enters a quantity — once. The source stays in the ledger, and the valuation cancels
 * the pair logically (BR-COST-32). A bounded heavy command (TECHNICAL_RULES section 9): never retried.
 *
 * The source, a fact, is read without a lock to choose the product's guard (SCH-31); a movement that
 * does not exist, or whose type no reversal may cancel (BR-INV-36), is no reversal's source: NOT_FOUND.
 * The locks in the order of TR §7.1: the onboarding state, share (step 2.3); the product, update (step
 * 6), under which, in the order of RC-10, the source is not yet reversed (STALE_VERSION; BR-INV-35,
 * RACE-10), onboarding is off for a RECEIPT or a MANUAL_ISSUE (ONBOARDING_FREEZE; an OPENING may also
 * be reversed while it is on, D-087), and the product is not blocked (POSTING_BLOCKED; G-11); then its
 * balance, its valuation and the projections the replay may rewrite, update (step 7), and stock stays
 * non-negative now (INSUFFICIENT_STOCK). The reversal and its REVERSAL are written, then the replay
 * recomputes the product from the source to the end of its logical ledger within the movement cap and
 * the time budget, and validates it: an issue that would have had no stock at its point, a value out
 * of range, the cap or the budget refuses the whole reversal REPLAY_REJECTED with nothing written
 * (BR-INV-37; G-07). Its result is published in this transaction: the valuation with the next
 * publication number, every projection whose status or amount changed — a reversed manual issue's
 * becomes CANCELLED — and the clearance of every active mark of the source's cost evidence (SCHEMA
 * section 5.8). A refused reversal writes no mark (TR-TIME-01).
 *
 * Write steps (TEST_STRATEGY TS-21): the reversal, its REVERSAL, the balance, the valuation, the
 * projections rewritten, the clearances, the audit event, then the result.
 */
final class ConfirmReversal extends CommandAction
{
    public const string OPERATION = 'reversal.confirm';

    /** COPY_DECK section 13.1: INSUFFICIENT_STOCK and REPLAY_REJECTED of a reversal. */
    private const string INSUFFICIENT_STOCK = 'ux.state.insufficient_stock.reversal';

    private const string REPLAY_REJECTED = 'ux.state.replay_rejected.reversal';

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    private const string REVERSAL = 'reversal';

    /** RP-403 (ROUTE_CONTRACTS section 2) of the REVERSAL. */
    private const string MOVEMENT_PAGE = '/stock/movements/';

    public function __construct(
        CommandServices $services,
        private readonly PostingGuard $guard,
        private readonly OnboardingState $onboarding,
        private readonly PostingBlocks $blocks,
        private readonly StockLedger $ledger,
        private readonly Reversals $reversals,
        private readonly InventoryValuations $valuations,
        private readonly IssueAllocations $allocations,
        private readonly CostIncompleteMarks $marks,
        private readonly ProductReplay $replay,
        private readonly ReturnAllocations $returns,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap17];
    }

    protected function heavy(): bool
    {
        return true;
    }

    /**
     * RP-403 of the REVERSAL, which the Owner opens (CAP-14; ROUTE_CONTRACTS RC-10).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap17)) {
            return null;
        }

        $reversal = $result->ref ?? throw new LogicException('A reversal result names its reversal.');
        $movement = $this->reversals->movementOf($reversal) ?? throw new LogicException('A committed reversal posted its REVERSAL.');

        return ['type' => $result->type, 'id' => $reversal, 'location' => self::MOVEMENT_PAGE.$movement];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $budget = ReplayBudget::startingNow($this->services->clock);
        $payload = $context->envelope->payload;
        $movementId = $payload['movementId'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (! is_int($movementId) || ! is_string($reason)) {
            throw new LogicException('The payload was validated before the Action.');
        }

        // The source, a fact, read without a lock to choose the product's guard (SCH-31).
        $source = $this->reversals->source($movementId);

        if ($source === null || ! $source->eligible()) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        $onboarding = $this->onboarding->lockShared() ?? throw new LogicException('The bootstrap inserts the onboarding state (SEC-OP-01).');
        $this->services->points->afterLock('2.3');
        $product = $this->guard->lockProducts([$source->productId])[$source->productId] ?? throw new LogicException('A movement names its product (SCHEMA section 5.7).');
        $this->services->points->afterLock('6');

        // Under the guard, in the order of RC-10.
        if ($this->reversals->isReversed($source->movementId)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        if ($onboarding === OnboardingState::ACTIVE && $source->movementType !== StockLedger::OPENING) {
            throw CommandRefused::of(ErrorCode::OnboardingFreeze);
        }

        if ($this->blocks->blockedAmong([$source->productId]) !== []) {
            throw CommandRefused::of(ErrorCode::PostingBlocked);
        }

        $before = $this->ledger->lockPositions([$source->productId => $product])[$source->productId] ?? throw new LogicException('A position is read for its product.');
        $valuation = $this->valuations->lock([$source->productId])[$source->productId] ?? throw new LogicException('A valuation is read for its product.');
        $this->allocations->lockProjectionsFrom($source->productId, $source->productSequence);
        $this->returns->lockProjectionsFrom($source->productId, $source->productSequence);
        $this->services->points->afterLock('7');

        if (! $before->stored || ! $valuation->stored) {
            throw new LogicException('A product with a movement has its balance and its valuation (ADR-008 section 4.2).');
        }

        $after = self::positionAfter($before, $source);

        try {
            return $this->reverse($actor, $commandId, $source, $product, $after, $valuation, $budget, $reason);
        } catch (ReplayRefused) {
            throw new CommandRefused(ErrorCode::ReplayRejected, messageKey: self::REPLAY_REJECTED);
        } catch (Throwable $failure) {
            // A statement of the replay or of its publication that met the statement timeout is a
            // timeout of the replay (TR-TIME-01): the reversal is refused with nothing written, and
            // no mark follows a refused reversal.
            if (SqlState::statementTimedOut($failure)) {
                throw new CommandRefused(ErrorCode::ReplayRejected, messageKey: self::REPLAY_REJECTED);
            }

            throw $failure;
        }
    }

    /**
     * The writes of a reversal whose checks under the locks passed, with the replay between its
     * movement and its publication.
     *
     * @throws ReplayRefused
     */
    private function reverse(User $actor, int $commandId, ReversalSource $source, GuardedProduct $product, StockPosition $after, ProductValuation $valuation, ReplayBudget $budget, string $reason): StoredResult
    {
        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $number = $valuation->publicationNo + 1;

        $reversal = $this->reversals->insert($source, $reason, $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $movement = $this->ledger->appendReversal($product, $after->lastSequence, $source->inverse(), $reversal, $actor->id, $commandId, $at);
        $this->written();

        $this->ledger->writePosition($after, $at);
        $this->written();

        // The replay from the source, which with its REVERSAL it now skips, then the publication's
        // reserve of the budget (TR-TIME-01; ADR-008 section 4.3).
        $result = $this->replay->recompute($source->productId, $product->quantityPrecision, $source->productSequence, $budget);
        $budget->check();

        $this->valuations->publish(new ProductValuation($source->productId, $result->value, $number, $after->lastSequence, true), $at);
        $this->written();

        // The issue projections and the return projections linked to them, recomputed together, are one
        // publication (D-061; TASK-033).
        $this->allocations->republish($result->changes, $number, $at);
        $this->returns->republish($result->returnChanges, $number, $at);
        $this->written();

        $cleared = $this->marks->activeOfMovement($source->movementId);
        $this->marks->clearByReversal($cleared, $source->movementId, $reversal, Actor::user($actor->id, $actor->display_name), $commandId, $at);
        $this->written();

        // Values are the Owner's alone in the audit; a reversal records none (BR-AUD-02, BR-COST-07).
        $this->services->audit->record(
            AuditCategory::Business,
            'REVERSAL_CONFIRM',
            Actor::user($actor->id, $actor->display_name),
            [
                'originalMovementId' => $source->movementId,
                'originalType' => $source->movementType,
                'originalQuantity' => $source->quantity->toCanonical(),
                'movementId' => $movement,
                'inverseQuantity' => $source->inverse()->toCanonical(),
                'productSequence' => $after->lastSequence,
            ],
            [
                'publicationNo' => $number,
                'projectionsRewritten' => count($result->changes) + count($result->returnChanges),
                'marksCleared' => count($cleared),
            ],
            subject: ['type' => self::REVERSAL, 'id' => $reversal],
            reason: $reason,
            commandId: $commandId,
            productId: $source->productId,
        );
        $this->written();

        return new StoredResult(self::REVERSAL, $reversal);
    }

    /**
     * The product's stock after the REVERSAL, in the next place of its sequence: never negative now
     * (INSUFFICIENT_STOCK; BR-INV-04, BR-INV-37), and a result past the range of stock is no safe
     * replay (REPLAY_REJECTED; TR-TIME-01).
     *
     * @throws CommandRefused
     */
    private static function positionAfter(StockPosition $before, ReversalSource $source): StockPosition
    {
        try {
            return $before->moved($source->inverse());
        } catch (NumberOutOfRange) {
            if ($source->inverse()->isNegative()) {
                throw new CommandRefused(ErrorCode::InsufficientStock, messageKey: self::INSUFFICIENT_STOCK);
            }

            throw new CommandRefused(ErrorCode::ReplayRejected, messageKey: self::REPLAY_REJECTED);
        }
    }
}
