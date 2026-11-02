<?php

namespace Tests\Unit\Inventory;

use App\Domain\Inventory\StockState;
use App\Support\Numbers\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The attention states of INVENTORY section 10 (BR-INV-40): with a minimum of 5, on hand 6 is
 * NORMAL, 5 and 1 are LOW and 0 is OUT; a minimum of zero has no LOW interval. Exact, whatever
 * the decimals of the product.
 */
final class StockStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, StockState}>
     */
    public static function cases(): iterable
    {
        yield 'above the minimum' => ['6', '5', StockState::Normal];
        yield 'at the minimum' => ['5', '5', StockState::Low];
        yield 'below the minimum' => ['1', '5', StockState::Low];
        yield 'nothing on hand' => ['0', '5', StockState::Out];
        yield 'a minimum of zero has no low interval' => ['1', '0', StockState::Normal];
        yield 'a new product with stock 0' => ['0', '0', StockState::Out];
        yield 'the smallest quantity above the minimum' => ['5.001', '5', StockState::Normal];
        yield 'the smallest quantity on hand' => ['0.001', '5', StockState::Low];
    }

    #[DataProvider('cases')]
    public function test_the_state_follows_the_balance_and_the_minimum(string $onHand, string $minimum, StockState $state): void
    {
        $this->assertSame($state, StockState::of(Quantity::of($onHand, 3), Quantity::of($minimum, 3)));
    }

    public function test_the_values_are_those_of_inventory_section_10(): void
    {
        $this->assertSame(['OUT', 'LOW', 'NORMAL'], array_map(static fn (StockState $state): string => $state->value, StockState::cases()));
    }
}
