<?php

namespace Tests\Unit\Support\Formatting;

use App\Support\Formatting\Format;
use App\Support\Localization\Dictionary;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MissingTranslationKey;
use Tests\TestCase;

/**
 * The server's run of the one table of formatter cases,
 * tests/Unit/Support/cases/formatting.json (COPY_DECK sections 1.3 and 1.4,
 * AC-003-04): every case, then every case again under other default time
 * zones, process locales and application locales, with the same results.
 * tests/Unit/Support/format.test.ts runs the same table on the client.
 */
final class FormatCasesTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function cases(): array
    {
        $cases = [];

        foreach (self::table()['cases'] as $case) {
            $cases[sprintf('%s %s', self::string($case['format']), json_encode($case['input']))] = [$case];
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('cases')]
    public function test_a_format_gives_the_copy_deck_text(array $case): void
    {
        $this->assertSame($case['expected'], self::format($case));
    }

    public function test_the_formats_ignore_the_servers_time_zone_and_locale(): void
    {
        $timeZone = date_default_timezone_get();
        $locale = setlocale(LC_ALL, '0');

        try {
            foreach ([['America/Los_Angeles', 'C', 'en'], ['Pacific/Kiritimati', 'C.utf8', 'en_US'], ['UTC', 'POSIX', 'fr']] as [$zone, $processLocale, $applicationLocale]) {
                date_default_timezone_set($zone);
                setlocale(LC_ALL, $processLocale);
                $this->app->setLocale($applicationLocale);

                foreach (self::table()['cases'] as $case) {
                    $this->assertSame($case['expected'], self::format($case), sprintf('%s under %s, %s, %s', json_encode($case), $zone, $processLocale, $applicationLocale));
                }
            }
        } finally {
            date_default_timezone_set($timeZone);
            setlocale(LC_ALL, $locale === false ? 'C' : $locale);
            $this->app->setLocale('id');
        }
    }

    public function test_the_tables_dictionary_is_the_ux_format_texts_of_lang_id(): void
    {
        $formats = array_filter(Dictionary::forInterface(), fn (string $key): bool => str_starts_with($key, 'ux.format.'), ARRAY_FILTER_USE_KEY);

        $this->assertSame($formats, self::table()['dictionary']);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private static function format(array $case): ?string
    {
        $input = $case['input'];

        try {
            return match ($case['format']) {
                'money' => Format::money(Money::of(self::string($input))),
                'quantity' => Format::quantity(Quantity::of(self::string($input), self::int($case['precision'] ?? null)), isset($case['unit']) ? self::string($case['unit']) : null),
                'cost' => Format::cost(self::costFigure(self::string($input))),
                'date' => Format::date(BusinessDate::fromString(self::string($input))),
                'dateTime' => Format::dateTime(CarbonImmutable::parse(self::string($input))),
                'timeOfDay' => Format::timeOfDay(CarbonImmutable::parse(self::string($input))),
                'dayName' => Format::dayName(BusinessDate::fromString(self::string($input))),
                'count' => Format::count(self::int($input)),
                // The table also holds amounts that are not integers, which
                // the formatter refuses at run time.
                'duration' => Format::duration($input, self::string($case['unit'] ?? null)), // @phpstan-ignore argument.type
                'variance' => Format::variance(Money::of(self::string($input))),
                default => self::fail('Unknown format'),
            };
        } catch (MissingTranslationKey $missing) {
            throw $missing;
        } catch (NonCanonicalNumber|NumberOutOfRange|InvalidArgumentException|LogicException) {
            return null;
        }
    }

    /**
     * A cost value, which may be UNKNOWN, or a signed cost figure such as a
     * gross profit, with at most six decimals.
     */
    private static function costFigure(string $input): Cost|BigDecimal
    {
        return $input === Cost::UNKNOWN ? Cost::of($input) : CanonicalNumber::parse($input, Cost::SCALE, true, 'cost figure');
    }

    /**
     * @return array{dictionary: array<string, string>, cases: list<array<string, mixed>>}
     */
    private static function table(): array
    {
        /** @var array{dictionary: array<string, string>, cases: list<array<string, mixed>>} $table */
        $table = json_decode((string) file_get_contents(__DIR__.'/../cases/formatting.json'), true, 512, JSON_THROW_ON_ERROR);

        return $table;
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
