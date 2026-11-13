<?php

namespace Tests\Unit\Costing;

use App\Domain\Costing\Replay\LedgerEntry;
use App\Domain\Costing\Replay\ProjectionChange;
use App\Domain\Costing\Replay\ReplayRefused;
use App\Domain\Costing\Replay\ReplayResult;
use App\Domain\Costing\Replay\ReplayStart;
use App\Domain\Costing\Replay\ReturnLink;
use App\Domain\Costing\Replay\ValuationReplay;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use Closure;
use PHPUnit\Framework\TestCase;

/**
 * The recomputation of the bounded replay (AC-025-01, AC-025-03; ADR-008 sections 4.1, 4.3 and 5;
 * COSTING BR-COST-09 to BR-COST-15, BR-COST-23, BR-COST-24; G-07; suites FIN02 and FIN03; TASK-025
 * section 7, L1): the L-15 example replayed after a correction; an UNKNOWN cost completed later,
 * with the allocations that depended on it; a known pool after zero, which an earlier UNKNOWN does
 * not reach; the single rounding where SQL would round twice; only the projections that change; the
 * same input giving the same result; and the refusals — an issue beyond the logical quantity, an end
 * that is not the stock on hand, a value out of range, and the time budget; and the measured
 * recomputation of the movement cap.
 */
final class ValuationReplayTest extends TestCase
{
    public function test_a_corrected_receipt_recomputes_the_l_15_issues_after_it(): void
    {
        // COSTING section 4: 10 units at Rp100.000 and 10 at Rp140.000, three units out, then a
        // manual issue of 2. The second receipt is corrected to Rp160.000: 20 units worth Rp260.000,
        // so three take Rp39.000 and two then Rp26.000 of the 17 left, worth Rp221.000.
        $start = new ReplayStart(self::units('10'), Cost::of('100000'));
        $entries = [
            LedgerEntry::increase(2, self::units('10'), Cost::of('160000')),
            LedgerEntry::issue(3, self::units('-3'), 31, Cost::of('36000')),
            LedgerEntry::issue(4, self::units('-2'), 32, Cost::of('24000')),
        ];

        $result = ValuationReplay::recompute($start, $entries, self::units('15'), self::always());

        $this->assertSame(['195000', 3], [$result->value->toCanonical(), $result->movements]);
        $this->assertSame([[31, '39000'], [32, '26000']], self::changes($result));
    }

    public function test_the_reversal_fixture_of_costing_section_8_on_the_logical_ledger(): void
    {
        // AC-026-02, L1 (TASK-026): OPENING 10 at 100 (a RECEIPT until TASK-035), RECEIPT 10 at 200,
        // an issue of 5 (75). Reversing the RECEIPT leaves it and its REVERSAL out of the logical
        // ledger (ReplayLedger): from the start, the opening's state, then the issue alone: quantity
        // 5, value 50, the issue's projection 50 (BR-COST-32; ADR-008 section 4.1).
        $result = ValuationReplay::recompute(new ReplayStart(self::units('10'), Cost::of('100')), [LedgerEntry::issue(3, self::units('-5'), 90, Cost::of('75'))], self::units('5'), self::always());

        $this->assertSame(['50', 1], [$result->value->toCanonical(), $result->movements]);
        $this->assertSame([[90, '50']], self::changes($result));

        // The invalid history: RECEIPT 10, ISSUE 10, RECEIPT 20; cancelling the first leaves the issue
        // of 10 without stock at its point: refused, even though 10 would remain (BR-INV-37).
        $this->assertRefused('UNSAFE_HISTORY', new ReplayStart(self::units('0'), Cost::of('0')), [LedgerEntry::issue(2, self::units('-10'), 91, Cost::of('100')), LedgerEntry::increase(3, self::units('20'), Cost::of('400'))], self::units('10'));
    }

    public function test_an_unknown_cost_completed_later_completes_the_allocations_that_depended_on_it(): void
    {
        // BR-COST-13, BR-COST-22: a receipt of 4 at UNKNOWN made a later issue UNKNOWN; completed to
        // Rp8.000 with 6 units at Rp9.000 before it, 10 units are worth Rp17.000 and 5 take Rp8.500.
        $start = new ReplayStart(self::units('6'), Cost::of('9000'));
        $entries = [
            LedgerEntry::increase(2, self::units('4'), Cost::of('8000')),
            LedgerEntry::issue(3, self::units('-5'), 40, Cost::unknown()),
        ];

        $result = ValuationReplay::recompute($start, $entries, self::units('5'), self::always());

        $this->assertSame([[40, '8500']], self::changes($result));
        $this->assertSame('8500', $result->value->toCanonical());
    }

    public function test_a_known_pool_after_zero_is_not_reached_by_an_earlier_unknown(): void
    {
        // BR-COST-15, ADR-008 section 4.1: an UNKNOWN receipt; its stock issued to zero (UNKNOWN, and
        // the pool starts again at a known zero); a known receipt of 10 at Rp50.000; 4 out at
        // Rp20.000. The replay keeps the second issue KNOWN and the first UNKNOWN, never zero.
        $start = new ReplayStart(self::units('0'), Cost::of('0'));
        $entries = [
            LedgerEntry::increase(1, self::units('3'), Cost::unknown()),
            LedgerEntry::issue(2, self::units('-3'), 50, Cost::unknown()),
            LedgerEntry::increase(3, self::units('10'), Cost::of('50000')),
            LedgerEntry::issue(4, self::units('-4'), 51, Cost::of('20000')),
        ];

        $result = ValuationReplay::recompute($start, $entries, self::units('6'), self::always());

        $this->assertSame([], self::changes($result), 'Nothing changed, so nothing is rewritten.');
        $this->assertSame('30000', $result->value->toCanonical());

        // While the pool is UNKNOWN an issue's allocation is UNKNOWN, and a stale KNOWN projection changes.
        $stale = ValuationReplay::recompute($start, [LedgerEntry::increase(1, self::units('3'), Cost::unknown()), LedgerEntry::issue(2, self::units('-1'), 52, Cost::of('10'))], self::units('2'), self::always());
        $this->assertSame([[52, 'UNKNOWN']], self::changes($stale));
        $this->assertFalse($stale->value->isKnown());
    }

    public function test_an_allocation_is_rounded_once_where_sql_would_round_twice_and_the_last_unit_takes_the_rest(): void
    {
        // ADR-008 section 5 and its test list: 84764889.521997 × 28655.202 / 79662.459 rounds once,
        // half-up, to 30490585.681776; the issue that takes the rest takes exactly what is left.
        $start = new ReplayStart(Quantity::of('79662.459', 3), Cost::of('84764889.521997'));
        $entries = [
            LedgerEntry::issue(2, Quantity::of('-28655.202', 3), 60, Cost::of('1')),
            LedgerEntry::issue(3, Quantity::of('-51007.257', 3), 61, Cost::of('1')),
        ];

        $result = ValuationReplay::recompute($start, $entries, Quantity::of('0', 3), self::always());

        $this->assertSame([[60, '30490585.681776'], [61, '54274303.840221']], self::changes($result));
        $this->assertSame('0', $result->value->toCanonical());
    }

    public function test_the_same_input_gives_the_same_result(): void
    {
        // AC-025-03: deterministic, in the order of the sequence.
        $start = new ReplayStart(self::units('3'), Cost::of('100'));
        $entries = [LedgerEntry::issue(2, self::units('-1'), 70, Cost::of('0')), LedgerEntry::issue(3, self::units('-1'), 71, Cost::of('0')), LedgerEntry::issue(4, self::units('-1'), 72, Cost::of('0'))];

        $first = ValuationReplay::recompute($start, $entries, self::units('0'), self::always());
        $second = ValuationReplay::recompute($start, $entries, self::units('0'), self::always());

        $this->assertSame([[70, '33.333333'], [71, '33.333334'], [72, '33.333333']], self::changes($first));
        $this->assertSame(self::changes($first), self::changes($second));
        $this->assertSame($first->value->toCanonical(), $second->value->toCanonical());
    }

    public function test_an_unsafe_history_or_a_value_out_of_range_is_refused(): void
    {
        // BR-COST-24, G-07: an issue beyond the logical quantity at its point.
        $this->assertRefused('UNSAFE_HISTORY', new ReplayStart(self::units('2'), Cost::of('20')), [LedgerEntry::issue(2, self::units('-3'), 80, Cost::of('20'))], self::units('0'));

        // The end of the ledger is the stock on hand (G-03).
        $this->assertRefused('UNSAFE_HISTORY', new ReplayStart(self::units('2'), Cost::of('20')), [LedgerEntry::issue(2, self::units('-1'), 81, Cost::of('10'))], self::units('2'));

        // A value past the range of TECHNICAL_RULES section 11.1 (ADR-008 section 4.3).
        $this->assertRefused('UNSAFE_HISTORY', new ReplayStart(self::units('1'), Cost::of('999999999999999')), [LedgerEntry::increase(2, self::units('1'), Cost::of('999999999999'))], self::units('2'));
    }

    public function test_the_budget_is_asked_before_every_movement_and_ends_the_replay(): void
    {
        // TR-TIME-01: the third check finds the budget spent.
        $asked = 0;
        $budget = static function () use (&$asked): void {
            $asked++;

            if ($asked === 3) {
                throw ReplayRefused::timeBudget();
            }
        };
        $entries = [LedgerEntry::increase(1, self::units('1'), Cost::of('1')), LedgerEntry::increase(2, self::units('1'), Cost::of('1')), LedgerEntry::increase(3, self::units('1'), Cost::of('1'))];

        try {
            ValuationReplay::recompute(new ReplayStart(self::units('0'), Cost::of('0')), $entries, self::units('3'), $budget);
            $this->fail('The budget ends the replay.');
        } catch (ReplayRefused $refused) {
            // Asked once per movement: only the third movement's check could end it.
            $this->assertSame('TIME_BUDGET', $refused->rejection);
        }
    }

    public function test_the_recomputation_of_the_movement_cap_is_measured(): void
    {
        // TASK-025 section 8, BR-COST-28: the recomputation alone of the reference cap, 10,000
        // movements — 2 in at Rp200, then 1 out, 5,000 times, each stale projection Rp99 rewritten to
        // Rp100 — measured for the evidence on the test's error stream; not a PERF01 pass.
        $entries = [];

        for ($pair = 1; $pair <= 5000; $pair++) {
            $entries[] = LedgerEntry::increase(2 * $pair - 1, self::units('2'), Cost::of('200'));
            $entries[] = LedgerEntry::issue(2 * $pair, self::units('-1'), $pair, Cost::of('99'));
        }

        $started = microtime(true);
        $result = ValuationReplay::recompute(new ReplayStart(self::units('0'), Cost::of('0')), $entries, self::units('5000'), self::always());
        $elapsed = (int) round((microtime(true) - $started) * 1000);

        fwrite(STDERR, sprintf("TASK-025 replay measurement: the recomputation of %d movements took %d ms\n", $result->movements, $elapsed));
        $this->assertSame(['500000', 10000, 5000], [$result->value->toCanonical(), $result->movements, count($result->changes)]);
        $this->assertSame([1, '100'], [$result->changes[0]->allocationId, $result->changes[0]->amount->toCanonical()]);
    }

    public function test_a_corrected_receipt_recomputes_the_sale_issue_and_its_linked_return_together(): void
    {
        // AC-033-04, L1 (TASK-033); D-061's worked example; ADR-008 sections 3 and 5: 10 units received at
        // Rp1.000, all 10 sold (issue 41, Rp1.000), 2 returned saleable (return 51, Rp200). The receipt
        // corrected to Rp1.200: the issue's projection becomes Rp1.200 and the linked return's Rp240,
        // recomputed together; the 2 returned units are worth Rp240, and net cost of goods sold is
        // Rp960, 8 × Rp120.
        $entries = [
            LedgerEntry::increase(1, self::units('10'), Cost::of('1200')),
            LedgerEntry::issue(2, self::units('-10'), 41, Cost::of('1000')),
            LedgerEntry::returnReceipt(3, self::units('2'), 51, Cost::of('200'), new ReturnLink(41, self::units('10'), Cost::of('1000'), self::units('0'))),
        ];

        $result = ValuationReplay::recompute(new ReplayStart(self::units('0'), Cost::of('0')), $entries, self::units('2'), self::always());

        $this->assertSame(['240', 3], [$result->value->toCanonical(), $result->movements]);
        $this->assertSame([[41, '1200']], self::changes($result));
        $this->assertSame([[51, '240']], self::returnChanges($result));
    }

    public function test_the_returns_of_an_issue_before_the_start_keep_its_cost_and_leave_no_residue(): void
    {
        // ADR-008 sections 4.3 and 5, T-17 e (TASK-033): an issue before the start position keeps its
        // projection, Rp100 for 3 units, so its returns after the start restore from it, one unit at a
        // time: 33.333333, 33.333334 and 33.333333 — Rp100 in all, exactly, never more. Only the second
        // return's projection, stored wrongly, is rewritten.
        $entries = [
            LedgerEntry::increase(5, self::units('4'), Cost::of('400')),
            LedgerEntry::returnReceipt(6, self::units('1'), 61, Cost::of('33.333333'), new ReturnLink(40, self::units('3'), Cost::of('100'), self::units('0'))),
            LedgerEntry::returnReceipt(7, self::units('1'), 62, Cost::of('0'), new ReturnLink(40, self::units('3'), Cost::of('100'), self::units('1'))),
            LedgerEntry::returnReceipt(8, self::units('1'), 63, Cost::of('33.333333'), new ReturnLink(40, self::units('3'), Cost::of('100'), self::units('2'))),
        ];

        $result = ValuationReplay::recompute(new ReplayStart(self::units('0'), Cost::of('0')), $entries, self::units('7'), self::always());

        $this->assertSame(['500', [], [[62, '33.333334']]], [$result->value->toCanonical(), self::changes($result), self::returnChanges($result)]);
    }

    public function test_an_unknown_issue_gives_an_unknown_return_until_its_cost_is_completed(): void
    {
        // ADR-008 section 3, "UNKNOWN"; BR-COST-13, BR-COST-22; D-061 (TASK-033): a receipt of 5 at
        // UNKNOWN cost, all 5 sold (UNKNOWN), 1 returned (UNKNOWN). Completing the receipt at Rp500
        // completes the issue, Rp500, and its return, Rp100, together, and so the returned unit's value.
        $entries = [
            LedgerEntry::increase(1, self::units('5'), Cost::of('500')),
            LedgerEntry::issue(2, self::units('-5'), 71, Cost::unknown()),
            LedgerEntry::returnReceipt(3, self::units('1'), 72, Cost::unknown(), new ReturnLink(71, self::units('5'), Cost::unknown(), self::units('0'))),
        ];

        $result = ValuationReplay::recompute(new ReplayStart(self::units('0'), Cost::of('0')), $entries, self::units('1'), self::always());

        $this->assertSame(['100', [[71, '500']], [[72, '100']]], [$result->value->toCanonical(), self::changes($result), self::returnChanges($result)]);

        // While the receipt is UNKNOWN, the return stays UNKNOWN, never zero, and so does the valuation.
        $stale = ValuationReplay::recompute(new ReplayStart(self::units('0'), Cost::of('0')), [LedgerEntry::increase(1, self::units('5'), Cost::unknown()), ...array_slice($entries, 1)], self::units('1'), self::always());

        $this->assertSame(['UNKNOWN', [], []], [$stale->value->toCanonical(), self::changes($stale), self::returnChanges($stale)]);
    }

    public function test_a_return_beyond_its_issue_is_an_unsafe_history(): void
    {
        // ADR-008 section 4.1, a valid history (TASK-033): the returns linked to an issue never take back
        // more than it issued: 1 already back and 2 more of an issue of 2 is refused.
        $this->assertRefused('UNSAFE_HISTORY', new ReplayStart(self::units('0'), Cost::of('0')), [
            LedgerEntry::increase(1, self::units('2'), Cost::of('200')),
            LedgerEntry::issue(2, self::units('-2'), 81, Cost::of('200')),
            LedgerEntry::returnReceipt(3, self::units('2'), 82, Cost::of('200'), new ReturnLink(81, self::units('2'), Cost::of('200'), self::units('1'))),
        ], self::units('2'));
    }

    /**
     * @param  list<LedgerEntry>  $entries
     */
    private function assertRefused(string $rejection, ReplayStart $start, array $entries, Quantity $onHand): void
    {
        try {
            ValuationReplay::recompute($start, $entries, $onHand, self::always());
            $this->fail('The replay is refused '.$rejection.'.');
        } catch (ReplayRefused $refused) {
            $this->assertSame($rejection, $refused->rejection);
        }
    }

    /**
     * The projections a result rewrites: allocation and amount, or UNKNOWN.
     *
     * @return list<array{int, string}>
     */
    private static function changes(ReplayResult $result): array
    {
        return array_map(static fn (ProjectionChange $change): array => [$change->allocationId, $change->amount->toCanonical()], $result->changes);
    }

    /**
     * The return projections a result rewrites: allocation and amount, or UNKNOWN (TASK-033).
     *
     * @return list<array{int, string}>
     */
    private static function returnChanges(ReplayResult $result): array
    {
        return array_map(static fn (ProjectionChange $change): array => [$change->allocationId, $change->amount->toCanonical()], $result->returnChanges);
    }

    /**
     * A budget never spent.
     *
     * @return Closure(): void
     */
    private static function always(): Closure
    {
        return static function (): void {};
    }

    private static function units(string $quantity): Quantity
    {
        return Quantity::of($quantity, 0);
    }
}
