<?php

namespace Tests\Unit\Costing;

use App\Domain\Costing\ProductValuation;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Limits;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * The increase of the moving weighted average (AC-021-04; ADR-008 sections 4.1, 4.2 and 5;
 * BR-COST-09, BR-COST-12, BR-COST-13, BR-COST-15; suites FIN01 and FIN02), the cases of
 * ADR-008's list that concern receipts, at L1: the receipts of the L-15 example, whose sums are
 * exact and whose average is one division of them; UNKNOWN, which is never zero and stays until
 * stock reaches zero; a new pool at zero; the range of a value; and the publication number.
 */
final class ProductValuationTest extends TestCase
{
    public function test_the_empty_prefix_is_a_known_zero_with_nothing_published(): void
    {
        $none = ProductValuation::none(7);

        $this->assertSame([7, '0', 0, 0, false], [$none->productId, $none->value->toCanonical(), $none->publicationNo, $none->lastSequence, $none->stored]);
    }

    public function test_the_receipts_of_the_l_15_example_give_their_value_and_average(): void
    {
        // COSTING section 4: 10 units for Rp100.000, then 10 for Rp140.000: 20 units worth
        // Rp240.000, average Rp12.000.
        $first = ProductValuation::none(1)->increased(self::units('0'), Cost::of('100000'), 1);
        $second = $first->increased(self::units('10'), Cost::of('140000'), 2);

        $this->assertSame(['100000', 1, 1], [$first->value->toCanonical(), $first->publicationNo, $first->lastSequence]);
        $this->assertSame(['240000', 2, 2], [$second->value->toCanonical(), $second->publicationNo, $second->lastSequence]);
        $this->assertSame('12000', Cost::quotient($second->value->amount(), BigDecimal::of('20'))->toCanonical());
    }

    public function test_the_value_is_an_exact_sum_rounded_nowhere_and_the_average_one_division_of_it(): void
    {
        // BR-COST-12, ADR-008 section 5: aggregated before rounding. Three thirds of 100,
        // each rounded by the supplier's invoice, sum exactly; dividing each first would not.
        $state = ProductValuation::none(1);

        foreach (['33.333333', '33.333333', '33.333334'] as $index => $amount) {
            $state = $state->increased(self::units((string) $index), Cost::of($amount), $index + 1);
        }

        $this->assertSame('100', $state->value->toCanonical());
        $this->assertSame('33.333333', Cost::quotient($state->value->amount(), BigDecimal::of('3'))->toCanonical());

        // The smallest amounts add without loss, and a zero adds nothing.
        $small = ProductValuation::none(1)->increased(self::units('0'), Cost::of('0.000001'), 1)->increased(self::units('1'), Cost::of('0.000002'), 2)->increased(self::units('2'), Cost::of('0'), 3);
        $this->assertSame('0.000003', $small->value->toCanonical());
    }

    public function test_an_unknown_cost_makes_the_value_unknown_never_zero_and_it_stays_while_stock_remains(): void
    {
        // BR-COST-03, BR-COST-13.
        $unknown = ProductValuation::none(1)->increased(self::units('0'), Cost::unknown(), 1);

        $this->assertFalse($unknown->value->isKnown());
        $this->assertSame('UNKNOWN', $unknown->value->toCanonical());

        // A later KNOWN increase does not complete it.
        $later = $unknown->increased(self::units('5'), Cost::of('100'), 2);
        $this->assertSame('UNKNOWN', $later->value->toCanonical());

        // A KNOWN value becomes UNKNOWN with the first UNKNOWN cost.
        $known = ProductValuation::none(1)->increased(self::units('0'), Cost::of('100'), 1);
        $this->assertSame('UNKNOWN', $known->increased(self::units('5'), Cost::unknown(), 2)->value->toCanonical());
    }

    public function test_stock_at_zero_starts_a_new_pool(): void
    {
        // BR-COST-15, ADR-008 section 4.1: whatever the state that stock reached zero in.
        $emptied = new ProductValuation(1, Cost::unknown(), 4, 4, true);

        $this->assertSame('250', $emptied->increased(self::units('0'), Cost::of('250'), 5)->value->toCanonical());
        $this->assertSame('UNKNOWN', $emptied->increased(self::units('0'), Cost::unknown(), 5)->value->toCanonical());

        $residue = new ProductValuation(1, Cost::of('12.5'), 4, 4, true);
        $this->assertSame('250', $residue->increased(self::units('0'), Cost::of('250'), 5)->value->toCanonical());

        // With stock left, the pool goes on.
        $this->assertSame('262.5', $residue->increased(self::units('1'), Cost::of('250'), 5)->value->toCanonical());
    }

    public function test_a_publication_counts_every_posting_and_a_stored_row_stays_stored(): void
    {
        $stored = new ProductValuation(9, Cost::of('1'), 41, 41, true);
        $next = $stored->increased(self::units('1'), Cost::unknown(), 42);
        $after = $next->increased(self::units('2'), Cost::unknown(), 43);

        $this->assertSame([9, 43, 43, true], [$after->productId, $after->publicationNo, $after->lastSequence, $after->stored]);
    }

    public function test_a_value_past_its_range_is_refused_and_the_limit_itself_is_kept(): void
    {
        // TECHNICAL_RULES section 11.1: a posting that would exceed it is refused.
        $state = new ProductValuation(1, Cost::of(Limits::VALUE_MAX), 1, 1, true);

        $this->assertSame(Limits::VALUE_MAX, $state->increased(self::units('1'), Cost::of('0'), 2)->value->toCanonical());
        $this->assertSame('UNKNOWN', $state->increased(self::units('1'), Cost::unknown(), 2)->value->toCanonical());

        $this->expectException(NumberOutOfRange::class);
        $state->increased(self::units('1'), Cost::of('0.000001'), 2);
    }

    private static function units(string $quantity): Quantity
    {
        return Quantity::of($quantity, 0);
    }
}
