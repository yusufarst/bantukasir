<?php

namespace Tests\Unit\Support\Validation;

use App\Support\Validation\CostRule;
use App\Support\Validation\MoneyRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The validation rules for canonical number strings (contract item 3,
 * AC-003-02, TR-VAL-06): every non-canonical form and every JSON number is
 * refused, never repaired, with the COPY_DECK message of section 16.1.
 */
final class NumberRulesTest extends TestCase
{
    private const MONEY = 'Isi jumlah rupiah yang benar, tanpa angka di belakang koma.';

    private const MONEY_MIN = 'Tidak boleh kurang dari Rp0.';

    private const MONEY_MAX = 'Paling banyak Rp999.999.999.999.';

    private const POSITIVE = 'Isi jumlah lebih dari 0.';

    private const WHOLE = 'Produk ini dihitung utuh. Isi tanpa koma.';

    private const QUANTITY_MAX = 'Jumlah terlalu besar. Paling banyak 99.999.999,999.';

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function money(): iterable
    {
        yield 'a canonical amount' => ['75000', null];
        yield 'zero' => ['0', null];
        yield 'the largest amount' => ['999999999999', null];
        yield 'a JSON integer' => [75000, self::MONEY];
        yield 'a JSON float' => [75000.0, self::MONEY];
        yield 'a decimal' => ['75000.5', self::MONEY];
        yield 'a decimal zero' => ['75000.0', self::MONEY];
        yield 'a thousands separator' => ['75.000', self::MONEY];
        yield 'a comma' => ['75,000', self::MONEY];
        yield 'an exponent' => ['7.5e4', self::MONEY];
        yield 'a leading plus' => ['+75000', self::MONEY];
        yield 'a leading zero' => ['075000', self::MONEY];
        yield 'whitespace' => [' 75000', self::MONEY];
        yield 'a negative zero' => ['-0', self::MONEY];
        yield 'an array' => [['75000'], self::MONEY];
        yield 'a boolean' => [true, self::MONEY];
        yield 'a negative amount' => ['-1', self::MONEY_MIN];
        yield 'above the range' => ['1000000000000', self::MONEY_MAX];
        yield 'far above the range' => [str_repeat('9', 60), self::MONEY_MAX];
        yield 'five thousand digits' => [str_repeat('9', 5000), self::MONEY_MAX];
    }

    #[DataProvider('money')]
    public function test_a_money_field_is_refused_unless_canonical_and_in_range(mixed $value, ?string $message): void
    {
        $this->assertSame($message, $this->firstError(new MoneyRule, $value));
    }

    /**
     * @return iterable<string, array{int, mixed, ?string}>
     */
    public static function quantities(): iterable
    {
        yield 'three decimals at precision 3' => [3, '1.250', null];
        yield 'the smallest quantity' => [3, '0.001', null];
        yield 'the largest quantity' => [3, '99999999.999', null];
        yield 'a whole quantity at precision 0' => [0, '3', null];
        yield 'zero' => [3, '0', self::POSITIVE];
        yield 'zero with decimals' => [3, '0.000', self::POSITIVE];
        yield 'a negative quantity' => [3, '-1', self::POSITIVE];
        yield 'a JSON number' => [3, 1.5, self::POSITIVE];
        yield 'a decimal comma' => [3, '1,5', self::POSITIVE];
        yield 'an exponent' => [3, '1e2', self::POSITIVE];
        yield 'a point without a fraction' => [3, '1.', self::POSITIVE];
        yield 'decimals at precision 0' => [0, '1.5', self::WHOLE];
        yield 'a decimal zero at precision 0' => [0, '3.0', self::WHOLE];
        yield 'three decimals at precision 2' => [2, '1.505', 'Paling banyak 2 angka di belakang koma untuk produk ini.'];
        yield 'four decimals at precision 3' => [3, '0.0001', 'Paling banyak 3 angka di belakang koma untuk produk ini.'];
        yield 'a trailing zero beyond precision 1' => [1, '1.50', 'Paling banyak 1 angka di belakang koma untuk produk ini.'];
        yield 'above the range' => [3, '100000000', self::QUANTITY_MAX];
        yield 'far above the range' => [0, str_repeat('9', 60), self::QUANTITY_MAX];
    }

    #[DataProvider('quantities')]
    public function test_a_quantity_field_is_refused_unless_canonical_positive_and_in_range(int $precision, mixed $value, ?string $message): void
    {
        $this->assertSame($message, $this->firstError(new QuantityRule($precision), $value));
    }

    public function test_a_quantity_rule_takes_a_precision_from_0_to_3(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QuantityRule(4);
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function costs(): iterable
    {
        yield 'six decimals' => ['3333.333333', null];
        yield 'zero' => ['0', null];
        yield 'the largest cost' => ['999999999999.999999', null];
        yield 'seven decimals' => ['1.0000001', 'invalid'];
        yield 'a negative cost' => ['-1', 'invalid'];
        yield 'the UNKNOWN marker' => ['UNKNOWN', 'invalid'];
        yield 'a JSON number' => [12.5, 'invalid'];
        yield 'above the range' => ['1000000000000', 'too large'];
    }

    #[DataProvider('costs')]
    public function test_a_cost_field_is_refused_unless_canonical_and_in_range(mixed $value, ?string $message): void
    {
        $this->assertSame($message, $this->firstError(new CostRule('invalid', 'too large'), $value));
    }

    private function firstError(ValidationRule $rule, mixed $value): ?string
    {
        $validator = Validator::make(['field' => $value], ['field' => [$rule]]);

        return $validator->fails() ? $validator->errors()->first('field') : null;
    }
}
