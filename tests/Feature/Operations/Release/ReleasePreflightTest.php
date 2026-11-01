<?php

namespace Tests\Feature\Operations\Release;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\TestDatabase;

/**
 * `release:preflight` (AC-014-02; RELEASE_PROCESS REL-04, REL-06, REL-23; ADR-011
 * item 7; ENVIRONMENTS ENV-04, ENV-32; suite DB01; TASK-014 scope item 3), as L2
 * refusals on the real test database: each condition is induced, is refused by its
 * code, and leaves every statement a read, so that the job stops before its first.
 */
final class ReleasePreflightTest extends DatabaseTestCase
{
    /**
     * @param  array<string, mixed>  $changes
     * @return array{int, string}
     */
    private function preflight(array $changes = []): array
    {
        $options = array_filter([
            '--environment' => 'test',
            '--migrations' => 'none',
            '--backup-schedule-paused' => true,
            ...$changes,
        ], static fn (mixed $value): bool => $value !== null);

        $exit = Artisan::call('release:preflight', $options);

        return [$exit, Artisan::output()];
    }

    private function pendingFrom(string $folder): void
    {
        $this->app->make(Migrator::class)->path(base_path('tests/Fixtures/Release/'.$folder));
    }

    /**
     * @param  array{int, string}  $outcome
     */
    private function assertRefused(string $code, array $outcome): void
    {
        $this->assertSame(1, $outcome[0], $outcome[1]);
        $this->assertStringContainsString('PREFLIGHT_REFUSED '.$code.' ', $outcome[1]);
        $this->assertStringNotContainsString('PREFLIGHT_PASSED', $outcome[1]);
    }

    /**
     * A row of backup_runs as the backup role writes it, finished this long ago.
     */
    private function backupRun(string $outcome, string $finishedAgo): void
    {
        $this->backup()->statement(<<<'SQL'
            insert into backup_runs (backup_id, reason, started_at, finished_at, outcome, failure_code, tiers, snapshot_at, dump_bytes, files_uploaded, uploaded_bytes, store_bytes, dump_object_sha256, manifest_object_sha256)
            select '20261031T000000Z-' || ?::text, 'PRE_RELEASE', now() - ?::interval, now() - ?::interval, ?::text, case when ?::text = 'FAILED' then 'TIMEOUT' end, '6h', now(), 1, 0, 1, 1, decode(repeat('00', 32), 'hex'), decode(repeat('00', 32), 'hex')
            SQL, [strtoupper(Str::random(8)), $finishedAgo, $finishedAgo, $outcome, $outcome]);
    }

    public function test_it_passes_when_every_condition_holds_and_every_statement_is_a_read(): void
    {
        $statements = [];
        $this->migration()->listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });
        $before = $this->migration()->table('migrations')->pluck('migration')->all();

        [$exit, $output] = $this->preflight();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('PREFLIGHT_PASSED test migrations=0', $output);
        $this->assertNotSame([], $statements);

        foreach ($statements as $statement) {
            $this->assertMatchesRegularExpression('/\A\s*select\b/i', $statement);
        }

        $this->assertSame($before, $this->migration()->table('migrations')->pluck('migration')->all());
    }

    public function test_it_refuses_a_typed_environment_that_is_not_the_configuration_and_the_marker(): void
    {
        $this->assertRefused('ENVIRONMENT', $this->preflight(['--environment' => 'production']));

        // The configuration differs from the marker, whatever is typed.
        config(['app.environment_name' => 'staging']);
        $this->assertRefused('ENVIRONMENT', $this->preflight(['--environment' => 'staging']));
        $this->assertRefused('ENVIRONMENT', $this->preflight());
    }

    public function test_it_stops_at_the_identification_without_reading_anything_else(): void
    {
        $this->pendingFrom('pending');

        [$exit, $output] = $this->preflight(['--environment' => 'production', '--backup-schedule-paused' => null]);

        $this->assertSame(1, $exit);
        $this->assertStringNotContainsString('MIGRATIONS', $output);
        $this->assertStringNotContainsString('BACKUP_SCHEDULE', $output);
    }

    public function test_a_connection_that_is_not_the_migration_role_is_refused(): void
    {
        config(['database.connections.'.TestDatabase::MIGRATION.'.username' => config('database.connections.'.TestDatabase::RUNTIME.'.username')]);
        DB::purge(TestDatabase::MIGRATION);

        $this->assertRefused('ENVIRONMENT', $this->preflight());
    }

    public function test_only_production_needs_the_backup_by_its_configuration(): void
    {
        $this->assertSame(['production'], config('release.backup_required_in'));
        $this->assertSame(0, $this->preflight()[0], 'The environment test needs no backup.');
    }

    public function test_where_a_backup_is_needed_it_refuses_none_a_failed_one_and_one_an_hour_old(): void
    {
        config(['release.backup_required_in' => ['test']]);

        $this->assertRefused('BACKUP', $this->preflight());

        $this->backupRun('FAILED', '5 minutes');
        $this->assertRefused('BACKUP', $this->preflight());

        $this->backupRun('SUCCEEDED', '61 minutes');
        $this->assertRefused('BACKUP', $this->preflight());

        $this->backupRun('SUCCEEDED', '59 minutes');
        $this->assertSame(0, $this->preflight()[0]);
    }

    public function test_it_refuses_pending_migrations_that_are_not_the_release_records_list(): void
    {
        $this->pendingFrom('pending');
        $name = '2026_12_31_000001_harness_release_note';

        $missing = $this->preflight();
        $this->assertRefused('MIGRATIONS', $missing);
        $this->assertStringContainsString('Pending, not listed: '.$name.'. Listed, not pending: none.', $missing[1]);

        $other = $this->preflight(['--migrations' => $name.',2026_12_31_000099_other']);
        $this->assertRefused('MIGRATIONS', $other);
        $this->assertStringContainsString('Pending, not listed: none. Listed, not pending: 2026_12_31_000099_other.', $other[1]);

        // One that has already run is not pending.
        $ran = $this->preflight(['--migrations' => $name.',2026_10_24_000001_create_cache_tables']);
        $this->assertRefused('MIGRATIONS', $ran);

        $this->assertSame(0, $this->preflight(['--migrations' => $name])[0]);
    }

    public function test_it_refuses_a_destructive_migration_without_its_approval_reference(): void
    {
        $this->pendingFrom('destructive');
        $list = ['--migrations' => '2026_12_31_000002_harness_marked_destructive'];

        $this->assertRefused('DESTRUCTIVE', $this->preflight($list));
        $this->assertRefused('DESTRUCTIVE', $this->preflight([...$list, '--destructive-approvals' => 'D-900']));
    }

    public function test_an_approved_destructive_migration_needs_the_release_record_to_name_its_approval(): void
    {
        $this->pendingFrom('approved');
        $list = ['--migrations' => '2026_12_31_000003_harness_approved_destructive'];

        $this->assertRefused('DESTRUCTIVE', $this->preflight($list));
        $this->assertRefused('DESTRUCTIVE', $this->preflight([...$list, '--destructive-approvals' => 'D-901']));
        $this->assertSame(0, $this->preflight([...$list, '--destructive-approvals' => 'D-900'])[0]);
    }

    public function test_it_refuses_synchronous_commit_off_in_the_session(): void
    {
        $this->migration()->statement('set synchronous_commit = off');

        $this->assertRefused('SYNCHRONOUS_COMMIT', $this->preflight());
    }

    public function test_it_refuses_a_role_setting_that_turns_synchronous_commit_off(): void
    {
        $this->migration()->statement('alter role current_user set synchronous_commit = off');

        try {
            $this->assertRefused('SYNCHRONOUS_COMMIT', $this->preflight());
        } finally {
            $this->migration()->statement('alter role current_user reset synchronous_commit');
        }

        $this->assertSame(0, $this->preflight()[0]);
    }

    public function test_it_refuses_a_backup_schedule_that_the_operator_has_not_confirmed_paused(): void
    {
        $this->assertRefused('BACKUP_SCHEDULE', $this->preflight(['--backup-schedule-paused' => null]));
    }

    public function test_the_first_installation_is_refused_on_a_database_that_holds_tables(): void
    {
        config(['release.backup_required_in' => ['test']]);

        $outcome = $this->preflight(['--first-installation' => true]);

        $this->assertRefused('BACKUP', $outcome);
        $this->assertStringContainsString('already holds tables', $outcome[1]);
    }

    public function test_it_reports_every_refusal_of_one_run(): void
    {
        config(['release.backup_required_in' => ['test']]);
        $this->pendingFrom('pending');

        $outcome = $this->preflight(['--backup-schedule-paused' => null]);

        foreach (['BACKUP', 'MIGRATIONS', 'BACKUP_SCHEDULE'] as $code) {
            $this->assertRefused($code, $outcome);
        }
    }

    public function test_it_refuses_missing_options_and_options_out_of_their_form(): void
    {
        foreach ([['--environment' => null], ['--migrations' => null], ['--migrations' => ''], ['--migrations' => 'a b'], ['--destructive-approvals' => 'approved']] as $changes) {
            $this->assertRefused('OPTIONS', $this->preflight($changes));
        }
    }

    public function test_a_database_it_cannot_read_is_refused_without_naming_the_server(): void
    {
        config(['database.connections.'.TestDatabase::MIGRATION.'.port' => 1]);
        DB::purge(TestDatabase::MIGRATION);

        $outcome = $this->preflight();

        $this->assertRefused('DATABASE', $outcome);
        $this->assertStringNotContainsString('Host', $outcome[1]);
    }
}
