<?php

namespace Tests\Feature\Operations\Backup;

use App\Domain\Operations\Backup\BackupFailed;
use App\Domain\Operations\Backup\BackupFailure;
use App\Domain\Operations\Heartbeat;
use App\Domain\Operations\OutboundHttp;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Harness\Backup\TestStore;
use Tests\TestCase;

/**
 * The store's client and the ping client against the test server (TASK-013 scope
 * items 4 and 7; L6), through PHP's HTTP wrapper: signed requests, the large object
 * in parts with its lock and metadata, paged lists, the usage of every version, an
 * object that a signed request's hash does not match, and a real ping.
 */
final class ObjectStoreServerTest extends TestCase
{
    private string $area = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = sys_get_temp_dir().'/bk-store-test-'.Str::random(16);
        File::ensureDirectoryExists($this->area, 0700);

        config([
            'backup.store.endpoint' => TestStore::ENDPOINT,
            'backup.store.region' => null,
            'backup.store.bucket' => TestStore::newBucket(),
            'backup.store.key_id' => TestStore::KEY_ID,
            'backup.store.key' => TestStore::KEY,
            'backup.recipient' => TestStore::newRecoveryKey($this->area.'/identity'),
            'backup.part_bytes' => 5 * 1024 * 1024,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->area);

        parent::tearDown();
    }

    public function test_a_large_object_goes_in_parts_with_its_lock_and_metadata(): void
    {
        $path = $this->area.'/large';
        $bytes = random_bytes(11 * 1024 * 1024);
        file_put_contents($path, $bytes);
        $until = Date::now()->addDays(7);
        $store = TestStore::open();

        $store->put('db/6h/large.age', $path, ['bk-recipient' => 'the-recipient'], $until);

        $object = $store->head('db/6h/large.age');
        $this->assertNotNull($object);
        $this->assertSame(strlen($bytes), $object->size);
        $this->assertSame('the-recipient', $object->recipient);
        $this->assertSame('GOVERNANCE', $object->lockMode);
        $this->assertSame($until->copy()->utc()->format('Y-m-d'), substr($object->retainUntil, 0, 10));
        $this->assertSame(['db/6h/large.age' => strlen($bytes)], $store->sizes('db/6h/large.age'));

        $this->assertTrue($store->download('db/6h/large.age', $this->area.'/back'));
        $this->assertSame(hash('sha256', $bytes), hash_file('sha256', $this->area.'/back'));
    }

    public function test_lists_usage_text_and_absent_objects(): void
    {
        $store = TestStore::open();
        $this->assertSame([], $store->sizes('files/'));
        $this->assertSame(0, $store->usageBytes());
        $this->assertNull($store->getText('state/absent'));
        $this->assertNull($store->head('state/absent'));
        $this->assertFalse($store->download('state/absent', $this->area.'/absent'));

        $store->putText('files/a.age', 'first');
        $store->putText('files/a.age', 'second!');
        $store->putText('files/b.age', 'other');
        $store->putText('db/6h/c.age', 'dump');

        $this->assertSame(['files/a.age' => strlen('second!'), 'files/b.age' => strlen('other')], $store->sizes('files/'));
        $this->assertSame('second!', $store->getText('files/a.age'));

        // The usage counts the superseded version too.
        $this->assertSame(strlen('first') + strlen('second!') + strlen('other') + strlen('dump'), $store->usageBytes());
    }

    public function test_a_request_of_a_wrong_signature_is_store_auth(): void
    {
        config(['backup.store.key' => 'not-the-key-of-the-store']);

        try {
            TestStore::open()->sizes('files/');
            $this->fail('The store accepted a wrong signature.');
        } catch (BackupFailed $failed) {
            $this->assertSame(BackupFailure::STORE_AUTH, $failed->failure);
            $this->assertSame('STORE_AUTH', $failed->getMessage());
        }
    }

    public function test_the_ping_client_reaches_a_real_address_and_a_closed_one_is_false(): void
    {
        $heartbeat = new Heartbeat(OutboundHttp::client());

        config(['heartbeat.checks.backup' => TestStore::ENDPOINT.'/minio/health/live']);
        $this->assertTrue($heartbeat->ping(Heartbeat::BACKUP));

        config(['heartbeat.checks.backup' => 'http://backup-store:1/secret']);
        $this->assertFalse($heartbeat->ping(Heartbeat::BACKUP));

        config(['heartbeat.checks.backup' => null]);
        $this->assertFalse($heartbeat->ping(Heartbeat::BACKUP));
    }
}
