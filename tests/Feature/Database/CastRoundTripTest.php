<?php

namespace Tests\Feature\Database;

use App\Database\Identifier;
use App\Support\Casts\CostCast;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\QuantityCast;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Eloquent\Model;
use Tests\Harness\DatabaseTestCase;

/**
 * The kernel's casts through real columns (AC-004-07; ADR-006 item 5, SCHEMA
 * section 3.2; TASK-003's casts): Money through bigint, Quantity through
 * numeric(13,3) at the row's precision, Cost through numeric(18,6) and
 * numeric(21,6) and as the KNOWN or UNKNOWN pair, written by the runtime role
 * and read back by an independent session, exactly and with no float on the
 * way: every binding sent and every value read is an integer or a string.
 */
final class CastRoundTripTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->harnessTable('harness_numbers');
        $this->migration()->statement(<<<'SQL'
            CREATE TABLE harness_numbers (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                amount bigint NOT NULL,
                quantity_precision smallint NOT NULL CHECK (quantity_precision BETWEEN 0 AND 3),
                quantity numeric(13,3) NOT NULL,
                unit_cost numeric(18,6) NOT NULL,
                value numeric(21,6) NOT NULL,
                cost_status text NOT NULL CHECK (cost_status IN ('KNOWN', 'UNKNOWN')),
                cost_amount numeric(18,6) NULL,
                CHECK ((cost_status = 'KNOWN') = (cost_amount IS NOT NULL))
            )
            SQL);
        $this->migration()->statement('GRANT SELECT, INSERT ON TABLE harness_numbers TO '.Identifier::quote($this->roles()->runtime));
    }

    public function test_boundary_values_round_trip_exactly(): void
    {
        $rows = [
            ['999999999999', 3, '99999999.999', '999999999999.999999', '999999999999999.999999', '999999999999.999999'],
            ['-999999999999', 0, '99999999', '0', '0', null],
            ['9223372036854775807', 1, '0.1', '0.000001', '0.000001', '0'],
            ['-9223372036854775808', 2, '1234.5', '12000', '1999999999998.000001', '0.000001'],
            ['0', 3, '0.001', '1.5', '7.25', null],
        ];

        $ids = [];
        $this->runtime()->enableQueryLog();

        foreach ($rows as [$amount, $precision, $quantity, $unitCost, $value, $cost]) {
            $number = $this->model();
            $number->setAttribute('quantity_precision', $precision);
            $number->setAttribute('amount', Money::of($amount));
            $number->setAttribute('quantity', Quantity::of($quantity, $precision));
            $number->setAttribute('unit_cost', Cost::of($unitCost));
            $number->setAttribute('value', Cost::of($value));
            $number->setAttribute('cost', $cost === null ? Cost::unknown() : Cost::of($cost));
            $number->save();
            $ids[] = $number->getKey();
        }

        foreach ($this->runtime()->getQueryLog() as $query) {
            foreach ($query['bindings'] as $binding) {
                $this->assertTrue(is_int($binding) || is_string($binding) || $binding === null, 'A binding was sent as '.get_debug_type($binding));
            }
        }

        $read = $this->model()->setConnection($this->observerName());

        foreach ($rows as $index => [$amount, $precision, $quantity, $unitCost, $value, $cost]) {
            $raw = (array) $this->observer()->selectOne('select amount, quantity, unit_cost, value, cost_amount from harness_numbers where id = ?', [$ids[$index]]);

            foreach ($raw as $column => $item) {
                $this->assertTrue(is_int($item) || is_string($item) || $item === null, $column.' was read as '.get_debug_type($item));
            }

            $number = $read->newQuery()->whereKey($ids[$index])->firstOrFail();

            $this->assertSame(Money::of($amount)->toCanonical(), self::money($number->getAttribute('amount'))->toCanonical());
            $this->assertSame(Quantity::of($quantity, $precision)->toCanonical(), self::quantity($number->getAttribute('quantity'))->toCanonical());
            $this->assertSame(Cost::of($unitCost)->toCanonical(), self::cost($number->getAttribute('unit_cost'))->toCanonical());
            $this->assertSame(Cost::of($value)->toCanonical(), self::cost($number->getAttribute('value'))->toCanonical());
            $this->assertSame($cost === null ? Cost::UNKNOWN : Cost::of($cost)->toCanonical(), self::cost($number->getAttribute('cost'))->toCanonical());
        }

        $this->assertSame(
            ['amount' => '-9223372036854775808', 'quantity' => '1234.500', 'unit_cost' => '12000.000000', 'cost_status' => 'KNOWN', 'cost_amount' => '0.000001'],
            array_map(fn (mixed $item): mixed => is_int($item) ? (string) $item : $item, (array) $this->observer()->selectOne('select amount, quantity, unit_cost, cost_status, cost_amount from harness_numbers where id = ?', [$ids[3]])),
        );
    }

    public function test_random_values_round_trip_exactly(): void
    {
        mt_srand(20261024);
        $written = [];

        for ($i = 0; $i < 200; $i++) {
            $precision = mt_rand(0, 3);
            $units = (string) mt_rand(1, 99999999999);
            $quantity = $precision === 0 ? substr($units, 0, 8) : (ltrim(substr($units, 0, -$precision), '0') ?: '0').'.'.substr($units, -$precision);

            $number = $this->model();
            $number->setAttribute('quantity_precision', $precision);
            $number->setAttribute('amount', Money::of((mt_rand(0, 1) === 1 ? '-' : '').mt_rand(1, PHP_INT_MAX)));
            $number->setAttribute('quantity', Quantity::of($quantity, $precision));
            $number->setAttribute('unit_cost', Cost::of(mt_rand(0, 999999999).'.'.str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT)));
            $number->setAttribute('value', Cost::of(mt_rand(1, 999999999).str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT).'.'.str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT)));
            $number->setAttribute('cost', mt_rand(0, 3) === 0 ? Cost::unknown() : Cost::of(mt_rand(0, 999999).'.'.str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT)));
            $number->save();

            $written[self::key($number)] = [
                self::money($number->getAttribute('amount'))->toCanonical(),
                self::quantity($number->getAttribute('quantity'))->toCanonical(),
                self::cost($number->getAttribute('unit_cost'))->toCanonical(),
                self::cost($number->getAttribute('value'))->toCanonical(),
                self::cost($number->getAttribute('cost'))->toCanonical(),
            ];
        }

        foreach ($this->model()->setConnection($this->observerName())->newQuery()->orderBy('id')->get() as $number) {
            $this->assertSame($written[self::key($number)], [
                self::money($number->getAttribute('amount'))->toCanonical(),
                self::quantity($number->getAttribute('quantity'))->toCanonical(),
                self::cost($number->getAttribute('unit_cost'))->toCanonical(),
                self::cost($number->getAttribute('value'))->toCanonical(),
                self::cost($number->getAttribute('cost'))->toCanonical(),
            ]);
        }

        $this->assertCount(200, $written);
    }

    private function model(): Model
    {
        return new class extends Model
        {
            protected $table = 'harness_numbers';

            public $timestamps = false;

            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'amount' => MoneyCast::class,
                    'quantity' => QuantityCast::class.':quantity_precision',
                    'unit_cost' => CostCast::class,
                    'value' => CostCast::class,
                    'cost' => CostCast::class.':cost_amount,cost_status',
                ];
            }
        };
    }

    private function observerName(): string
    {
        return $this->observer()->getName() ?? 'pgsql';
    }

    private static function key(Model $number): int
    {
        $key = $number->getKey();

        return is_int($key) ? $key : self::fail('Not an integer key: '.get_debug_type($key));
    }

    private static function money(mixed $value): Money
    {
        return $value instanceof Money ? $value : self::fail('Not Money: '.get_debug_type($value));
    }

    private static function quantity(mixed $value): Quantity
    {
        return $value instanceof Quantity ? $value : self::fail('Not a Quantity: '.get_debug_type($value));
    }

    private static function cost(mixed $value): Cost
    {
        return $value instanceof Cost ? $value : self::fail('Not a Cost: '.get_debug_type($value));
    }
}
