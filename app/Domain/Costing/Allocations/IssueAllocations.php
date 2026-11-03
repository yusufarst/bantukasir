<?php

namespace App\Domain\Costing\Allocations;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The issue-cost allocations, in the Costing module (SCHEMA section 5.8; ADR-008 sections 1 and
 * 2; D-060; G-13, G-14), the only writer of their facts and, at a posting, of their projections
 * (STK-12). Each issue movement has exactly one fact, the allocation as it was computed at
 * commit, never changed, and one projection, inserted in the same transaction with the same
 * status and amount; afterwards only a replay publication writes the projection (TASK-025). The
 * posting Action calls it beside the Inventory module, in its transaction (STK-11), after the
 * movement: the presence key of the movement refuses a commit without its fact, and the fact's
 * key refuses one without its projection (BR-INV-08).
 *
 * A SALE_ISSUE allocation names its SaleLine, whose key to sale_lines TASK-028 completes; until
 * then no allocation of a Sale is written (EXECUTION_CONTEXT EXC-44).
 */
final class IssueAllocations
{
    /** The issue types that an allocation is written for today. */
    private const array TYPES = ['MANUAL_ISSUE', 'ADJUSTMENT'];

    /**
     * Records the fact and its equal projection of the issue movement of this type, product,
     * issued quantity and position, and returns the fact's internal key. The projection is
     * written by the publication that the posting makes, the valuation's after the issue.
     */
    public function record(int $movementId, int $productId, string $issueType, Quantity $issued, int $productSequence, IssueAllocation $allocation, string $at): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An allocation is written in the transaction of its movement (BR-INV-08).');
        }

        if (! in_array($issueType, self::TYPES, true)) {
            throw new LogicException('An allocation of a Sale is written once TASK-028 completes its key (EXC-44).');
        }

        $id = DB::table('issue_cost_allocations')->insertGetId([
            'movement_id' => $movementId,
            'product_id' => $productId,
            'issue_type' => $issueType,
            'movement_quantity' => $issued->negated()->toCanonical(),
            'product_sequence' => $productSequence,
            'issued_quantity' => $issued->toCanonical(),
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
}
