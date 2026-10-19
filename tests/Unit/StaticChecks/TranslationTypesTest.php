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

    /**
     * TASK-003 adversarial finding A-15: a capitalized placeholder (`:Name`,
     * `:NAME`) in lang/id fails the check and the generation alike, so that
     * the server and the client stay equal by construction; a colon followed
     * by a space or a lower-case placeholder is not one.
     */
    public function test_a_capitalized_placeholder_fails_the_check_and_the_generation(): void
    {
        $directory = self::makeTemporaryDirectory('translation-types');

        try {
            foreach ([['--check'], []] as $mode) {
                $process = self::runCheck([
                    PHP_BINARY, 'scripts/checks/translation-types.php', ...$mode,
                    '--lang='.self::SAMPLES.'/capitalized/lang/id',
                    '--output='.$directory.'/translation-keys.ts',
                ]);

                $this->assertSame(1, $process->getExitCode(), $process->getOutput());
                $this->assertStringContainsString('CAPITALIZED PLACEHOLDER :Nama in ux.format.greeting', $process->getOutput());
                $this->assertStringContainsString('CAPITALIZED PLACEHOLDER :NAMA in ux.format.shout', $process->getOutput());
                $this->assertStringNotContainsString('ux.format.time', $process->getOutput());
                $this->assertStringNotContainsString('ux.format.variance', $process->getOutput());
                $this->assertFileDoesNotExist($directory.'/translation-keys.ts');
            }
        } finally {
            self::removeTemporaryDirectory($directory);
        }
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
