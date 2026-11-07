<?php

namespace App\Domain\Costing\Replay;

/**
 * The bounded, deterministic replay of one product (ADR-008 section 4.3; D-034; COSTING BR-COST-23
 * to BR-COST-28; TECHNICAL_RULES TR-TIME-01; G-07), which the caller runs under the product's guard
 * (TR §7.1 step 6) for its whole duration: the state before the start position, then every logical
 * movement from the start position to the end, at most the movement cap of them, recomputed within
 * the time budget. It writes nothing: the caller publishes the result in its own transaction, all
 * of it or nothing (BR-COST-25), and a refusal leaves everything as it was.
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

        return ValuationReplay::recompute($start, $entries, $this->ledger->onHand($productId, $precision), $budget->checker());
    }
}
