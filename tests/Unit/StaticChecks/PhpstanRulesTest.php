<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;

/**
 * Negative samples of PHPStan with Larastan and of the project's rules
 * (TEST_STRATEGY section 6, AC-001-04): each sample in
 * tests/Fixtures/StaticChecks/Php is reported on its line, and the compliant
 * samples are not. The real tree passes the same rules in composer analyse.
 */
final class PhpstanRulesTest extends TestCase
{
    use RunsChecks;

    /**
     * The identifiers reported per sample file and line.
     *
     * @var array<string, array<int, list<string>>>
     */
    private static array $reported = [];

    private static ?int $exitCode = null;

    public static function setUpBeforeClass(): void
    {
        $process = self::runCheck([
            PHP_BINARY, 'vendor/bin/phpstan', 'analyse',
            '--configuration=tests/Fixtures/StaticChecks/phpstan.neon',
            '--error-format=json', '--no-progress', '--memory-limit=1G',
            'tests/Fixtures/StaticChecks/Php',
        ]);

        self::$exitCode = $process->getExitCode();
        $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $files = is_array($report) && is_array($report['files'] ?? null) ? $report['files'] : [];

        foreach ($files as $file => $result) {
            $messages = is_array($result) && is_array($result['messages'] ?? null) ? $result['messages'] : [];

            foreach ($messages as $message) {
                if (is_array($message) && is_int($message['line'] ?? null) && is_string($message['identifier'] ?? null)) {
                    self::$reported[basename((string) $file)][$message['line']][] = $message['identifier'];
                }
            }
        }
    }

    public function test_phpstan_fails_on_the_samples(): void
    {
        $this->assertSame(1, self::$exitCode);
    }

    public function test_level_nine_and_larastan_report_their_samples(): void
    {
        $this->assertContains('binaryOp.invalid', self::$reported['LevelSamples.php'][10] ?? []);
        $this->assertContains('larastan.noEnvCallsOutsideOfConfig', self::$reported['LevelSamples.php'][15] ?? []);
    }

    public function test_process_functions_and_backticks_are_refused(): void
    {
        $this->assertSame([9, 10, 11, 12, 13, 14, 15, 17, 18], $this->linesOf('ProcessFunctionSamples.php', 'bantukasir.processFunction'));
    }

    public function test_sql_from_input_and_unbound_placeholders_are_refused(): void
    {
        $this->assertSame([13, 14, 15, 16, 17, 18, 19], $this->linesOf('RawSqlSamples.php', 'bantukasir.rawSql'));
    }

    public function test_floats_are_refused_in_exact_value_code_only(): void
    {
        // Lines 31 and 32: Brick Math's toFloat() and Carbon's diffInDays(),
        // calls that return a float (TASK-003).
        $this->assertSame([10, 12, 14, 19, 26, 27, 28, 29, 30, 31, 32], $this->linesOf('FloatSamples.php', 'bantukasir.floatInExactValue'));
        $this->assertSame([], $this->linesOf('FloatsOutsideScope.php', 'bantukasir.floatInExactValue'));
    }

    /**
     * @return list<int>
     */
    private function linesOf(string $file, string $identifier): array
    {
        $lines = [];

        foreach (self::$reported[$file] ?? [] as $line => $identifiers) {
            if (in_array($identifier, $identifiers, true)) {
                $lines[] = $line;
            }
        }

        sort($lines);

        return $lines;
    }
}
