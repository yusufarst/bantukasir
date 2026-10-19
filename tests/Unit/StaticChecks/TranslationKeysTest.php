<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Negative and compliant samples of the translation-key check (TEST_STRATEGY
 * section 6, AC-001-04) and of its literal check of PHP and Blade (TASK-003,
 * L10N01). The real tree passes in composer lint.
 */
final class TranslationKeysTest extends TestCase
{
    use RunsChecks;

    private const SAMPLES = 'tests/Fixtures/StaticChecks/translation';

    public function test_keys_missing_from_the_dictionary_fail(): void
    {
        $process = $this->check('failing');

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('MISSING sample.greeting.missing', $process->getOutput());
        $this->assertStringContainsString('MISSING unknown.key', $process->getOutput());
        $this->assertStringContainsString('MISSING sample.greeting.absent', $process->getOutput());
        $this->assertStringNotContainsString('sample.greeting.title', $process->getOutput());
    }

    public function test_literal_messages_and_blade_text_fail(): void
    {
        $output = $this->check('failing')->getOutput();
        $php = self::SAMPLES.'/failing/LiteralMessages.php';
        $blade = self::SAMPLES.'/failing/literals.blade.php';

        foreach ([
            "LITERAL Tidak boleh ({$php}:12)",
            "LITERAL Tidak ditemukan ({$php}:13)",
            "LITERAL Wajib diisi. ({$php}:14)",
            "LITERAL Harga belum benar ({$php}:16)",
            "LITERAL Halo ({$blade}:3)",
            "LITERAL Cari produk ({$blade}:4)",
            "LITERAL Pencarian ({$blade}:4)",
            "LITERAL Logo toko ({$blade}:5)",
        ] as $expected) {
            $this->assertStringContainsString($expected, $output);
        }

        $this->assertSame(8, substr_count($output, 'LITERAL '), $output);
    }

    public function test_keys_present_in_the_dictionary_pass(): void
    {
        $process = $this->check('passing');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('7 used, all present', $process->getOutput());
        $this->assertStringContainsString('no literal user-facing text', $process->getOutput());
    }

    private function check(string $sources): Process
    {
        return self::runCheck([
            PHP_BINARY, 'scripts/checks/translation-keys.php',
            '--lang='.self::SAMPLES.'/lang/id',
            '--source='.self::SAMPLES.'/'.$sources,
        ]);
    }
}
