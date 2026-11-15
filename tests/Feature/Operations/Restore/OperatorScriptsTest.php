<?php

namespace Tests\Feature\Operations\Restore;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Harness\Backup\BackupTestCase;
use Tests\Harness\Backup\TestStore;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Restore\TestRestore;

/**
 * The operator's scripts of RB-01 phase C and RB-02 steps 3 and 5 (TASK-037 scope item 7; BACKUP_RECOVERY
 * BKP-07, BKP-08, sections 4 and 5): `fetch-backup.sh` downloads a backup that backup:run stored on the test
 * server, checks every SHA-256 against its manifest and decrypts it with the recovery key, which only the
 * harness holds, and refuses a key that is not the backup's; `compare-counts.sql` finds the counts of a
 * database equal to its manifest and names each table that differs, and refuses another environment or
 * role. Here they run in the test process's container with the harness's synthetic key; the rehearsal of
 * scripts/checks/restore-rehearsal.sh runs them on a container of their own, the operator's machine.
 */
final class OperatorScriptsTest extends BackupTestCase
{
    private const string FETCH = 'scripts/operations/restore/fetch-backup.sh';

    private const string COUNTS = 'scripts/operations/restore/compare-counts.sql';

    private const string PSQL = '/usr/lib/postgresql/18/bin/psql';

    public function test_the_fetch_checks_and_decrypts_a_stored_backup_with_its_recovery_key(): void
    {
        $key = $this->storeLogo();
        $this->atJakarta('2026-11-17 07:00:00');
        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);
        $manifestHash = $this->readText($this->rowOf($outcome->backupId)['manifest'] ?? null);

        $result = $this->fetch($outcome->backupId, $this->area.'/fetched', $this->identity);
        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertSame('FETCHED '.$outcome->backupId.' files=1 manifest_sha256='.$manifestHash, trim($result->output()));

        $plain = $this->area.'/fetched/plain';
        $manifest = (array) json_decode((string) file_get_contents($plain.'/manifest.json'), true);
        $this->assertSame($outcome->backupId, $manifest['backupId'] ?? null);
        $this->assertSame(((array) ($manifest['dump'] ?? []))['plaintextSha256'] ?? null, hash_file('sha256', $plain.'/dump.pgcustom'));
        $this->assertSame(hash('sha256', (string) $this->media()->get($key)), hash_file('sha256', $plain.'/files/'.$key));
        $this->assertSame(['dump.pgcustom', 'files', 'manifest.json'], array_values(array_diff((array) scandir($plain), ['.', '..'])));
        $this->assertFalse(File::exists($this->area.'/fetched/encrypted'), 'The encrypted objects are removed.');
        $this->assertSame(0, Process::run([self::PG_RESTORE, '--list', $plain.'/dump.pgcustom'])->exitCode());

        // Another key decrypts nothing; a work folder in use is refused.
        $other = $this->area.'/other-identity';
        TestStore::newRecoveryKey($other);
        $refused = $this->fetch($outcome->backupId, $this->area.'/refused', $other);
        $this->assertFalse($refused->successful());
        $this->assertStringContainsString('FETCH_FAILED the manifest does not decrypt with this recovery key', $refused->errorOutput());
        $this->assertStringContainsString('the work folder is not empty', $this->fetch($outcome->backupId, $this->area.'/fetched', $this->identity)->errorOutput());
        $this->assertStringContainsString('the backup identifier has another form', $this->fetch('20261117T000000Z', $this->area.'/x', $this->identity)->errorOutput());
    }

    public function test_the_count_comparison_finds_the_manifests_counts_and_names_each_difference(): void
    {
        $this->atJakarta('2026-11-17 07:00:00');
        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);
        $manifest = $this->area.'/manifest.json';
        file_put_contents($manifest, $this->decrypted('db/6h/'.$outcome->backupId.'.manifest.age'));

        // The run's own row is written after its snapshot, so the counts are those of its dump, restored
        // as RB-01 step 9 restores it (TS-24).
        $dump = $this->area.'/dump.pgcustom';
        file_put_contents($dump, $this->decrypted('db/6h/'.$outcome->backupId.'.dump.age'));
        TestRestore::restore($dump);

        $equal = $this->compare($manifest, 'test', 'pgsql_migration');
        $this->assertTrue($equal->successful(), $equal->errorOutput());
        $this->assertMatchesRegularExpression('/BantuKasir counts equal to the manifest: [0-9]+ tables/', $equal->errorOutput());

        TokoContoh::category('Rak baru');
        $differs = $this->compare($manifest, 'test', 'pgsql_migration');
        $this->assertFalse($differs->successful());
        $this->assertMatchesRegularExpression('/BantuKasir counts differ from the manifest: categories: manifest [0-9]+, restored [0-9]+/', $differs->errorOutput());

        $this->assertStringContainsString('the typed environment is not the marker of this database', $this->compare($manifest, 'staging', 'pgsql_migration')->errorOutput());
        $this->assertStringContainsString('the session is not the migration role of this database', $this->compare($manifest, 'test', 'pgsql')->errorOutput());
    }

    private function fetch(string $backupId, string $work, string $identity): ProcessResult
    {
        return Process::timeout(120)->env([
            'BANTUKASIR_STORE_ENDPOINT' => TestStore::ENDPOINT,
            'BANTUKASIR_STORE_REGION' => '',
            'BANTUKASIR_STORE_BUCKET' => $this->bucket,
            'BANTUKASIR_STORE_KEY_ID' => TestStore::KEY_ID,
            'BANTUKASIR_STORE_KEY' => TestStore::KEY,
            'BANTUKASIR_RECOVERY_KEY' => $identity,
        ])->run(['bash', base_path(self::FETCH), $backupId, $work]);
    }

    private function compare(string $manifest, string $environment, string $connection): ProcessResult
    {
        $value = static fn (string $name): string => is_scalar($setting = config('database.connections.'.$connection.'.'.$name)) ? (string) $setting : '';

        return Process::timeout(60)->env(['BANTUKASIR_MANIFEST' => $manifest, 'PGPASSWORD' => ''])->run([
            self::PSQL, '-X', '-v', 'ON_ERROR_STOP=1', '-v', 'environment='.$environment,
            '-h', $value('host'), '-p', $value('port'), '-U', $value('username'), '-d', $value('database'),
            '-f', base_path(self::COUNTS),
        ]);
    }

    private function media(): Filesystem
    {
        return Storage::disk('media');
    }
}
