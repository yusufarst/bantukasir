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

        // Eight: the @lang lookup of keys.blade.php is read too since the
        // check finds every entry point of the translator (TASK-003 finding A-06).
        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('8 used, all present', $process->getOutput());
        $this->assertStringContainsString('no literal user-facing text', $process->getOutput());
    }

    /**
     * TASK-003 adversarial findings A-06 and A-07: every lookup of a missing
     * or non-literal key, through any entry point of the translator, and every
     * literal in a server sink or a Blade view is reported on its line (the
     * lines tagged K); the controls are not (the lines tagged C).
     */
    public function test_every_bypass_of_the_review_is_reported_and_no_control_is(): void
    {
        $process = $this->check('bypass');
        $output = $process->getOutput();
        $missed = [];
        $reportedControls = [];

        $this->assertSame(1, $process->getExitCode(), $output);

        foreach (['KeyBypass.php', 'LiteralSinks.php', 'sinks.blade.php', 'keys.ts'] as $file) {
            foreach (file(self::projectRoot().'/'.self::SAMPLES.'/bypass/'.$file) ?: [] as $index => $line) {
                if (preg_match('/\b([KC])(\d\d)\b/', $line, $tag) !== 1) {
                    continue;
                }

                $reported = str_contains($output, sprintf('(%s/bypass/%s:%d)', self::SAMPLES, $file, $index + 1));

                if ($tag[1] === 'K' && ! $reported) {
                    $missed[] = "{$file} {$tag[0]}";
                } elseif ($tag[1] === 'C' && $reported) {
                    $reportedControls[] = "{$file} {$tag[0]}";
                }
            }
        }

        $this->assertSame([], $missed, "bypasses that composer lint lets through:\n{$output}");
        $this->assertSame([], $reportedControls, "controls that composer lint refuses:\n{$output}");
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
