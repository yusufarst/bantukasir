<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Negative and compliant samples of the translation-key check (TEST_STRATEGY
 * section 6, AC-001-04). The real tree passes in composer lint.
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

    public function test_keys_present_in_the_dictionary_pass(): void
    {
        $process = $this->check('passing');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('2 used, all present', $process->getOutput());
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
