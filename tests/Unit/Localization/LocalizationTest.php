<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\Dictionary;
use App\Support\Validation\MoneyRule;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Tests\MissingTranslationKey;
use Tests\TestCase;

/**
 * The localization of ADR-005 on the server (contract item 7, AC-003-06,
 * L10N01): the files of COPY_DECK section 1.1 in lang/id, locale and fallback
 * `id`, a key missing from lang/id throwing in tests, and the dictionary that
 * React receives.
 */
final class LocalizationTest extends TestCase
{
    public function test_lang_id_holds_the_files_of_copy_deck_section_1_1(): void
    {
        $files = array_map(fn (string $path): string => basename($path, '.php'), glob(lang_path('id').'/*.php') ?: []);
        sort($files);
        $expected = Dictionary::FILES;
        sort($expected);

        $this->assertSame($expected, $files);
    }

    public function test_the_locale_and_the_fallback_are_id(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('id', config('app.fallback_locale'));
        $this->assertSame('id', $this->app->getLocale());
    }

    public function test_a_missing_server_key_throws_in_tests(): void
    {
        $this->assertSame('Oktober', __('ux.format.month.october'));

        try {
            __('ux.format.month.smarch');
        } catch (MissingTranslationKey $missing) {
            $this->assertSame('ux.format.month.smarch', $missing->key);

            return;
        }

        $this->fail('A missing key did not throw.');
    }

    public function test_a_built_in_rule_without_a_text_throws_and_the_validators_probes_do_not(): void
    {
        $refused = Validator::make(['harga' => 'abc'], ['harga' => [new MoneyRule]]);

        $this->assertSame('Isi jumlah rupiah yang benar, tanpa angka di belakang koma.', $refused->errors()->first('harga'));

        try {
            Validator::make(['harga' => ''], ['harga' => 'required'])->errors();
        } catch (MissingTranslationKey $missing) {
            $this->assertSame('validation.required', $missing->key);

            return;
        }

        $this->fail('The default message of a built-in rule did not throw.');
    }

    /**
     * TASK-003 adversarial finding A-09 (a): the three lookups of Laravel's
     * ValidationException::summarize() are framework probes in tests, so that
     * a request with several invalid fields answers 422; no other lookup of
     * an English text is let through. The production body of a 422 response,
     * which must never carry that summary, is TASK-008's (OPEN_ITEMS T-26).
     */
    public function test_exactly_the_three_summary_lookups_of_a_validation_exception_are_probes(): void
    {
        foreach (['(and :count more error)', '(and :count more errors)', 'The given data was invalid.'] as $summary) {
            $this->assertTrue(MissingTranslationKey::isFrameworkProbe($summary), $summary);
        }

        foreach (['(and :count more warnings)', 'The given data was invalid', 'the given data was invalid.', 'validation.required', 'ux.format.month.smarch', 'Unauthenticated.'] as $text) {
            $this->assertFalse(MissingTranslationKey::isFrameworkProbe($text), $text);
        }
    }

    public function test_the_dictionary_holds_every_text_by_its_full_key(): void
    {
        $dictionary = Dictionary::forInterface();

        $this->assertSame('Oktober', $dictionary['ux.format.month.october']);
        $this->assertSame(':uang (:keterangan)', $dictionary['ux.format.variance']);
        $this->assertSame('menit', $dictionary['ux.format.duration.minute']);
        // Every key of lang/id (TASK-003 had 32; each interface Task adds its own).
        $this->assertCount(count(Dictionary::fromDirectory(dirname(__DIR__, 3).'/lang/id')), $dictionary);
        $this->assertGreaterThanOrEqual(32, count($dictionary));

        foreach ($dictionary as $key => $text) {
            $this->assertSame($text, __($key), $key);
        }
    }

    /**
     * OPEN_ITEMS T-29 (TASK-009): the child of a key that is itself a text is
     * a literal dotted key at the top of its file, which Laravel's translator
     * reads first; the dictionary holds both by their full keys, and refuses
     * a dotted key whose parent is no text, one given twice, and one that is
     * not snake_case.
     */
    public function test_the_child_of_a_text_is_a_top_level_dotted_key_and_nothing_else_is(): void
    {
        $dictionary = Dictionary::forInterface();
        $this->assertSame('Penjualan', $dictionary['commands.family.sale']);
        $this->assertSame('Jangan minta pelanggan membayar atau mentransfer lagi. Penjualan ini mungkin sudah tersimpan.', $dictionary['commands.family.sale.must_not']);
        $this->assertSame($dictionary['commands.family.sale.must_not'], __('commands.family.sale.must_not'));

        $refused = [
            'no parent text' => "['family' => ['sale' => 'Penjualan'], 'family.other.must_not' => 'Teks']",
            'twice' => "['family' => ['sale' => ['must_not' => 'A']], 'family.sale.must_not' => 'B']",
            'not snake_case' => "['family' => ['sale' => 'Penjualan'], 'family.Sale.must_not' => 'Teks']",
            'a group' => "['family' => ['sale' => 'Penjualan'], 'family.sale.must_not' => ['x' => 'Teks']]",
        ];

        foreach ($refused as $case => $texts) {
            $directory = sys_get_temp_dir().'/bantukasir-dictionary-'.bin2hex(random_bytes(8));
            mkdir($directory, 0700);
            file_put_contents($directory.'/commands.php', "<?php\n\nreturn {$texts};\n");

            try {
                Dictionary::fromDirectory($directory);
                $this->fail("A dictionary with a dotted key ({$case}) is refused.");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            } finally {
                unlink($directory.'/commands.php');
                rmdir($directory);
            }
        }
    }

    public function test_a_dictionary_file_with_a_key_that_is_not_snake_case_is_refused(): void
    {
        $directory = sys_get_temp_dir().'/bantukasir-dictionary-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory.'/ux.php', "<?php\n\nreturn ['Format' => ['month' => 'Januari']];\n");

        try {
            $this->expectException(LogicException::class);
            Dictionary::fromDirectory($directory);
        } finally {
            unlink($directory.'/ux.php');
            rmdir($directory);
        }
    }
}
