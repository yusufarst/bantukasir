<?php

namespace Tests\Harness\Backup;

use App\Domain\Operations\Backup\BackupOutcome;
use App\Domain\Operations\Backup\BackupReason;
use App\Domain\Operations\Backup\BackupRunner;
use App\Domain\Operations\Heartbeat;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Media\TestImages;

/**
 * The base of the tests of backup:run (TASK-013; suite REC01): the real programs of
 * the image, the test server and the test database, with Toko Contoh installed and
 * a bucket and a recovery key of its own for each test. The ping goes to a handler
 * that records it. Stored files come through RC-32, as every fixture comes through
 * its Action (EXC-45).
 */
abstract class BackupTestCase extends DatabaseTestCase
{
    use CommandRequests;

    protected const string PING_ADDRESS = 'https://heartbeat.test/the-secret-ping-address';

    protected const string PG_RESTORE = '/usr/lib/postgresql/18/bin/pg_restore';

    protected string $bucket = '';

    protected string $recipient = '';

    protected string $identity = '';

    protected string $area = '';

    /** @var list<string> */
    protected array $pings = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        TokoContoh::shop();
        $this->withoutVite();
        $this->useDatabaseSessions();
        $this->people['Hendra'] = TokoContoh::person('Hendra');
        $this->epoch = TokoContoh::recoveryEpoch();

        $this->area = sys_get_temp_dir().'/bk-backup-test-'.Str::random(16);
        File::ensureDirectoryExists($this->area, 0700);

        $this->identity = $this->area.'/identity';
        $this->recipient = TestStore::newRecoveryKey($this->identity);
        $this->bucket = TestStore::newBucket();

        config([
            'backup.store.endpoint' => TestStore::ENDPOINT,
            'backup.store.region' => null,
            'backup.store.bucket' => $this->bucket,
            'backup.store.key_id' => TestStore::KEY_ID,
            'backup.store.key' => TestStore::KEY,
            'backup.recipient' => $this->recipient,
            'backup.temporary_path' => $this->area.'/work',
            'heartbeat.checks.backup' => self::PING_ADDRESS,
        ]);

        $this->app->instance(Heartbeat::class, new Heartbeat(new Client(['handler' => function (RequestInterface $request) {
            $this->pings[] = (string) $request->getUri();

            return Create::promiseFor(new Response(200));
        }])));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->area);

        parent::tearDown();
    }

    protected function runBackup(BackupReason $reason = BackupReason::SCHEDULED): BackupOutcome
    {
        return $this->app->make(BackupRunner::class)->run($reason);
    }

    /**
     * Sets the clock to a time in Asia/Jakarta. A date in the future, because the
     * test server refuses a retention that has passed.
     */
    protected function atJakarta(string $time): void
    {
        $this->travelTo(Date::parse($time, 'Asia/Jakarta'));
    }

    /**
     * A logo through RC-32: its storage key.
     */
    protected function storeLogo(): string
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);

        $revision = $this->observer()->table('business_profile_current')->value('revision_id');
        $this->assertIsInt($revision);

        $this->post('/commands/business_profile.change', [
            'operation' => 'business_profile.change',
            'version' => '1',
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => ['revisionId' => (string) $revision],
            'payload' => [
                'shopName' => TokoContoh::SHOP, 'address' => '', 'contact' => '', 'email' => '', 'documentHeader' => '', 'documentFooter' => '', 'documentIdentity' => '',
                'accentToken' => 'green', 'logoAction' => 'replace',
                'logo' => UploadedFile::fake()->createWithContent('logo.jpg', TestImages::jpegWithMetadata(40, 20, 6, 'Lokasi')),
            ],
        ], ['Accept' => 'application/json'])->assertStatus(201);

        return $this->readText($this->observer()->table('stored_files')->orderByDesc('id')->value('storage_key'));
    }

    /**
     * The row of a run, with its hashes in hexadecimal.
     *
     * @return array<mixed>
     */
    protected function rowOf(string $backupId): array
    {
        return (array) $this->observer()->selectOne(
            "select backup_id, reason, outcome, failure_code, tiers, snapshot_at, dump_bytes, files_uploaded, uploaded_bytes, store_bytes, reverified_backup_id, pg_catalog.encode(dump_object_sha256, 'hex') as dump, pg_catalog.encode(manifest_object_sha256, 'hex') as manifest from backup_runs where backup_id = ?",
            [$backupId],
        );
    }

    /**
     * Downloads and decrypts an object as the operator's machine would: its plaintext.
     */
    protected function decrypted(string $key): string
    {
        $encrypted = $this->area.'/'.Str::random(12).'.age';

        $this->assertTrue(TestStore::open()->download($key, $encrypted), $key);

        return TestStore::decrypt($this->identity, $encrypted, $encrypted.'.plain');
    }

    /**
     * A program for a tool of config/backup.php, written at run time and left in the
     * harness's area.
     */
    protected function toolStub(string $name, string $script): string
    {
        $path = $this->area.'/'.$name;

        file_put_contents($path, "#!/bin/bash\n".$script."\n");
        chmod($path, 0755);

        return $path;
    }

    protected function assertNothingLeftBehind(): void
    {
        $this->assertSame([], File::allFiles($this->area.'/work'));
        $this->assertSame([], File::directories($this->area.'/work'));
    }
}
