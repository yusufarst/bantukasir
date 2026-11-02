<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Role;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\GuardedProduct;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\CostEvidence;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\PostingGuard;
use App\Domain\Inventory\Receivings;
use App\Domain\Inventory\StockLedger;
use App\Models\User;
use App\Support\Formatting\Format;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use App\Support\Validation\QuantityRule;
use LogicException;

/**
 * RC-07 `receiving.confirm` (INTERACTION_CONTRACTS RC-07; TECHNICAL_RULES section 7.2,
 * "Receiving confirmation, manual issue, adjustment"; BR-INV-12 to BR-INV-17; BR-COST-02 to
 * BR-COST-05; ADR-008 sections 4.1 and 4.2; D-033, D-047, D-069 (a); CAP-11). The Owner and
 * OPERATIONS_ADMIN confirm a reviewed document: each line posts one RECEIPT, with its cost
 * evidence, KNOWN or UNKNOWN. An explicit zero is the Owner's alone, with its evidence or
 * reason: no V1 page offers it to staff, who mark the cost unknown (BR-COST-04; REQ-INV-04;
 * INFORMATION_ARCHITECTURE section 14). A line of a product may repeat: each posts its own
 * movement, in the order of the lines.
 *
 * The locks in the order of TR §7.1: the onboarding state, share (step 2.3); the products,
 * update, ascending (step 6), under which every check is made (TR-VAL-08); their balances,
 * then their valuations, update, ascending (step 7). Every position and valuation is computed
 * before anything is written, so that a value past its range refuses the whole command, and
 * the first rows of a product are inserted at step 7. The document date is stored apart and
 * moves no fact: the time is the server's and the order the sequence (BR-INV-05). A movement
 * changes no field of its product, so no version moves.
 *
 * Write steps (TEST_STRATEGY TS-21): each product's balance, each product's valuation, the
 * document; for each line its row, its movement and its evidence; the audit event, then the
 * result.
 */
final class ConfirmReceiving extends CommandAction
{
    public const string OPERATION = 'receiving.confirm';

    /** TECHNICAL_RULES section 11.2. */
    public const int MAX_LINES = 500;

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    private const string DOCUMENT = 'receiving_document';

    /** RP-406 of a document (ROUTE_CONTRACTS section 2), which TASK-022 builds. */
    private const string DOCUMENT_PAGE = '/stock/receivings/';

    public function __construct(
        CommandServices $services,
        private readonly PostingGuard $guard,
        private readonly StockLedger $ledger,
        private readonly Receivings $receivings,
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
        return [Capability::Cap11];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap11)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::DOCUMENT_PAGE.(string) $result->ref];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $lines = ReceivingLine::allOf($payload['lines'] ?? null);
        $reference = self::optionalText($payload['reference'] ?? null);
        $documentDate = self::optionalText($payload['documentDate'] ?? null);
        self::refuseZeroOfStaff($lines, $actor);

        $this->guard->requireOnboardingOff();
        $this->services->points->afterLock('2.3');

        $lineProducts = array_map(static fn (ReceivingLine $line): int => $line->productId, $lines);
        $productIds = array_values(array_unique($lineProducts));
        sort($productIds);
        $products = $this->guard->lockProducts($productIds);
        $this->services->points->afterLock('6');
        $this->guard->requirePostable($lineProducts, $products, self::expectedVersions($context->envelope->expected));
        $quantities = self::quantitiesWithinPrecision($lines, $products);

        $positions = $this->ledger->lockPositions($products);
        $valuations = $this->valuations->lock($productIds);
        $this->services->points->afterLock('7');

        $sequences = [];

        foreach ($lines as $index => $line) {
            $before = $positions[$line->productId];

            try {
                $positions[$line->productId] = $before->moved($quantities[$index]);
            } catch (NumberOutOfRange) {
                throw self::refusedAt('payload.lines.'.(string) $index.'.quantity', 'ux.validation.quantity_max');
            }

            $sequences[$index] = $positions[$line->productId]->lastSequence;

            try {
                $valuations[$line->productId] = $valuations[$line->productId]->increased($before->onHand, $line->cost->cost, $sequences[$index]);
            } catch (NumberOutOfRange) {
                throw self::refusedAt('payload.lines.'.(string) $index.'.cost.amount', 'ux.validation.money_max');
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

        $document = $this->receivings->insertDocument($reference, $documentDate, count($lines), $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $safe = [];
        $costs = [];

        foreach ($lines as $index => $line) {
            $lineNo = $index + 1;
            $lineId = $this->receivings->insertLine($document, $lineNo, $line->productId, $quantities[$index]);
            $this->written();

            $movement = $this->ledger->appendReceipt($products[$line->productId], $sequences[$index], $quantities[$index], $lineId, $actor->id, $commandId, $at);
            $this->written();

            $evidence = $this->evidence->insert($movement, $line->productId, StockLedger::RECEIPT, $quantities[$index], $line->cost, $reference, $actor->id, $actor->display_name, $commandId, $at);
            $this->written();

            $safe[] = ['lineNo' => $lineNo, 'productId' => $line->productId, 'quantity' => $quantities[$index]->toCanonical(), 'movementId' => $movement, 'productSequence' => $sequences[$index]];
            $costs[] = ['lineNo' => $lineNo, 'evidenceId' => $evidence, 'status' => $line->cost->status(), 'amount' => $line->cost->amount(), 'zeroNote' => $line->cost->zeroNote];
        }

        // Cost details are the Owner's alone in the audit (BR-AUD-02).
        $this->services->audit->record(
            AuditCategory::Business,
            'RECEIVING_CONFIRM',
            Actor::user($actor->id, $actor->display_name),
            ['lineCount' => count($lines), 'documentDate' => $documentDate, 'lines' => $safe],
            ['lines' => $costs],
            subject: ['type' => self::DOCUMENT, 'id' => $document],
            reference: $reference,
            commandId: $commandId,
            productId: count($productIds) === 1 ? $productIds[0] : null,
        );
        $this->written();

        return new StoredResult(self::DOCUMENT, $document);
    }

    /**
     * An explicit zero needs evidence or a reason confirmed by the Owner, and is refused from
     * anyone else at the amount, under the actor's lock (BR-COST-04; REQ-INV-04).
     *
     * @param  list<ReceivingLine>  $lines
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function refuseZeroOfStaff(array $lines, User $actor): void
    {
        if (in_array(Role::SuperAdmin, $actor->roles(), true)) {
            return;
        }

        $fields = [];

        foreach ($lines as $index => $line) {
            if ($line->cost->isExplicitZero()) {
                $fields['payload.lines.'.(string) $index.'.cost.amount'] = [['key' => 'costing.field.zero_disabled', 'params' => []]];
            }
        }

        if ($fields !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: $fields);
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
     * @param  list<ReceivingLine>  $lines
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

    private static function optionalText(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
