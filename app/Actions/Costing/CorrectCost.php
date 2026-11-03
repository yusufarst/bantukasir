<?php

namespace App\Actions\Costing;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Actions\Commands\CommandTransaction;
use App\Database\SqlState;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\GuardedProduct;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\AcquisitionCost;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\CostCorrections;
use App\Domain\Costing\CostIncompleteMarks;
use App\Domain\Costing\CostSource;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Costing\ProductValuation;
use App\Domain\Costing\Replay\ProductReplay;
use App\Domain\Costing\Replay\ReplayBudget;
use App\Domain\Costing\Replay\ReplayRefused;
use App\Domain\Inventory\PostingBlocks;
use App\Models\User;
use App\Support\Numbers\Cost;
use LogicException;
use Throwable;

/**
 * RC-15 `cost_correction.confirm` (INTERACTION_CONTRACTS RC-15 and section 1.5; ROUTE_CONTRACTS
 * section 6; TECHNICAL_RULES section 7.2, "Cost correction or completion", section 9 and TR-TIME-01;
 * ADR-008 section 4.3; SCHEMA section 5.8; D-034, D-063; BR-COST-20 to BR-COST-27; G-07, G-11, G-15,
 * G-21; CAP-19; WF-26). The Owner alone, with recent authentication, which the route requires,
 * corrects or completes the cost of one cost source after posting: a new, append-only correction
 * with the effective value before it and the new value, always KNOWN — an explicit zero with its
 * evidence or reason — and a reason. The original evidence never changes (BR-COST-21). A bounded
 * heavy command (TECHNICAL_RULES section 9): never retried.
 *
 * The locks in the order of TR §7.1: the product of the cost source, update (step 6), read from the
 * evidence, a fact, without a lock (SCH-31); under it the correction reviewed must still be the
 * latest (STALE_VERSION; G-15) and the product not blocked (POSTING_BLOCKED; G-11); then its
 * valuation and the projections the replay may rewrite, update (step 7). The correction is added,
 * then the replay recomputes the product from the cost source's movement to the end of its ledger
 * with the new value, within the movement cap and the time budget, and its result is published in
 * this transaction: the valuation with the next publication number, and every projection whose
 * status or amount changed — all of it or nothing (BR-COST-25). The active marks of the same cost
 * source are cleared by this correction (SCHEMA section 5.8).
 *
 * A replay that cannot be done safely — the movement cap, the time budget, an unsafe history or a
 * value out of range — refuses the correction REPLAY_REJECTED with nothing written; after the
 * rollback, the mark and its audit event are written in a transaction of their own, once per refused
 * intent, and only once it has committed is REPLAY_REJECTED answered, SERVICE_BUSY otherwise
 * (TR-TIME-01; refused()). So is a transaction that PostgreSQL ended with its session at the budget,
 * whose mark is written on a new connection. No mark follows STALE_VERSION, POSTING_BLOCKED or a
 * lock wait (SERVICE_BUSY): none of them started the replay (D-063).
 *
 * Write steps (TEST_STRATEGY TS-21): the correction, the valuation, the projections rewritten, the
 * clearances, the audit event, then the result.
 */
final class CorrectCost extends CommandAction
{
    public const string OPERATION = 'cost_correction.confirm';

    /** COPY_DECK section 13.1: REPLAY_REJECTED of a cost correction. */
    private const string REPLAY_REJECTED = 'ux.state.replay_rejected.cost';

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    private const string CORRECTION = 'cost_correction';

    private const string MARK = 'cost_incomplete_mark';

    /** The correction that a replay refused, whose mark refused() writes. */
    private ?RefusedCorrection $refusedReplay = null;

    /** The correction under way, whose mark refused() writes if the budget ended its session. */
    private ?RefusedCorrection $underWay = null;

    private bool $budgetEnded = false;

    public function __construct(
        CommandServices $services,
        private readonly Products $products,
        private readonly PostingBlocks $blocks,
        private readonly CostCorrections $corrections,
        private readonly CostIncompleteMarks $marks,
        private readonly InventoryValuations $valuations,
        private readonly IssueAllocations $allocations,
        private readonly ProductReplay $replay,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap19];
    }

    protected function heavy(): bool
    {
        return true;
    }

    /**
     * RP-505 of the corrected product, which CAP-20 opens (UI_STATES section 4; NAVIGATION_CONTRACTS
     * UXP-504): the page that opened UXP-504 goes back there itself.
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap20)) {
            return null;
        }

        $correction = $result->ref ?? throw new LogicException('A cost correction result names its correction.');
        $product = $this->corrections->productOf($correction) ?? throw new LogicException('A committed correction exists.');

        return ['type' => $result->type, 'id' => $correction, 'location' => '/products/'.$product.'/cost-history'];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $this->refusedReplay = null;
        $this->underWay = null;
        $this->budgetEnded = false;
        $budget = ReplayBudget::startingNow($this->services->clock);
        $payload = $context->envelope->payload;
        $evidenceId = $payload['evidenceId'] ?? null;
        $newCost = $payload['newCost'] ?? null;
        $reason = $payload['reason'] ?? null;
        $expected = $context->envelope->expected['correctionNo'] ?? null;

        if (! is_int($evidenceId) || ! is_array($newCost) || ! is_string($reason) || ! is_int($expected)) {
            throw new LogicException('The payload and the correction reviewed were validated before the Action.');
        }

        [$new, $zeroNote] = self::newValue($newCost);

        // The cost source, a fact, read without a lock to choose the product's guard (SCH-31).
        $source = $this->corrections->source($evidenceId) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $product = $this->products->lockForPosting([$source->productId])[$source->productId] ?? throw new LogicException('A cost source names its product (SCHEMA section 5.8).');
        $this->services->points->afterLock('6');

        // Under the guard, in the order of RC-15: the correction reviewed is the latest (G-15), then
        // the product is not blocked (G-11). Neither refusal starts the replay or writes a mark.
        $latest = $this->corrections->latest($source->evidenceId);
        $seen = $latest === null ? 0 : $latest->correctionNo;

        if ($seen !== $expected) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        if ($this->blocks->blockedAmong([$source->productId]) !== []) {
            throw CommandRefused::of(ErrorCode::PostingBlocked);
        }

        $old = $latest === null ? $source->original : $latest->newAmount;
        $underWay = new RefusedCorrection($source, CostIncompleteMarks::TIME_BUDGET, $seen, $new, $zeroNote, $reason);
        $this->underWay = $underWay;

        try {
            return $this->correct($actor, $commandId, $source, $product, $budget, $seen + 1, $old, $new, $zeroNote, $reason);
        } catch (ReplayRefused $refusal) {
            $this->refusedReplay = $underWay->because($refusal->rejection);

            throw new CommandRefused(ErrorCode::ReplayRejected, messageKey: self::REPLAY_REJECTED);
        } catch (Throwable $failure) {
            // A session that the budget ended has not committed (TR-TIME-01).
            $this->budgetEnded = SqlState::budgetEnded($failure);

            throw $failure;
        }
    }

    /**
     * After a refusal: a correction whose replay was refused, or whose session the budget ended
     * during the replay, gets its mark and the mark's audit event in a transaction of their own, on
     * a new connection if the budget ended the old one, once per refused intent; REPLAY_REJECTED is
     * answered only once that transaction has committed, and SERVICE_BUSY if it fails, so that the
     * client stays uncertain and repeats the same request (SCHEMA section 5.8; TR-TIME-01).
     */
    protected function refused(CommandContext $context, CommandRefused $refusal): CommandRefused
    {
        $refused = match (true) {
            $refusal->error === ErrorCode::ReplayRejected => $this->refusedReplay,
            $refusal->error === ErrorCode::ServiceBusy && $this->budgetEnded => $this->underWay,
            default => null,
        };

        if ($refused === null) {
            return $refusal;
        }

        try {
            CommandTransaction::run(function () use ($context, $refused): void {
                $this->mark($context, $refused);
            });
        } catch (Throwable $failure) {
            report($failure);

            return CommandRefused::of(ErrorCode::ServiceBusy);
        }

        return new CommandRefused(ErrorCode::ReplayRejected, messageKey: self::REPLAY_REJECTED);
    }

    /**
     * Steps 7 and 8 of a correction whose checks under the product's guard passed.
     *
     * @throws ReplayRefused
     */
    private function correct(User $actor, int $commandId, CostSource $source, GuardedProduct $product, ReplayBudget $budget, int $correctionNo, Cost $old, Cost $new, ?string $zeroNote, string $reason): StoredResult
    {
        $valuation = $this->valuations->lock([$source->productId])[$source->productId] ?? throw new LogicException('A valuation is read for its product.');

        if (! $valuation->stored) {
            throw new LogicException('A product with a cost source has a published valuation (ADR-008 section 4.2).');
        }

        $this->allocations->lockProjectionsFrom($source->productId, $source->productSequence);
        $this->services->points->afterLock('7');

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $number = $valuation->publicationNo + 1;
        $correction = $this->corrections->insert($source, $correctionNo, $old, $new, $zeroNote, $reason, $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        // The replay from the cost source's movement, with the new value, then the publication's
        // reserve of the budget (TR-TIME-01).
        $result = $this->replay->recompute($source->productId, $product->quantityPrecision, $source->productSequence, $budget);
        $budget->check();

        $this->valuations->publish(new ProductValuation($source->productId, $result->value, $number, $valuation->lastSequence, true), $at);
        $this->written();

        $this->allocations->republish($result->changes, $number, $at);
        $this->written();

        $cleared = $this->marks->activeOf($source->evidenceId);
        $this->marks->clearByCorrection($cleared, $source, $correction, Actor::user($actor->id, $actor->display_name), $commandId, $at);
        $this->written();

        // The values and the reason are the Owner's alone in the audit (BR-AUD-02, D-047).
        $this->services->audit->record(
            AuditCategory::Business,
            'COST_CORRECTION',
            Actor::user($actor->id, $actor->display_name),
            [
                'evidenceId' => $source->evidenceId,
                'movementId' => $source->movementId,
                'correctionNo' => $correctionNo,
                'publicationNo' => $number,
                'projectionsRewritten' => count($result->changes),
                'marksCleared' => count($cleared),
            ],
            ['old' => self::valueOf($old), 'new' => self::valueOf($new), 'zeroNote' => $zeroNote, 'reason' => $reason],
            subject: ['type' => self::CORRECTION, 'id' => $correction],
            commandId: $commandId,
            productId: $source->productId,
        );
        $this->written();

        return new StoredResult(self::CORRECTION, $correction);
    }

    /**
     * The mark of a refused correction, in its own transaction: the actor (step 2.1, share) and the
     * product (step 6, update), then the mark if the refused intent has none. A correction of the
     * same source that committed after the refused attempt saw the corrections published newer
     * evidence, so the mark is written with its clearance by that correction; a reversal of the
     * source's movement clears it the same way once reversals exist (TASK-026).
     */
    private function mark(CommandContext $context, RefusedCorrection $refused): void
    {
        $actor = $this->services->actorGuard->hold($context->actorId, $this->capabilities());
        $this->services->points->afterLock('2.1');
        $source = $refused->source;
        $this->products->lockForPosting([$source->productId]);
        $this->services->points->afterLock('6');

        $operation = $context->envelope->operation;
        $intent = $context->envelope->sourceIntentId;

        if ($this->marks->existsFor($operation, $intent)) {
            return;
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $mark = $this->marks->insert($source, $operation, $intent, $refused->rejection, $refused->correctionsSeen, $actor->id, $actor->display_name, $at);
        $latest = $this->corrections->latest($source->evidenceId);
        $cleared = $latest !== null && $latest->correctionNo > $refused->correctionsSeen;

        if ($latest !== null && $cleared) {
            $this->marks->clearByCorrection([$mark], $source, $latest->id, Actor::user($actor->id, $actor->display_name), null, $at);
        }

        // The value attempted and its reason are the Owner's alone (BR-AUD-02); a mark carries no amount.
        $this->services->audit->record(
            AuditCategory::Business,
            'COST_INCOMPLETE_MARK',
            Actor::user($actor->id, $actor->display_name),
            ['evidenceId' => $source->evidenceId, 'movementId' => $source->movementId, 'rejection' => $refused->rejection, 'correctionsSeen' => $refused->correctionsSeen, 'cleared' => $cleared],
            ['attempted' => self::valueOf($refused->newAmount), 'zeroNote' => $refused->zeroNote, 'reason' => $refused->reason],
            subject: ['type' => self::MARK, 'id' => $mark],
            productId: $source->productId,
        );
        $this->services->points->afterOwnWrite('mark');
    }

    /**
     * The new value of the payload: KNOWN, at most the range of one cost, and an explicit zero with
     * its evidence or reason (BR-COST-04), as the FormRequest validated it.
     *
     * @param  array<array-key, mixed>  $newCost
     * @return array{Cost, string|null}
     */
    private static function newValue(array $newCost): array
    {
        $amount = $newCost['amount'] ?? null;
        $note = $newCost['zeroEvidence'] ?? null;

        if (! is_string($amount)) {
            throw new LogicException('The new cost was validated before the Action.');
        }

        $new = Cost::of($amount)->assertUnit();

        if (! $new->amount()->isZero()) {
            return [$new, null];
        }

        return [$new, is_string($note) ? $note : throw new LogicException('An explicit zero carries its evidence or reason (BR-COST-04).')];
    }

    /**
     * A value as the audit holds it: its status and its canonical amount, none for UNKNOWN.
     *
     * @return array{status: string, amount: string|null}
     */
    private static function valueOf(Cost $value): array
    {
        return ['status' => $value->isKnown() ? AcquisitionCost::KNOWN : AcquisitionCost::UNKNOWN, 'amount' => $value->isKnown() ? $value->toCanonical() : null];
    }
}
