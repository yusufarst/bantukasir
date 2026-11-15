<?php

namespace App\Actions\Operations;

use App\Actions\Commands\CommandTransaction;
use App\Database\CommandPoints;
use App\Database\SqlState;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Catalog\Barcodes\Barcodes;
use App\Domain\Catalog\Products;
use App\Domain\Costing\AcquisitionCost;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\Allocations\ReturnAllocations;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Costing\ProductValuation;
use App\Domain\Costing\Replay\ReplayRefused;
use App\Domain\Inventory\StockLedger;
use App\Domain\Inventory\StockPosition;
use App\Domain\Operations\Reconciliation\MappingReconciliation;
use App\Domain\Operations\Reconciliation\ProductReconciliation;
use App\Domain\Operations\Reconciliation\RepairRefused;
use App\Domain\Sales\Corrections\SaleCorrections;
use App\Support\Numbers\Cost;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * OP-07, the projection rebuild (SECURITY_MODEL SEC-OP-07 and section 14; ROUTE_CONTRACTS OP-07;
 * OPERATIONS_RUNBOOK RB-04 step 5; BR-INV-46; SCHEMA guard G-03; D-088; TASK-036 scope item 4). An operator
 * procedure, without an envelope (TR-CMD-07), on the Owner's request after a reconciliation incident:
 * the projections of one product, of one Sale or of one token, recomputed from the immutable facts under
 * the same guards as the commands that write them, and written through the services of the modules that
 * own them (STK-12), in one transaction:
 *
 * - a product: its guard (TR §7.1 step 6), then its balance, its valuation and the projections a replay
 *   may rewrite (step 7); the stock on hand and the last place from its movements, the valuation and
 *   every issue and return projection from its replay by the application's arithmetic (ADR-008 section
 *   5); a projection rewritten carries the valuation's next publication number (ADR-008 section 4.3);
 * - a Sale: the original Sale's lock (step 5); its running totals from its refund and return lines;
 * - a token: the guards of the products of its active row, of its unretired mapping and of its last
 *   mapping, which RC-30 corrects, ascending (step 6); its active row from its mapping facts.
 *
 * Facts are never changed. When the facts themselves fail validation — a history that cannot be replayed,
 * a refund that is not the sum of its lines, a running total beyond its bound, two unretired mappings of a
 * token — it refuses and changes nothing, and the incident stays open for the Owner (RB-04 step 5). A
 * rebuild that finds every projection equal writes nothing. The audit event PROJECTION_REBUILT names the
 * procedure as actor, with the operator's note; cost values are only in its Owner-only data.
 *
 * Write steps (TEST_STRATEGY TS-21), each counted where it writes: the projections, then the audit event.
 */
final class RebuildProjection
{
    public const string PROCESS = 'projection_rebuild';

    public const string PRODUCT = 'product';

    public const string SALE = 'sale';

    public const string TOKEN = 'token';

    /** The SQLSTATE of a check constraint's refusal: a running total beyond its bound. */
    private const string CHECK_VIOLATION = '23514';

    private int $writeStep = 0;

    public function __construct(
        private readonly Clock $clock,
        private readonly CommandPoints $points,
        private readonly AuditLog $audit,
        private readonly Products $products,
        private readonly StockLedger $ledger,
        private readonly InventoryValuations $valuations,
        private readonly IssueAllocations $allocations,
        private readonly ReturnAllocations $returns,
        private readonly ProductReconciliation $reconciliation,
        private readonly SaleCorrections $corrections,
        private readonly MappingReconciliation $mappings,
        private readonly Barcodes $barcodes,
    ) {}

    /**
     * Rebuilds the subject's projections; true when one of them changed, false when every one already
     * equalled its facts.
     *
     * @throws RepairRefused
     */
    public function __invoke(string $subject, int $id, string $operatorNote): bool
    {
        $this->writeStep = 0;

        try {
            return CommandTransaction::run(fn (): bool => match ($subject) {
                self::PRODUCT => $this->product($id, $operatorNote),
                self::SALE => $this->sale($id, $operatorNote),
                self::TOKEN => $this->token($id, $operatorNote),
                default => throw new LogicException('The subject of a rebuild is a product, a Sale or a token.'),
            }, true);
        } catch (Throwable $failure) {
            if (SqlState::of($failure) === self::CHECK_VIOLATION) {
                throw new RepairRefused('The facts give a running total beyond its bound: nothing was changed; the incident stays open for the Owner (RB-04 step 5).');
            }

            throw $failure;
        }
    }

    private function product(int $productId, string $note): bool
    {
        $product = $this->products->lockForPosting([$productId])[$productId] ?? throw new RepairRefused('No such product: nothing was changed.');
        $this->points->afterLock('6');
        $position = $this->ledger->lockPositions([$productId => $product])[$productId] ?? throw new LogicException('A position is read for its product.');
        $valuation = $this->valuations->lock([$productId])[$productId] ?? throw new LogicException('A valuation is read for its product.');
        $this->allocations->lockProjectionsFrom($productId, 1);
        $this->returns->lockProjectionsFrom($productId, 1);
        $this->points->afterLock('7');

        try {
            $facts = $this->reconciliation->recompute($productId, $product->quantityPrecision);
        } catch (ReplayRefused) {
            throw new RepairRefused('The product\'s facts do not form a valid history: nothing was changed; the incident stays open for the Owner (RB-04 step 5).');
        }

        if ($facts->onHand === null || $facts->lastSequence === null || $facts->value === null) {
            if ($position->stored || $valuation->stored) {
                throw new RepairRefused('The product has a balance or a valuation but no movement, which no rebuild can repair: nothing was changed.');
            }

            return false;
        }

        $at = $this->clock->now()->format('Y-m-d H:i:s.uP');
        $balanceDiffers = ! $position->stored || $position->onHand->compareTo($facts->onHand) !== 0 || $position->lastSequence !== $facts->lastSequence;
        $valuationDiffers = ! $valuation->stored || $valuation->lastSequence !== $facts->lastSequence || ! self::same($valuation->value, $facts->value);
        $projections = count($facts->issueChanges) + count($facts->returnChanges);

        if (! $balanceDiffers && ! $valuationDiffers && $projections === 0) {
            return false;
        }

        if ($balanceDiffers) {
            $this->ledger->writePosition(new StockPosition($productId, $facts->onHand, $facts->lastSequence, $position->stored), $at);
            $this->written();
        }

        // A valuation or projection rewritten is a publication of its own, one number on (ADR-008 section 4.3).
        $number = $valuation->publicationNo + 1;

        if ($valuationDiffers || $projections > 0) {
            $this->valuations->publish(new ProductValuation($productId, $facts->value, $number, $facts->lastSequence, $valuation->stored), $at);
            $this->allocations->republish($facts->issueChanges, $number, $at);
            $this->returns->republish($facts->returnChanges, $number, $at);
            $this->written();
        }

        $this->audit->record(
            AuditCategory::Business,
            'PROJECTION_REBUILT',
            Actor::operatorProcedure(self::PROCESS),
            ['subject' => self::PRODUCT, 'balanceRebuilt' => $balanceDiffers, 'valuationRebuilt' => $valuationDiffers, 'publicationNo' => $valuationDiffers || $projections > 0 ? $number : null, 'issueProjections' => count($facts->issueChanges), 'returnProjections' => count($facts->returnChanges)],
            // The values are cost, the Owner's alone (BR-AUD-02, D-047).
            ['valueBefore' => self::valueOf($valuation->stored ? $valuation->value : null), 'valueAfter' => self::valueOf($facts->value)],
            subject: ['type' => self::PRODUCT, 'id' => $productId],
            operatorNote: $note,
            productId: $productId,
        );
        $this->written();
        $this->points->beforeCommit();

        return true;
    }

    private function sale(int $saleId, string $note): bool
    {
        if ($this->corrections->lockSale($saleId) === null) {
            throw new RepairRefused('No such Sale: nothing was changed.');
        }

        $this->points->afterLock('5');

        if (! $this->corrections->refundsAgreeWithLines($saleId)) {
            throw new RepairRefused('A refund of the Sale is not the sum of its lines: nothing was changed; the incident stays open for the Owner (RB-04 step 5).');
        }

        $changed = $this->corrections->rebuildTotals($saleId, $this->clock->now()->format('Y-m-d H:i:s.uP'));

        if ($changed === 0) {
            return false;
        }

        $this->written();
        $this->audit->record(
            AuditCategory::Business,
            'PROJECTION_REBUILT',
            Actor::operatorProcedure(self::PROCESS),
            ['subject' => self::SALE, 'totalsRebuilt' => $changed],
            subject: ['type' => self::SALE, 'id' => $saleId],
            operatorNote: $note,
        );
        $this->written();
        $this->points->beforeCommit();

        return true;
    }

    private function token(int $tokenId, string $note): bool
    {
        if (DB::scalar('select exists (select 1 from barcode_tokens where id = ?)', [$tokenId]) !== true) {
            throw new RepairRefused('No such token: nothing was changed.');
        }

        // The facts and the active row as they stand choose the guards; all are read again under them. The
        // last mapping is among them even when retired: RC-30 corrects it under its product's guard, so a
        // correction cannot add an active row between these reads and the rebuild's.
        $products = [];

        foreach ([...$this->mappings->unretiredOf($tokenId), ...array_filter([$this->barcodes->activeRowOf($tokenId), $this->mappings->lastOf($tokenId)])] as $row) {
            $products[$row['product']] = $row['product'];
        }

        ksort($products);
        $this->products->lockForPosting(array_values($products));
        $this->points->afterLock('6');

        $unretired = $this->mappings->unretiredOf($tokenId);
        $current = $this->barcodes->activeRowOf($tokenId);

        if (count($unretired) > 1) {
            throw new RepairRefused('The token has more than one mapping without a retirement, a fact that should not exist: nothing was changed; this is a security incident (RB-04 step 3).');
        }

        foreach ([...$unretired, ...array_filter([$current, $this->mappings->lastOf($tokenId)])] as $row) {
            if (! array_key_exists($row['product'], $products)) {
                throw new RepairRefused('The token changed while the rebuild took its guards: nothing was changed; run it again.');
            }
        }

        $expected = $unretired[0] ?? null;

        if (! $this->barcodes->rebuildActive($tokenId, $expected['mapping'] ?? null, $expected['product'] ?? null)) {
            return false;
        }

        $this->written();
        $this->audit->record(
            AuditCategory::Business,
            'PROJECTION_REBUILT',
            Actor::operatorProcedure(self::PROCESS),
            ['subject' => self::TOKEN, 'mappingBefore' => $current['mapping'] ?? null, 'mappingAfter' => $expected['mapping'] ?? null],
            subject: ['type' => self::TOKEN, 'id' => $tokenId],
            operatorNote: $note,
        );
        $this->written();
        $this->points->beforeCommit();

        return true;
    }

    private function written(): void
    {
        $this->points->afterWrite(++$this->writeStep);
    }

    private static function same(Cost $stored, Cost $computed): bool
    {
        if ($stored->isKnown() !== $computed->isKnown()) {
            return false;
        }

        return ! $computed->isKnown() || $stored->compareTo($computed) === 0;
    }

    /**
     * A value as the audit holds it: its status and its canonical amount, none for UNKNOWN; null for none.
     *
     * @return array{status: string, amount: string|null}|null
     */
    private static function valueOf(?Cost $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return ['status' => $value->isKnown() ? AcquisitionCost::KNOWN : AcquisitionCost::UNKNOWN, 'amount' => $value->isKnown() ? $value->toCanonical() : null];
    }
}
