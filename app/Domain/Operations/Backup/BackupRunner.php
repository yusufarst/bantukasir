<?php

namespace App\Domain\Operations\Backup;

use App\Database\DatabaseRefused;
use App\Database\DatabaseRoles;
use App\Database\EnvironmentMarker;
use App\Database\Identifier;
use App\Database\PrivilegeMatrix;
use App\Domain\Media\MediaStore;
use App\Domain\Operations\Heartbeat;
use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * One run of backup:run (SC-03): BACKUP_RECOVERY section 4 steps 1 to 9, as the
 * backup role (SCH-42). A backup exists only once its manifest is stored, so the
 * manifest is written last (ADR-010 item 8). Any failure writes one row with its
 * code, removes the temporary files, pings nothing and leaves the earlier copies
 * untouched.
 *
 * The counts of the manifest and the dump come from one exported snapshot, so that
 * the restore compares like with like while the shop works. The exporting session
 * holds its transaction until pg_dump has finished, which the snapshot needs only
 * until pg_dump has started; nothing tells this process when that is.
 *
 * @phpstan-type FileRow array{storageKey: string, purpose: string, size: int, sha256: string}
 * @phpstan-type Snapshot array{at: string, postgres: string, migration: string|null, counts: array<string, int>}
 */
final class BackupRunner
{
    public const string CONNECTION = 'pgsql_backup';

    /** The object that says which recipient every current file is encrypted to. */
    public const string RECIPIENT_MARKER = 'state/files-recipient';

    /** The marker's text while a rotation is uploading files again. */
    public const string ROTATING = 'ROTATING';

    public const string RECIPIENT_METADATA = 'bk-recipient';

    /** The classes of table whose counts the restore compares (BACKUP_RECOVERY section 5). */
    private const array COUNTED_CLASSES = ['F', 'FS', 'M', 'S'];

    /** The names of ENV-04, the local and test ones included for the rehearsal. */
    private const array ENVIRONMENTS = ['local', 'test', 'drill', 'staging', 'production'];

    private const int FILE_PAGE = 1000;

    public function __construct(
        private readonly ObjectStoreFactory $stores,
        private readonly DiskSpace $disk,
        private readonly MediaStore $media,
        private readonly Heartbeat $heartbeat,
    ) {}

    /**
     * @throws BackupRefused when the run cannot even start
     */
    public function run(BackupReason $reason): BackupOutcome
    {
        $settings = BackupSettings::fromConfig();
        $database = $this->database();
        $environment = $this->identify($database);

        $startedAt = Date::now();
        $backupId = $startedAt->copy()->utc()->format('Ymd\THis\Z').'-'.Str::random(8);
        $tiers = TierPlan::for($reason, $startedAt);
        $deadline = Deadline::after($settings->timeLimitSeconds);
        $work = rtrim($settings->temporaryPath, '/').'/run-'.$backupId;
        $snapshotAt = null;

        try {
            $this->prepare($settings->temporaryPath, $work);
            $this->requireSpace($database, $settings->temporaryPath);

            $store = $this->stores->make($settings, $deadline);
            $tools = new BackupTools($settings, $deadline);

            // Step 2: the dump and its check; step 3: its encryption.
            $plain = $work.'/dump.plain';
            $encrypted = $work.'/dump.age';
            $snapshot = $this->dump($database, $tools, $plain);
            $snapshotAt = $snapshot['at'];

            if (filesize($plain) === 0) {
                throw new BackupFailed(BackupFailure::DUMP_INVALID, 'An empty dump.');
            }

            $tools->checkArchive($plain);
            $plainHash = self::hashOf($plain);
            $tools->encryptFile($plain, $encrypted);
            File::delete($plain);
            $encryptedHash = self::hashOf($encrypted);
            $encryptedSize = self::sizeOf($encrypted);

            // Step 4: the files, read after the dump, so a superset of its rows.
            $rows = $this->storedFiles($database);
            [$filesUploaded, $filesBytes] = $this->uploadFiles($store, $tools, $settings, $rows, $work, $deadline);

            // Step 5: the dump in each tier.
            $dumpKeys = [];

            foreach ($tiers as $tier) {
                $key = self::objectKey($tier, $backupId, 'dump');
                $store->put($key, $encrypted, [], $this->retainUntil($settings, $tier));
                $dumpKeys[] = $key;
            }

            // Step 6: the manifest, last. The backup exists from this moment.
            $manifest = $work.'/manifest.age';
            $tools->encryptBytes($this->manifest($settings, $backupId, $environment, $startedAt, $snapshot, $dumpKeys, $encryptedSize, $encryptedHash, $plainHash, $rows), $manifest);
            $manifestHash = self::hashOf($manifest);

            foreach ($tiers as $tier) {
                $store->put(self::objectKey($tier, $backupId, 'manifest'), $manifest, [], $this->retainUntil($settings, $tier));
            }

            // Step 7: the Sunday run re-verifies the previous weekly copy.
            $reverified = in_array(TierPlan::WEEKLY, $tiers, true) ? $this->reverify($database, $store, $work) : null;

            // Step 8: the record, then the ping.
            $tierCount = count($tiers);
            $uploadedBytes = $filesBytes + $tierCount * ($encryptedSize + self::sizeOf($manifest));
            $storeBytes = $store->usageBytes();

            $this->record($database, $backupId, $reason, $startedAt, 'SUCCEEDED', null, $snapshotAt, $tiers, [
                'dump_bytes' => $encryptedSize,
                'files_uploaded' => $filesUploaded,
                'uploaded_bytes' => $uploadedBytes,
                'store_bytes' => $storeBytes,
                'dump' => $encryptedHash,
                'manifest' => $manifestHash,
                'reverified' => $reverified,
            ]);

            return new BackupOutcome($backupId, null, $filesUploaded, $this->heartbeat->ping(Heartbeat::BACKUP), $storeBytes >= $settings->usageThresholdBytes);
        } catch (Throwable $thrown) {
            [$failure, $diagnostic] = self::classify($thrown, $deadline);

            // Step 9: one row, with the code only.
            $this->record($this->database(), $backupId, $reason, $startedAt, 'FAILED', $failure, $snapshotAt, $tiers, []);

            return new BackupOutcome($backupId, $failure, diagnostic: $diagnostic);
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * The code of a failure. A store's refusal after the time limit is the limit,
     * since the limit is what cut its request; what no step named is the dump's
     * code, whose runbook action is to read the log and run again (RB-09).
     *
     * @return array{BackupFailure, string}
     */
    private static function classify(Throwable $thrown, Deadline $deadline): array
    {
        if (! $thrown instanceof BackupFailed) {
            return [$deadline->expired() ? BackupFailure::TIMEOUT : BackupFailure::DUMP_INVALID, $thrown::class.': '.$thrown->getMessage()];
        }

        if ($thrown->failure === BackupFailure::STORE_REFUSED && $deadline->expired()) {
            return [BackupFailure::TIMEOUT, $thrown->diagnostic];
        }

        return [$thrown->failure, $thrown->diagnostic];
    }

    /**
     * @throws BackupFailed
     */
    private static function hashOf(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new BackupFailed(BackupFailure::DUMP_INVALID, 'A temporary file cannot be read.');
        }

        return $hash;
    }

    /**
     * @throws BackupFailed
     */
    private static function sizeOf(string $path): int
    {
        $size = filesize($path);

        if ($size === false) {
            throw new BackupFailed(BackupFailure::DUMP_INVALID, 'A temporary file cannot be read.');
        }

        return $size;
    }

    private static function objectKey(string $tier, string $backupId, string $kind): string
    {
        return 'db/'.$tier.'/'.$backupId.'.'.$kind.'.age';
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }

    private static function textOf(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function database(): Connection
    {
        return DB::connection(self::CONNECTION);
    }

    /**
     * Step 1: the environment's name equals the database's marker (ENV-04), and the
     * connection is the backup role. Neither failure can write a row: the database
     * may not be this environment's.
     *
     * @throws BackupRefused
     */
    private function identify(Connection $database): string
    {
        try {
            DatabaseRoles::requireCurrent($database, 'backup');

            return EnvironmentMarker::require($database, self::ENVIRONMENTS);
        } catch (DatabaseRefused) {
            throw new BackupRefused('environment');
        }
    }

    /**
     * The private temporary area, with nothing left of a run that was killed: the
     * no-overlap lock says that no other run is working there.
     */
    private function prepare(string $base, string $work): void
    {
        File::ensureDirectoryExists($base, 0700);

        foreach (File::directories($base) as $directory) {
            if (str_starts_with(basename($directory), 'run-')) {
                File::deleteDirectory($directory);
            }
        }

        File::ensureDirectoryExists($work, 0700);
    }

    /**
     * Step 1: twice the last dump's size free, else NO_SPACE.
     *
     * @throws BackupFailed
     */
    private function requireSpace(Connection $database, string $base): void
    {
        $last = self::integer($database->table('backup_runs')->where('outcome', 'SUCCEEDED')->orderByDesc('finished_at')->orderByDesc('id')->value('dump_bytes'));
        $free = $this->disk->freeBytes($base);

        if ($free <= 0 || $free < 2 * $last) {
            throw new BackupFailed(BackupFailure::NO_SPACE, 'Less than twice the last dump is free.');
        }
    }

    /**
     * Step 2: the dump from one exported snapshot, which the counts read too.
     *
     * @return Snapshot
     *
     * @throws BackupFailed
     */
    private function dump(Connection $database, BackupTools $tools, string $file): array
    {
        $database->beginTransaction();

        try {
            $database->statement('set transaction isolation level repeatable read read only');
            $database->statement('set local idle_in_transaction_session_timeout = 0');

            $row = (array) $database->selectOne("select pg_catalog.pg_export_snapshot() as snapshot, pg_catalog.clock_timestamp() as at, pg_catalog.current_setting('server_version') as postgres");
            $counts = [];

            foreach (PrivilegeMatrix::load()->tables() as $table => $entry) {
                if (in_array($entry['class'], self::COUNTED_CLASSES, true)) {
                    $counts[$table] = self::integer($database->scalar('select count(*) from '.Identifier::quote($table)));
                }
            }

            $migration = $database->table('migrations')->orderByDesc('id')->value('migration');

            $tools->dump(self::textOf($row['snapshot'] ?? null), $file);

            return [
                'at' => self::utc(self::textOf($row['at'] ?? null)),
                'postgres' => self::textOf($row['postgres'] ?? null),
                'migration' => is_string($migration) ? $migration : null,
                'counts' => $counts,
            ];
        } finally {
            $database->rollBack();
        }
    }

    private static function utc(string $time): string
    {
        return Date::parse($time)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Every row of stored_files, by pages of its key.
     *
     * @return list<FileRow>
     */
    private function storedFiles(Connection $database): array
    {
        $files = [];
        $after = 0;

        do {
            $page = $database->select(
                "select id, storage_key, purpose, byte_size, pg_catalog.encode(sha256, 'hex') as sha256 from stored_files where id > ? order by id limit 1000",
                [$after],
            );

            foreach ($page as $row) {
                $file = (array) $row;
                $after = self::integer($file['id'] ?? null);
                $files[] = [
                    'storageKey' => self::textOf($file['storage_key'] ?? null),
                    'purpose' => self::textOf($file['purpose'] ?? null),
                    'size' => self::integer($file['byte_size'] ?? null),
                    'sha256' => self::textOf($file['sha256'] ?? null),
                ];
            }
        } while (count($page) === self::FILE_PAGE);

        return $files;
    }

    /**
     * Step 4: each file whose object is missing, or whose metadata names another
     * recipient, is checked, encrypted and uploaded once.
     *
     * Reading each object's metadata costs one capped transaction per file per run,
     * which the store's free allowance could not bear, so the marker object says
     * that every current file is encrypted to this recipient, and the metadata is
     * read only when it says otherwise: at the first run and after a rotation. The
     * marker reads ROTATING until such a pass has finished, so that a pass cut
     * short, or a recipient set back in between, is walked again (BACKUP_RECOVERY
     * sections 4 and 9).
     *
     * @param  list<FileRow>  $rows
     * @return array{int, int}
     *
     * @throws BackupFailed
     */
    private function uploadFiles(BackupStore $store, BackupTools $tools, BackupSettings $settings, array $rows, string $work, Deadline $deadline): array
    {
        $existing = $store->keys('files/');
        $current = $store->getText(self::RECIPIENT_MARKER) === $settings->recipient;

        if (! $current) {
            $store->putText(self::RECIPIENT_MARKER, self::ROTATING);
        }

        $uploaded = 0;
        $bytes = 0;

        foreach ($rows as $row) {
            $deadline->check();

            $key = self::fileKey($row['storageKey']);
            $present = array_key_exists($key, $existing);

            if ($present && ($current || $store->head($key)?->recipient === $settings->recipient)) {
                continue;
            }

            $bytes += $this->uploadFile($store, $tools, $settings, $row, $key, $work.'/file.age');
            $uploaded++;
        }

        if (! $current) {
            $store->putText(self::RECIPIENT_MARKER, $settings->recipient);
        }

        return [$uploaded, $bytes];
    }

    /**
     * @param  FileRow  $row
     * @return int the bytes uploaded
     *
     * @throws BackupFailed
     */
    private function uploadFile(BackupStore $store, BackupTools $tools, BackupSettings $settings, array $row, string $key, string $encrypted): int
    {
        $bytes = $this->media->read($row['storageKey']);

        if ($bytes === null) {
            throw new BackupFailed(BackupFailure::FILE_MISSING, 'A stored file is absent.');
        }

        if (! hash_equals($row['sha256'], hash('sha256', $bytes))) {
            throw new BackupFailed(BackupFailure::FILE_CORRUPT, 'A stored file differs from its checksum.');
        }

        $tools->encryptBytes($bytes, $encrypted);
        $size = self::sizeOf($encrypted);
        $store->put($key, $encrypted, [self::RECIPIENT_METADATA => $settings->recipient]);
        File::delete($encrypted);

        return $size;
    }

    private static function fileKey(string $storageKey): string
    {
        return 'files/'.$storageKey.'.age';
    }

    private function retainUntil(BackupSettings $settings, string $tier): CarbonInterface
    {
        return Date::now()->addDays($settings->retentionOf($tier));
    }

    /**
     * The manifest of BACKUP_RECOVERY section 5.
     *
     * @param  Snapshot  $snapshot
     * @param  list<string>  $dumpKeys
     * @param  list<FileRow>  $rows
     */
    private function manifest(
        BackupSettings $settings,
        string $backupId,
        string $environment,
        CarbonInterface $startedAt,
        array $snapshot,
        array $dumpKeys,
        int $encryptedSize,
        string $encryptedHash,
        string $plainHash,
        array $rows,
    ): string {
        $items = [];
        $total = 0;

        foreach ($rows as $row) {
            $items[] = [
                'storageKey' => $row['storageKey'],
                'purpose' => $row['purpose'],
                'size' => $row['size'],
                'sha256' => $row['sha256'],
                'objectKey' => self::fileKey($row['storageKey']),
            ];
            $total += $row['size'];
        }

        return json_encode([
            'format' => 1,
            'backupId' => $backupId,
            'environment' => $environment,
            'startedAt' => $startedAt->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'snapshotAt' => $snapshot['at'],
            'release' => $settings->release,
            'migration' => $snapshot['migration'],
            'postgres' => $snapshot['postgres'],
            'dump' => [
                'objectKey' => $dumpKeys[0] ?? '',
                'objectKeys' => $dumpKeys,
                'size' => $encryptedSize,
                'sha256' => $encryptedHash,
                'plaintextSha256' => $plainHash,
                'excludedTables' => BackupTools::EXCLUDED_TABLES,
            ],
            'files' => ['count' => count($items), 'size' => $total, 'items' => $items],
            'counts' => $snapshot['counts'],
            'recipient' => $settings->recipient,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Step 7: the previous weekly copy still returns the bytes it was given, as
     * they were recorded at its upload, without decrypting them. A copy that is
     * gone or differs is REMOTE_MISMATCH, an S1 incident (RB-09).
     *
     * @return string|null the copy re-verified; null when none exists yet
     *
     * @throws BackupFailed
     */
    private function reverify(Connection $database, BackupStore $store, string $work): ?string
    {
        $row = $database->selectOne(<<<'SQL'
            select backup_id,
                   pg_catalog.encode(dump_object_sha256, 'hex') as dump,
                   pg_catalog.encode(manifest_object_sha256, 'hex') as manifest
            from backup_runs
            where outcome = 'SUCCEEDED' and tiers like '%weekly%'
            order by finished_at desc, id desc
            limit 1
            SQL);

        if ($row === null) {
            return null;
        }

        $previous = (array) $row;
        $backupId = self::textOf($previous['backup_id'] ?? null);
        $file = $work.'/verify.age';

        foreach (['dump' => self::textOf($previous['dump'] ?? null), 'manifest' => self::textOf($previous['manifest'] ?? null)] as $kind => $expected) {
            if (! $store->download(self::objectKey(TierPlan::WEEKLY, $backupId, $kind), $file) || ! hash_equals($expected, self::hashOf($file))) {
                throw new BackupFailed(BackupFailure::REMOTE_MISMATCH, 'A weekly object is gone or differs.');
            }

            File::delete($file);
        }

        return $backupId;
    }

    /**
     * Steps 8 and 9: the one row of the run (SCHEMA section 5.12).
     *
     * @param  list<string>  $tiers
     * @param  array{dump_bytes?: int, files_uploaded?: int, uploaded_bytes?: int, store_bytes?: int, dump?: string, manifest?: string, reverified?: string|null}  $success
     */
    private function record(
        Connection $database,
        string $backupId,
        BackupReason $reason,
        CarbonInterface $startedAt,
        string $outcome,
        ?BackupFailure $failure,
        ?string $snapshotAt,
        array $tiers,
        array $success,
    ): void {
        $database->insert(
            "insert into backup_runs (backup_id, reason, started_at, finished_at, outcome, failure_code, snapshot_at, tiers, dump_bytes, files_uploaded, uploaded_bytes, store_bytes, dump_object_sha256, manifest_object_sha256, reverified_backup_id) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, pg_catalog.decode(?, 'hex'), pg_catalog.decode(?, 'hex'), ?)",
            [
                $backupId,
                $reason->value,
                $startedAt->format('Y-m-d H:i:s.uP'),
                Date::now()->format('Y-m-d H:i:s.uP'),
                $outcome,
                $failure?->value,
                $snapshotAt,
                implode(',', $tiers),
                $success['dump_bytes'] ?? null,
                $success['files_uploaded'] ?? null,
                $success['uploaded_bytes'] ?? null,
                $success['store_bytes'] ?? null,
                $success['dump'] ?? null,
                $success['manifest'] ?? null,
                $success['reverified'] ?? null,
            ],
        );
    }
}
