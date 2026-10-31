<?php

namespace Tests\Harness\Backup;

use App\Domain\Operations\Backup\BackupSettings;
use App\Domain\Operations\Backup\Deadline;
use App\Domain\Operations\Backup\ObjectStore;
use App\Domain\Operations\Backup\SigV4;
use App\Domain\Operations\OutboundHttp;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The S3-compatible test server of compose.yaml, the service backup-store (TASK-013
 * scope item 7), with its default test credentials, and the recovery key that the
 * harness keeps outside the application's configuration (AC-013-02): the
 * application is given only the recipient, and only this class decrypts.
 */
final class TestStore
{
    public const string ENDPOINT = 'http://backup-store:9000';

    public const string HOST = 'backup-store:9000';

    public const string KEY_ID = 'minioadmin';

    public const string KEY = 'minioadmin';

    /**
     * A new bucket for one test, with Object Lock unless the test wants a store
     * that refuses a retention.
     */
    public static function newBucket(bool $objectLock = true): string
    {
        $bucket = 'bk-test-'.strtolower(Str::random(16));
        $headers = $objectLock ? ['x-amz-bucket-object-lock-enabled' => 'true'] : [];
        $signed = SigV4::sign('PUT', self::HOST, '/'.$bucket, [], $headers, hash('sha256', ''), 'us-east-1', self::KEY_ID, self::KEY, gmdate('Ymd\THis\Z'));
        $response = OutboundHttp::client()->request('PUT', self::ENDPOINT.'/'.$bucket, ['headers' => $signed, 'timeout' => 30]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('The test bucket was not created: HTTP '.$response->getStatusCode().' '.$response->getBody());
        }

        return $bucket;
    }

    /**
     * The store that the application's configuration names, for the test's reads
     * and for its tampering, with the server's credentials.
     */
    public static function open(): ObjectStore
    {
        return new ObjectStore(OutboundHttp::client(), BackupSettings::fromConfig(), Deadline::after(300));
    }

    /**
     * A new recovery key in a file of the harness: its recipient.
     */
    public static function newRecoveryKey(string $path): string
    {
        Process::run(['age-keygen', '-o', $path])->throw();

        $key = (string) file_get_contents($path);

        if (preg_match('/^# public key: (age1[a-z0-9]+)$/m', $key, $match) !== 1) {
            throw new RuntimeException('age-keygen gave no recipient.');
        }

        return $match[1];
    }

    /**
     * Decrypts an object, as the operator's machine would (BKP-08): its plaintext.
     */
    public static function decrypt(string $identity, string $source, string $target): string
    {
        Process::run(['age', '--decrypt', '--identity', $identity, '--output', $target, $source])->throw();

        return (string) file_get_contents($target);
    }
}
