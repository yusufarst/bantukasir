<?php

namespace Tests\Feature\Operations\Backup;

use App\Domain\Operations\Backup\BackupFailure;
use App\Domain\Operations\Backup\BackupOutcome;
use App\Domain\Operations\Backup\DiskSpace;
use App\Domain\Operations\Backup\ObjectStoreFactory;
use App\Domain\Operations\Heartbeat;
use App\Domain\Operations\OutboundHttp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Harness\Backup\BackupTestCase;
use Tests\Harness\Backup\RecordingStoreFactory;
use Tests\Harness\Backup\TestStore;

/**
 * Each failure code of BACKUP_RECOVERY section 4, induced (AC-013-01, AC-013-03,
 * AC-013-06, AC-013-08): one row with its code and nothing else that could hold a
 * path, a key or a message of the store; the temporary files removed; no ping; no
 * manifest stored, so that no backup is claimed. Then the refusals before a run.
 */
final class BackupFailuresTest extends BackupTestCase
{
    public function test_no_space_fails_before_the_dump(): void
    {
        $first = $this->runBackup();
        $this->assertNull($first->failure, $first->diagnostic);
        $this->pings = [];

        $last = $this->rowOf($first->backupId)['dump_bytes'];
        $this->assertIsInt($last);

        // One byte less than twice the last dump is too little.
        $this->freeSpace(2 * $last - 1);
        $outcome = $this->runBackup();
        $this->assertFailedWith(BackupFailure::NO_SPACE, $outcome);
        $this->assertNull($this->rowOf($outcome->backupId)['snapshot_at'], 'No dump was made.');

        // Exactly twice is enough.
        $this->freeSpace(2 * $last);
        $enough = $this->runBackup();
        $this->assertNull($enough->failure, $enough->diagnostic);
    }

    public function test_a_stored_size_that_differs_is_store_refused(): void
    {
        foreach (['.dump.age', '.manifest.age', 'files/'] as $shrink) {
            $this->pings = [];
            $this->storeLogo();
            $this->app->instance(ObjectStoreFactory::class, new RecordingStoreFactory(shrink: $shrink));

            $this->assertFailedWith(BackupFailure::STORE_REFUSED, $this->runBackup());
        }
    }

    public function test_a_dump_that_fails_or_cannot_be_read_is_dump_invalid(): void
    {
        config(['backup.tools.pg_dump' => $this->toolStub('pg-dump-fails', 'exit 1')]);
        $this->assertFailedWith(BackupFailure::DUMP_INVALID, $this->runBackup());

        $this->pings = [];
        config(['backup.tools.pg_dump' => $this->toolStub('pg-dump-garbage', <<<'BASH'
            for argument in "$@"; do
                case "$argument" in
                    --file=*) printf 'not an archive' > "${argument#--file=}" ;;
                esac
            done
            BASH)]);
        $this->assertFailedWith(BackupFailure::DUMP_INVALID, $this->runBackup());
    }

    public function test_a_stored_file_that_is_missing_or_differs_fails_the_run(): void
    {
        $key = $this->storeLogo();
        $bytes = (string) Storage::disk('media')->get($key);

        Storage::disk('media')->delete($key);
        $this->assertFailedWith(BackupFailure::FILE_MISSING, $this->runBackup());

        Storage::disk('media')->put($key, $bytes.'x');
        $this->assertFailedWith(BackupFailure::FILE_CORRUPT, $this->runBackup());

        // Put back, the next run succeeds.
        Storage::disk('media')->put($key, $bytes);
        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);
    }

    public function test_a_store_that_refuses_a_retention_is_store_refused(): void
    {
        config(['backup.store.bucket' => TestStore::newBucket(false)]);

        $this->assertFailedWith(BackupFailure::STORE_REFUSED, $this->runBackup());
    }

    public function test_a_store_out_of_reach_is_store_refused(): void
    {
        config(['backup.store.endpoint' => 'http://backup-store:1']);

        $this->assertFailedWith(BackupFailure::STORE_REFUSED, $this->runBackup());
    }

    public function test_a_key_that_the_store_does_not_accept_is_store_auth(): void
    {
        config(['backup.store.key' => 'not-the-key-of-the-store']);
        $this->assertFailedWith(BackupFailure::STORE_AUTH, $this->runBackup());

        $this->pings = [];
        config(['backup.store.key' => TestStore::KEY, 'backup.store.key_id' => 'not-a-key-of-the-store']);
        $this->assertFailedWith(BackupFailure::STORE_AUTH, $this->runBackup());
    }

    public function test_a_failure_at_the_manifest_leaves_no_backup(): void
    {
        $this->app->instance(ObjectStoreFactory::class, new RecordingStoreFactory('.manifest.age'));

        $outcome = $this->runBackup();

        $this->assertFailedWith(BackupFailure::STORE_REFUSED, $outcome);

        // The dump is there and no manifest: not a backup (BKP-02).
        $this->assertNotNull(TestStore::open()->head('db/6h/'.$outcome->backupId.'.dump.age'));
        $this->assertNull(TestStore::open()->head('db/6h/'.$outcome->backupId.'.manifest.age'));
    }

    public function test_a_weekly_copy_that_differs_is_remote_mismatch_and_the_next_sunday_recovers(): void
    {
        $this->atJakarta('2030-01-06 00:00:10');
        $first = $this->runBackup();
        $this->assertNull($first->failure, $first->diagnostic);
        $this->pings = [];

        // A later version of the object, as a store that returns other bytes.
        TestStore::open()->putText('db/weekly/'.$first->backupId.'.dump.age', 'other bytes');

        $this->atJakarta('2030-01-13 00:00:10');
        $mismatch = $this->runBackup();
        $this->assertFailedWith(BackupFailure::REMOTE_MISMATCH, $mismatch, backupStored: true);
        $this->assertSame('6h,weekly', $this->rowOf($mismatch->backupId)['tiers']);
        $this->assertNotNull(TestStore::open()->head('db/weekly/'.$mismatch->backupId.'.manifest.age'), 'Its own copy was stored.');

        // The next Sunday judges the failed Sunday's own copy: the failure does not last.
        $this->atJakarta('2030-01-20 00:00:10');
        $recovered = $this->runBackup();
        $this->assertNull($recovered->failure, $recovered->diagnostic);
        $this->assertSame($mismatch->backupId, $this->rowOf($recovered->backupId)['reverified_backup_id']);
    }

    public function test_a_weekly_copy_that_is_gone_is_remote_mismatch_and_the_next_sunday_recovers(): void
    {
        $this->atJakarta('2030-01-06 00:00:10');
        $first = $this->runBackup();
        $this->assertNull($first->failure, $first->diagnostic);
        $this->pings = [];

        // A new bucket, the exit path of ENVIRONMENTS section 15: the copy is not in it.
        config(['backup.store.bucket' => TestStore::newBucket()]);

        $this->atJakarta('2030-01-13 00:00:10');
        $gone = $this->runBackup();
        $this->assertFailedWith(BackupFailure::REMOTE_MISMATCH, $gone, backupStored: true);

        $this->atJakarta('2030-01-20 00:00:10');
        $recovered = $this->runBackup();
        $this->assertNull($recovered->failure, $recovered->diagnostic);
        $this->assertSame($gone->backupId, $this->rowOf($recovered->backupId)['reverified_backup_id']);
    }

    public function test_a_weekly_copy_past_its_retention_is_not_judged(): void
    {
        $this->atJakarta('2030-01-06 00:00:10');
        $first = $this->runBackup();
        $this->assertNull($first->failure, $first->diagnostic);

        // Eight weeks without a run: the store has rightly removed the copy of 35 days.
        $this->atJakarta('2030-03-03 00:00:10');
        $late = $this->runBackup();
        $this->assertNull($late->failure, $late->diagnostic);
        $this->assertNull($this->rowOf($late->backupId)['reverified_backup_id']);

        $this->atJakarta('2030-03-10 00:00:10');
        $next = $this->runBackup();
        $this->assertNull($next->failure, $next->diagnostic);
        $this->assertSame($late->backupId, $this->rowOf($next->backupId)['reverified_backup_id']);
    }

    public function test_the_time_limit_is_timeout_before_a_step_and_during_a_program(): void
    {
        config(['backup.time_limit_seconds' => 0]);
        $this->assertFailedWith(BackupFailure::TIMEOUT, $this->runBackup());

        $this->pings = [];
        config(['backup.time_limit_seconds' => 1, 'backup.tools.pg_dump' => $this->toolStub('pg-dump-sleeps', 'exec sleep 20')]);
        $this->assertFailedWith(BackupFailure::TIMEOUT, $this->runBackup());
    }

    public function test_a_ping_that_fails_prints_no_address_and_does_not_fail_the_backup(): void
    {
        $address = 'http://backup-store:1/the-secret-ping-address';
        config(['heartbeat.checks.backup' => $address]);
        $this->app->instance(Heartbeat::class, new Heartbeat(OutboundHttp::client()));

        $status = Artisan::call('backup:run');
        $output = Artisan::output();

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('BACKUP_PING_FAILED', $output);
        $this->assertStringNotContainsString('the-secret-ping-address', $output);
        $this->assertSame('SUCCEEDED', $this->observer()->table('backup_runs')->value('outcome'));
    }

    public function test_the_command_says_only_the_code_of_a_failure_and_exits_with_an_error(): void
    {
        config(['backup.store.key' => 'not-the-key-of-the-store']);

        $status = Artisan::call('backup:run');
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertMatchesRegularExpression('/\ABACKUP_FAILED \d{8}T\d{6}Z-[A-Za-z0-9]{8} STORE_AUTH\s*\z/', $output);
        $this->assertSame([], $this->pings);
    }

    public function test_a_run_that_overlaps_another_writes_nothing(): void
    {
        $lock = Cache::lock('backup:run', 60);
        $this->assertTrue((bool) $lock->get());

        $status = Artisan::call('backup:run');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('BACKUP_ALREADY_RUNNING', Artisan::output());
        $this->assertNothingIn(['backup_runs']);

        $lock->release();
    }

    public function test_a_database_of_another_environment_writes_nothing(): void
    {
        config(['app.environment_name' => 'production']);

        $status = Artisan::call('backup:run');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('BACKUP_REFUSED environment', Artisan::output());
        $this->assertSame([], $this->pings);

        config(['app.environment_name' => 'test']);
        $this->assertNothingIn(['backup_runs']);
    }

    public function test_a_setting_or_a_reason_of_another_form_refuses_the_run(): void
    {
        foreach (['backup.recipient' => '$(touch /tmp/x)', 'backup.store.endpoint' => 'ftp://store.test', 'backup.store.bucket' => '../x', 'backup.retention_profile' => 'none'] as $name => $value) {
            $kept = config($name);
            config([$name => $value]);

            $this->assertSame(1, Artisan::call('backup:run'), $name);
            $this->assertStringContainsString('BACKUP_REFUSED settings', Artisan::output());
            config([$name => $kept]);
        }

        $this->assertSame(1, Artisan::call('backup:run', ['--reason' => 'rm -rf']));
        $this->assertStringContainsString('BACKUP_REFUSED reason', Artisan::output());
        $this->assertNothingIn(['backup_runs']);
    }

    private function freeSpace(int $bytes): void
    {
        $this->app->instance(DiskSpace::class, new class($bytes) implements DiskSpace
        {
            public function __construct(private readonly int $bytes) {}

            public function freeBytes(string $path): int
            {
                return $this->bytes;
            }
        });
    }

    private function assertFailedWith(BackupFailure $failure, BackupOutcome $outcome, bool $backupStored = false): void
    {
        $this->assertSame($failure, $outcome->failure, $outcome->diagnostic);
        $this->assertSame([], $this->pings, 'A failed run pings nothing.');
        $this->assertNothingLeftBehind();

        $row = $this->rowOf($outcome->backupId);

        $this->assertSame(['FAILED', $failure->value, null, null, null, null], [
            $row['outcome'], $row['failure_code'], $row['dump_bytes'], $row['files_uploaded'], $row['uploaded_bytes'], $row['store_bytes'],
        ]);
        $this->assertNull($row['reverified_backup_id']);
        $this->assertSame($backupStored, $row['dump'] !== null, 'The hashes are those of a stored copy only.');
        $this->assertSame($backupStored, $row['manifest'] !== null);

        // Every text of the row is one of its allowed words: no path, key or message.
        foreach (['reason', 'outcome', 'failure_code', 'tiers'] as $column) {
            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_,]+\z/', $this->readText($row[$column]), $column);
        }
    }
}
