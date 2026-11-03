<?php

namespace Tests\Unit\Costing;

use App\Domain\Costing\Allocations\IssueAllocation;
use App\Domain\Costing\ProductValuation;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Limits;
use App\Support\Numbers\Quantity;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The issue-cost allocation at commit (AC-023-02, AC-023-05; ADR-008 sections 2, 4.1, 4.2 and 5;
 * BR-COST-10 to BR-COST-13, BR-COST-15; INVENTORY BR-INV-21; G-13; suites FIN01 and INV02), the
 * cases of ADR-008's test list that concern issues, at L1: the L-15 example with its issues; the
 * value whose single rounding differs from SQL's double rounding; the issue that takes stock to
 * zero, which takes the whole value; UNKNOWN, never zero, while the valuation is unknown, and the
 * known pool that starts at zero; the publication the allocation was based on and the next one.
 */
final class IssueAllocationTest extends TestCase
{
    public function test_the_issues_of_the_l_15_example_take_the_average_before_each_of_them(): void
    {
        // COSTING section 4: 20 units worth Rp240.000, average Rp12.000. Three units leave at that
        // average, Rp36.000 — the cost of the Sale of the example, by the same rule (BR-COST-10) —
        // leaving 17 units worth Rp204.000; a manual issue of 2 then takes Rp24.000.
        $three = IssueAllocation::compute(new ProductValuation(1, Cost::of('240000'), 2, 2, true), self::units('20'), self::units('3'), 3);
        $this->assertSame(['KNOWN', '36000', 2], [$three->status(), $three->canonicalAmount(), $three->basedOnPublicationNo]);
        $this->assertSame(['204000', 3, 3, true], [$three->valuation->value->toCanonical(), $three->valuation->publicationNo, $three->valuation->lastSequence, $three->valuation->stored]);

        $two = IssueAllocation::compute($three->valuation, self::units('17'), self::units('2'), 4);
        $this->assertSame(['KNOWN', '24000', 3], [$two->status(), $two->canonicalAmount(), $two->basedOnPublicationNo]);
        $this->assertSame(['180000', 4, 4], [$two->valuation->value->toCanonical(), $two->valuation->publicationNo, $two->valuation->lastSequence]);
    }

    public function test_an_allocation_is_rounded_once_where_sql_would_round_twice(): void
    {
        // ADR-008 section 5 and its test list: 84764889.521997 × 28655.202 / 79662.459 rounds once,
        // half-up, to 30490585.681776.
        $issue = IssueAllocation::compute(new ProductValuation(1, Cost::of('84764889.521997'), 1, 1, true), Quantity::of('79662.459', 3), Quantity::of('28655.202', 3), 2);

        $this->assertSame('30490585.681776', $issue->canonicalAmount());
        $this->assertSame('54274303.840221', $issue->valuation->value->toCanonical());
    }

    public function test_a_tie_rounds_half_up_and_what_is_left_is_the_exact_difference(): void
    {
        // V = 1, Q = 8, q = 1: 0.125 exactly; V = 0.000001, Q = 2, q = 1: 0.0000005 rounds up.
        $exact = IssueAllocation::compute(new ProductValuation(1, Cost::of('1'), 1, 1, true), self::units('8'), self::units('1'), 2);
        $this->assertSame(['0.125', '0.875'], [$exact->canonicalAmount(), $exact->valuation->value->toCanonical()]);

        $tie = IssueAllocation::compute(new ProductValuation(1, Cost::of('0.000001'), 1, 1, true), self::units('2'), self::units('1'), 2);
        $this->assertSame(['0.000001', '0'], [$tie->canonicalAmount(), $tie->valuation->value->toCanonical()]);

        // A third of 100 is 33.333333, and the rest is exact.
        $third = IssueAllocation::compute(new ProductValuation(1, Cost::of('100'), 1, 1, true), self::units('3'), self::units('1'), 2);
        $this->assertSame(['33.333333', '66.666667'], [$third->canonicalAmount(), $third->valuation->value->toCanonical()]);
    }

    public function test_the_issue_that_takes_stock_to_zero_takes_the_whole_remaining_value(): void
    {
        // BR-COST-11: no rounding residue is left, whatever the earlier issues rounded.
        $state = new ProductValuation(1, Cost::of('100'), 1, 1, true);
        $onHand = self::units('3');

        foreach ([['1', '33.333333'], ['1', '33.333334'], ['1', '33.333333']] as $index => [$quantity, $expected]) {
            $issue = IssueAllocation::compute($state, $onHand, self::units($quantity), $index + 2);
            $this->assertSame($expected, $issue->canonicalAmount(), (string) $index);
            $state = $issue->valuation;
            $onHand = $onHand->minus(self::units($quantity));
        }

        $this->assertSame(['0', true], [$state->value->toCanonical(), $state->value->isKnown()]);

        // The whole value of the largest range, at once.
        $all = IssueAllocation::compute(new ProductValuation(1, Cost::of(Limits::VALUE_MAX), 1, 1, true), self::units('7'), self::units('7'), 2);
        $this->assertSame([Limits::VALUE_MAX, '0'], [$all->canonicalAmount(), $all->valuation->value->toCanonical()]);
    }

    public function test_an_unknown_valuation_gives_an_unknown_allocation_never_zero_until_stock_reaches_zero(): void
    {
        // BR-COST-13, BR-COST-03; ADR-008 section 4.1, BR-COST-15: at zero the next pool starts
        // KNOWN and empty.
        $unknown = new ProductValuation(1, Cost::unknown(), 5, 5, true);

        $partial = IssueAllocation::compute($unknown, self::units('10'), self::units('4'), 6);
        $this->assertSame(['UNKNOWN', null, 5], [$partial->status(), $partial->canonicalAmount(), $partial->basedOnPublicationNo]);
        $this->assertSame(['UNKNOWN', 6, 6], [$partial->valuation->value->toCanonical(), $partial->valuation->publicationNo, $partial->valuation->lastSequence]);

        $last = IssueAllocation::compute($partial->valuation, self::units('6'), self::units('6'), 7);
        $this->assertSame(['UNKNOWN', null], [$last->status(), $last->canonicalAmount()]);
        $this->assertSame(['0', true], [$last->valuation->value->toCanonical(), $last->valuation->value->isKnown()]);

        // The next KNOWN receipt starts a known pool.
        $this->assertSame('500', $last->valuation->increased(self::units('0'), Cost::of('500'), 8)->value->toCanonical());
    }

    public function test_an_allocation_of_a_known_zero_value_is_a_known_zero(): void
    {
        // An Owner's evidenced zero cost (BR-COST-04) is KNOWN, so its issue is a known zero.
        $issue = IssueAllocation::compute(new ProductValuation(1, Cost::of('0'), 1, 1, true), self::units('4'), self::units('1'), 2);

        $this->assertSame(['KNOWN', '0', '0'], [$issue->status(), $issue->canonicalAmount(), $issue->valuation->value->toCanonical()]);
    }

    public function test_an_issue_beyond_the_stock_or_without_a_valuation_is_a_defect_of_the_caller(): void
    {
        // G-02 is checked before; an issue never makes a value negative.
        foreach ([
            [new ProductValuation(1, Cost::of('10'), 1, 1, true), '2', '3'],
            [new ProductValuation(1, Cost::of('10'), 1, 1, true), '2', '0'],
            [ProductValuation::none(1), '2', '1'],
        ] as $index => [$valuation, $onHand, $issued]) {
            try {
                IssueAllocation::compute($valuation, self::units($onHand), self::units($issued), 2);
                $this->fail('Accepted case '.(string) $index);
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private static function units(string $quantity): Quantity
    {
        return Quantity::of($quantity, 0);
    }
}
