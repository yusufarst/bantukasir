<?php

namespace App\Domain\Operations\Backup;

/**
 * The free bytes of the file system of a path (BACKUP_RECOVERY section 4 step 1);
 * zero when it cannot be measured. PHP gives the measure as a float, which this
 * module does not hold (ADR-006 item 5), so it is measured outside it
 * (App\Console\LocalDiskSpace).
 */
interface DiskSpace
{
    public function freeBytes(string $path): int;
}
