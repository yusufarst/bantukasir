<?php

namespace Tests\Unit\Localization;

use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The server's run of the placeholder cases,
 * tests/Unit/Support/cases/placeholders.json: Laravel's translator replaces
 * `:name` placeholders as t() does on the client (COPY_DECK section 1.2). The
 * texts are added to the translator for the test only.
 */
final class PlaceholderCasesTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, string>, string}>
     */
    public static function cases(): array
    {
        /** @var array{cases: list<array{case: string, text: string, replacements: array<string, string>, expected: string}>} $table */
        $table = json_decode((string) file_get_contents(__DIR__.'/../Support/cases/placeholders.json'), true, 512, JSON_THROW_ON_ERROR);
        $cases = [];

        foreach ($table['cases'] as $case) {
            $cases[$case['case']] = [$case['text'], $case['replacements'], $case['expected']];
        }

        return $cases;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    #[DataProvider('cases')]
    public function test_the_translator_replaces_placeholders_as_t_does(string $text, array $replacements, string $expected): void
    {
        Lang::addLines(['ux.placeholder_case' => $text], 'id');

        $this->assertSame($expected, __('ux.placeholder_case', $replacements));
    }
}
