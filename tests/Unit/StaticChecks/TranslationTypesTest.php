<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Negative and compliant samples of the check that the TypeScript key type
 * equals lang/id (ADR-005 item 5, TASK-003): a key type that lacks a key of
 * the dictionary fails composer lint; the one generated from it passes. The
 * real tree passes in composer lint.
 */
final class TranslationTypesTest extends TestCase
{
    use RunsChecks;

    private const SAMPLES = 'tests/Fixtures/StaticChecks/translation-types';

    public function test_a_stale_key_type_fails(): void
    {
        $process = $this->check('stale.ts');

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('stale.ts differs from what', $process->getOutput());
    }

    public function test_the_generated_key_type_passes_with_its_placeholders(): void
    {
        $process = $this->check('current.ts');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('is current, 2 keys', $process->getOutput());
        $this->assertStringContainsString(
            "    'ux.format.month.january': never;\n    'ux.format.variance': 'keterangan' | 'uang';\n",
            (string) file_get_contents(self::projectRoot().'/'.self::SAMPLES.'/current.ts'),
        );
    }

    private function check(string $output): Process
    {
        return self::runCheck([
            PHP_BINARY, 'scripts/checks/translation-types.php', '--check',
            '--lang='.self::SAMPLES.'/lang/id',
            '--output='.self::SAMPLES.'/'.$output,
        ]);
    }
}
