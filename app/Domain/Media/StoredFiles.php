<?php

namespace App\Domain\Media;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The records of stored files (SCHEMA section 5.5, `stored_files`), which only
 * the Media module writes (STK-12): the transaction of the command that
 * stored a file inserts its record and the reference to it (TR-TX-05). A
 * record is never changed or deleted (class F). Guard G-20 — a reference
 * names a file of the matching purpose — is purposeOf() in the Actions.
 */
final class StoredFiles
{
    public const string LOGO = 'LOGO';

    public const string PRODUCT_PHOTO = 'PRODUCT_PHOTO';

    public const string PRODUCT_THUMBNAIL = 'PRODUCT_THUMBNAIL';

    public function __construct(private readonly Clock $clock) {}

    /**
     * The path of RD-07 that delivers a stored file by its internal key,
     * never by its storage key (SEC-CH-06, THR-12).
     */
    public static function deliveryPath(int $id): string
    {
        return '/media/'.$id;
    }

    /**
     * Records a file stored before the transaction, in the caller's
     * transaction, and returns its internal key.
     */
    public function insert(StoredBlob $blob, string $purpose, int $actorUserId, int $commandId): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A stored file is recorded in the transaction of the command that references it (TR-TX-05).');
        }

        if (! in_array($purpose, [self::LOGO, self::PRODUCT_PHOTO], true)) {
            throw new LogicException('A thumbnail is recorded with the photo it derives from (TASK-018).');
        }

        $id = DB::scalar(
            "insert into stored_files (storage_key, purpose, media_type, byte_size, width, height, sha256, derived_from_file_id, created_at, actor_user_id, command_id)
             values (?, ?, ?, ?, ?, ?, decode(?, 'hex'), null, ?, ?, ?) returning id",
            [
                $blob->storageKey, $purpose, $blob->mediaType, $blob->byteSize, $blob->width, $blob->height, bin2hex($blob->sha256),
                $this->clock->now()->format('Y-m-d H:i:s.uP'), $actorUserId, $commandId,
            ],
        );

        if (! is_int($id)) {
            throw new LogicException('The stored file record has its internal key.');
        }

        return $id;
    }

    /**
     * The purpose of a stored file, or null when there is none with this key
     * (guard G-20).
     */
    public function purposeOf(int $id): ?string
    {
        $purpose = DB::table('stored_files')->where('id', $id)->value('purpose');

        return is_string($purpose) ? $purpose : null;
    }

    /**
     * A stored file's record for delivery, or null.
     */
    public function find(int $id): ?StoredFile
    {
        $row = DB::table('stored_files')->where('id', $id)->first(['id', 'purpose', 'media_type', 'storage_key', 'derived_from_file_id']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $key = $values['id'] ?? null;
        $purpose = $values['purpose'] ?? null;
        $mediaType = $values['media_type'] ?? null;
        $storageKey = $values['storage_key'] ?? null;
        $derived = $values['derived_from_file_id'] ?? null;

        if (! is_int($key) || ! is_string($purpose) || ! is_string($mediaType) || ! is_string($storageKey)) {
            throw new LogicException('A stored file record has its key, purpose, media type and storage key.');
        }

        return new StoredFile($key, $purpose, $mediaType, $storageKey, is_int($derived) ? $derived : null);
    }
}
