<?php

namespace App\Domain\Costing\Replay;

use App\Domain\Costing\Allocations\ReturnAllocation;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Closure;

/**
 * The recomputation of a replay (ADR-008 sections 3, 4.1, 4.3 and 5; COSTING BR-COST-09 to BR-COST-15,
 * BR-COST-17, BR-COST-18, BR-COST-23, BR-COST-24; D-061; G-07), with no database: from the state before
 * the start position, every logical movement to the end of the ledger is applied in order, by the rules
 * of an ordinary posting, and every issue and return allocation reached is computed again:
 *
 * - an increase adds its quantity and, while the value and its cost are KNOWN, its cost; otherwise
 *   the value becomes UNKNOWN (BR-COST-09, BR-COST-13); one that starts from zero starts a new pool,
 *   so a KNOWN increase after zero gives a KNOWN value again (BR-COST-15);
 * - an issue of q out of Q takes round6(V × q / Q), the exact product divided once, half-up; the
 *   issue that takes Q to zero takes the whole remaining value, and the next pool starts at a known
 *   zero; while V is UNKNOWN the allocation is UNKNOWN, never zero (BR-COST-10 to BR-COST-13);
 * - a RETURN_RECEIPT is an increase whose cost is that of its linked SALE_ISSUE allocation by the
 *   cumulative rule (ReturnAllocation::restored): from the issue's projection as this replay recomputed
 *   it, or, for an issue before the start position, as it stands, which nothing here changes. So the
 *   issue and its linked returns are recomputed together, the cost is restored exactly once and never
 *   twice (BR-COST-18; D-061), and an UNKNOWN issue gives an UNKNOWN return.
 *
 * The history is validated as it is recomputed: no issue exceeds the logical quantity at its point, no
 * return takes back more than its issue issued, no value leaves its range, and the end equals the
 * product's stock on hand — otherwise the replay is refused UNSAFE_HISTORY with nothing written. The
 * budget is asked before every movement. The same input gives the same result: the rules are
 * deterministic and the order is the sequence's.
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
        $returnChanges = [];
        /** @var array<int, Cost> $issues the issue allocations recomputed by this replay, by their key */
        $issues = [];

        try {
            foreach ($entries as $entry) {
                $withinBudget();

                if ($entry->link !== null) {
                    $link = $entry->link;
                    $cost = ReturnAllocation::restored($issues[$link->issueAllocationId] ?? $link->issueProjection, $link->issued, $link->returnedBefore, $entry->quantity);
                    $pool = $quantity->isZero() ? Cost::of('0') : $value;
                    $quantity = $quantity->plus($entry->quantity)->assertStock();
                    $value = $pool->plus($cost)->assertValue();

                    if ($entry->allocationId !== null && ! self::same($cost, $entry->projection)) {
                        $returnChanges[] = new ProjectionChange($entry->allocationId, $cost);
                    }

                    continue;
                }

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

                if ($entry->allocationId !== null) {
                    $issues[$entry->allocationId] = $allocation;

                    if (! self::same($allocation, $entry->projection)) {
                        $changes[] = new ProjectionChange($entry->allocationId, $allocation);
                    }
                }
            }

            $value = $value->assertValue();
        } catch (NumberOutOfRange|NonCanonicalNumber) {
            throw ReplayRefused::unsafeHistory();
        }

        if ($quantity->compareTo($onHand) !== 0) {
            throw ReplayRefused::unsafeHistory();
        }

        return new ReplayResult($value, $changes, count($entries), $returnChanges);
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
