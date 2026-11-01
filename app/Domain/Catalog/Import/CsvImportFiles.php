<?php

namespace App\Domain\Catalog\Import;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use RuntimeException;

/**
 * The private temporary area of the import files (ARCHITECTURE ARC-43; SECURITY_MODEL
 * section 13; D-054; ENVIRONMENTS): a disk of its own outside the file volume, so that it
 * is never backed up, and no route ever serves it. A file lies under a new random key in
 * the folder of the actor who previewed it, which binds the import to that actor: another
 * actor's request finds nothing. Neither its name nor its declared type is ever read
 * (SEC-UP-01). A file older than 24 hours counts as gone and is left for housekeeping
 * (TASK-038), which also removes what a removal here could not.
 */
final class CsvImportFiles
{
    public const string DISK = 'csv_imports';

    /** ARC-43: housekeeping removes a file older than this. */
    public const int MAX_AGE_SECONDS = 86400;

    /** The key of 256 random bits. */
    private const string KEY = '/^[0-9a-f]{64}$/D';

    public function __construct(private readonly Clock $clock) {}

    /**
     * Writes the bytes under a new random key and returns it, the import's identifier.
     */
    public function store(int $actorId, string $bytes): string
    {
        $key = bin2hex(random_bytes(32));

        if (! Storage::disk(self::DISK)->put(self::path($actorId, $key), $bytes)) {
            throw new RuntimeException('The temporary file could not be written.');
        }

        return $key;
    }

    /**
     * The bytes of this actor's import, or null when it is missing or expired.
     */
    public function read(int $actorId, string $key): ?string
    {
        if (preg_match(self::KEY, $key) !== 1) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $path = self::path($actorId, $key);

        try {
            if ($this->clock->now()->getTimestamp() - $disk->lastModified($path) >= self::MAX_AGE_SECONDS) {
                return null;
            }

            return $disk->get($path);
        } catch (FilesystemException) {
            return null;
        }
    }

    /**
     * Removes this actor's import, if it is there. A failure leaves it for housekeeping.
     */
    public function remove(int $actorId, string $key): void
    {
        if (preg_match(self::KEY, $key) === 1) {
            Storage::disk(self::DISK)->delete(self::path($actorId, $key));
        }
    }

    private static function path(int $actorId, string $key): string
    {
        return (string) $actorId.'/'.$key;
    }
}
