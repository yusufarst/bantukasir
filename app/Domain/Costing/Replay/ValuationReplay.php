<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Closure;

/**
 * The recomputation of a replay (ADR-008 sections 4.1, 4.3 and 5; COSTING BR-COST-09 to BR-COST-15,
 * BR-COST-23, BR-COST-24; G-07), with no database: from the state before the start position, every
 * logical movement to the end of the ledger is applied in order, by the rules of an ordinary
 * posting, and every issue allocation reached is computed again:
 *
 * - an increase adds its quantity and, while the value and its cost are KNOWN, its cost; otherwise
 *   the value becomes UNKNOWN (BR-COST-09, BR-COST-13); one that starts from zero starts a new pool,
 *   so a KNOWN increase after zero gives a KNOWN value again (BR-COST-15);
 * - an issue of q out of Q takes round6(V × q / Q), the exact product divided once, half-up; the
 *   issue that takes Q to zero takes the whole remaining value, and the next pool starts at a known
 *   zero; while V is UNKNOWN the allocation is UNKNOWN, never zero (BR-COST-10 to BR-COST-13).
 *
 * The history is validated as it is recomputed: no issue exceeds the logical quantity at its point,
 * no value leaves its range, and the end equals the product's stock on hand — otherwise the replay
 * is refused UNSAFE_HISTORY with nothing written. The budget is asked before every movement. The
 * same input gives the same result: the rules are deterministic and the order is the sequence's.
 */
final class ValuationReplay
{
    /**
     * @param  list<LedgerEntry>  $entries  the logical movements from the start position, in order
     * @param  Closure(): void  $withinBudget  throws ReplayRefused once the time budget is spent
     *
     * @throws ReplayRefused
     */
    public static function recompute(ReplayStart $start, array $entries, Quantity $onHand, Closure $withinBudget): ReplayResult
    {
        $quantity = $start->quantity;
        $value = $start->value;
        $changes = [];

        try {
            foreach ($entries as $entry) {
                $withinBudget();

                if ($entry->isIncrease()) {
                    $cost = $entry->cost ?? Cost::unknown();
                    $pool = $quantity->isZero() ? Cost::of('0') : $value;
                    $quantity = $quantity->plus($entry->quantity)->assertStock();
                    $value = $pool->plus($cost)->assertValue();

                    continue;
                }

                $issued = $entry->quantity->negated();

                if ($issued->compareTo($quantity) > 0) {
                    throw ReplayRefused::unsafeHistory();
                }

                if ($issued->compareTo($quantity) === 0) {
                    $allocation = $value;
                    $value = Cost::of('0');
                } elseif (! $value->isKnown()) {
                    $allocation = Cost::unknown();
                } else {
                    $allocation = Cost::mulDiv($value, $issued, $quantity)->assertValue();
                    $value = $value->minus($allocation);
                }

                $quantity = $quantity->minus($issued);

                if ($entry->allocationId !== null && ! self::same($allocation, $entry->projection)) {
                    $changes[] = new ProjectionChange($entry->allocationId, $allocation);
                }
            }

            $value = $value->assertValue();
        } catch (NumberOutOfRange|NonCanonicalNumber) {
            throw ReplayRefused::unsafeHistory();
        }

        if ($quantity->compareTo($onHand) !== 0) {
            throw ReplayRefused::unsafeHistory();
        }

        return new ReplayResult($value, $changes, count($entries));
    }

    /**
     * Whether two allocations hold the same status and amount.
     */
    private static function same(Cost $computed, ?Cost $current): bool
    {
        if ($current === null || $computed->isKnown() !== $current->isKnown()) {
            return false;
        }

        return ! $computed->isKnown() || $computed->compareTo($current) === 0;
    }
}
