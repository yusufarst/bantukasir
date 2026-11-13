<?php

namespace App\Actions\Sales;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\Allocations\ReturnAllocation;
use App\Domain\Costing\Allocations\ReturnAllocations;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\PostingBlocks;
use App\Domain\Inventory\StockLedger;
use App\Domain\Sales\Corrections\CorrectedLine;
use App\Domain\Sales\Corrections\CorrectedSale;
use App\Domain\Sales\Corrections\CorrectionBounds;
use App\Domain\Sales\Corrections\LineTotals;
use App\Domain\Sales\Corrections\Payout;
use App\Domain\Sales\Corrections\SaleCorrections;
use App\Domain\Sales\Corrections\SaleLineFacts;
use App\Models\User;
use App\Support\Formatting\Format;
use App\Support\Numbers\Money;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use App\Support\Validation\QuantityRule;
use Closure;
use LogicException;

/**
 * The correction engine of a Sale, shared by RC-06 `sale_correction.other` (TASK-033) and RC-05
 * `sale_correction.drawer_funded` (TASK-034) (INTERACTION_CONTRACTS RC-05 and RC-06; TECHNICAL_RULES
 * section 7.2, "Owner-funded or BANK_TRANSFER refund or return" and "Drawer-funded refund or return";
 * SCHEMA sections 5.7 to 5.9; ADR-008 sections 3 to 5; SALES_CASH BR-RFD-01 to BR-RFD-17; COSTING
 * BR-COST-17 to BR-COST-19, BR-COST-36; D-035, D-061; G-06, G-10, G-11, G-13, G-14, G-16, G-18; CAP-24;
 * WF-11). The Owner alone, with recent authentication, which the route requires, corrects one Sale with a
 * mandatory reason: one command holds a refund, a return or both (BR-RFD-04). The original Sale, its lines,
 * its Payment and its receipt stay as they are; the correction is a new fact linked to them, dated by its
 * posting (BR-RFD-12, BR-COST-36).
 *
 * Before any lock, from facts that never change (SCH-31): the Sale exists (NOT_FOUND), each line names a
 * line of that Sale, and each quantity returned fits its product's precision, which a product keeps once
 * it has moved (BR-CAT-07; VALIDATION_FAILED at the field). The locks in the order of TR §7.1: the
 * onboarding state, share, when a saleable return is received (step 2.3); the session of RC-05 (step 4,
 * beforeSale()); the original Sale, update (step 5), under which the cumulative bounds of the Sale and of
 * each line are checked (BR-RFD-03; RACE-10), then onboarding off for a saleable return (ONBOARDING_FREEZE);
 * the products of the saleable returns, update, ascending (step 6), each not blocked (POSTING_BLOCKED) and
 * active to receive saleable stock (PRODUCT_INACTIVE); their balances, valuations and linked issue
 * projections (step 7). A refund without a return adds no stock, a not-saleable return adds no saleable
 * stock and restores no cost, and a saleable return posts its RETURN_RECEIPT with a return allocation linked
 * to the SALE_ISSUE allocation of its line, restoring that cost once by the cumulative rule (BR-RFD-11;
 * D-061). The product guard is the replay's too, so a return waits for a running replay of its product, or
 * is refused SERVICE_BUSY past the lock timeout, and a replay waits for the return (RACE-11).
 *
 * Write steps (TEST_STRATEGY TS-21): the correction; the refund and each of its lines; each return line;
 * the Sale's running total; each corrected line's running total; for each saleable return, its product's
 * balance and valuation, its RETURN_RECEIPT and its return allocation with its projection; then what
 * afterRecords() adds (RC-05's session); the audit event, then the result.
 */
abstract class CorrectionAction extends CommandAction
{
    /** TECHNICAL_RULES section 11.2: at most the lines of the original Sale, which holds at most 200. */
    public const int MAX_LINES = 200;

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    public const string RESULT_TYPE = 'sale_correction';

    public function __construct(
        CommandServices $services,
        protected readonly SaleCorrections $corrections,
        private readonly Products $products,
        private readonly OnboardingState $onboarding,
        private readonly PostingBlocks $blocks,
        private readonly StockLedger $ledger,
        private readonly InventoryValuations $valuations,
        private readonly IssueAllocations $issues,
        private readonly ReturnAllocations $returns,
    ) {
        parent::__construct($services);
    }

    /**
     * The payout of the correction's refund, decided under the original Sale's lock, or null when the
     * correction refunds no money (BR-RFD-05).
     */
    abstract protected function payout(CorrectionPayload $request, CorrectedSale $sale, Money $refund): ?Payout;

    /**
     * Between steps 2.3 and 5: RC-05's selected session and its checks (step 4). Nothing for RC-06, which
     * touches no session (D-035).
     */
    protected function beforeSale(CorrectionPayload $request, Money $refund): void {}

    /**
     * After the correction's records and before its audit event: RC-05's effect on its session. Nothing
     * for RC-06.
     */
    protected function afterRecords(string $at): void {}

    /**
     * The session that the audit event belongs to: RC-05's; none for RC-06.
     */
    protected function auditSession(): ?int
    {
        return null;
    }

    public function capabilities(): array
    {
        return [Capability::Cap24];
    }

    /**
     * RP-204, the correction recorded, for the Owner, who alone holds CAP-24 (ROUTE_CONTRACTS RC-05,
     * RC-06; SEC-AZ-06); nobody else.
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap24)) {
            return null;
        }

        $correction = $result->ref ?? throw new LogicException('A correction result names its correction.');
        $sale = $this->corrections->saleOf($correction) ?? throw new LogicException('A committed correction names its Sale.');

        return ['type' => $result->type, 'id' => $correction, 'location' => SaleCorrections::recordedPath($sale, $correction)];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $request = CorrectionPayload::of($context->envelope->payload);
        $refund = $request->refundTotal();

        // Facts read without a lock (SCH-31): the Sale, its lines and their products' precision.
        $sale = $this->corrections->sale($request->saleId) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $lines = $this->resolve($request, $this->corrections->linesOf($sale->id));
        $saleable = array_values(array_filter($lines, static fn (CorrectedLine $line): bool => $line->saleable->isPositive()));

        // Step 2.3, when a saleable return is received; its state is judged after the bounds (RC-06).
        $onboarding = null;

        if ($saleable !== []) {
            $onboarding = $this->onboarding->lockShared() ?? throw new LogicException('The bootstrap inserts the onboarding state (SEC-OP-01).');
            $this->services->points->afterLock('2.3');
        }

        $this->beforeSale($request, $refund);

        // Step 5: the original Sale, then the cumulative bounds of the Sale and its lines (BR-RFD-03).
        $totals = $this->corrections->lockSale($sale->id) ?? throw new LogicException('A Sale has its running total (SCHEMA section 5.9).');
        $this->services->points->afterLock('5');
        $lineTotals = $this->corrections->lineTotals(array_map(static fn (CorrectedLine $line): int => $line->line->id, $lines));
        CorrectionBounds::require($lines, $lineTotals, $totals);

        if ($onboarding === OnboardingState::ACTIVE) {
            throw CommandRefused::of(ErrorCode::OnboardingFreeze);
        }

        $payout = $this->payout($request, $sale, $refund);

        if ($refund->isPositive() !== ($payout !== null)) {
            throw new LogicException('A correction pays out exactly the money it refunds (BR-RFD-05).');
        }

        // Steps 6 and 7, for the saleable returns.
        $postings = $saleable === [] ? [] : $this->post($saleable);

        return $this->record($actor, $commandId, $request, $sale, $lines, $lineTotals, $refund, $payout, $postings);
    }

    /**
     * The request's lines against the Sale's: each names a line of that Sale, and each quantity fits its
     * product's precision; a line of zeros is left out.
     *
     * @param  array<int, SaleLineFacts>  $facts
     * @return list<CorrectedLine>
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private function resolve(CorrectionPayload $request, array $facts): array
    {
        $resolved = [];
        $fields = [];

        foreach ($request->lines as $line) {
            $path = 'payload.lines.'.(string) $line->index;
            $fact = $facts[$line->saleLineId] ?? null;

            if ($fact === null) {
                $fields[$path.'.saleLineId'] = [['key' => 'ux.validation.choose_one', 'params' => []]];

                continue;
            }

            $product = $this->products->read($fact->productId) ?? throw new LogicException('A Sale line names its product (SCHEMA section 5.9).');
            $rule = new QuantityRule($product->quantityPrecision, zeroAllowed: true);
            $fits = true;

            foreach (['returnedSaleable' => $line->saleable, 'returnedNotSaleable' => $line->notSaleable] as $field => $value) {
                $problem = $rule->problem($value, $path.'.'.$field);

                if ($problem !== null) {
                    $fields[$path.'.'.$field] = [['key' => 'ux.validation.'.$problem, 'params' => $problem === 'quantity_decimals' ? ['desimal' => Format::count($product->quantityPrecision)] : []]];
                    $fits = false;
                }
            }

            if ($fits) {
                $resolved[] = new CorrectedLine($line->index, $fact, $product->quantityPrecision, $product->baseUnit, $line->refund, Quantity::of($line->saleable, $product->quantityPrecision), Quantity::of($line->notSaleable, $product->quantityPrecision));
            }
        }

        if ($fields !== []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: $fields);
        }

        $moving = array_values(array_filter($resolved, static fn (CorrectedLine $line): bool => $line->moves()));

        // Its FormRequest refuses a correction that refunds no money and returns no goods; so does the
        // Action, which every caller reaches (ARC-30).
        if ($moving === []) {
            throw new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.lines' => [['key' => 'ux.validation.lines_min', 'params' => []]]]);
        }

        return $moving;
    }

    /**
     * Steps 6 and 7 of the saleable returns: the products under their update locks, ascending, each not
     * blocked and active, in the order of RC-06 (G-11); their balances, valuations and the SALE_ISSUE
     * allocations of their lines; then each RETURN_RECEIPT's position and return allocation, computed
     * before anything is written (ADR-008 sections 3 and 4.2). A Sale stores one line per product (D-067),
     * so each product has one saleable line here.
     *
     * @param  list<CorrectedLine>  $saleable
     * @return list<ReturnPosting>
     *
     * @throws CommandRefused POSTING_BLOCKED, PRODUCT_INACTIVE or VALIDATION_FAILED
     */
    private function post(array $saleable): array
    {
        $productIds = array_values(array_unique(array_map(static fn (CorrectedLine $line): int => $line->line->productId, $saleable)));
        sort($productIds);
        $held = $this->products->lockForPosting($productIds);
        $this->services->points->afterLock('6');

        $blocked = $this->blocks->blockedAmong(array_keys($held));
        self::refuseLines(ErrorCode::PostingBlocked, $saleable, static fn (CorrectedLine $line): bool => in_array($line->line->productId, $blocked, true));
        self::refuseLines(ErrorCode::ProductInactive, $saleable, static fn (CorrectedLine $line): bool => ! ($held[$line->line->productId] ?? throw new LogicException('A Sale line names its product.'))->active);

        $precisions = [];

        foreach ($saleable as $line) {
            $precisions[$line->line->id] = $line->precision;
        }

        $positions = $this->ledger->lockPositions($held);
        $valuations = $this->valuations->lock($productIds);
        $linked = $this->issues->ofSaleLines($precisions);
        $this->services->points->afterLock('7');

        $postings = [];

        foreach ($saleable as $line) {
            $product = $held[$line->line->productId] ?? throw new LogicException('A Sale line names its product.');
            $issue = $linked[$line->line->id] ?? throw new LogicException('A Sale line issued its product with its allocation (BR-SALE-09, BR-INV-08).');
            $before = $positions[$product->id] ?? throw new LogicException('A position is read for its product.');
            $valuation = $valuations[$product->id] ?? throw new LogicException('A valuation is read for its product.');
            $path = 'payload.lines.'.(string) $line->index.'.returnedSaleable';

            if ($product->quantityPrecision !== $line->precision) {
                throw new LogicException('A product keeps its precision once it has moved (BR-CAT-07).');
            }

            try {
                $after = $before->moved($line->saleable);
                $allocation = ReturnAllocation::compute($issue->projection, $issue->issued, $this->returns->returnedOf($issue->allocationId, $line->precision), $line->saleable, $valuation, $before->onHand, $after->lastSequence);
            } catch (NumberOutOfRange) {
                // A stock or a value past its range (TECHNICAL_RULES section 11.1).
                throw new CommandRefused(ErrorCode::ValidationFailed, fields: [$path => [['key' => 'ux.validation.quantity_max', 'params' => []]]]);
            }

            $postings[] = new ReturnPosting($line, $product, $after, $issue, $allocation);
        }

        return $postings;
    }

    /**
     * Step 8: every record of the correction, in its transaction, after every check.
     *
     * @param  list<CorrectedLine>  $lines
     * @param  array<int, LineTotals>  $lineTotals
     * @param  list<ReturnPosting>  $postings
     */
    private function record(User $actor, int $commandId, CorrectionPayload $request, CorrectedSale $sale, array $lines, array $lineTotals, Money $refund, ?Payout $payout, array $postings): StoredResult
    {
        // The time and its business date, the correction's posting date (BR-COST-36; G-18).
        $now = $this->services->clock->now();
        $date = BusinessDate::ofInstant($now);
        $at = $now->format('Y-m-d H:i:s.uP');

        $correction = $this->corrections->insertCorrection($sale->id, $date, $request->reason, $at, $actor->id, $actor->display_name, $commandId);
        $this->written();

        if ($payout !== null) {
            $refundId = $this->corrections->insertRefund($correction, $sale->id, $refund, $payout);
            $this->written();

            foreach ($lines as $line) {
                if ($line->refund->isPositive()) {
                    $this->corrections->insertRefundLine($refundId, $sale->id, $line->line->id, $line->refund);
                    $this->written();
                }
            }
        }

        $returnLines = [];

        foreach ($lines as $line) {
            foreach ([SaleCorrections::SALEABLE => $line->saleable, SaleCorrections::NOT_SALEABLE => $line->notSaleable] as $condition => $quantity) {
                if ($quantity->isPositive()) {
                    $returnLine = $this->corrections->insertReturnLine($correction, $sale->id, $line->line->id, $line->line->productId, $quantity, $condition);
                    $this->written();

                    if ($condition === SaleCorrections::SALEABLE) {
                        $returnLines[$line->index] = $returnLine;
                    }
                }
            }
        }

        if ($refund->isPositive()) {
            $this->corrections->addRefunded($sale->id, $refund, $at);
            $this->written();
        }

        foreach ($lines as $line) {
            $this->corrections->addToLine($line, array_key_exists($line->line->id, $lineTotals), $at);
            $this->written();
        }

        $moved = [];
        $restored = [];

        foreach ($postings as $posting) {
            $line = $posting->line;
            $returnLine = $returnLines[$line->index] ?? throw new LogicException('A saleable return has its return line.');
            $sequence = $posting->position->lastSequence;

            $this->ledger->writePosition($posting->position, $at);
            $this->written();

            $this->valuations->publish($posting->allocation->valuation, $at);
            $this->written();

            $movement = $this->ledger->appendReturnReceipt($posting->product, $sequence, $line->saleable, $returnLine, $actor->id, $commandId, $at);
            $this->written();

            $allocation = $this->returns->record($movement, $returnLine, $posting->product->id, $line->line->id, $posting->issue->allocationId, $line->saleable, $sequence, $posting->allocation, $at);
            $this->written();

            $moved[$line->index] = ['movementId' => $movement, 'productSequence' => $sequence];
            $restored[] = ['saleLineId' => $line->line->id, 'allocationId' => $allocation, 'issueAllocationId' => $posting->issue->allocationId, 'status' => $posting->allocation->status(), 'amount' => $posting->allocation->canonicalAmount(), 'basedOnPublicationNo' => $posting->allocation->basedOnPublicationNo];
        }

        $this->afterRecords($at);

        $returned = array_values(array_unique(array_map(static fn (CorrectedLine $line): int => $line->line->productId, array_filter($lines, static fn (CorrectedLine $line): bool => $line->returned()->isPositive()))));

        // The cost a return restored is the Owner's part of the event (BR-AUD-02, BR-COST-06).
        $this->services->audit->record(
            AuditCategory::Business,
            'SALE_CORRECTION',
            Actor::user($actor->id, $actor->display_name),
            [
                'saleId' => $sale->id,
                'saleNumber' => $sale->number,
                'businessDate' => $date->toString(),
                'refund' => $payout === null ? null : ['amount' => $refund->toCanonical(), 'payoutMethod' => $payout->method, 'fundingSource' => $payout->fundingSource, 'payoutReference' => $payout->reference],
                'lines' => array_map(static fn (CorrectedLine $line): array => [
                    'saleLineId' => $line->line->id,
                    'productId' => $line->line->productId,
                    'refundAmount' => $line->refund->toCanonical(),
                    'returnedSaleable' => $line->saleable->toCanonical(),
                    'returnedNotSaleable' => $line->notSaleable->toCanonical(),
                    'movementId' => $moved[$line->index]['movementId'] ?? null,
                    'productSequence' => $moved[$line->index]['productSequence'] ?? null,
                ], $lines),
            ],
            ['returns' => $restored],
            subject: ['type' => self::RESULT_TYPE, 'id' => $correction],
            reference: $sale->number,
            reason: $request->reason,
            commandId: $commandId,
            productId: count($returned) === 1 ? $returned[0] : null,
            cashSessionId: $this->auditSession(),
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $correction);
    }

    /**
     * The refusal of this code, naming every line whose product the test holds for, if any, by its
     * product (INTERACTION_CONTRACTS section 1.6).
     *
     * @param  list<CorrectedLine>  $lines
     * @param  Closure(CorrectedLine): bool  $refused
     *
     * @throws CommandRefused
     */
    private static function refuseLines(ErrorCode $code, array $lines, Closure $refused): void
    {
        $refusedLines = [];

        foreach ($lines as $line) {
            if ($refused($line)) {
                $refusedLines[$line->index] = ['productId' => $line->line->productId];
            }
        }

        if ($refusedLines !== []) {
            throw new CommandRefused($code, lines: $refusedLines);
        }
    }
}
