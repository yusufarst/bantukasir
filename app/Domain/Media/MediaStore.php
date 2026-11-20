<?php

namespace App\Domain\Media;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use LogicException;
use RuntimeException;

/**
 * The private storage of stored files (ARCHITECTURE ARC-40 to ARC-42;
 * SECURITY_MODEL section 13, SEC-UP-01, SEC-UP-06; THR-12): the disk `media`
 * of config/filesystems.php, outside the web root and never served by the
 * web server; files are delivered only through RD-07.
 *
 * A file is written under a new random key of 256 bits — never derived from
 * a name, a declared type or any other input — before the command's
 * transaction (TR-TX-05), with an exclusive create, so that a stored file is
 * never overwritten, and a file that a `stored_files` row names is never
 * deleted (D-042, BR-CFG-03). A file whose transaction rolled back stays
 * unreferenced until the housekeeping of SC-02 removes it after its grace
 * period (removeUnreferenced(), TASK-038).
 */
final class MediaStore
{
    public const string DISK = 'media';

    /** The storage key's format (SCHEMA section 5.5). */
    public const string KEY_FORMAT = '/^[A-Za-z0-9_-]{32,128}$/D';

    /** TR-TX-05: the grace period is at least one hour, well above the longest request. */
    public const int MIN_GRACE_SECONDS = 3600;

    /**
     * Removes at most $limit files that no `stored_files` row names and that
     * were last written more than $graceSeconds before $now, and returns how
     * many it removed (TR-TX-05; ARCHITECTURE ARC-41; SCHEMA section 4; SC-02,
     * TASK-038). Only a file at the root of the disk whose name is a storage
     * key counts; anything else is left as it is. The rows are read in
     * batches of $batch keys just before their files are removed: a row for a
     * key is inserted only by the transaction of the command that wrote its
     * file, which ended long before the grace period (TR-TX-05), so a key
     * without its row then never gains one.
     */
    public function removeUnreferenced(CarbonImmutable $now, int $graceSeconds, int $batch, int $limit): int
    {
        if ($graceSeconds < self::MIN_GRACE_SECONDS) {
            throw new InvalidArgumentException('The grace period of an unreferenced file is at least one hour (TR-TX-05).');
        }

        $disk = self::disk();
        $writtenBefore = $now->getTimestamp() - $graceSeconds;
        $candidates = [];

        foreach ($disk->files() as $name) {
            if (count($candidates) >= $limit) {
                break;
            }

            if (preg_match(self::KEY_FORMAT, $name) !== 1) {
                continue;
            }

            try {
                if ($disk->lastModified($name) < $writtenBefore) {
                    $candidates[] = $name;
                }
            } catch (FilesystemException) {
                continue;
            }
        }

        $removed = 0;

        foreach (array_chunk($candidates, max(1, $batch)) as $keys) {
            $referenced = DB::table('stored_files')->whereIn('storage_key', $keys)->pluck('storage_key')->all();

            foreach (array_diff($keys, $referenced) as $key) {
                if ($disk->delete($key)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    /**
     * Writes the image under a new random key and returns what the
     * transaction records.
     */
    public function store(NormalizedImage $image): StoredBlob
    {
        $key = self::newKey();
        $path = self::path($key);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private media storage cannot be created.');
        }

        // 'x': create only; the write fails rather than replace a file.
        $handle = @fopen($path, 'xb');

        if ($handle === false) {
            throw new RuntimeException('A stored file is never overwritten; the new key exists already.');
        }

        try {
            $written = fwrite($handle, $image->bytes);
        } finally {
            fclose($handle);
        }

        if ($written !== strlen($image->bytes)) {
            throw new RuntimeException('The stored file was not written whole.');
        }

        return new StoredBlob($key, $image->mediaType, strlen($image->bytes), $image->width, $image->height, hash('sha256', $image->bytes, true));
    }

    /**
     * The bytes of a stored file, or null when its file is missing.
     */
    public function read(string $key): ?string
    {
        $path = self::path($key);

        if (! is_file($path)) {
            return null;
        }

        $bytes = file_get_contents($path);

        return is_string($bytes) ? $bytes : null;
    }

    /**
     * A new random key: 32 random bytes in URL-safe Base64, 43 characters.
     */
    public static function newKey(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function path(string $key): string
    {
        if (preg_match(self::KEY_FORMAT, $key) !== 1) {
            throw new LogicException('A storage key is a random key of SCHEMA section 5.5, never a path.');
        }

        return self::disk()->path($key);
    }

    /**
     * The local disk of config/filesystems.php.
     */
    private static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }
}
