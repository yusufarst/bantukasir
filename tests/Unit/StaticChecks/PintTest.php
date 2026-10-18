<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;

/**
 * Negative sample of Pint (TEST_STRATEGY section 6, AC-001-04). The real tree
 * passes in composer lint.
 */
final class PintTest extends TestCase
{
    use RunsChecks;

    public function test_pint_fails_on_a_badly_formatted_sample(): void
    {
        $process = self::runCheck([
            PHP_BINARY, 'vendor/bin/pint', '--test', '--no-ansi',
            '--config=tests/Fixtures/StaticChecks/pint.json',
            'tests/Fixtures/StaticChecks/pint/BadlyFormatted.php',
        ]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('BadlyFormatted.php', $process->getOutput());
    }
}
