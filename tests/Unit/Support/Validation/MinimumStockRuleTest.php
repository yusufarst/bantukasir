<?php

namespace Tests\Unit\Support\Validation;

use App\Support\Validation\MinimumStockRule;
use App\Support\Validation\MoneyRule;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The minimum stock of a product (BR-CAT-11; SCHEMA section 3.2; TR-VAL-06; the
 * keys of COPY_DECK section 16.1; TASK-015): zero is valid, a value is canonical,
 * within the precision and the stock range, and the leaf of its key is what
 * the error body of RT-07 names. MoneyRule's leaves are read the same way.
 */
final class MinimumStockRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int, string|null}>
     */
    public static function values(): iterable
    {
        yield 'zero' => ['0', 0, null];
        yield 'a whole number' => ['12', 0, null];
        yield 'within the precision' => ['1.5', 1, null];
        yield 'three decimals' => ['1.125', 3, null];
        yield 'the largest' => ['9999999999.999', 3, null];
        yield 'above a line quantity' => ['100000000', 0, null];
        yield 'negative' => ['-1', 0, 'min_stock'];
        yield 'a JSON number' => [5, 0, 'min_stock'];
        yield 'a float' => [1.5, 3, 'min_stock'];
        yield 'not canonical' => ['01', 0, 'min_stock'];
        yield 'a separator' => ['1,5', 1, 'min_stock'];
        yield 'an exponent' => ['1e3', 0, 'min_stock'];
        yield 'an empty text' => ['', 0, 'min_stock'];
        yield 'decimals at precision 0' => ['1.5', 0, 'quantity_whole'];
        yield 'a decimal zero at precision 0' => ['1.0', 0, 'quantity_whole'];
        yield 'too many decimals' => ['1.25', 1, 'quantity_decimals'];
        yield 'above the range' => ['10000000000', 0, 'quantity_max'];
    }

    #[DataProvider('values')]
    public function test_a_value_passes_or_fails_with_its_key(mixed $value, int $precision, ?string $problem): void
    {
        $rule = new MinimumStockRule($precision);

        $this->assertSame($problem, $rule->problem($value, 'minimumStock'));
        $this->assertSame($problem === null, Validator::make(['minimumStock' => $value], ['minimumStock' => [$rule]])->passes());
    }

    public function test_a_precision_outside_0_to_3_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MinimumStockRule(4);
    }

    public function test_a_money_value_names_the_leaf_of_its_key(): void
    {
        $this->assertNull(MoneyRule::problem('7500', 'sellingPrice'));
        $this->assertSame('money', MoneyRule::problem('7500.5', 'sellingPrice'));
        $this->assertSame('money', MoneyRule::problem(7500, 'sellingPrice'));
        $this->assertSame('money_min', MoneyRule::problem('-1', 'sellingPrice'));
        $this->assertSame('money_max', MoneyRule::problem('1000000000000', 'sellingPrice'));
    }
}
