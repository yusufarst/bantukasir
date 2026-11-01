<?php

namespace Tests\Unit\StaticChecks;

use App\Domain\Operations\Release\MigrationReview;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Negative and compliant samples of the migration review (TEST_STRATEGY
 * section 6, REL-06, REL-20, AC-001-04, AC-014-05). The real tree passes in
 * composer lint.
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
        $this->assertStringContainsString('2026_01_01_000007_marked_destructive.php: marked destructive, with no REL-06 approval reference', $output);
    }

    public function test_a_migration_without_its_rel_20_metadata_fails(): void
    {
        $output = $this->review('failing')->getOutput();

        $this->assertStringContainsString('2026_01_01_000005_no_metadata.php: no REL-20 metadata: class, expected duration, lock impact, verification queries', $output);
        $this->assertStringContainsString('2026_01_01_000006_partial_metadata.php: no REL-20 metadata: class, lock impact, verification queries', $output);
        $this->assertStringNotContainsString('2026_01_01_000007_marked_destructive.php: no REL-20 metadata', $output);
    }

    public function test_additive_and_approved_migrations_pass(): void
    {
        $process = $this->review('passing');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('2 migration(s), none destructive without its approval, each with its REL-20 metadata', $process->getOutput());
    }

    public function test_the_reader_finds_the_class_the_approvals_and_a_label_running_over_a_line(): void
    {
        $code = "/*\n * Class: expand (section 5). Expected duration: a\n * minute. Lock impact: none. Verification\n * (REL-20): counts.\n */\n// REL-06 approval: D-900\n// REL-06 approval: D-901\n";

        $this->assertSame('expand', MigrationReview::classOf($code));
        $this->assertSame([], MigrationReview::missingMetadata($code));
        $this->assertSame(['D-900', 'D-901'], MigrationReview::approvals($code));
        $this->assertFalse(MigrationReview::isDestructive($code));
        $this->assertTrue(MigrationReview::isDestructive("Class: destructive\n"));
        $this->assertTrue(MigrationReview::isDestructive("\$table->dropColumn('a');"));
        $this->assertContains('lock impact', MigrationReview::missingMetadata("/* Class: additive. Expected duration: a second. Lock impact: */\n"));
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
