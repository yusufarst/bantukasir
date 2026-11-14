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
 * RC-11 `opening_stock.confirm` (INTERACTION_CONTRACTS RC-11; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2, "Opening stock"; BR-INV-23 to BR-INV-27, BR-INV-47; BR-COST-02 to BR-COST-04; ADR-008
 * section 4.2; WF-25; G-10, G-11, G-22, G-26; RACE-06; CAP-15). The Owner alone records the opening
 * stock of the shop's counted products, up to 100 rows, all or none: each positive row, with its count
 * reference and its acquisition cost KNOWN or UNKNOWN, posts one OPENING at the product's first place
 * with its cost evidence, its balance and its valuation; each zero row records a zero count on the
 * checklist and posts nothing (BR-INV-27). No recent authentication (ROUTE_CONTRACTS RC-11).
 *
 * The locks in the order of TR §7.1: the onboarding state, share (step 2.3), under which onboarding is
 * ACTIVE, never released and not held, at the version reviewed (STALE_VERSION; D-071, D-072); the
 * products, update, ascending (step 6), under which each is active, not blocked and at the version
 * reviewed, each quantity within its precision, and each without an effective onboarding entry (G-22):
 * a zero count already effective is VALIDATION_FAILED with `inventory.opening_entry.already_zero`, an
 * effective OPENING or another movement with `inventory.opening_entry.not_eligible`, and so is a positive
 * row for a product whose OPENING was reversed, which only RC-48 records again; then the balances and
 * valuations of the positive rows' products, update, ascending (step 7). A product's earlier movement
 * and its effective entry are read under its guard, which every posting, reversal and onboarding entry
 * of it holds, so two commands never record two entries of one product (RACE-06).
 *
 * Write steps (TEST_STRATEGY TS-21): the balance and the valuation of each positive row's product, the
 * entry; for each positive row its line, its OPENING and its evidence; for each zero row its zero count;
 * the audit event, then the result.
 */
final class ConfirmOpeningStock extends CommandAction
{
    public const string OPERATION = 'opening_stock.confirm';

    /** BR-INV-25; TECHNICAL_RULES section 11.2. */
    public const int MAX_ROWS = 100;

    /** RP-412 (ROUTE_CONTRACTS section 2), every onboarding command's location. */
    public const string ONBOARDING_PAGE = '/stock/onboarding';

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    public const string ENTRY = 'opening_stock_entry';

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

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::ONBOARDING_PAGE];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $rows = OpeningRow::allOf($context->envelope->payload['rows'] ?? null);

        $status = $this->onboarding->lockForEntry();
        $this->services->points->afterLock('2.3');
        OnboardingChecks::requireOpen($status, $context->envelope->expected);

        $rowProducts = array_map(static fn (OpeningRow $row): int => $row->productId, $rows);
        $productIds = array_values(array_unique($rowProducts));
        sort($productIds);
        $products = $this->guard->lockProducts($productIds);
        $this->services->points->afterLock('6');
        $this->guard->requirePostable($rowProducts, $products, self::expectedVersions($context->envelope->expected));
        $quantities = self::quantitiesWithinPrecision($rows, $products);
        self::requireEligible($rows, $quantities, $this->entries->entriesOf($productIds));

        $counted = [];

        foreach ($rows as $index => $row) {
            if ($quantities[$index]->isPositive()) {
                $counted[$row->productId] = $products[$row->productId];
            }
        }

        ksort($counted);
        $positions = $this->ledger->lockPositions($counted);
        $valuations = $this->valuations->lock(array_keys($counted));
        $this->services->points->afterLock('7');

        $sequences = [];

        foreach ($rows as $index => $row) {
            if (! $quantities[$index]->isPositive()) {
                continue;
            }

            $cost = $row->cost ?? throw new LogicException('A positive row carries its cost (BR-INV-25).');
            $before = $positions[$row->productId];

            try {
                $positions[$row->productId] = $before->moved($quantities[$index]);
            } catch (NumberOutOfRange) {
                throw new LogicException('A line quantity from nothing stays within the range of stock.');
            }

            $sequences[$index] = $positions[$row->productId]->lastSequence;

            try {
                $valuations[$row->productId] = $valuations[$row->productId]->increased($before->onHand, $cost->cost, $sequences[$index]);
            } catch (NumberOutOfRange) {
                throw OnboardingChecks::refusedAt('payload.rows.'.(string) $index.'.cost.amount', 'ux.validation.money_max');
            }
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

        $entry = $this->entries->insertEntry(count($rows), $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $safe = [];
        $costs = [];

        foreach ($rows as $index => $row) {
            $rowNo = $index + 1;

            if (! $quantities[$index]->isPositive()) {
                $zeroCount = $this->entries->insertZeroCount($row->productId, $row->countReference, $actor->id, $actor->display_name, $commandId, $at);
                $this->written();
                $safe[] = ['rowNo' => $rowNo, 'productId' => $row->productId, 'quantity' => '0', 'countReference' => $row->countReference, 'zeroCountId' => $zeroCount];

                continue;
            }

            $cost = $row->cost ?? throw new LogicException('A positive row carries its cost (BR-INV-25).');
            $reference = $row->countReference ?? throw new LogicException('A positive row carries its count reference (BR-INV-25).');
            $line = $this->entries->insertLine($entry, $rowNo, $row->productId, $quantities[$index], $reference);
            $this->written();

            $movement = $this->ledger->appendOpening($products[$row->productId], $sequences[$index], $quantities[$index], $line, false, $actor->id, $commandId, $at);
            $this->written();

            $evidence = $this->evidence->insert($movement, $row->productId, StockLedger::OPENING, $quantities[$index], $cost, $reference, $actor->id, $actor->display_name, $commandId, $at);
            $this->written();

            $safe[] = ['rowNo' => $rowNo, 'productId' => $row->productId, 'quantity' => $quantities[$index]->toCanonical(), 'countReference' => $reference, 'movementId' => $movement];
            $costs[] = ['rowNo' => $rowNo, 'evidenceId' => $evidence, 'status' => $cost->status(), 'amount' => $cost->amount(), 'zeroNote' => $cost->zeroNote];
        }

        // Costs are the Owner's alone in the audit (BR-AUD-02, BR-COST-07).
        $this->services->audit->record(
            AuditCategory::Business,
            'OPENING_STOCK_CONFIRM',
            Actor::user($actor->id, $actor->display_name),
            ['rowCount' => count($rows), 'rows' => $safe],
            ['rows' => $costs],
            subject: ['type' => self::ENTRY, 'id' => $entry],
            commandId: $commandId,
            productId: count($productIds) === 1 ? $productIds[0] : null,
        );
        $this->written();

        return new StoredResult(self::ENTRY, $entry);
    }

    /**
     * G-22, BR-INV-26, BR-INV-27: each row's product without an effective onboarding entry — a zero
     * count already effective `already_zero`, an effective OPENING or another movement `not_eligible` —
     * and a positive row only for a product with no movement at all, so that a product whose OPENING
     * was reversed is recorded again only by RC-48; every row concerned is refused at its product.
     *
     * @param  list<OpeningRow>  $rows
     * @param  array<int, Quantity>  $quantities
     * @param  array<int, OnboardingEntry>  $entries
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function requireEligible(array $rows, array $quantities, array $entries): void
    {
        $fields = [];

        foreach ($rows as $index => $row) {
            $entry = $entries[$row->productId] ?? throw new LogicException('A locked product has its onboarding facts.');
            $allowed = $quantities[$index]->isPositive() ? [OnboardingEntry::ELIGIBLE] : [OnboardingEntry::ELIGIBLE, OnboardingEntry::REPLACEABLE];

            if (in_array($entry->eligibility, $allowed, true)) {
                continue;
            }

            $key = $entry->eligibility === OnboardingEntry::ZERO_COUNTED ? 'inventory.opening_entry.already_zero' : 'inventory.opening_entry.not_eligible';
            $fields['payload.rows.'.(string) $index.'.productId'] = [['key' => $key, 'params' => []]];
        }

        if ($fields !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: $fields);
        }
    }

    /**
     * The version of each product as the Owner reviewed it, from `expected.products`.
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
     * Each row's quantity within its product's precision, zero for a zero count, which only its lock
     * can tell (BR-INV-06; G-11), refused at the field of every row that it does not fit.
     *
     * @param  list<OpeningRow>  $rows
     * @param  array<int, GuardedProduct>  $products
     * @return array<int, Quantity>
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function quantitiesWithinPrecision(array $rows, array $products): array
    {
        $quantities = [];
        $fields = [];

        foreach ($rows as $index => $row) {
            $precision = $products[$row->productId]->quantityPrecision;
            $path = 'payload.rows.'.(string) $index.'.quantity';
            $problem = OnboardingChecks::quantityProblem($row->quantity, $precision, $path, true);

            if ($problem !== null) {
                $fields[$path] = $problem;

                continue;
            }

            $quantities[$index] = Quantity::of($row->quantity, $precision);
        }

        if ($fields !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: $fields);
        }

        return $quantities;
    }
}
