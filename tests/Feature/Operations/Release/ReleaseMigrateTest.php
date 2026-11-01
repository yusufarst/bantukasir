<?php

namespace Tests\Feature\Operations\Release;

use App\Domain\Operations\Release\Verification;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\TestDatabase;

/**
 * The migration job's body (AC-014-03; ADR-011 item 7; RELEASE_PROCESS RB-20 step
 * 8, REL-20; suite DB01; TASK-014 scope item 4), as L2 tests on the real test
 * database: the preflight, the migrations and the verification queries in that
 * order, each stopping the job; one job at a time. The image checks run the script
 * around it in the release image (scripts/checks/image-checks.sh).
 */
final class ReleaseMigrateTest extends DatabaseTestCase
{
    private const string NOTE = '2026_12_31_000001_harness_release_note';

    private const string FAILING = '2026_12_31_000004_harness_failing';

    protected function tearDown(): void
    {
        // The migrations table survives the harness's reset.
        $this->migration()->table('migrations')->whereIn('migration', [self::NOTE, self::FAILING])->delete();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{int, string}
     */
    private function job(array $changes = []): array
    {
        $options = array_filter([
            '--environment' => 'test',
            '--migrations' => self::NOTE,
            '--backup-schedule-paused' => true,
            ...$changes,
        ], static fn (mixed $value): bool => $value !== null);

        $exit = Artisan::call('release:migrate', $options);

        return [$exit, Artisan::output()];
    }

    private function pendingFrom(string $folder): void
    {
        $this->app->make(Migrator::class)->path(base_path('tests/Fixtures/Release/'.$folder));
    }

    private function applied(string $migration): bool
    {
        return $this->migration()->table('migrations')->where('migration', $migration)->exists();
    }

    public function test_it_runs_the_preflight_the_migrations_and_the_verification_in_that_order(): void
    {
        $this->pendingFrom('pending');

        [$exit, $output] = $this->job();

        $this->assertSame(0, $exit, $output);
        $this->assertTrue($this->applied(self::NOTE));

        $positions = [];

        foreach (['JOB_STEP preflight', 'PREFLIGHT_PASSED test migrations=1', 'JOB_STEP migrations', self::NOTE, 'JOB_STEP verification', 'VERIFY_COUNT users before=0 after=0', 'Privilege check passed', 'VERIFY_NOT_AVAILABLE reconciliation:run', 'JOB_PASSED'] as $step) {
            $position = strpos($output, $step);
            $this->assertIsInt($position, $step.' is missing: '.$output);
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, $output);
    }

    public function test_a_refused_preflight_stops_the_job_before_its_first_statement(): void
    {
        $this->pendingFrom('pending');

        [$exit, $output] = $this->job(['--backup-schedule-paused' => null]);

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('PREFLIGHT_REFUSED BACKUP_SCHEDULE', $output);
        $this->assertStringContainsString('JOB_REFUSED PREFLIGHT no statement ran', $output);
        $this->assertStringNotContainsString('JOB_STEP migrations', $output);
        $this->assertFalse($this->applied(self::NOTE));
    }

    public function test_a_failing_migration_stops_the_job_before_the_verification_and_leaves_nothing(): void
    {
        $this->pendingFrom('failing');

        [$exit, $output] = $this->job(['--migrations' => self::FAILING]);

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('JOB_FAILED MIGRATIONS Illuminate\Database\QueryException 42883', $output);
        $this->assertStringNotContainsString('JOB_STEP verification', $output);
        $this->assertFalse($this->applied(self::FAILING));

        // Its lines leave the container: nothing of the server or of the SQL.
        $this->assertStringNotContainsString('Host', $output);
        $this->assertStringNotContainsString('harness_no_such_function', $output);
    }

    public function test_a_typed_approval_that_answers_to_no_migration_stops_the_job(): void
    {
        $this->pendingFrom('pending');

        [$exit, $output] = $this->job(['--destructive-approvals' => 'D-999']);

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('PREFLIGHT_REFUSED DESTRUCTIVE', $output);
        $this->assertFalse($this->applied(self::NOTE));
    }

    public function test_a_failed_privilege_check_fails_the_job_after_the_migrations(): void
    {
        $this->pendingFrom('pending');
        $table = $this->harnessTable('harness_release_extra');
        $this->migration()->statement('create table harness_release_extra (id bigint)');

        [$exit, $output] = $this->job();

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString($table, $output);
        $this->assertStringContainsString('JOB_FAILED VERIFICATION', $output);
        $this->assertStringNotContainsString('JOB_PASSED', $output);
        $this->assertTrue($this->applied(self::NOTE));
    }

    public function test_one_job_at_a_time_on_a_database(): void
    {
        $this->pendingFrom('pending');
        config(['database.connections.harness_other_job' => config('database.connections.'.TestDatabase::MIGRATION)]);
        $other = DB::connection('harness_other_job');
        $other->scalar('select pg_catalog.pg_advisory_lock(8014001::bigint)');

        try {
            [$exit, $output] = $this->job();
        } finally {
            $other->scalar('select pg_catalog.pg_advisory_unlock(8014001::bigint)');
            DB::purge('harness_other_job');
        }

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('JOB_REFUSED ALREADY_RUNNING', $output);
        $this->assertFalse($this->applied(self::NOTE));

        // The lock was given back: the next job runs.
        $this->assertSame(0, $this->job()[0]);
    }

    public function test_the_lock_is_given_back_after_a_refused_job(): void
    {
        $this->pendingFrom('pending');

        $this->assertSame(1, $this->job(['--backup-schedule-paused' => null])[0]);

        config(['database.connections.harness_other_job' => config('database.connections.'.TestDatabase::MIGRATION)]);
        $this->assertTrue(DB::connection('harness_other_job')->scalar('select pg_catalog.pg_try_advisory_lock(8014001::bigint)'));
        DB::purge('harness_other_job');
    }

    public function test_a_connection_that_is_not_the_migration_role_is_refused(): void
    {
        $username = config('database.connections.'.TestDatabase::MIGRATION.'.username');
        config(['database.connections.'.TestDatabase::MIGRATION.'.username' => config('database.connections.'.TestDatabase::RUNTIME.'.username')]);
        DB::purge(TestDatabase::MIGRATION);

        try {
            [$exit, $output] = $this->job();
        } finally {
            config(['database.connections.'.TestDatabase::MIGRATION.'.username' => $username]);
            DB::purge(TestDatabase::MIGRATION);
        }

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('JOB_REFUSED ROLE', $output);
    }

    public function test_the_verification_finds_rows_lost_and_constraints_left_invalid(): void
    {
        $verification = $this->app->make(Verification::class);
        $this->migration()->statement('create table harness_release_invalid (id bigint)');
        $this->harnessTable('harness_release_invalid');

        $this->assertSame([], $verification->findings($this->migration(), [], [], false));
        $this->assertSame(['ROWS_LOST users 2 1'], $verification->findings($this->migration(), ['users' => 2, 'sessions' => 0], ['users' => 1, 'sessions' => 0], false));
        $this->assertSame([], $verification->findings($this->migration(), ['users' => 2], ['users' => 1], true));

        $this->migration()->statement('alter table harness_release_invalid add constraint harness_release_invalid_check check (id > 0) not valid');
        $this->assertSame(['CONSTRAINT_NOT_VALID harness_release_invalid.harness_release_invalid_check'], $verification->findings($this->migration(), [], [], false));
    }

    public function test_the_counts_are_those_of_the_retained_tables_only(): void
    {
        $counts = $this->app->make(Verification::class)->counts($this->migration());

        $this->assertArrayHasKey('users', $counts);
        $this->assertArrayHasKey('backup_runs', $counts);
        $this->assertArrayNotHasKey('cache', $counts);
        $this->assertArrayNotHasKey('sessions', $counts);
    }
}
