<?php

namespace Tests\Unit\Support\Numbers;

use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The server's run of the shared table of arithmetic, canonical-string and
 * rounding cases, tests/Unit/Support/cases/numbers.json (AC-003-01,
 * AC-003-02; FIN01). tests/Unit/Support/exact.test.ts runs the same table on
 * the client.
 */
final class NumberCasesTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function canonicalCases(): array
    {
        return self::named('canonical', fn (array $case): string => (string) json_encode([$case['kind'], $case['precision'] ?? null, $case['input']]));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function lineTotalCases(): array
    {
        return self::named('lineTotals', fn (array $case): string => self::string($case['case']));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function saleTotalCases(): array
    {
        return self::named('saleTotals', fn (array $case): string => self::string($case['case']));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function roundingCases(): array
    {
        return self::named('rounding', fn (array $case): string => sprintf('%s to %d', self::string($case['input']), self::int($case['scale'])));
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('canonicalCases')]
    public function test_a_canonical_string_is_accepted_or_refused(array $case): void
    {
        $input = self::string($case['input']);

        $actual = self::refusedAsNull(fn (): string => match ($case['kind']) {
            'money' => Money::of($input)->toCanonical(),
            'quantity' => Quantity::of($input, self::int($case['precision']))->toCanonical(),
            'cost' => Cost::of($input)->toCanonical(),
            default => self::fail('Unknown kind'),
        });

        $this->assertSame($case['expected'], $actual);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('lineTotalCases')]
    public function test_a_line_total_is_rounded_half_up_once(array $case): void
    {
        $actual = self::refusedAsNull(fn (): string => Money::lineTotal(
            Quantity::of(self::string($case['quantity']), self::int($case['precision'])),
            Money::of(self::string($case['unitPrice'])),
        )->toCanonical());

        $this->assertSame($case['expected'], $actual);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('saleTotalCases')]
    public function test_a_sale_total_is_the_exact_sum_of_its_line_totals(array $case): void
    {
        $lines = array_map(fn (mixed $line): Money => Money::of(self::string($line)), is_array($case['lines']) ? $case['lines'] : []);

        $this->assertSame($case['expected'], Money::sum(...$lines)->assertStored()->toCanonical());
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('roundingCases')]
    public function test_half_up_rounds_ties_away_from_zero(array $case): void
    {
        $rounded = BigDecimal::of(self::string($case['input']))->toScale(max(0, self::int($case['scale'])), RoundingMode::HalfUp);

        $this->assertSame($case['expected'], CanonicalNumber::format($rounded));
    }

    /**
     * @param  callable(): string  $operation
     */
    private static function refusedAsNull(callable $operation): ?string
    {
        try {
            return $operation();
        } catch (NonCanonicalNumber|NumberOutOfRange) {
            return null;
        }
    }

    /**
     * @param  callable(array<string, mixed>): string  $name
     * @return array<string, array{array<string, mixed>}>
     */
    private static function named(string $section, callable $name): array
    {
        $table = json_decode((string) file_get_contents(__DIR__.'/../cases/numbers.json'), true, 512, JSON_THROW_ON_ERROR);
        $cases = [];

        foreach (is_array($table) && is_array($table[$section] ?? null) ? $table[$section] : [] as $case) {
            if (is_array($case)) {
                /** @var array<string, mixed> $case */
                $cases[$name($case)] = [$case];
            }
        }

        return $cases;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : self::fail('A string was expected in the table.');
    }

    private static function int(mixed $value): int
    {
        return is_int($value) ? $value : self::fail('An integer was expected in the table.');
    }
}
