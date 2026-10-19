<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Negative and compliant samples of the migration review (TEST_STRATEGY
 * section 6, REL-06, AC-001-04). The real tree passes in composer lint.
 */
final class MigrationReviewTest extends TestCase
{
    use RunsChecks;

    private const SAMPLES = 'tests/Fixtures/StaticChecks/migrations';

    public function test_destructive_migrations_without_a_recorded_approval_fail(): void
    {
        $process = $this->review('failing');
        $output = $process->getOutput();

        $this->assertSame(1, $process->getExitCode(), $output);
        $this->assertStringContainsString('2026_01_01_000001_drop_sample_note.php: a schema-builder drop, with no REL-06 approval reference', $output);
        $this->assertStringContainsString('2026_01_01_000002_truncate_sample_items.php: TRUNCATE, with no REL-06 approval reference', $output);
        $this->assertStringContainsString('2026_01_01_000003_delete_sample_rows.php: a query-builder delete or update, with no REL-06 approval reference', $output);
        $this->assertStringContainsString('2026_01_01_000004_unrecorded_approval.php: UPDATE … SET, with a REL-06 approval reference to a decision that', $output);
    }

    public function test_additive_and_approved_migrations_pass(): void
    {
        $process = $this->review('passing');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('2 migration(s), none destructive without its approval', $process->getOutput());
    }

    private function review(string $migrations): Process
    {
        return self::runCheck([
            PHP_BINARY, 'scripts/checks/migration-review.php',
            '--migrations='.self::SAMPLES.'/'.$migrations,
            '--decisions='.self::SAMPLES.'/DECISIONS.md',
        ]);
    }
}
