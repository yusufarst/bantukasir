<?php

namespace App\Domain\Costing\Replay;

/**
 * The bounded, deterministic replay of one product (ADR-008 section 4.3; D-034; COSTING BR-COST-23
 * to BR-COST-28; TECHNICAL_RULES TR-TIME-01; G-07), which the caller runs under the product's guard
 * (TR §7.1 step 6) for its whole duration: the state before the start position, then every logical
 * movement from the start position to the end, at most the movement cap of them, recomputed within
 * the time budget. It writes nothing: the caller publishes the result in its own transaction, all
 * of it or nothing (BR-COST-25), and a refusal leaves everything as it was.
 *
 * The ledger is the logical one (ADR-008 section 4.1): a reversal, written before its replay in the
 * same transaction, starts it at its source, which with its REVERSAL the replay skips, and the source's
 * allocation, if it is a manual issue's, becomes CANCELLED in the same publication (BR-COST-32; TASK-026).
 * Every RETURN_RECEIPT reached is recomputed from its link together with its SALE_ISSUE, and its return
 * projection is published with the issue projections (D-061; TASK-033).
 */
final class ProductReplay
{
    public function __construct(private readonly ReplayLedger $ledger) {}

    /**
     * The replay of the product from the movement at this place of its sequence.
     *
     * @throws ReplayRefused MOVEMENT_CAP, TIME_BUDGET or UNSAFE_HISTORY
     */
    public function recompute(int $productId, int $precision, int $startSequence, ReplayBudget $budget): ReplayResult
    {
        $cap = config()->integer('costing.replay.movement_cap');
        $start = $this->ledger->before($productId, $precision, $startSequence);
        $budget->check();
        $entries = $this->ledger->from($productId, $precision, $startSequence, $cap + 1);

        if (count($entries) > $cap) {
            throw ReplayRefused::movementCap();
        }

        $result = ValuationReplay::recompute($start, $entries, $this->ledger->onHand($productId, $precision), $budget->checker());
        $cancelled = array_map(static fn (int $allocation): ProjectionChange => ProjectionChange::cancelled($allocation), $this->ledger->cancelledIssues($productId, $startSequence));

        return $cancelled === [] ? $result : new ReplayResult($result->value, [...$cancelled, ...$result->changes], $result->movements, $result->returnChanges);
    }
}
