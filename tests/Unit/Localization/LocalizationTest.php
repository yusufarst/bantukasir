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

    public function test_the_dictionary_holds_every_text_by_its_full_key(): void
    {
        $dictionary = Dictionary::forInterface();

        $this->assertSame('Oktober', $dictionary['ux.format.month.october']);
        $this->assertSame(':uang (:keterangan)', $dictionary['ux.format.variance']);
        $this->assertSame('menit', $dictionary['ux.format.duration.minute']);
        $this->assertCount(32, $dictionary);

        foreach ($dictionary as $key => $text) {
            $this->assertSame($text, __($key), $key);
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
