<?php

namespace App\Domain\Costing\Replay;

use App\Domain\Costing\AcquisitionCost;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The replay's read of one product's ledger (ADR-008 sections 4.1 and 4.3; STACK_AND_MODULES STK-11:
 * Costing reads the ledger), under the product's guard, which every writer of its movements, cost
 * evidence, corrections and projections holds, so that what it reads cannot change meanwhile.
 *
 * The movement types whose writers exist are RECEIPT, SALE_ISSUE, MANUAL_ISSUE, ADJUSTMENT and
 * REVERSAL (TASK-021 to TASK-024, TASK-026, TASK-028). The ledger read is the logical ledger (ADR-008
 * section 4.1): every pair of an OPENING, RECEIPT or MANUAL_ISSUE with the REVERSAL of its reversal is
 * left out — of the zero point, of the state before the start and of the movements from it — and only
 * the valuation skips them; the physical ledger keeps both (BR-COST-32). A cancelled pair sums to
 * zero, so the logical quantity at the end is still the stock on hand. A SALE_ISSUE is an issue like
 * the others: the replay recomputes its projection at its point, validates it against the logical
 * quantity there and counts it against the movement cap, and never reads or writes its Sale, its line,
 * its Payment, its receipt or its session (D-060, REQ-COST-09); it is never reversed (BR-INV-36).
 * TASK-033 and TASK-035 extend the replay with RETURN_RECEIPT with its linked return projections, and
 * OPENING; until then a logical movement of another type is a defect.
 *
 * The cost values are sums and differences of stored values only, which PostgreSQL's numeric adds
 * exactly; every value computed by multiplication or division is the application's (ADR-008
 * section 5).
 */
final class ReplayLedger
{
    /** The movement types of an increase, with their cost evidence. */
    private const array INCREASES = ['RECEIPT', 'ADJUSTMENT'];

    /** The movement types of an issue, with their allocation (TASK-028: the Sale's). */
    private const array ISSUES = ['SALE_ISSUE', 'MANUAL_ISSUE', 'ADJUSTMENT'];

    /** The effective amount of an evidence: its latest correction's new amount, or its own. */
    private const string CORRECTED = '(select c.new_amount from cost_corrections c where c.evidence_id = e.id order by c.correction_no desc limit 1)';

    /**
     * The movements of the logical ledger: neither a REVERSAL nor a movement that a reversal cancelled
     * (ADR-008 section 4.1; BR-COST-32).
     */
    private const string LOGICAL = "m.movement_type <> 'REVERSAL' and not exists (select 1 from reversals r where r.original_movement_id = m.id)";

    /**
     * The state before the start position (ADR-008 section 4.3): Q is the sum of the movements since
     * the last point before the start at which the running quantity became zero — or since the
     * product's first movement — V the sum of their increases' effective costs less the sum of their
     * issues' current projections, and UNKNOWN when any of those is UNKNOWN. Nothing before the start
     * position changes during a replay, so this is exact. This read covers the history back to that
     * point and lies outside the movement cap; PERF01 measures it with the replay.
     *
     * @throws ReplayRefused UNSAFE_HISTORY when the stored history gives no valid state
     */
    public function before(int $productId, int $precision, int $startSequence): ReplayStart
    {
        $zero = DB::selectOne('
            select coalesce(max(z.product_sequence), 0) as zero_point
            from (
                select m.product_sequence, sum(m.quantity) over (order by m.product_sequence) as running
                from stock_movements m
                where m.product_id = ? and m.product_sequence < ? and '.self::LOGICAL.'
            ) z
            where z.running = 0
            ', [$productId, $startSequence]);
        $zeroPoint = self::field($zero, 'zero_point');

        if (! is_int($zeroPoint)) {
            throw new LogicException('The zero point is a place of the sequence.');
        }

        $window = [$productId, $zeroPoint, $startSequence];
        $quantity = self::field(DB::selectOne('select coalesce(sum(m.quantity), 0) as quantity from stock_movements m where m.product_id = ? and m.product_sequence > ? and m.product_sequence < ? and '.self::LOGICAL, $window), 'quantity');
        $increases = DB::selectOne("
            select count(*) filter (where c.status = 'UNKNOWN') as unknown, coalesce(sum(c.amount) filter (where c.status = 'KNOWN'), 0) as total
            from (
                select case when lc.new_amount is not null then 'KNOWN' else e.cost_status end as status, coalesce(lc.new_amount, e.total_amount) as amount
                from stock_movements m
                join acquisition_cost_evidence e on e.movement_id = m.id
                left join lateral (
                    select k.new_amount from cost_corrections k where k.evidence_id = e.id order by k.correction_no desc limit 1
                ) lc on true
                where m.product_id = ? and m.product_sequence > ? and m.product_sequence < ? and ".self::LOGICAL.'
            ) c
            ', $window);
        $issues = DB::selectOne("
            select count(*) filter (where p.current_status = 'UNKNOWN') as unknown, coalesce(sum(p.current_amount) filter (where p.current_status = 'KNOWN'), 0) as total
            from stock_movements m
            join issue_cost_allocations a on a.movement_id = m.id
            join issue_cost_allocation_projections p on p.allocation_id = a.id
            where m.product_id = ? and m.product_sequence > ? and m.product_sequence < ? and ".self::LOGICAL, $window);

        $unknownIncreases = self::field($increases, 'unknown');
        $unknownIssues = self::field($issues, 'unknown');
        $increaseTotal = self::field($increases, 'total');
        $issueTotal = self::field($issues, 'total');

        if (! is_string($quantity) || ! is_int($unknownIncreases) || ! is_int($unknownIssues) || ! is_string($increaseTotal) || ! is_string($issueTotal)) {
            throw new LogicException('The sums before the start hold their types.');
        }

        try {
            $before = Quantity::fromDatabase($quantity, $precision)->assertStock();
            $value = $unknownIncreases > 0 || $unknownIssues > 0
                ? Cost::unknown()
                : Cost::fromDatabase($increaseTotal)->minus(Cost::fromDatabase($issueTotal))->assertValue();
        } catch (NumberOutOfRange|NonCanonicalNumber) {
            throw ReplayRefused::unsafeHistory();
        }

        return new ReplayStart($before, $before->isZero() ? Cost::of('0') : $value);
    }

    /**
     * The logical movements from the start position to the end, in the order of the sequence, at
     * most $limit of them: the caller reads one more than the cap to know that the cap is passed. A
     * cancelled pair is not among them, and does not count against the cap (TR-TIME-01).
     *
     * @return list<LedgerEntry>
     */
    public function from(int $productId, int $precision, int $startSequence, int $limit): array
    {
        $rows = DB::table('stock_movements as m')
            ->leftJoin('acquisition_cost_evidence as e', 'e.movement_id', '=', 'm.id')
            ->leftJoin('issue_cost_allocations as a', 'a.movement_id', '=', 'm.id')
            ->leftJoin('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('m.product_id', $productId)
            ->where('m.product_sequence', '>=', $startSequence)
            ->whereRaw(self::LOGICAL)
            ->orderBy('m.product_sequence')
            ->limit($limit)
            ->select(['m.product_sequence', 'm.movement_type', 'm.quantity', 'e.id as evidence_id', 'e.cost_status', 'e.total_amount', 'a.id as allocation_id', 'p.current_status', 'p.current_amount'])
            ->selectRaw(self::CORRECTED.' as corrected_amount')
            ->get();
        $entries = [];

        foreach ($rows as $row) {
            $entries[] = self::entry((array) $row, $precision);
        }

        return $entries;
    }

    /**
     * The allocations of the manual issues from the start position on that a reversal cancelled and
     * whose projection is not yet CANCELLED: a cancelled issue reduces no value (ADR-008 section 2;
     * BR-COST-32). Every reversal publishes its source's, so this is at most the source of the reversal
     * under way; its fact stays as it was committed (D-060).
     *
     * @return list<int>
     */
    public function cancelledIssues(int $productId, int $startSequence): array
    {
        $ids = DB::table('stock_movements as m')
            ->join('reversals as r', 'r.original_movement_id', '=', 'm.id')
            ->join('issue_cost_allocations as a', 'a.movement_id', '=', 'm.id')
            ->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('m.product_id', $productId)
            ->where('m.product_sequence', '>=', $startSequence)
            ->where('p.current_status', '<>', 'CANCELLED')
            ->orderBy('m.product_sequence')
            ->pluck('a.id');
        $allocations = [];

        foreach ($ids as $id) {
            $allocations[] = is_int($id) ? $id : throw new LogicException('An allocation has its key.');
        }

        return $allocations;
    }

    /**
     * The product's stock on hand from its balance, which every posting keeps equal to the sum of
     * its movements (G-03); zero for a product with no movement.
     */
    public function onHand(int $productId, int $precision): Quantity
    {
        $onHand = DB::table('stock_balances')->where('product_id', $productId)->value('on_hand');

        return Quantity::fromDatabase(is_string($onHand) ? $onHand : '0', $precision);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function entry(array $values, int $precision): LedgerEntry
    {
        $sequence = $values['product_sequence'] ?? null;
        $type = $values['movement_type'] ?? null;
        $quantity = $values['quantity'] ?? null;

        if (! is_int($sequence) || ! is_string($type) || ! is_string($quantity)) {
            throw new LogicException('A movement holds its columns (SCHEMA section 5.7).');
        }

        $signed = Quantity::fromDatabase($quantity, $precision);

        if ($signed->isPositive() && in_array($type, self::INCREASES, true)) {
            $status = $values['cost_status'] ?? null;
            $amount = $values['total_amount'] ?? null;
            $corrected = $values['corrected_amount'] ?? null;

            if (! is_int($values['evidence_id'] ?? null) || ! is_string($status) || ($amount !== null && ! is_string($amount)) || ($corrected !== null && ! is_string($corrected))) {
                throw new LogicException('An increase carries its cost evidence (BR-INV-08).');
            }

            $cost = $corrected !== null ? Cost::fromDatabase($corrected) : Cost::fromDatabase($amount, $status);

            return LedgerEntry::increase($sequence, $signed, $cost);
        }

        if ($signed->isNegative() && in_array($type, self::ISSUES, true)) {
            $allocation = $values['allocation_id'] ?? null;
            $status = $values['current_status'] ?? null;
            $amount = $values['current_amount'] ?? null;

            if (! is_int($allocation) || ! in_array($status, [AcquisitionCost::KNOWN, AcquisitionCost::UNKNOWN], true) || ($amount !== null && ! is_string($amount))) {
                throw new LogicException('An issue carries its allocation and its projection (BR-INV-08; ADR-008 section 1).');
            }

            return LedgerEntry::issue($sequence, $signed, $allocation, Cost::fromDatabase($amount, $status));
        }

        throw new LogicException(sprintf('The replay does not apply a %s yet: the Task that writes it extends the replay (TASK-025 section 12).', $type));
    }

    /**
     * A column of the row that a select of one row answered, or null without a row.
     */
    private static function field(mixed $row, string $name): mixed
    {
        return is_object($row) ? ((array) $row)[$name] ?? null : null;
    }
}
