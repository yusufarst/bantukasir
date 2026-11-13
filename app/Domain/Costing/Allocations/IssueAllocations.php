<?php

namespace App\Domain\Costing\Allocations;

use App\Domain\Costing\Replay\ProjectionChange;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The issue-cost allocations, in the Costing module (SCHEMA section 5.8; ADR-008 sections 1 and
 * 2; D-060; G-13, G-14), the only writer of their facts and, at a posting, of their projections
 * (STK-12). Each issue movement has exactly one fact, the allocation as it was computed at
 * commit, never changed, and one projection, inserted in the same transaction with the same
 * status and amount; afterwards only a replay publication writes the projection (republish(),
 * TASK-025). The posting Action calls it beside the Inventory module, in its transaction
 * (STK-11), after the movement: the presence key of the movement refuses a commit without its
 * fact, and the fact's key refuses one without its projection (BR-INV-08).
 *
 * A SALE_ISSUE allocation names its SaleLine, whose keys to sale_lines and to its movement's line
 * hold that it issues that line's quantity (TASK-028; EXECUTION_CONTEXT EXC-44); only a SALE_ISSUE's
 * allocation enters cost of goods sold, which the reports read (ADR-008 section 6).
 */
final class IssueAllocations
{
    public const string SALE_ISSUE = 'SALE_ISSUE';

    /** The issue types of an allocation (SCHEMA section 5.8; ADR-008 section 2). */
    private const array TYPES = [self::SALE_ISSUE, 'MANUAL_ISSUE', 'ADJUSTMENT'];

    /**
     * Records the fact and its equal projection of the issue movement of this type, product,
     * issued quantity and position, and returns the fact's internal key; a SALE_ISSUE names its
     * SaleLine, and no other type names one. The projection is written by the publication that the
     * posting makes, the valuation's after the issue.
     */
    public function record(int $movementId, int $productId, string $issueType, Quantity $issued, int $productSequence, IssueAllocation $allocation, string $at, ?int $saleLineId = null): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An allocation is written in the transaction of its movement (BR-INV-08).');
        }

        if (! in_array($issueType, self::TYPES, true) || ($issueType === self::SALE_ISSUE) !== ($saleLineId !== null)) {
            throw new LogicException('An allocation names its SaleLine exactly when it is a SALE_ISSUE (SCHEMA section 5.8).');
        }

        $id = DB::table('issue_cost_allocations')->insertGetId([
            'movement_id' => $movementId,
            'product_id' => $productId,
            'issue_type' => $issueType,
            'movement_quantity' => $issued->negated()->toCanonical(),
            'product_sequence' => $productSequence,
            'issued_quantity' => $issued->toCanonical(),
            'sale_line_id' => $saleLineId,
            'at_commit_status' => $allocation->status(),
            'at_commit_amount' => $allocation->canonicalAmount(),
            'based_on_publication_no' => $allocation->basedOnPublicationNo,
            'recorded_at' => $at,
        ]);

        // G-14: the projection is inserted equal to its fact.
        DB::table('issue_cost_allocation_projections')->insert([
            'allocation_id' => $id,
            'issue_type' => $issueType,
            'current_status' => $allocation->status(),
            'current_amount' => $allocation->canonicalAmount(),
            'publication_no' => $allocation->valuation->publicationNo,
            'updated_at' => $at,
        ]);

        return $id;
    }

    /**
     * The SALE_ISSUE allocation of each of these Sale lines with its current projection, by the line's
     * internal key: what a saleable return of the line links to and restores its cost from (ADR-008
     * section 3; D-061; TASK-033). The caller holds the guards of the lines' products, which every replay
     * of a product holds too, so that no projection is rewritten meanwhile (ADR-008 section 7). Each
     * quantity is read at the precision given for its line.
     *
     * @param  array<int, int>  $precisions  the precision of each line's product, by the line's internal key
     * @return array<int, LinkedIssue>
     */
    public function ofSaleLines(array $precisions): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A linked issue is read under the product guard of its transaction (ADR-008 section 7).');
        }

        $rows = DB::table('issue_cost_allocations as a')
            ->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.issue_type', self::SALE_ISSUE)
            ->whereIn('a.sale_line_id', array_keys($precisions))
            ->orderBy('a.sale_line_id')
            ->get(['a.id', 'a.sale_line_id', 'a.product_id', 'a.issued_quantity', 'p.current_status', 'p.current_amount']);
        $linked = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $line = $values['sale_line_id'] ?? null;
            $product = $values['product_id'] ?? null;
            $issued = $values['issued_quantity'] ?? null;
            $status = $values['current_status'] ?? null;
            $amount = $values['current_amount'] ?? null;

            if (! is_int($id) || ! is_int($line) || ! is_int($product) || ! is_string($issued) || ! in_array($status, ['KNOWN', 'UNKNOWN'], true) || ($amount !== null && ! is_string($amount))) {
                throw new LogicException('A SALE_ISSUE allocation holds its columns, and its projection is KNOWN or UNKNOWN (SCHEMA section 5.8).');
            }

            $linked[$line] = new LinkedIssue($id, $line, $product, Quantity::fromDatabase($issued, $precisions[$line]), Cost::fromDatabase($amount, $status));
        }

        return $linked;
    }

    /**
     * Takes the update locks of the projections that a replay of the product from this place of its
     * sequence may rewrite, ascending (TR §7.1 step 7; SCHEMA section 7), under the product's guard
     * that the caller holds; the lock names the projections alone (SCH-34).
     */
    public function lockProjectionsFrom(int $productId, int $fromSequence): void
    {
        self::requireTransaction();

        DB::table('issue_cost_allocation_projections as p')
            ->join('issue_cost_allocations as a', 'a.id', '=', 'p.allocation_id')
            ->where('a.product_id', $productId)
            ->where('a.product_sequence', '>=', $fromSequence)
            ->orderBy('p.allocation_id')
            ->lock('for no key update of p')
            ->pluck('p.allocation_id');
    }

    /**
     * Rewrites, as a replay's publication, the projections whose status or amount it changed, each
     * with the publication's number (ADR-008 section 4.3; G-14), in one statement of the transaction
     * that publishes the product's valuation, so that all appear at once or none does (BR-COST-25).
     * The facts are never touched: the runtime role may not change them (SCHEMA section 10).
     *
     * @param  list<ProjectionChange>  $changes
     */
    public function republish(array $changes, int $publicationNo, string $at): void
    {
        self::requireTransaction();

        if ($changes === []) {
            return;
        }

        $ids = [];
        $statuses = [];
        $amounts = [];

        foreach ($changes as $change) {
            $ids[] = (string) $change->allocationId;
            // A manual issue that a reversal cancelled is CANCELLED (ADR-008 section 2; TASK-026).
            $statuses[] = $change->cancelled ? 'CANCELLED' : ($change->amount->isKnown() ? 'KNOWN' : 'UNKNOWN');
            // UNKNOWN and CANCELLED have no amount, never a zero (BR-COST-03): an element NULL of the array.
            $amounts[] = ! $change->cancelled && $change->amount->isKnown() ? $change->amount->toCanonical() : 'NULL';
        }

        $changed = DB::update(<<<'SQL'
            update issue_cost_allocation_projections p
            set current_status = c.status, current_amount = c.amount, publication_no = ?, updated_at = ?::timestamptz
            from unnest(?::bigint[], ?::text[], ?::numeric[]) as c(allocation_id, status, amount)
            where p.allocation_id = c.allocation_id
            SQL, [$publicationNo, $at, '{'.implode(',', $ids).'}', '{'.implode(',', $statuses).'}', '{'.implode(',', $amounts).'}']);

        if ($changed !== count($changes)) {
            throw new LogicException('Every projection that a replay rewrites exists, once.');
        }
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A projection is locked and rewritten inside the transaction of its replay (BR-COST-25).');
        }
    }
}
