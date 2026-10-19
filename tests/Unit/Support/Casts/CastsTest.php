<?php

namespace Tests\Unit\Support\Casts;

use App\Support\Casts\CostCast;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\QuantityCast;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Eloquent casts of the kernel without a float conversion (ADR-006 item 5,
 * contract item 2): values in and out of a model's attributes, as PDO returns
 * them, and a float refused both ways. No database is involved; TASK-004
 * proves the round-trip on PostgreSQL.
 */
final class CastsTest extends TestCase
{
    public function test_money_is_read_from_an_integer_or_a_string_and_written_as_a_string(): void
    {
        $line = $this->line(['unit_price' => 75000]);

        $this->assertInstanceOf(Money::class, $line->getAttribute('unit_price'));
        $this->assertSame('75000', $this->canonical($line, 'unit_price'));

        $line->setAttribute('unit_price', Money::of('3333'));

        $this->assertSame('3333', $line->getAttributes()['unit_price']);
        $this->assertSame('3333', $this->canonical($this->line(['unit_price' => '3333']), 'unit_price'));
    }

    public function test_quantity_takes_the_precision_from_its_row_or_its_argument(): void
    {
        $line = $this->line(['quantity' => '1.500', 'quantity_precision' => 1, 'balance' => '10.000']);

        $this->assertSame('1.5', $this->canonical($line, 'quantity'));
        $this->assertSame(1, $this->quantity($line, 'quantity')->precision());
        $this->assertSame(3, $this->quantity($line, 'balance')->precision());

        $line->setAttribute('quantity', Quantity::of('2.5', 1));

        $this->assertSame('2.5', $line->getAttributes()['quantity']);
        $this->assertSame(0, $this->quantity($this->line(['quantity' => '3.000', 'quantity_precision' => '0']), 'quantity')->precision());
        $this->expectException(InvalidArgumentException::class);
        $this->line(['quantity' => '1.000'])->getAttribute('quantity');
    }

    public function test_a_cost_with_its_status_column_keeps_unknown_apart_from_zero(): void
    {
        $line = $this->line(['cost_amount' => '12.500000', 'cost_status' => 'KNOWN']);

        $this->assertSame('12.5', $this->canonical($line, 'cost'));

        $line->setAttribute('cost', Cost::unknown());

        $this->assertNull($line->getAttributes()['cost_amount']);
        $this->assertSame('UNKNOWN', $line->getAttributes()['cost_status']);
        $this->assertSame('UNKNOWN', $this->canonical($line, 'cost'));

        $line->setAttribute('cost', Cost::of('0'));

        $this->assertSame('0', $line->getAttributes()['cost_amount']);
        $this->assertSame('KNOWN', $line->getAttributes()['cost_status']);
        $this->assertNull($this->line([])->getAttribute('cost'));
    }

    public function test_a_plain_cost_column_holds_a_known_cost_only(): void
    {
        $line = $this->line(['unit_cost' => '3333.333333']);

        $this->assertSame('3333.333333', $this->canonical($line, 'unit_cost'));

        $line->setAttribute('unit_cost', Cost::of('1.5'));

        $this->assertSame('1.5', $line->getAttributes()['unit_cost']);
        $this->expectException(InvalidArgumentException::class);
        $line->setAttribute('unit_cost', Cost::unknown());
    }

    public function test_a_model_serializes_the_values_as_strings_never_as_json_numbers(): void
    {
        $line = $this->line(['unit_price' => 75000, 'quantity' => '1.500', 'quantity_precision' => 1, 'cost_amount' => null, 'cost_status' => 'UNKNOWN']);

        $json = $line->toJson(JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"unit_price":"75000"', $json);
        $this->assertStringContainsString('"quantity":"1.5"', $json);
        $this->assertStringContainsString('"cost_amount":null,"cost_status":"UNKNOWN"', $json);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function floats(): iterable
    {
        yield 'a float price' => ['unit_price', 75000.0];
        yield 'a float quantity' => ['quantity', 1.5];
        yield 'a float cost' => ['cost', 12.5];
        yield 'a canonical string instead of a value object' => ['unit_price', '75000'];
    }

    #[DataProvider('floats')]
    public function test_a_float_or_a_raw_value_is_refused_when_written(string $attribute, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->line(['quantity_precision' => 1])->setAttribute($attribute, $value);
    }

    public function test_a_float_read_from_a_column_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->line(['unit_price' => 75000.0])->getAttribute('unit_price');
    }

    public function test_the_cost_cast_names_both_columns_or_neither(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CostCast('cost_amount');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function line(array $attributes): Model
    {
        $line = new class extends Model
        {
            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'unit_price' => MoneyCast::class,
                    'quantity' => QuantityCast::class.':quantity_precision',
                    'balance' => QuantityCast::class.':3',
                    'cost' => CostCast::class.':cost_amount,cost_status',
                    'unit_cost' => CostCast::class,
                ];
            }
        };

        $line->setRawAttributes($attributes);

        return $line;
    }

    /**
     * The canonical string of the value object an attribute is read as.
     */
    private function canonical(Model $model, string $attribute): string
    {
        $value = $model->getAttribute($attribute);

        return $value instanceof Money || $value instanceof Quantity || $value instanceof Cost
            ? $value->toCanonical()
            : $this->fail('A value object was expected.');
    }

    private function quantity(Model $model, string $attribute): Quantity
    {
        $value = $model->getAttribute($attribute);

        return $value instanceof Quantity ? $value : $this->fail('A Quantity was expected.');
    }
}
