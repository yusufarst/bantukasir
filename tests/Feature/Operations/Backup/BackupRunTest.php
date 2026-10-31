<?php

namespace Tests\Feature\Operations\Backup;

use App\Domain\Operations\Backup\BackupReason;
use App\Domain\Operations\Backup\BackupRunner;
use App\Domain\Operations\Backup\ObjectStoreFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Harness\Backup\BackupTestCase;
use Tests\Harness\Backup\RecordingStoreFactory;
use Tests\Harness\Backup\TestStore;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * A successful backup:run against the test server, with the real pg_dump and age
 * (AC-013-02 to AC-013-05, AC-013-08; BACKUP_RECOVERY sections 2, 4 and 5; L6): what
 * reaches the store can be read only with the recovery key that the harness keeps,
 * holds what a restore needs and nothing of the four excluded tables, and is
 * described by a manifest written last.
 */
final class BackupRunTest extends BackupTestCase
{
    public function test_a_run_stores_the_dump_the_files_and_the_manifest_records_its_row_and_pings(): void
    {
        $storageKey = $this->storeLogo();

        $outcome = $this->runBackup();

        $this->assertNull($outcome->failure, $outcome->diagnostic);
        $this->assertSame(1, $outcome->filesUploaded);
        $this->assertSame([self::PING_ADDRESS], $this->pings);
        $this->assertNothingLeftBehind();

        $row = $this->rowOf($outcome->backupId);
        $this->assertSame(['SCHEDULED', 'SUCCEEDED', null, 1, null], [$row['reason'], $row['outcome'], $row['failure_code'], $row['files_uploaded'], $row['reverified_backup_id']]);
        $this->assertNotNull($row['snapshot_at']);
        $this->assertIsInt($row['store_bytes']);
        $this->assertGreaterThan(0, $row['store_bytes']);

        $store = TestStore::open();
        $dumpKey = 'db/6h/'.$outcome->backupId.'.dump.age';
        $manifestKey = 'db/6h/'.$outcome->backupId.'.manifest.age';

        // The manifest of section 5, encrypted like the dump.
        $manifest = json_decode($this->decrypted($manifestKey), true, 16, JSON_THROW_ON_ERROR);
        $this->assertIsArray($manifest);
        $this->assertSame(['format', 'backupId', 'environment', 'startedAt', 'snapshotAt', 'release', 'migration', 'postgres', 'dump', 'files', 'counts', 'recipient'], array_keys($manifest));
        $this->assertSame(1, $manifest['format']);
        $this->assertSame($outcome->backupId, $manifest['backupId']);
        $this->assertSame('test', $manifest['environment']);
        $this->assertSame($this->recipient, $manifest['recipient']);
        $this->assertSame($this->backup()->table('migrations')->orderByDesc('id')->value('migration'), $manifest['migration']);
        $this->assertSame(['version', 'commit', 'imageDigest'], array_keys((array) $manifest['release']));
        $this->assertStringStartsWith('18.', $this->readText($manifest['postgres']));
        $this->assertSame($dumpKey, data_get($manifest, 'dump.objectKey'));
        $this->assertSame(['sessions', 'password_reset_tokens', 'cache', 'cache_locks'], data_get($manifest, 'dump.excludedTables'));
        $this->assertSame($row['dump'], data_get($manifest, 'dump.sha256'));
        $this->assertSame($row['dump_bytes'], data_get($manifest, 'dump.size'));
        $this->assertSame(1, data_get($manifest, 'files.count'));
        $this->assertSame($storageKey, data_get($manifest, 'files.items.0.storageKey'));
        $this->assertSame('LOGO', data_get($manifest, 'files.items.0.purpose'));
        $this->assertSame('files/'.$storageKey.'.age', data_get($manifest, 'files.items.0.objectKey'));

        // Every table of classes F, FS, M and S is counted, and no other.
        $counts = (array) $manifest['counts'];
        $this->assertSame(1, $counts['stored_files']);
        $this->assertSame(0, $counts['backup_runs']);
        $this->assertSame($this->observer()->table('users')->count(), $counts['users']);
        $this->assertArrayNotHasKey('sessions', $counts);
        $this->assertArrayNotHasKey('security_log_entries', $counts);
        $this->assertArrayNotHasKey('command_request_receipts', $counts);

        // The hashes recorded are those of the encrypted objects.
        $this->assertTrue($store->download($dumpKey, $this->area.'/dump.age'));
        $this->assertSame($row['dump'], hash_file('sha256', $this->area.'/dump.age'));
        $this->assertSame(data_get($manifest, 'dump.plaintextSha256'), hash('sha256', TestStore::decrypt($this->identity, $this->area.'/dump.age', $this->area.'/dump.plain')));
        $this->assertTrue($store->download($manifestKey, $this->area.'/manifest.age'));
        $this->assertSame($row['manifest'], hash_file('sha256', $this->area.'/manifest.age'));

        // The file's object: its recipient, and the bytes of stored_files.
        $this->assertSame($this->recipient, $store->head('files/'.$storageKey.'.age')?->recipient);
        $bytes = $this->decrypted('files/'.$storageKey.'.age');
        $this->assertSame(data_get($manifest, 'files.items.0.sha256'), hash('sha256', $bytes));
        $this->assertSame(Storage::disk('media')->get($storageKey), $bytes);
    }

    public function test_the_dump_holds_every_security_log_row_and_none_of_the_excluded_data(): void
    {
        // A row of each excluded table, so that its absence says something.
        $this->storeLogo();
        TokoContoh::staff('Uji', ['cashier'], false);
        $this->runtime()->table('cache')->insert(['key' => 'sentinel-of-the-cache', 'value' => 'x', 'expiration' => 2000000000]);
        $this->runtime()->table('cache_locks')->insert(['key' => 'sentinel-of-the-locks', 'owner' => 'x', 'expiration' => 2000000000]);

        foreach (['sessions', 'password_reset_tokens', 'cache', 'cache_locks'] as $table) {
            $this->assertGreaterThan(0, $this->observer()->table($table)->count(), $table);
        }

        foreach (['2020-01-01 00:00:00+00', '2026-10-31 00:00:00+00'] as $when) {
            $this->runtime()->table('security_log_entries')->insert(['occurred_at' => $when, 'event_type' => 'SIGN_IN_FAILED', 'actor_kind' => 'ANONYMOUS', 'request_id' => 'request-of-the-test', 'outcome_code' => 'REFUSED']);
        }

        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);

        $encrypted = $this->area.'/dump.age';
        $this->assertTrue(TestStore::open()->download('db/6h/'.$outcome->backupId.'.dump.age', $encrypted));
        TestStore::decrypt($this->identity, $encrypted, $this->area.'/dump.plain');

        $list = Process::run([self::PG_RESTORE, '--list', $this->area.'/dump.plain'])->throw()->output();

        foreach (['sessions', 'password_reset_tokens', 'cache', 'cache_locks'] as $table) {
            $this->assertMatchesRegularExpression('/ TABLE public '.$table.' /', $list, 'The definition stays.');
            $this->assertDoesNotMatchRegularExpression('/ TABLE DATA public '.$table.' /', $list);
        }

        $this->assertMatchesRegularExpression('/ TABLE DATA public users /', $list);

        $sql = $this->area.'/log.sql';
        Process::run([self::PG_RESTORE, '--data-only', '--table=security_log_entries', '--file='.$sql, $this->area.'/dump.plain'])->throw();
        $data = (string) file_get_contents($sql);

        $this->assertSame(1, preg_match('/^COPY public\.security_log_entries .*? FROM stdin;\n(.*?)^\\\\\.$/ms', $data, $copy));
        $rows = $copy[1] ?? '';
        $this->assertSame($this->observer()->table('security_log_entries')->count(), substr_count($rows, "\n"));
        $this->assertStringContainsString('request-of-the-test', $rows);
    }

    public function test_the_manifest_is_written_last_in_every_tier(): void
    {
        $this->storeLogo();
        // Sunday the 1st of September 2030, at 00.00.
        $this->atJakarta('2030-09-01 00:00:10');
        $factory = new RecordingStoreFactory;
        $this->app->instance(ObjectStoreFactory::class, $factory);

        $outcome = $this->runBackup();

        $this->assertNull($outcome->failure, $outcome->diagnostic);
        $this->assertNotNull($factory->store);
        $id = $outcome->backupId;
        $this->assertSame([
            "db/6h/{$id}.dump.age", "db/weekly/{$id}.dump.age", "db/monthly/{$id}.dump.age",
            "db/6h/{$id}.manifest.age", "db/weekly/{$id}.manifest.age", "db/monthly/{$id}.manifest.age",
        ], array_slice($factory->store->puts, -6));
        $this->assertSame('6h,weekly,monthly', $this->rowOf($id)['tiers']);
    }

    public function test_each_tier_has_its_object_lock_and_an_on_demand_run_writes_only_the_first(): void
    {
        // Sunday the 1st of September 2030, at 00.00.
        $this->atJakarta('2030-09-01 00:00:10');
        $now = Date::now();

        $scheduled = $this->runBackup();
        $this->assertNull($scheduled->failure, $scheduled->diagnostic);

        $store = TestStore::open();

        foreach (['6h' => 7, 'weekly' => 35, 'monthly' => 93] as $tier => $days) {
            foreach (['dump', 'manifest'] as $kind) {
                $object = $store->head("db/{$tier}/{$scheduled->backupId}.{$kind}.age");
                $this->assertNotNull($object);
                $this->assertSame('GOVERNANCE', $object->lockMode);
                $this->assertSame($now->copy()->addDays($days)->utc()->format('Y-m-d'), substr($object->retainUntil, 0, 10), "{$tier} {$kind}");
            }
        }

        $onDemand = $this->runBackup(BackupReason::ON_DEMAND);
        $this->assertNull($onDemand->failure, $onDemand->diagnostic);
        $this->assertSame(['ON_DEMAND', '6h'], [$this->rowOf($onDemand->backupId)['reason'], $this->rowOf($onDemand->backupId)['tiers']]);
        $this->assertNull($store->head("db/weekly/{$onDemand->backupId}.dump.age"));
    }

    public function test_the_reduced_retention_is_set_at_upload(): void
    {
        $this->atJakarta('2030-01-01 06:00:10');
        config(['backup.retention_profile' => 'reduced']);

        $reduced = $this->runBackup();

        $this->assertNull($reduced->failure, $reduced->diagnostic);
        $this->assertSame(Date::now()->addDays(3)->utc()->format('Y-m-d'), substr((string) TestStore::open()->head("db/6h/{$reduced->backupId}.dump.age")?->retainUntil, 0, 10));
    }

    public function test_a_sunday_run_reverifies_the_previous_weekly_copy(): void
    {
        $this->atJakarta('2030-01-06 00:00:10');
        $first = $this->runBackup();
        $this->assertNull($first->failure, $first->diagnostic);
        $this->assertNull($this->rowOf($first->backupId)['reverified_backup_id'], 'No earlier copy exists.');

        $this->atJakarta('2030-01-13 00:00:10');
        $second = $this->runBackup();
        $this->assertNull($second->failure, $second->diagnostic);
        $this->assertSame($first->backupId, $this->rowOf($second->backupId)['reverified_backup_id']);
    }

    public function test_a_later_run_uploads_only_what_is_missing_and_reads_no_object(): void
    {
        $this->storeLogo();
        $factory = new RecordingStoreFactory;
        $this->app->instance(ObjectStoreFactory::class, $factory);

        $first = $this->runBackup();
        $this->assertNull($first->failure, $first->diagnostic);
        $this->assertSame(1, $first->filesUploaded);

        $second = $this->runBackup();
        $this->assertSame(0, $second->filesUploaded);
        $this->assertSame(0, $factory->store?->heads);

        $this->storeLogo();
        $third = $this->runBackup();
        $this->assertSame(1, $third->filesUploaded);
    }

    public function test_a_rotation_goes_by_a_bounded_number_of_files_per_run_with_a_backup_in_each(): void
    {
        $first = $this->storeLogo();
        $second = $this->storeLogo();
        $initial = $this->runBackup();
        $this->assertNull($initial->failure, $initial->diagnostic);
        $this->assertSame(2, $initial->filesUploaded);

        // BKP-22: the new recipient, at one file per run.
        $newIdentity = $this->area.'/new-identity';
        $newRecipient = TestStore::newRecoveryKey($newIdentity);
        config(['backup.recipient' => $newRecipient, 'backup.rotation_files_per_run' => 1]);

        $factory = new RecordingStoreFactory;
        $this->app->instance(ObjectStoreFactory::class, $factory);
        $store = TestStore::open();

        $one = $this->runBackup();
        $this->assertNull($one->failure, $one->diagnostic);
        $this->assertSame(1, $one->filesUploaded);
        $this->assertSame($newRecipient, $store->head('files/'.$first.'.age')?->recipient);
        $this->assertSame($this->recipient, $store->head('files/'.$second.'.age')?->recipient, 'The second file waits for the next run.');
        $this->assertNotNull($store->head('db/6h/'.$one->backupId.'.manifest.age'), 'The backup does not wait for the rotation.');
        $this->assertStringStartsWith(BackupRunner::ROTATING.' '.$newRecipient.' ', (string) $store->getText(BackupRunner::RECIPIENT_MARKER));

        // A file added meanwhile goes to the new recipient by step 4, and only once.
        $third = $this->storeLogo();
        $two = $this->runBackup();
        $this->assertNull($two->failure, $two->diagnostic);
        $this->assertSame(2, $two->filesUploaded);
        $this->assertSame($newRecipient, $store->getText(BackupRunner::RECIPIENT_MARKER), 'The pass is finished.');

        foreach ([$first, $second, $third] as $key) {
            $this->assertSame($newRecipient, $store->head('files/'.$key.'.age')?->recipient);
            $this->assertTrue($store->download('files/'.$key.'.age', $this->area.'/rotated.age'));
            $this->assertSame(Storage::disk('media')->get($key), TestStore::decrypt($newIdentity, $this->area.'/rotated.age', $this->area.'/rotated-'.$key));
        }

        // Finished, it reads no object and uploads nothing; a pass cut short by a bad
        // marker starts again rather than leaving an old recipient behind.
        $done = $this->runBackup();
        $this->assertSame(0, $done->filesUploaded);
        $this->assertSame(0, $factory->store?->heads);

        config(['backup.rotation_files_per_run' => 1000]);
        $store->putText(BackupRunner::RECIPIENT_MARKER, BackupRunner::ROTATING);
        $restarted = $this->runBackup();
        $this->assertSame(3, $restarted->filesUploaded);
        $this->assertSame($newRecipient, $store->getText(BackupRunner::RECIPIENT_MARKER));
    }

    public function test_a_rotation_stops_short_of_the_time_limit_and_the_run_succeeds(): void
    {
        $this->storeLogo();
        $initial = $this->runBackup();
        $this->assertNull($initial->failure, $initial->diagnostic);

        $newRecipient = TestStore::newRecoveryKey($this->area.'/new-identity');
        config(['backup.recipient' => $newRecipient, 'backup.time_limit_seconds' => 200]);

        $short = $this->runBackup();

        $this->assertNull($short->failure, $short->diagnostic);
        $this->assertSame(0, $short->filesUploaded);
        $this->assertStringStartsWith(BackupRunner::ROTATING.' '.$newRecipient.' 0 ', (string) TestStore::open()->getText(BackupRunner::RECIPIENT_MARKER));

        config(['backup.time_limit_seconds' => 1800]);
        $next = $this->runBackup();

        $this->assertSame(1, $next->filesUploaded);
        $this->assertSame($newRecipient, TestStore::open()->getText(BackupRunner::RECIPIENT_MARKER));
    }

    public function test_the_dump_and_the_counts_share_one_snapshot(): void
    {
        $user = $this->readText(config('database.connections.pgsql_backup.username'));
        $host = $this->readText(config('database.connections.pgsql_backup.host'));
        $database = $this->readText(config('database.connections.pgsql_backup.database'));

        // A row committed after the snapshot was exported and before pg_dump starts.
        config(['backup.tools.pg_dump' => $this->toolStub('pg-dump-after-a-commit', <<<BASH
            /usr/lib/postgresql/18/bin/psql -X -q -h '{$host}' -U '{$user}' -d '{$database}' -c "insert into backup_runs (backup_id, reason, started_at, finished_at, outcome, failure_code, tiers) values ('20261031T000000Z-SNAPSHOT', 'SCHEDULED', now(), now(), 'FAILED', 'NO_SPACE', '6h')" || exit 9
            exec /usr/lib/postgresql/18/bin/pg_dump "\$@"
            BASH)]);

        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);
        $this->assertSame(1, $this->observer()->table('backup_runs')->where('backup_id', '20261031T000000Z-SNAPSHOT')->count(), 'The row was committed.');

        $manifest = json_decode($this->decrypted('db/6h/'.$outcome->backupId.'.manifest.age'), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame(0, data_get($manifest, 'counts.backup_runs'));

        $encrypted = $this->area.'/dump.age';
        $this->assertTrue(TestStore::open()->download('db/6h/'.$outcome->backupId.'.dump.age', $encrypted));
        TestStore::decrypt($this->identity, $encrypted, $this->area.'/dump.plain');
        $sql = $this->area.'/runs.sql';
        Process::run([self::PG_RESTORE, '--data-only', '--table=backup_runs', '--file='.$sql, $this->area.'/dump.plain'])->throw();
        $this->assertStringNotContainsString('SNAPSHOT', (string) file_get_contents($sql));
    }

    public function test_the_run_says_when_the_store_passes_its_threshold(): void
    {
        config(['backup.usage_threshold_bytes.production' => 1]);

        $this->assertSame(0, Artisan::call('backup:run'));
        $this->assertStringContainsString('BACKUP_STORE_ABOVE_THRESHOLD', Artisan::output());
    }

    public function test_a_program_gets_no_stored_name_and_the_plaintext_is_gone_before_the_next_step(): void
    {
        $storageKey = $this->storeLogo();
        $log = $this->area.'/age.log';
        $work = $this->area.'/work';

        config(['backup.tools.age' => $this->toolStub('age-recorder', <<<BASH
            printf 'ARGS %s\\n' "\$*" >> '{$log}'
            ls -1 '{$work}'/run-*/ >> '{$log}'
            exec /usr/local/bin/age "\$@"
            BASH)]);

        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);

        $recorded = (string) file_get_contents($log);
        $calls = explode('ARGS ', $recorded);
        array_shift($calls);

        // The dump, the file and the manifest, and no name of a stored file.
        $this->assertCount(3, $calls);
        $this->assertStringNotContainsString($storageKey, $recorded);
        $this->assertStringContainsString('dump.plain', $calls[0], 'The dump is encrypted from its temporary file.');
        $this->assertStringNotContainsString('dump.plain', $calls[1], 'The plaintext is removed at once.');
        $this->assertStringNotContainsString('dump.plain', $calls[2]);
    }

    public function test_the_command_prints_no_address_key_or_path(): void
    {
        $this->storeLogo();

        $status = Artisan::call('backup:run', ['--reason' => 'PRE_RELEASE']);
        $output = Artisan::output();

        $this->assertSame(0, $status, $output);
        $this->assertMatchesRegularExpression('/\ABACKUP_SUCCEEDED \d{8}T\d{6}Z-[A-Za-z0-9]{8} files_uploaded=1\s*\z/', $output);
        $this->assertSame('PRE_RELEASE', $this->observer()->table('backup_runs')->value('reason'));
        $this->assertSame([self::PING_ADDRESS], $this->pings);
    }
}
