<?php

namespace Tests\Unit\Inventory;

use App\Domain\Inventory\StockPosition;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * The balance a posting computes, line by line, before it writes anything (BR-INV-03, BR-INV-04,
 * BR-INV-05, BR-INV-07; G-02): the quantities of one product added together, each in the next
 * place of its sequence, never below zero and never past the range of a balance.
 */
final class StockPositionTest extends TestCase
{
    public function test_a_product_with_no_movement_has_nothing_on_hand_and_no_row(): void
    {
        $none = StockPosition::none(5, 2);

        $this->assertSame([5, '0', 2, 0, false], [$none->productId, $none->onHand->toCanonical(), $none->onHand->precision(), $none->lastSequence, $none->stored]);
    }

    public function test_the_lines_of_one_product_add_together_each_in_the_next_place(): void
    {
        $first = StockPosition::none(5, 2)->moved(Quantity::of('1.25', 2));
        $second = $first->moved(Quantity::of('0.75', 2));

        $this->assertSame(['1.25', 1, false], [$first->onHand->toCanonical(), $first->lastSequence, $first->stored]);
        $this->assertSame(['2', 2, false], [$second->onHand->toCanonical(), $second->lastSequence, $second->stored]);

        $stored = new StockPosition(5, Quantity::of('2', 2), 9, true);
        $this->assertSame(['3', 10, true], [$stored->moved(Quantity::of('1', 2))->onHand->toCanonical(), $stored->moved(Quantity::of('1', 2))->lastSequence, $stored->moved(Quantity::of('1', 2))->stored]);
    }

    public function test_stock_never_goes_below_zero(): void
    {
        // BR-INV-04, BR-INV-07: what an issue will meet (TASK-023).
        $position = new StockPosition(5, Quantity::of('1', 0), 1, true);
        $this->assertSame('0', $position->moved(Quantity::of('-1', 0))->onHand->toCanonical());

        $this->expectException(NumberOutOfRange::class);
        $position->moved(Quantity::of('-2', 0));
    }

    public function test_a_balance_never_passes_its_range(): void
    {
        // TECHNICAL_RULES section 11.1: 9,999,999,999.999.
        $position = new StockPosition(5, Quantity::of('9999999999.998', 3), 1, true);
        $this->assertSame('9999999999.999', $position->moved(Quantity::of('0.001', 3))->onHand->toCanonical());

        $this->expectException(NumberOutOfRange::class);
        $position->moved(Quantity::of('0.002', 3));
    }
}
