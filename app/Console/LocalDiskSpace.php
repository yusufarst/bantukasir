<?php

namespace App\Console;

use App\Domain\Operations\Backup\DiskSpace;

/**
 * DiskSpace on the container's own file system.
 */
final class LocalDiskSpace implements DiskSpace
{
    public function freeBytes(string $path): int
    {
        $free = @disk_free_space($path);

        return $free === false ? 0 : (int) $free;
    }
}
