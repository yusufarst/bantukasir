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
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\ManualIssues;
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
 * RC-08 `manual_issue.confirm` (INTERACTION_CONTRACTS RC-08; TECHNICAL_RULES section 7.2,
 * "Receiving confirmation, manual issue, adjustment"; BR-INV-06 to BR-INV-11, BR-INV-18 to
 * BR-INV-22; BR-COST-10 to BR-COST-16; ADR-008 sections 2, 4.1, 4.2 and 5; D-018, D-060;
 * G-02, G-13, G-14; CAP-13). The Owner and OPERATIONS_ADMIN record goods that leave stock for a
 * reason other than a sale, one of the five of BR-INV-19, with an optional note: each line posts
 * one MANUAL_ISSUE with its issue-cost allocation, the fact and its equal projection. A line of a
 * product may repeat: the lines of each product are added together for the stock check
 * (BR-INV-07), and each posts its own movement, in the order of the lines, its allocation taken
 * from the valuation the line before it left. Insufficient stock refuses the whole issue, every
 * line of a short product named with the stock available; a warning is never an override
 * (BR-INV-20). No Sale, no Payment, no receipt and no cost of goods sold is created (BR-INV-22).
 *
 * The locks in the order of TR §7.1: the onboarding state, share (step 2.3); the products,
 * update, ascending (step 6), under which every check is made (TR-VAL-08); their balances, then
 * their valuations, update, ascending (step 7). Every position, allocation and valuation is
 * computed before anything is written. A movement changes no field of its product, so no version
 * moves.
 *
 * Write steps (TEST_STRATEGY TS-21): each product's balance, each product's valuation, the issue;
 * for each line its row, its movement and its allocation, the fact with its projection; the audit
 * event, then the result.
 */
final class ConfirmManualIssue extends CommandAction
{
    public const string OPERATION = 'manual_issue.confirm';

    /** TECHNICAL_RULES section 11.2. */
    public const int MAX_LINES = 200;

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    private const string ISSUE = 'manual_issue';

    /** RP-409 of an issue (ROUTE_CONTRACTS section 2). */
    private const string ISSUE_PAGE = '/stock/issues/';

    public function __construct(
        CommandServices $services,
        private readonly PostingGuard $guard,
        private readonly StockLedger $ledger,
        private readonly ManualIssues $issues,
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
        return [Capability::Cap13];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap13)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::ISSUE_PAGE.(string) $result->ref];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $lines = IssueLine::allOf($payload['lines'] ?? null);
        $reasonCode = $payload['reasonCode'] ?? null;
        $note = $payload['note'] ?? null;
        $note = is_string($note) && $note !== '' ? $note : null;

        if (! is_string($reasonCode)) {
            throw new LogicException('The reason was validated before the Action.');
        }

        $this->guard->requireOnboardingOff();
        $this->services->points->afterLock('2.3');

        $lineProducts = array_map(static fn (IssueLine $line): int => $line->productId, $lines);
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
                $positions[$line->productId] = $before->moved($quantities[$index]->negated());
            } catch (NumberOutOfRange) {
                throw new LogicException('The stock was checked for the lines of each product added together (G-02).');
            }

            $sequences[$index] = $positions[$line->productId]->lastSequence;

            try {
                $computed[$index] = IssueAllocation::compute($valuations[$line->productId], $before->onHand, $quantities[$index], $sequences[$index]);
            } catch (NumberOutOfRange) {
                // An issue takes at most the value it reduces, which was within its range.
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

        $issue = $this->issues->insertIssue($reasonCode, $note, count($lines), $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $safe = [];
        $costs = [];

        foreach ($lines as $index => $line) {
            $lineNo = $index + 1;
            $lineId = $this->issues->insertLine($issue, $lineNo, $line->productId, $quantities[$index]);
            $this->written();

            $movement = $this->ledger->appendManualIssue($products[$line->productId], $sequences[$index], $quantities[$index], $lineId, $actor->id, $commandId, $at);
            $this->written();

            $allocation = $this->allocations->record($movement, $line->productId, StockLedger::MANUAL_ISSUE, $quantities[$index], $sequences[$index], $computed[$index], $at);
            $this->written();

            $safe[] = ['lineNo' => $lineNo, 'productId' => $line->productId, 'quantity' => $quantities[$index]->toCanonical(), 'movementId' => $movement, 'productSequence' => $sequences[$index]];
            $costs[] = ['lineNo' => $lineNo, 'allocationId' => $allocation, 'status' => $computed[$index]->status(), 'amount' => $computed[$index]->canonicalAmount(), 'basedOnPublicationNo' => $computed[$index]->basedOnPublicationNo];
        }

        // The allocations are the Owner's alone in the audit (BR-AUD-02, BR-COST-07).
        $this->services->audit->record(
            AuditCategory::Business,
            'MANUAL_ISSUE_CONFIRM',
            Actor::user($actor->id, $actor->display_name),
            ['reasonCode' => $reasonCode, 'note' => $note, 'lineCount' => count($lines), 'lines' => $safe],
            ['lines' => $costs],
            subject: ['type' => self::ISSUE, 'id' => $issue],
            commandId: $commandId,
            productId: count($productIds) === 1 ? $productIds[0] : null,
        );
        $this->written();

        return new StoredResult(self::ISSUE, $issue);
    }

    /**
     * G-02, BR-INV-07, BR-INV-20: the stock of each product suffices for its lines added
     * together, before any write; otherwise every line of a short product is refused with the
     * stock available, in the product's unit (INTERACTION_CONTRACTS section 1.6).
     *
     * @param  list<IssueLine>  $lines
     * @param  array<int, Quantity>  $quantities
     * @param  array<int, StockPosition>  $positions
     *
     * @throws CommandRefused INSUFFICIENT_STOCK
     */
    private static function requireStock(array $lines, array $quantities, array $positions): void
    {
        $totals = [];

        foreach ($lines as $index => $line) {
            $totals[$line->productId] = isset($totals[$line->productId]) ? $totals[$line->productId]->plus($quantities[$index]) : $quantities[$index];
        }

        $short = [];

        foreach ($lines as $index => $line) {
            $onHand = $positions[$line->productId]->onHand;

            if ($totals[$line->productId]->compareTo($onHand) > 0) {
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
     * @param  list<IssueLine>  $lines
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
}
