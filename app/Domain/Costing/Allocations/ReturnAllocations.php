<?php

namespace App\Domain\Costing\Allocations;

use App\Domain\Costing\Replay\ProjectionChange;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The return allocations, in the Costing module (SCHEMA section 5.8; ADR-008 sections 1 and 3; D-061;
 * G-13, G-14), the only writer of their facts and projections (STK-12). Each saleable return line has
 * exactly one fact, the cost it restored at commit, linked to the SALE_ISSUE allocation of the same line
 * and product, never changed, and one projection, inserted in the same transaction equal to it;
 * afterwards only a replay publication rewrites the projection, recomputed from the link together with
 * its issue (republish()). The correction's Action calls it beside the Inventory module, in its
 * transaction, after the RETURN_RECEIPT (STK-11; TASK-033): the movement's presence key refuses a commit
 * without the fact, and the fact's key one without its projection (BR-INV-08).
 *
 * Every writer of a product's return allocations holds the product's guard (TR §7.1 step 6), as every
 * replay of the product does, so what it reads under that guard cannot change meanwhile (ADR-008
 * section 7).
 */
final class ReturnAllocations
{
    /** The only condition of a return line that has a return allocation (BR-COST-19). */
    public const string SALEABLE = 'SALEABLE';

    /**
     * Records the fact and its equal projection of the RETURN_RECEIPT of a saleable return line, linked
     * to the SALE_ISSUE allocation of its Sale line, and returns the fact's internal key. The projection
     * is written by the publication that the correction makes, the valuation's after the return.
     */
    public function record(int $movementId, int $returnLineId, int $productId, int $saleLineId, int $issueAllocationId, Quantity $returned, int $productSequence, ReturnAllocation $allocation, string $at): int
    {
        self::requireTransaction();

        $id = DB::table('return_cost_allocations')->insertGetId([
            'return_line_id' => $returnLineId,
            'movement_id' => $movementId,
            'product_id' => $productId,
            'sale_line_id' => $saleLineId,
            'return_condition' => self::SALEABLE,
            'issue_allocation_id' => $issueAllocationId,
            'returned_quantity' => $returned->toCanonical(),
            'product_sequence' => $productSequence,
            'at_commit_status' => $allocation->status(),
            'at_commit_amount' => $allocation->canonicalAmount(),
            'based_on_publication_no' => $allocation->basedOnPublicationNo,
            'recorded_at' => $at,
        ]);

        // G-14: the projection is inserted equal to its fact.
        DB::table('return_cost_allocation_projections')->insert([
            'allocation_id' => $id,
            'current_status' => $allocation->status(),
            'current_amount' => $allocation->canonicalAmount(),
            'publication_no' => $allocation->valuation->publicationNo,
            'updated_at' => $at,
        ]);

        return $id;
    }

    /**
     * R(k−1) of the cumulative rule (ADR-008 section 5): the quantity that the saleable returns linked to
     * this issue allocation took back so far, at the product's precision, under the product's guard that
     * the caller holds. A not-saleable return has no return allocation and never counts here.
     */
    public function returnedOf(int $issueAllocationId, int $precision): Quantity
    {
        self::requireTransaction();

        $row = DB::selectOne('select coalesce(sum(returned_quantity), 0) as total from return_cost_allocations where issue_allocation_id = ?', [$issueAllocationId]);
        $total = is_object($row) ? ((array) $row)['total'] ?? null : null;

        return Quantity::fromDatabase(is_string($total) ? $total : throw new LogicException('A sum of quantities is a numeric value.'), $precision);
    }

    /**
     * Takes the update locks of the return projections that a replay of the product from this place of
     * its sequence may rewrite, ascending (TR §7.1 step 7; SCHEMA section 7), under the product's guard
     * that the caller holds; the lock names the projections alone (SCH-34).
     */
    public function lockProjectionsFrom(int $productId, int $fromSequence): void
    {
        self::requireTransaction();

        DB::table('return_cost_allocation_projections as p')
            ->join('return_cost_allocations as a', 'a.id', '=', 'p.allocation_id')
            ->where('a.product_id', $productId)
            ->where('a.product_sequence', '>=', $fromSequence)
            ->orderBy('p.allocation_id')
            ->lock('for no key update of p')
            ->pluck('p.allocation_id');
    }

    /**
     * Rewrites, as a replay's publication, the return projections whose status or amount it changed, each
     * with the publication's number (ADR-008 section 4.3; G-14), in one statement of the transaction that
     * publishes the product's valuation and its issue projections, so that all appear at once or none
     * does (BR-COST-25). The facts are never touched: the runtime role may not change them (SCHEMA
     * section 10).
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
            if ($change->cancelled) {
                throw new LogicException('A return allocation is never cancelled: no reversal cancels a RETURN_RECEIPT (BR-INV-36).');
            }

            $ids[] = (string) $change->allocationId;
            $statuses[] = $change->amount->isKnown() ? 'KNOWN' : 'UNKNOWN';
            // UNKNOWN has no amount, never a zero (BR-COST-03): an element NULL of the array.
            $amounts[] = $change->amount->isKnown() ? $change->amount->toCanonical() : 'NULL';
        }

        $changed = DB::update(<<<'SQL'
            update return_cost_allocation_projections p
            set current_status = c.status, current_amount = c.amount, publication_no = ?, updated_at = ?::timestamptz
            from unnest(?::bigint[], ?::text[], ?::numeric[]) as c(allocation_id, status, amount)
            where p.allocation_id = c.allocation_id
            SQL, [$publicationNo, $at, '{'.implode(',', $ids).'}', '{'.implode(',', $statuses).'}', '{'.implode(',', $amounts).'}']);

        if ($changed !== count($changes)) {
            throw new LogicException('Every return projection that a replay rewrites exists, once.');
        }
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A return allocation is read, locked and written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
