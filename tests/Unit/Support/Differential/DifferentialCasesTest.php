<?php

namespace Tests\Unit\Support\Differential;

use App\Support\Localization\Dictionary;
use Tests\TestCase;

/**
 * The server's half of the differential run of TASK-003 (the class-A
 * adversarial review's run, adopted: areas 4 and 5, AC-003-01 and AC-003-04):
 * the server kernel and formatters compute every case of the committed table
 * tests/Unit/Support/cases/differential.json again and give exactly its
 * expected outcomes, then the formatting cases again under other default time
 * zones, process locales and application locales. tests/Unit/Support/
 * differential.test.ts runs the same table on the client. The environment
 * variable DIFFERENTIAL_CASES names another table, such as the full-size run's
 * outside the tracked tree. Nothing is written.
 */
final class DifferentialCasesTest extends TestCase
{
    public function test_the_server_gives_the_expected_outcome_of_every_case(): void
    {
        $table = self::table();
        $differences = self::differences(ServerOutcomes::all($table['cases']), $table['expected']);

        $this->assertSame([], array_slice($differences, 0, 20), sprintf('%d differences', count($differences)));
        $this->assertGreaterThan(1000, array_sum(array_map(count(...), $table['expected'])));
    }

    public function test_the_formats_do_not_depend_on_the_servers_time_zone_or_locale(): void
    {
        $table = self::table();
        $timeZone = date_default_timezone_get();
        $locale = setlocale(LC_ALL, '0');
        $differences = [];

        try {
            foreach ([['Pacific/Kiritimati', 'C.utf8', 'en'], ['America/St_Johns', 'POSIX', 'fr'], ['Asia/Kathmandu', 'C', 'id_ID'], ['Australia/Lord_Howe', 'C.utf8', 'ar']] as [$zone, $processLocale, $applicationLocale]) {
                date_default_timezone_set($zone);
                setlocale(LC_ALL, $processLocale);
                $this->app->setLocale($applicationLocale);

                foreach (self::differences(ServerOutcomes::formats($table['cases']), $table['expected']) as $difference) {
                    $differences[] = "{$difference} under {$zone}, {$processLocale}, {$applicationLocale}";
                }
            }
        } finally {
            date_default_timezone_set($timeZone);
            setlocale(LC_ALL, $locale === false ? 'C' : $locale);
            $this->app->setLocale('id');
        }

        $this->assertSame([], array_slice($differences, 0, 20), sprintf('%d differences', count($differences)));
    }

    public function test_the_tables_dictionary_is_the_ux_format_texts_of_lang_id(): void
    {
        $formats = array_filter(Dictionary::forInterface(), fn (string $key): bool => str_starts_with($key, 'ux.format.'), ARRAY_FILTER_USE_KEY);

        $this->assertSame($formats, self::table()['dictionary']);
    }

    /**
     * @param  array<string, list<array{ok?: string, err?: string}>>  $actual
     * @param  array<string, list<mixed>>  $expected
     * @return list<string>
     */
    private static function differences(array $actual, array $expected): array
    {
        $differences = [];

        foreach ($actual as $category => $outcomes) {
            foreach ($outcomes as $index => $outcome) {
                if ($outcome !== ($expected[$category][$index] ?? null)) {
                    $differences[] = sprintf('%s #%d: %s instead of %s', $category, $index, json_encode($outcome, JSON_UNESCAPED_UNICODE), json_encode($expected[$category][$index] ?? null, JSON_UNESCAPED_UNICODE));
                }
            }

            if (count($outcomes) !== count($expected[$category] ?? [])) {
                $differences[] = sprintf('%s: %d outcomes instead of %d', $category, count($outcomes), count($expected[$category] ?? []));
            }
        }

        return $differences;
    }

    /**
     * @return array{cases: array<string, list<mixed>>, expected: array<string, list<mixed>>, dictionary: array<string, string>}
     */
    private static function table(): array
    {
        $file = getenv('DIFFERENTIAL_CASES');
        $file = is_string($file) && $file !== '' ? $file : __DIR__.'/../cases/differential.json';

        /** @var array{cases: array<string, list<mixed>>, expected: array<string, list<mixed>>, dictionary: array<string, string>} $table */
        $table = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return $table;
    }
}
