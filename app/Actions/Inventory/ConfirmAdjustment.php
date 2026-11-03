<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\GuardedProduct;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\Allocations\IssueAllocation;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\CostEvidence;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\Adjustments;
use App\Domain\Inventory\PostingGuard;
use App\Domain\Inventory\StockLedger;
use App\Domain\Inventory\StockPosition;
use App\Models\User;
use App\Support\Formatting\Format;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use App\Support\Validation\QuantityRule;
use LogicException;

/**
 * RC-09 `adjustment.confirm` (INTERACTION_CONTRACTS RC-09 and section 1.5; TECHNICAL_RULES
 * section 7.2, "Receiving confirmation, manual issue, adjustment"; BR-INV-06 to BR-INV-11,
 * BR-INV-31 to BR-INV-33; BR-COST-02 to BR-COST-05, BR-COST-10 to BR-COST-16; ADR-008 sections 2,
 * 4.1 and 4.2; WF-22; G-02, G-13, G-14; CAP-16). The Owner alone, with recent authentication, which
 * the route requires, adjusts stock to a count: a reason, the count reference and a reference, all
 * mandatory, and up to 200 lines, each an increase or a decrease of a positive quantity. Each line
 * posts one ADJUSTMENT of its signed quantity, in the order of the lines: an increase with its
 * acquisition cost evidence, KNOWN or UNKNOWN, an explicit zero with its evidence or reason; a
 * decrease with its issue-cost allocation, the fact and its equal projection, computed from the
 * valuation the line before it left, as a manual issue's (BR-INV-32). No adjustment makes stock
 * negative: the decreases of each product added together must fit the stock before the
 * adjustment, or the whole adjustment is refused INSUFFICIENT_STOCK, every decrease of a short
 * product named with the stock available (BR-INV-33, BR-INV-07). A negative adjustment never
 * counts as cost of goods sold; no Sale, Payment or receipt is created.
 *
 * The locks in the order of TR §7.1: the onboarding state, share (step 2.3); the products,
 * update, ascending (step 6), under which every check is made (TR-VAL-08); their balances, then
 * their valuations, update, ascending (step 7). Every position, cost, allocation and valuation is
 * computed before anything is written, so that a value past its range refuses the whole command.
 * A movement changes no field of its product, so no version moves.
 *
 * Write steps (TEST_STRATEGY TS-21): each product's balance, each product's valuation, the
 * adjustment; for each line its row, its movement, and its evidence or its allocation with its
 * projection; the audit event, then the result.
 */
final class ConfirmAdjustment extends CommandAction
{
    public const string OPERATION = 'adjustment.confirm';

    /** TECHNICAL_RULES section 11.2. */
    public const int MAX_LINES = 200;

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    private const string ADJUSTMENT = 'adjustment';

    /** RP-402 (ROUTE_CONTRACTS section 2), filtered to the adjusted products. */
    private const string HISTORY_PAGE = '/stock/history?product=';

    public function __construct(
        CommandServices $services,
        private readonly PostingGuard $guard,
        private readonly StockLedger $ledger,
        private readonly Adjustments $adjustments,
        private readonly CostEvidence $evidence,
        private readonly IssueAllocations $allocations,
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
        return [Capability::Cap16];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap16)) {
            return null;
        }

        $products = array_map(static fn (int $id): string => (string) $id, $this->adjustments->productsOf($result->ref));

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::HISTORY_PAGE.implode(',', $products)];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $lines = AdjustmentLine::allOf($payload['lines'] ?? null);
        $reason = $payload['reason'] ?? null;
        $countReference = $payload['countReference'] ?? null;
        $reference = $payload['reference'] ?? null;

        if (! is_string($reason) || ! is_string($countReference) || ! is_string($reference)) {
            throw new LogicException('The reason, the count reference and the reference were validated before the Action.');
        }

        $this->guard->requireOnboardingOff();
        $this->services->points->afterLock('2.3');

        $lineProducts = array_map(static fn (AdjustmentLine $line): int => $line->productId, $lines);
        $productIds = array_values(array_unique($lineProducts));
        sort($productIds);
        $products = $this->guard->lockProducts($productIds);
        $this->services->points->afterLock('6');
        $this->guard->requirePostable($lineProducts, $products, self::expectedVersions($context->envelope->expected));
        $quantities = self::quantitiesWithinPrecision($lines, $products);

        $positions = $this->ledger->lockPositions($products);
        $valuations = $this->valuations->lock($productIds);
        $this->services->points->afterLock('7');

        self::requireStock($lines, $quantities, $positions);

        $sequences = [];
        $computed = [];

        foreach ($lines as $index => $line) {
            $before = $positions[$line->productId];

            try {
                $positions[$line->productId] = $before->moved($line->increase ? $quantities[$index] : $quantities[$index]->negated());
            } catch (NumberOutOfRange) {
                if (! $line->increase) {
                    throw new LogicException('The stock was checked for the decreases of each product added together (G-02).');
                }

                throw self::refusedAt('payload.lines.'.(string) $index.'.quantity', 'ux.validation.quantity_max');
            }

            $sequences[$index] = $positions[$line->productId]->lastSequence;

            if ($line->increase) {
                $cost = $line->cost ?? throw new LogicException('An increase carries its cost.');

                try {
                    $valuations[$line->productId] = $valuations[$line->productId]->increased($before->onHand, $cost->cost, $sequences[$index]);
                } catch (NumberOutOfRange) {
                    throw self::refusedAt('payload.lines.'.(string) $index.'.cost.amount', 'ux.validation.money_max');
                }

                continue;
            }

            try {
                $computed[$index] = IssueAllocation::compute($valuations[$line->productId], $before->onHand, $quantities[$index], $sequences[$index]);
            } catch (NumberOutOfRange) {
                // A decrease takes at most the value it reduces, which was within its range.
                throw new LogicException('An allocation is at most the value of the valuation before it.');
            }

            $valuations[$line->productId] = $computed[$index]->valuation;
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');

        foreach ($positions as $position) {
            $this->ledger->writePosition($position, $at);
            $this->written();
        }

        foreach ($valuations as $valuation) {
            $this->valuations->publish($valuation, $at);
            $this->written();
        }

        $adjustment = $this->adjustments->insertAdjustment($reason, $countReference, $reference, count($lines), $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $safe = [];
        $costs = [];

        foreach ($lines as $index => $line) {
            $lineNo = $index + 1;
            $signed = $line->increase ? $quantities[$index] : $quantities[$index]->negated();
            $lineId = $this->adjustments->insertLine($adjustment, $lineNo, $line->productId, $signed);
            $this->written();

            $movement = $this->ledger->appendAdjustment($products[$line->productId], $sequences[$index], $signed, $lineId, $actor->id, $commandId, $at);
            $this->written();

            if ($line->increase) {
                $cost = $line->cost ?? throw new LogicException('An increase carries its cost.');
                $evidence = $this->evidence->insert($movement, $line->productId, StockLedger::ADJUSTMENT, $quantities[$index], $cost, $reference, $actor->id, $actor->display_name, $commandId, $at);
                $costs[] = ['lineNo' => $lineNo, 'evidenceId' => $evidence, 'status' => $cost->status(), 'amount' => $cost->amount(), 'zeroNote' => $cost->zeroNote];
            } else {
                $allocation = $this->allocations->record($movement, $line->productId, StockLedger::ADJUSTMENT, $quantities[$index], $sequences[$index], $computed[$index], $at);
                $costs[] = ['lineNo' => $lineNo, 'allocationId' => $allocation, 'status' => $computed[$index]->status(), 'amount' => $computed[$index]->canonicalAmount(), 'basedOnPublicationNo' => $computed[$index]->basedOnPublicationNo];
            }

            $this->written();

            $safe[] = ['lineNo' => $lineNo, 'productId' => $line->productId, 'quantity' => $signed->toCanonical(), 'movementId' => $movement, 'productSequence' => $sequences[$index]];
        }

        // Costs and allocations are the Owner's alone in the audit (BR-AUD-02, BR-COST-07).
        $this->services->audit->record(
            AuditCategory::Business,
            'ADJUSTMENT_CONFIRM',
            Actor::user($actor->id, $actor->display_name),
            ['reason' => $reason, 'countReference' => $countReference, 'lineCount' => count($lines), 'lines' => $safe],
            ['lines' => $costs],
            subject: ['type' => self::ADJUSTMENT, 'id' => $adjustment],
            reference: $reference,
            commandId: $commandId,
            productId: count($productIds) === 1 ? $productIds[0] : null,
        );
        $this->written();

        return new StoredResult(self::ADJUSTMENT, $adjustment);
    }

    /**
     * G-02, BR-INV-07, BR-INV-33: the stock of each product before the adjustment suffices for its
     * decreases added together, before any write, whatever increases of it the adjustment also
     * holds; otherwise every decrease of a short product is refused with the stock available, in
     * the product's unit (INTERACTION_CONTRACTS section 1.6).
     *
     * @param  list<AdjustmentLine>  $lines
     * @param  array<int, Quantity>  $quantities
     * @param  array<int, StockPosition>  $positions
     *
     * @throws CommandRefused INSUFFICIENT_STOCK
     */
    private static function requireStock(array $lines, array $quantities, array $positions): void
    {
        $totals = [];

        foreach ($lines as $index => $line) {
            if (! $line->increase) {
                $totals[$line->productId] = isset($totals[$line->productId]) ? $totals[$line->productId]->plus($quantities[$index]) : $quantities[$index];
            }
        }

        $short = [];

        foreach ($lines as $index => $line) {
            $onHand = $positions[$line->productId]->onHand;

            if (! $line->increase && $totals[$line->productId]->compareTo($onHand) > 0) {
                $short[$index] = ['productId' => $line->productId, 'available' => $onHand->toCanonical()];
            }
        }

        if ($short !== []) {
            throw new CommandRefused(ErrorCode::InsufficientStock, lines: $short);
        }
    }

    /**
     * The version of each product as the user reviewed it, from `expected.products`.
     *
     * @param  array<array-key, mixed>  $expected
     * @return array<int, int>
     */
    private static function expectedVersions(array $expected): array
    {
        $products = $expected['products'] ?? null;

        if (! is_array($products)) {
            throw new LogicException('The expected versions were validated before the Action.');
        }

        $versions = [];

        foreach ($products as $product) {
            $values = is_array($product) ? $product : [];
            $id = $values['productId'] ?? null;
            $version = $values['version'] ?? null;

            if (! is_int($id) || ! is_int($version)) {
                throw new LogicException('An expected version was validated before the Action.');
            }

            $versions[$id] = $version;
        }

        return $versions;
    }

    /**
     * Each line's quantity within its product's precision, which only its lock can tell
     * (BR-INV-06; G-11), refused at the field of every line that it does not fit.
     *
     * @param  list<AdjustmentLine>  $lines
     * @param  array<int, GuardedProduct>  $products
     * @return array<int, Quantity>
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function quantitiesWithinPrecision(array $lines, array $products): array
    {
        $quantities = [];
        $fields = [];

        foreach ($lines as $index => $line) {
            $precision = $products[$line->productId]->quantityPrecision;
            $path = 'payload.lines.'.(string) $index.'.quantity';
            $problem = (new QuantityRule($precision))->problem($line->quantity, $path);

            if ($problem !== null) {
                $fields[$path] = [['key' => 'ux.validation.'.$problem, 'params' => $problem === 'quantity_decimals' ? ['desimal' => Format::count($precision)] : []]];

                continue;
            }

            $quantities[$index] = Quantity::of($line->quantity, $precision)->assertLine();
        }

        if ($fields !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: $fields);
        }

        return $quantities;
    }

    private static function refusedAt(string $path, string $key): CommandRefused
    {
        return new CommandRefused(ErrorCode::ValidationFailed, fields: [$path => [['key' => $key, 'params' => []]]]);
    }
}
