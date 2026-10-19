<?php

namespace Tests\Feature\Numbers;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What PDO gives on real PostgreSQL for the column types of SCHEMA section 3.2
 * and for aggregates over them, read with a SELECT of literals only, so that
 * no table is touched, and that the kernel reads each without a float
 * (ADR-006 item 5; the class-A adversarial review of TASK-003, area 3). The
 * casts' round trip through real tables is TASK-004's.
 */
final class PdoTypesTest extends TestCase
{
    public function test_pdo_never_gives_a_float_for_bigint_numeric_or_their_aggregates(): void
    {
        $row = (array) DB::selectOne(<<<'SQL'
            select 9007199254740993::bigint as money,
                   1250.500::numeric(13,3) as quantity,
                   12000.000000::numeric(18,6) as cost,
                   sum(x)::numeric as money_sum,
                   avg(x) as money_average,
                   sum(y) as cost_sum,
                   0.1::float8 as double_value
            from (values (999999999999::bigint, 999999999999.999999::numeric(18,6)), (999999999999::bigint, 0.000001::numeric(18,6))) as t(x, y)
            SQL);

        $this->assertSame(['int', 'string', 'string', 'string', 'string', 'string', 'float'], array_map(get_debug_type(...), array_values($row)));
        $this->assertSame('9007199254740993', Money::fromDatabase(self::intOrString($row['money']))->toCanonical());
        $this->assertSame('1250.5', Quantity::fromDatabase(self::string($row['quantity']), 1)->toCanonical());
        $this->assertSame('12000', Cost::fromDatabase(self::string($row['cost']))->toCanonical());
        $this->assertSame('1999999999998', Money::fromDatabase(self::intOrString($row['money_sum']))->toCanonical());
        $this->assertSame('1000000000000', Cost::fromDatabase(self::string($row['cost_sum']))->toCanonical());
        $this->assertSame('999999999999.00000000', $row['money_average']);

        $this->expectException(NonCanonicalNumber::class);
        Cost::fromDatabase(self::string($row['money_average']));
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : self::fail('PDO gave '.get_debug_type($value));
    }

    private static function intOrString(mixed $value): int|string
    {
        return is_int($value) || is_string($value) ? $value : self::fail('PDO gave '.get_debug_type($value));
    }
}
