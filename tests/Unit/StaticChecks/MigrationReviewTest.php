<?php

namespace Tests\Unit\StaticChecks;

use App\Domain\Operations\Release\MigrationReview;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertStringContainsString('2026_01_01_000008_update_with_an_alias.php: UPDATE … SET, with no REL-06 approval reference', $output);
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

    /**
     * @return array<string, array{string}>
     */
    public static function destructiveForms(): array
    {
        return [
            'UPDATE with an alias' => ['UPDATE items AS i SET a = 1'],
            'UPDATE with a bare alias' => ['UPDATE items i SET a = 1'],
            'UPDATE ONLY' => ['UPDATE ONLY items SET a = 1'],
            'UPDATE over lines' => ["update items\n    set a = 1"],
            'an upsert in SQL' => ['INSERT INTO items VALUES (1) ON CONFLICT (id) DO UPDATE SET a = 1'],
            'MERGE' => ['MERGE INTO items USING other ON true WHEN MATCHED THEN DELETE'],
            'a column dropped by ONLY' => ['ALTER TABLE ONLY items DROP note'],
            'a column dropped with IF EXISTS' => ['ALTER TABLE IF EXISTS items DROP note'],
            'a column dropped in a list' => ['ALTER TABLE items ADD COLUMN b int, DROP note'],
            'a column dropped after a constraint' => ['ALTER TABLE items DROP CONSTRAINT c, DROP note'],
            'Schema::dropAllTypes' => ['Schema::dropAllTypes();'],
            'Schema::dropDatabaseIfExists' => ["Schema::dropDatabaseIfExists('x');"],
            'dropConstrainedForeignId' => ["\$table->dropConstrainedForeignId('x');"],
            'dropConstrainedForeignIdFor' => ['$table->dropConstrainedForeignIdFor(User::class);'],
            'updateFrom' => ["\$query->updateFrom(['a' => 1]);"],
            'updateOrCreate' => ["Item::query()->updateOrCreate(['id' => 1]);"],
            'updateQuietly' => ['$item->updateQuietly([]);'],
            'deleteQuietly' => ['$item->deleteQuietly();'],
            'deleteOrFail' => ['$item->deleteOrFail();'],
            'Model::destroy' => ['Item::destroy(1);'],
            'a type changed with USING' => ['ALTER TABLE items ALTER COLUMN a TYPE text USING a::text'],
            'a type set by SET DATA TYPE' => ['ALTER TABLE items ALTER a SET DATA TYPE text'],
            'a column changed by the builder' => ["\$table->string('a')->change();"],
            'DROP … CASCADE' => ['DROP TYPE kind CASCADE'],
            'DROP OWNED' => ['DROP OWNED BY someone'],
            'DROP SEQUENCE' => ['DROP SEQUENCE items_id_seq'],
            'DROP MATERIALIZED VIEW' => ['DROP MATERIALIZED VIEW totals'],
        ];
    }

    #[DataProvider('destructiveForms')]
    public function test_each_form_that_removes_or_rewrites_data_is_destructive(string $code): void
    {
        $this->assertTrue(MigrationReview::isDestructive($code), $code);
    }

    /**
     * What the merged migrations hold, and stays allowed.
     *
     * @return array<string, array{string}>
     */
    public static function harmlessForms(): array
    {
        return [
            'a grant' => ['GRANT SELECT, INSERT, UPDATE (state, set_at) ON TABLE items TO someone'],
            'a key action' => ['REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT, set_at timestamptz'],
            'a key that sets null' => ['REFERENCES users (id) ON DELETE SET NULL ON UPDATE SET NULL'],
            'a row lock' => ['SELECT id FROM items FOR UPDATE'],
            'a trigger' => ['CREATE TRIGGER t BEFORE UPDATE ON items FOR EACH ROW EXECUTE FUNCTION f()'],
            'a constraint dropped' => ['ALTER TABLE items DROP CONSTRAINT c'],
            'NOT NULL dropped' => ['ALTER TABLE items ALTER COLUMN a DROP NOT NULL'],
            'a default dropped' => ['ALTER TABLE items ALTER COLUMN a DROP DEFAULT'],
            'a value added to a type' => ["ALTER TYPE kind ADD VALUE 'b'"],
            'a key action in the builder' => ["\$table->foreignId('x')->constrained()->cascadeOnDelete();"],
        ];
    }

    #[DataProvider('harmlessForms')]
    public function test_each_form_that_keeps_the_data_is_not_destructive(string $code): void
    {
        $this->assertSame([], MigrationReview::destructiveStatements($code), $code);
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
