<?php

namespace Tests\Unit\Support\Casts;

use App\Support\Casts\CostCast;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\QuantityCast;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * The three casts round-trip random values with no loss, and set() refuses
 * what the row cannot hold before any write (ADR-006 item 3, BR-NUM-02;
 * TASK-003 adversarial finding A-12): a quantity with more decimals than the
 * row's precision, and money outside the range of a `bigint` column.
 */
final class CastsRoundTripTest extends TestCase
{
    private const string BIGINT_MAX = '9223372036854775807';

    private const string BIGINT_MIN = '-9223372036854775808';

    public function test_random_values_round_trip_with_no_loss(): void
    {
        mt_srand(20261020);

        for ($i = 0; $i < 3000; $i++) {
            $precision = mt_rand(0, 3);
            $money = (mt_rand(0, 1) === 1 ? '-' : '').mt_rand(1, PHP_INT_MAX);
            $units = str_pad((string) mt_rand(1, 9999999999999), $precision + 1, '0', STR_PAD_LEFT);
            $quantity = $precision === 0 ? $units : (ltrim(substr($units, 0, -$precision), '0') ?: '0').'.'.substr($units, -$precision);
            $cost = mt_rand(0, 999999999).'.'.str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

            $model = self::model($precision);
            $model->setAttribute('amount', Money::of($money));
            $model->setAttribute('quantity', Quantity::of($quantity, $precision));
            $model->setAttribute('cost', Cost::of($cost));

            $read = self::model($precision);
            $read->setRawAttributes($model->getAttributes());

            $this->assertSame(Money::of($money)->toCanonical(), self::money($read)->toCanonical());
            $this->assertSame(Quantity::of($quantity, $precision)->toCanonical(), self::quantity($read)->toCanonical());
            $this->assertSame(Cost::of($cost)->toCanonical(), self::cost($read)->toCanonical());
        }
    }

    public function test_a_quantity_with_more_decimals_than_the_rows_precision_is_refused_when_it_is_set(): void
    {
        $model = self::model(0);

        try {
            $model->setAttribute('quantity', Quantity::of('1.5', 1));
            $this->fail(sprintf('A quantity of 1.5 was written for a product counted whole (precision 0): %s', json_encode($model->getAttributes()['quantity'] ?? null)));
        } catch (NonCanonicalNumber) {
            $this->assertArrayNotHasKey('quantity', $model->getAttributes());
        }

        $fixed = new class extends Model
        {
            protected $guarded = [];

            protected function casts(): array
            {
                return ['quantity' => QuantityCast::class.':1'];
            }
        };

        $this->assertRefused(fn () => $fixed->setAttribute('quantity', Quantity::of('1.25', 2)));
        $fixed->setAttribute('quantity', Quantity::of('1.50', 2));
        $this->assertSame('1.5', $fixed->getAttributes()['quantity']);
    }

    public function test_a_quantity_whose_row_has_no_precision_yet_is_refused_when_it_is_set(): void
    {
        $model = self::model(null);

        $this->assertRefused(fn () => $model->setAttribute('quantity', Quantity::of('1', 0)));
        $this->assertArrayNotHasKey('quantity', $model->getAttributes());
    }

    public function test_a_money_value_the_bigint_column_cannot_hold_is_refused_when_it_is_set(): void
    {
        $model = self::model(0);

        foreach (['9223372036854775808', '-9223372036854775809', '99999999999999999999'] as $amount) {
            try {
                $model->setAttribute('amount', Money::of($amount));
                $this->fail("A money value outside the bigint range was written: {$amount}");
            } catch (NumberOutOfRange) {
                $this->assertArrayNotHasKey('amount', $model->getAttributes());
            }
        }

        $model->setAttribute('amount', Money::of(self::BIGINT_MAX));
        $this->assertSame(self::BIGINT_MAX, $model->getAttributes()['amount']);
        $model->setAttribute('amount', Money::of(self::BIGINT_MIN));
        $this->assertSame(self::BIGINT_MIN, $model->getAttributes()['amount']);
    }

    public function test_wrong_types_are_refused_both_ways(): void
    {
        $model = self::model(3);

        foreach (['amount' => '75000', 'quantity' => '1.5', 'cost' => '0.5'] as $attribute => $string) {
            $this->assertRefused(fn () => $model->setAttribute($attribute, $string));
            $this->assertRefused(fn () => $model->setAttribute($attribute, 1));
        }

        $this->assertRefused(fn () => $model->setAttribute('amount', Quantity::of('1', 0)));

        foreach ([['amount' => true], ['quantity' => 3], ['cost_amount' => 1, 'cost_status' => 'KNOWN'], ['cost_amount' => '1', 'cost_status' => 'UNKNOWN'], ['cost_amount' => null, 'cost_status' => 'KNOWN'], ['quantity' => '1.5', 'precision' => '4']] as $raw) {
            $read = self::model(3);
            $read->setRawAttributes($raw);
            $attribute = array_key_first($raw) === 'cost_amount' ? 'cost' : (string) array_key_first($raw);
            $this->assertRefused(fn () => $read->getAttribute($attribute));
        }
    }

    private function assertRefused(callable $compute): void
    {
        try {
            $compute();
            $this->fail('accepted');
        } catch (NonCanonicalNumber|NumberOutOfRange|InvalidArgumentException|LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    private static function model(?int $precision): Model
    {
        $model = new class extends Model
        {
            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'amount' => MoneyCast::class,
                    'quantity' => QuantityCast::class.':precision',
                    'cost' => CostCast::class.':cost_amount,cost_status',
                ];
            }
        };

        $model->setRawAttributes($precision === null ? [] : ['precision' => $precision]);

        return $model;
    }

    private static function money(Model $model): Money
    {
        $value = $model->getAttribute('amount');

        return $value instanceof Money ? $value : self::fail('not Money');
    }

    private static function quantity(Model $model): Quantity
    {
        $value = $model->getAttribute('quantity');

        return $value instanceof Quantity ? $value : self::fail('not a Quantity');
    }

    private static function cost(Model $model): Cost
    {
        $value = $model->getAttribute('cost');

        return $value instanceof Cost ? $value : self::fail('not a Cost');
    }
}
