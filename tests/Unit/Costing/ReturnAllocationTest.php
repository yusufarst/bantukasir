<?php

namespace Tests\Unit\Costing;

use App\Domain\Costing\Allocations\ReturnAllocation;
use App\Domain\Costing\ProductValuation;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * The return allocation at commit (AC-033-03, AC-033-04; ADR-008 sections 3, 4.1, 4.2 and 5; COSTING
 * BR-COST-09, BR-COST-13, BR-COST-15, BR-COST-17 to BR-COST-19; D-061; G-13; suites RET01 and FIN03;
 * TASK-033 section 7, L1): the cumulative rule — a return restores its share of the linked issue's cost,
 * several partial returns leave no residue and never pass it, the whole quantity back restores it exactly;
 * UNKNOWN stays UNKNOWN, never zero; a return beyond the issue is refused; and the valuation after it, an
 * increase that starts a new pool from zero, with the publication it was based on.
 */
final class ReturnAllocationTest extends TestCase
{
    public function test_a_return_restores_its_share_of_the_issue_by_the_cumulative_rule(): void
    {
        // D-061's worked example: 10 units issued for Rp1.000; 2 returned restore Rp200, and after the
        // cost is corrected to Rp1.200, Rp240.
        $this->assertSame('200', ReturnAllocation::restored(Cost::of('1000'), self::units('10'), self::units('0'), self::units('2'))->toCanonical());
        $this->assertSame('240', ReturnAllocation::restored(Cost::of('1200'), self::units('10'), self::units('0'), self::units('2'))->toCanonical());

        // An issue of 3 for Rp100 returned one unit at a time: 33.333333, 33.333334, 33.333333 — Rp100
        // in all, exactly; each the difference of the rounded cumulative shares (T-17 e).
        $restored = [];

        foreach (['0', '1', '2'] as $before) {
            $restored[] = ReturnAllocation::restored(Cost::of('100'), self::units('3'), self::units($before), self::units('1'))->toCanonical();
        }

        $this->assertSame(['33.333333', '33.333334', '33.333333'], $restored);

        // Two of three at once restore what the two single returns did, and the rest the remainder.
        $this->assertSame('66.666667', ReturnAllocation::restored(Cost::of('100'), self::units('3'), self::units('0'), self::units('2'))->toCanonical());
        $this->assertSame('33.333333', ReturnAllocation::restored(Cost::of('100'), self::units('3'), self::units('2'), self::units('1'))->toCanonical());

        // A weighed product: 0,5 of 3 kg issued for Rp10 restores round6(10 × 0,5 / 3).
        $this->assertSame('1.666667', ReturnAllocation::restored(Cost::of('10'), Quantity::of('3', 3), Quantity::of('0', 3), Quantity::of('0.5', 3))->toCanonical());

        // An issue of zero cost restores zero, a known zero.
        $this->assertSame('0', ReturnAllocation::restored(Cost::of('0'), self::units('4'), self::units('0'), self::units('4'))->toCanonical());
    }

    public function test_an_unknown_issue_restores_an_unknown_cost_and_a_return_beyond_it_is_refused(): void
    {
        // BR-COST-13, BR-DOM-08: UNKNOWN is never zero.
        $this->assertFalse(ReturnAllocation::restored(Cost::unknown(), self::units('5'), self::units('0'), self::units('1'))->isKnown());

        // ADR-008 section 3, part 2: the returns of an issue never take back more than it issued; the
        // line's running total refuses it first (BR-RFD-03).
        $this->expectException(NumberOutOfRange::class);
        ReturnAllocation::restored(Cost::of('100'), self::units('3'), self::units('2'), self::units('2'));
    }

    public function test_the_valuation_after_a_return_is_an_increase_with_the_restored_cost(): void
    {
        // ADR-008 sections 4.1 and 4.2; BR-COST-09: 5 on hand worth Rp500 at publication 4, then 2 back
        // of an issue of 10 for Rp1.000: Rp200 restored, 7 worth Rp700 at publication 5, based on 4.
        $after = ReturnAllocation::compute(Cost::of('1000'), self::units('10'), self::units('0'), self::units('2'), new ProductValuation(9, Cost::of('500'), 4, 6, true), self::units('5'), 7);

        $this->assertSame(['KNOWN', '200', 4], [$after->status(), $after->canonicalAmount(), $after->basedOnPublicationNo]);
        $this->assertSame(['700', 5, 7], [$after->valuation->value->toCanonical(), $after->valuation->publicationNo, $after->valuation->lastSequence]);

        // From zero on hand a new pool starts with the restored cost (BR-COST-15), even after an
        // UNKNOWN pool: the old pool's value does not survive its end.
        $fresh = ReturnAllocation::compute(Cost::of('1000'), self::units('10'), self::units('0'), self::units('2'), new ProductValuation(9, Cost::unknown(), 4, 6, true), self::units('0'), 7);
        $this->assertSame('200', $fresh->valuation->value->toCanonical());

        // An UNKNOWN restored cost makes the valuation UNKNOWN (BR-COST-13), with no amount.
        $unknown = ReturnAllocation::compute(Cost::unknown(), self::units('10'), self::units('0'), self::units('2'), new ProductValuation(9, Cost::of('500'), 4, 6, true), self::units('5'), 7);
        $this->assertSame(['UNKNOWN', null, 'UNKNOWN'], [$unknown->status(), $unknown->canonicalAmount(), $unknown->valuation->value->toCanonical()]);
    }

    private static function units(string $quantity): Quantity
    {
        return Quantity::of($quantity, 0);
    }
}
