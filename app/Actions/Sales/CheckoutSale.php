<?php

namespace App\Actions\Sales;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Cash\GuardedSession;
use App\Domain\Cash\Sessions;
use App\Domain\Catalog\GuardedProduct;
use App\Domain\Catalog\Products;
use App\Domain\Catalog\SaleProduct;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileRevision;
use App\Domain\Costing\Allocations\IssueAllocation;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\PostingGuard;
use App\Domain\Inventory\StockLedger;
use App\Domain\Inventory\StockPosition;
use App\Domain\Sales\PaymentMethods;
use App\Domain\Sales\PricedLine;
use App\Domain\Sales\SaleNumber;
use App\Domain\Sales\Sales;
use App\Models\User;
use App\Support\Formatting\Format;
use App\Support\Numbers\Money;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use App\Support\Validation\QuantityRule;
use LogicException;

/**
 * RC-01 `sale.checkout` (INTERACTION_CONTRACTS RC-01; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2, "Sale checkout", sections 9 and 11.2, TR-TX-10; SCHEMA sections 5.9 and 6; SALES_CASH
 * BR-SALE-01 to BR-SALE-23, BR-PAY-01 to BR-PAY-07, BR-DOC-01, BR-CASH-10; INVENTORY BR-INV-07;
 * COSTING BR-COST-10 to BR-COST-16; ADR-008 sections 2, 4.1, 4.2 and 5; D-036, D-040, D-062, D-067,
 * D-077; G-01, G-02, G-04, G-10, G-11, G-13, G-14, G-16 to G-18, G-23; CAP-21; WF-05). The Owner, a
 * cashier or a combined account sells in their own OPEN cash session: one Sale, paid in full by
 * CASH or BANK_TRANSFER, its lines priced from the revisions the cashier reviewed, each issuing its
 * product through a SALE_ISSUE with its cost allocation, its receipt snapshot frozen from the shop
 * identity in force, its number, and the session's activity version one on — all in one transaction,
 * or nothing (BR-SALE-17). The request names a product at most once and holds at most 200 lines,
 * which its FormRequest refuses before any lock (D-067); a method outside the list is refused there
 * too, and again here (ARC-30).
 *
 * The locks in the order of TR §7.1: the onboarding state, share (step 2.3); the BusinessProfile
 * pointer, share (step 2.4); the actor's own session, update (step 4); the products, update,
 * ascending (step 6), under which, in the order of RC-01, each product exists, is active, is not
 * blocked, takes its quantity within its precision and stands at the price revision reviewed; their
 * balances, then their valuations, update, ascending (step 7), under which the stock suffices. Then
 * the line totals, half-up once, and the Sale total, their sum; the payment, CASH tendered at least
 * the total. Every position, allocation and valuation is computed before anything is written, and
 * the number is taken at step 8 next to the clock, immediately before the Sale (SCH-21). The product
 * guard is the replay's too: a Sale waits for a running replay of its products, or is refused
 * SERVICE_BUSY past the lock timeout, and a replay waits for the Sale (RACE-11).
 *
 * The cashier is the actor, never a field of the request (TR-VAL-03), and every name is frozen from
 * the actor's row that the guard holds (G-16). The answer's location is RN-01, the user's home, until
 * TASK-029 delivers RP-103 (EXECUTION_CONTEXT EXC-52).
 *
 * Write steps (TEST_STRATEGY TS-21): the Sale; each line; the Payment; the receipt snapshot; the
 * running total; each product's balance; each product's valuation; for each line its SALE_ISSUE and
 * its allocation, the fact with its projection; the session's activity version; the audit event,
 * then the result.
 */
final class CheckoutSale extends CommandAction
{
    public const string OPERATION = 'sale.checkout';

    /** TECHNICAL_RULES section 11.2: at most 200 lines, one per product (D-067). */
    public const int MAX_LINES = 200;

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    public const string RESULT_TYPE = 'sale';

    /** RN-01, until RP-103 exists (TASK-029). */
    public const string LOCATION = '/';

    public function __construct(
        CommandServices $services,
        private readonly PostingGuard $guard,
        private readonly BusinessProfiles $profiles,
        private readonly Sessions $sessions,
        private readonly Products $products,
        private readonly StockLedger $ledger,
        private readonly InventoryValuations $valuations,
        private readonly IssueAllocations $allocations,
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
     * The Sale, its number and its page for its cashier, and for the Owner, who sees every Sale
     * (CAP-21 own, CAP-22; BR-DOC-06); nobody else (SEC-AZ-06).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $sale = $result->ref === null ? null : $this->sales->reference($result->ref);

        if ($sale === null) {
            return null;
        }

        $own = $sale->actorUserId === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);

        if (! $own && ! Capabilities::allows($viewer, Capability::Cap22)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $sale->id, 'number' => $sale->number, 'location' => self::LOCATION];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $request = CheckoutPayload::of($context->envelope->payload, $context->envelope->expected);
        $lines = $request->lines;

        $this->guard->requireOnboardingOff();
        $this->services->points->afterLock('2.3');

        $revision = $this->revisionInForce();
        $this->services->points->afterLock('2.4');

        $held = $this->sessions->lockForSale($request->sessionId, $actor->id);
        $this->services->points->afterLock('4');
        $session = $this->ownOpenSession($held, $actor->id);

        $lineProducts = array_map(static fn (CheckoutLine $line): int => $line->productId, $lines);
        $productIds = array_values(array_unique($lineProducts));
        sort($productIds);
        $products = $this->products->lockForSale($productIds);
        $this->services->points->afterLock('6');
        $guarded = array_map(static fn (SaleProduct $product): GuardedProduct => $product->guarded, $products);
        $this->guard->requireActiveAndUnblocked($lineProducts, $guarded);
        $quantities = self::quantitiesWithinPrecision($lines, $products);
        self::requireReviewedPrices($lines, $products);

        $positions = $this->ledger->lockPositions($guarded);
        $valuations = $this->valuations->lock($productIds);
        $this->services->points->afterLock('7');
        self::requireStock($lines, $quantities, $positions);

        $priced = self::priced($lines, $products, $quantities);
        $total = self::total($priced);
        $payment = PaymentMethods::pay($request->method, $total, $request->tendered, $request->reference);

        $sequences = [];
        $computed = [];

        foreach ($priced as $index => $line) {
            $productId = $line->product->guarded->id;
            $before = $positions[$productId];

            try {
                $positions[$productId] = $before->moved($line->quantity->negated());
            } catch (NumberOutOfRange) {
                throw new LogicException('The stock was checked for the lines of each product added together (G-02).');
            }

            $sequences[$index] = $positions[$productId]->lastSequence;

            try {
                $computed[$index] = IssueAllocation::compute($valuations[$productId], $before->onHand, $line->quantity, $sequences[$index]);
            } catch (NumberOutOfRange) {
                // An issue takes at most the value it reduces, which was within its range.
                throw new LogicException('An allocation is at most the value of the valuation before it.');
            }

            $valuations[$productId] = $computed[$index]->valuation;
        }

        // Step 8: the time, its business date and the counter next to each other, after every guard
        // and immediately before the Sale (SCH-21, TR-TX-10; G-18).
        $now = $this->services->clock->now();
        $date = BusinessDate::ofInstant($now);
        $at = $now->format('Y-m-d H:i:s.uP');
        $counter = $this->sales->nextCounter();
        $number = SaleNumber::of($date, $counter);

        $sale = $this->sales->insertSale($number, $counter, $date, $at, $session->id, $actor->id, $actor->display_name, $total, count($priced), $request->customerName, $commandId);
        $this->written();

        $lineIds = [];

        foreach ($priced as $index => $line) {
            $lineIds[$index] = $this->sales->insertLine($sale, $line);
            $this->written();
        }

        $this->sales->insertPayment($sale, $payment);
        $this->written();

        $this->sales->insertReceipt($sale, $number, $total, $session->id, $session->number, $actor->display_name, $revision, $at, $payment, $request->customerName, $priced);
        $this->written();

        $this->sales->insertCorrectionTotals($sale, $total, $at);
        $this->written();

        foreach ($positions as $position) {
            $this->ledger->writePosition($position, $at);
            $this->written();
        }

        foreach ($valuations as $valuation) {
            $this->valuations->publish($valuation, $at);
            $this->written();
        }

        $safe = [];
        $costs = [];

        foreach ($priced as $index => $line) {
            $product = $line->product->guarded;
            $movement = $this->ledger->appendSaleIssue($product, $sequences[$index], $line->quantity, $lineIds[$index], $actor->id, $commandId, $at);
            $this->written();

            $allocation = $this->allocations->record($movement, $product->id, StockLedger::SALE_ISSUE, $line->quantity, $sequences[$index], $computed[$index], $at, $lineIds[$index]);
            $this->written();

            $safe[] = [
                'lineNo' => $line->lineNo,
                'productId' => $product->id,
                'priceRevisionId' => $line->product->priceRevisionId,
                'unitPrice' => $line->product->price->toCanonical(),
                'quantity' => $line->quantity->toCanonical(),
                'lineTotal' => $line->lineTotal->toCanonical(),
                'movementId' => $movement,
                'productSequence' => $sequences[$index],
            ];
            $costs[] = ['lineNo' => $line->lineNo, 'allocationId' => $allocation, 'status' => $computed[$index]->status(), 'amount' => $computed[$index]->canonicalAmount(), 'basedOnPublicationNo' => $computed[$index]->basedOnPublicationNo];
        }

        $this->sessions->recordActivity($session->id, $at);
        $this->written();

        // The allocations are the Owner's alone in the audit (BR-AUD-02, BR-COST-07); the cashier
        // reads the rest of their own Sale's event (BR-AUD-04).
        $this->services->audit->record(
            AuditCategory::Business,
            'SALE_CHECKOUT',
            Actor::user($actor->id, $actor->display_name),
            [
                'number' => $number,
                'sessionNumber' => $session->number,
                'total' => $total->toCanonical(),
                'method' => $payment->method,
                'tendered' => $payment->tendered?->toCanonical(),
                'change' => $payment->change?->toCanonical(),
                'reference' => $payment->reference,
                'lineCount' => count($priced),
                'lines' => $safe,
            ],
            ['lines' => $costs],
            subject: ['type' => self::RESULT_TYPE, 'id' => $sale],
            commandId: $commandId,
            productId: count($productIds) === 1 ? $productIds[0] : null,
            cashSessionId: $session->id,
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $sale, ['number' => $number]);
    }

    /**
     * The revision in force under the pointer's share lock, which the receipt freezes (RACE-25). The
     * bootstrap wrote the first, and no command removes one (G-23): without one a Sale is refused
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

    /**
     * G-04, BR-SALE-02: the session that the cart names is the actor's own and OPEN. The actor's own
     * that closed meanwhile is SESSION_NOT_OPEN (RACE-08), and so is any other session while the actor
     * holds an OPEN one; without an OPEN session at all, NO_OPEN_SESSION. Another user's session is
     * never locked and answers as one that does not exist (SEC-AZ-03).
     *
     * @throws CommandRefused SESSION_NOT_OPEN or NO_OPEN_SESSION
     */
    private function ownOpenSession(?GuardedSession $held, int $actorId): GuardedSession
    {
        if ($held !== null && $held->open) {
            return $held;
        }

        if ($held === null && ! $this->sessions->openFor($actorId)) {
            throw CommandRefused::of(ErrorCode::NoOpenSession);
        }

        throw CommandRefused::of(ErrorCode::SessionNotOpen);
    }

    /**
     * Each line's quantity within its product's precision, which only its lock can tell (BR-INV-06;
     * G-11), refused at the field of every line that it does not fit.
     *
     * @param  list<CheckoutLine>  $lines
     * @param  array<int, SaleProduct>  $products
     * @return array<int, Quantity>
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function quantitiesWithinPrecision(array $lines, array $products): array
    {
        $quantities = [];
        $fields = [];

        foreach ($lines as $index => $line) {
            $precision = $products[$line->productId]->guarded->quantityPrecision;
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

    /**
     * BR-SALE-05, D-036, RACE-23: each product stands at the price revision that the cashier
     * reviewed; otherwise every changed line is refused with its product's price in force and that
     * price's revision, so that the total never changes silently (INTERACTION_CONTRACTS section 1.6;
     * BR-SALE-19). A revision of another product, or an older one, is such a change.
     *
     * @param  list<CheckoutLine>  $lines
     * @param  array<int, SaleProduct>  $products
     *
     * @throws CommandRefused PRICE_CHANGED
     */
    private static function requireReviewedPrices(array $lines, array $products): void
    {
        $changed = [];

        foreach ($lines as $index => $line) {
            $product = $products[$line->productId];

            if ($product->priceRevisionId !== $line->priceRevisionId) {
                $changed[$index] = ['productId' => $line->productId, 'price' => ['amount' => $product->price->toCanonical(), 'revisionId' => $product->priceRevisionId]];
            }
        }

        if ($changed !== []) {
            throw new CommandRefused(ErrorCode::PriceChanged, lines: $changed);
        }
    }

    /**
     * G-02, BR-INV-07, BR-SALE-09: the stock of each product suffices for its lines added together,
     * before any write; otherwise every line of a short product is refused with the stock available,
     * in the product's unit (INTERACTION_CONTRACTS section 1.6). Stock is never negative (BR-INV-04).
     *
     * @param  list<CheckoutLine>  $lines
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
     * Each line priced at its product's price in force, which is the one reviewed, its total
     * quantity × unit price rounded half-up once to whole rupiah (BR-SALE-06; BR-NUM-01); a line total
     * past the range of a stored amount is refused at its quantity (TECHNICAL_RULES section 11.1).
     *
     * @param  list<CheckoutLine>  $lines
     * @param  array<int, SaleProduct>  $products
     * @param  array<int, Quantity>  $quantities
     * @return list<PricedLine>
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function priced(array $lines, array $products, array $quantities): array
    {
        $priced = [];

        foreach ($lines as $index => $line) {
            $product = $products[$line->productId];

            try {
                $lineTotal = Money::lineTotal($quantities[$index], $product->price);
            } catch (NumberOutOfRange) {
                throw self::refusedAt('payload.lines.'.(string) $index.'.quantity', 'ux.validation.money_max');
            }

            $priced[] = new PricedLine($index + 1, $product, $quantities[$index], $lineTotal);
        }

        return $priced;
    }

    /**
     * G-01: the Sale total is the sum of its line totals (BR-SALE-06), within the range of a stored
     * amount, which a total above it refuses (TECHNICAL_RULES section 11.1).
     *
     * @param  list<PricedLine>  $priced
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function total(array $priced): Money
    {
        try {
            return Money::sum(...array_map(static fn (PricedLine $line): Money => $line->lineTotal, $priced))->assertStored();
        } catch (NumberOutOfRange) {
            throw self::refusedAt('payload.lines', 'ux.validation.money_max');
        }
    }

    /**
     * A refusal at one field, with its key of COPY_DECK section 16.1.
     */
    private static function refusedAt(string $path, string $key): CommandRefused
    {
        return new CommandRefused(ErrorCode::ValidationFailed, fields: [$path => [['key' => $key, 'params' => []]]]);
    }
}
