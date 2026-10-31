<?php

namespace App\Domain\Operations\Backup;

use Carbon\CarbonInterface;

/**
 * The operations of the backup store that a run uses (ADR-010 items 4 and 7). The
 * server's key can list, read, write and set a retention, and never delete or
 * bypass a lock, so nothing here deletes.
 *
 * A run confirms what it stored with sizes(), one capped transaction per thousand
 * objects, and never with head(), one per object (BACKUP_RECOVERY section 4).
 */
interface BackupStore
{
    /**
     * Uploads a local file with the store's integrity check; with a date, under an
     * Object Lock in governance mode until it.
     *
     * @param  array<string, string>  $metadata
     *
     * @throws BackupFailed
     */
    public function put(string $key, string $path, array $metadata = [], ?CarbonInterface $retainUntil = null): void;

    /**
     * @throws BackupFailed
     */
    public function putText(string $key, string $text): void;

    /**
     * The object's text, or null when it does not exist.
     *
     * @throws BackupFailed
     */
    public function getText(string $key): ?string;

    /**
     * What the store says of one object, or null when it does not exist: for the
     * operator's and the tests' reads.
     *
     * @throws BackupFailed
     */
    public function head(string $key): ?StoredObject;

    /**
     * The size of every current object under the prefix, by its key.
     *
     * @return array<string, int>
     *
     * @throws BackupFailed
     */
    public function sizes(string $prefix): array;

    /**
     * The bytes of every version in the bucket.
     *
     * @throws BackupFailed
     */
    public function usageBytes(): int;

    /**
     * Writes the object into a local file; false when it does not exist.
     *
     * @throws BackupFailed
     */
    public function download(string $key, string $path): bool;
}
