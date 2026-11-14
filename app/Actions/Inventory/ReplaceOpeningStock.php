<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\AcquisitionCost;
use App\Domain\Costing\CostEvidence;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\Onboarding\OnboardingEntry;
use App\Domain\Inventory\Onboarding\OpeningStock;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\PostingGuard;
use App\Domain\Inventory\StockLedger;
use App\Models\User;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * RC-48 `opening_stock.replace` (INTERACTION_CONTRACTS RC-48; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2, "Opening stock"; BR-INV-26, BR-INV-47, BR-INV-48; BR-COST-02 to BR-COST-04, BR-COST-32;
 * D-087; WF-25; G-22, G-24, G-26; RACE-31; CAP-15). The Owner alone, with recent authentication, which
 * the route requires, records the one explicit replacement of an OPENING that he reversed in full before
 * the release, for one product, with a reason, a count reference and its acquisition cost KNOWN or
 * UNKNOWN: an entry of one row whose line names the reversed line it replaces, and the replacement
 * OPENING at the product's next place with its cost evidence, balance and valuation. The original
 * OPENING, its REVERSAL and the replacement all stay in the history; no earlier fact changes.
 *
 * The line to replace, a fact, is read without a lock to choose its product's guard (SCH-31); one that
 * does not exist is NOT_FOUND. The locks in the order of TR §7.1: the onboarding state, share (step 2.3),
 * under which onboarding is ACTIVE, never released and not held, at the version reviewed
 * (STALE_VERSION); the product, update (step 6), under which the line's OPENING is reversed and no line
 * replaces it yet (STALE_VERSION), the product has no effective entry and only OPENINGs and their
 * REVERSALs (VALIDATION_FAILED with `inventory.opening_entry.not_eligible`; G-22, G-24), and the product
 * is active, not blocked and at the version reviewed, the quantity within its precision; then its balance
 * and its valuation, update (step 7). Every OPENING of the product is reversed, so nothing is on hand
 * and the replacement starts a new pool of the logical ledger (ADR-008 section 4.1). A second
 * replacement of the same line, concurrent or not, finds it replaced (RACE-31), and the unique key of
 * the replaced line refuses one that could pass.
 *
 * Write steps (TEST_STRATEGY TS-21): the balance, the valuation, the entry, the line, the OPENING, the
 * evidence, the audit event, then the result.
 */
final class ReplaceOpeningStock extends CommandAction
{
    public const string OPERATION = 'opening_stock.replace';

    public function __construct(
        CommandServices $services,
        private readonly OnboardingState $onboarding,
        private readonly PostingGuard $guard,
        private readonly OpeningStock $entries,
        private readonly StockLedger $ledger,
        private readonly CostEvidence $evidence,
        private readonly InventoryValuations $valuations,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap15];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap15)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => ConfirmOpeningStock::ONBOARDING_PAGE];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $lineId = $payload['replacesLineId'] ?? null;
        $quantity = $payload['quantity'] ?? null;
        $reference = $payload['countReference'] ?? null;
        $given = $payload['cost'] ?? null;
        $reason = $payload['reason'] ?? null;
        $expected = $context->envelope->expected['productVersion'] ?? null;

        if (! is_int($lineId) || ! is_string($quantity) || ! is_string($reference) || ! is_array($given) || ! is_string($reason) || ! is_int($expected)) {
            throw new LogicException('The payload and the versions reviewed were validated before the Action.');
        }

        $cost = AcquisitionCost::fromPayload($given);

        // The line, a fact, read without a lock to choose its product's guard (SCH-31).
        $line = $this->entries->line($lineId) ?? throw CommandRefused::of(ErrorCode::NotFound);

        $status = $this->onboarding->lockForEntry();
        $this->services->points->afterLock('2.3');
        OnboardingChecks::requireOpen($status, $context->envelope->expected);

        $products = $this->guard->lockProducts([$line->productId]);
        $this->services->points->afterLock('6');

        // Read again under the guard, which every reversal of its OPENING and every replacement holds.
        $held = $this->entries->line($lineId) ?? throw new LogicException('An opening line is never removed.');

        if (! $held->reversed || $held->replaced) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $entry = $this->entries->entriesOf([$held->productId])[$held->productId] ?? throw new LogicException('A locked product has its onboarding facts.');

        if ($entry->eligibility !== OnboardingEntry::REPLACEABLE || $entry->lineId !== $held->id) {
            throw OnboardingChecks::refusedAt('payload.replacesLineId', 'inventory.opening_entry.not_eligible');
        }

        $this->guard->requirePostable([$held->productId], $products, [$held->productId => $expected]);
        $product = $products[$held->productId];
        $problem = OnboardingChecks::quantityProblem($quantity, $product->quantityPrecision, 'payload.quantity', false);

        if ($problem !== null) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.quantity' => $problem]);
        }

        $counted = Quantity::of($quantity, $product->quantityPrecision);

        $before = $this->ledger->lockPositions([$held->productId => $product])[$held->productId] ?? throw new LogicException('A position is read for its product.');
        $valuation = $this->valuations->lock([$held->productId])[$held->productId] ?? throw new LogicException('A valuation is read for its product.');
        $this->services->points->afterLock('7');

        if (! $before->stored || ! $before->onHand->isZero()) {
            throw new LogicException('A product whose OPENINGs are all reversed holds nothing (G-24).');
        }

        try {
            $after = $before->moved($counted);
        } catch (NumberOutOfRange) {
            throw new LogicException('A line quantity from nothing stays within the range of stock.');
        }

        try {
            $published = $valuation->increased($before->onHand, $cost->cost, $after->lastSequence);
        } catch (NumberOutOfRange) {
            throw OnboardingChecks::refusedAt('payload.cost.amount', 'ux.validation.money_max');
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');

        $this->ledger->writePosition($after, $at);
        $this->written();

        $this->valuations->publish($published, $at);
        $this->written();

        $replacement = $this->entries->insertEntry(1, $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $newLine = $this->entries->insertLine($replacement, 1, $held->productId, $counted, $reference, $held, $reason);
        $this->written();

        $movement = $this->ledger->appendOpening($product, $after->lastSequence, $counted, $newLine, true, $actor->id, $commandId, $at);
        $this->written();

        $evidence = $this->evidence->insert($movement, $held->productId, StockLedger::OPENING, $counted, $cost, $reference, $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        // The cost is the Owner's alone in the audit (BR-AUD-02, BR-COST-07).
        $this->services->audit->record(
            AuditCategory::Business,
            'OPENING_STOCK_REPLACE',
            Actor::user($actor->id, $actor->display_name),
            [
                'replacesLineId' => $held->id,
                'replacesMovementId' => $held->movementId,
                'lineId' => $newLine,
                'movementId' => $movement,
                'quantity' => $counted->toCanonical(),
                'countReference' => $reference,
                'productSequence' => $after->lastSequence,
            ],
            ['evidenceId' => $evidence, 'status' => $cost->status(), 'amount' => $cost->amount(), 'zeroNote' => $cost->zeroNote],
            subject: ['type' => ConfirmOpeningStock::ENTRY, 'id' => $replacement],
            reason: $reason,
            commandId: $commandId,
            productId: $held->productId,
        );
        $this->written();

        return new StoredResult(ConfirmOpeningStock::ENTRY, $replacement);
    }
}
