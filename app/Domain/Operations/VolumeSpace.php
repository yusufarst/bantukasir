<?php

namespace App\Domain\Operations;

/**
 * The free and total bytes of the file system of a path, for the item "Ruang penyimpanan" of the system
 * status (OPERATIONS_RUNBOOK section 5; TASK-039); null when the measurement could not be made. PHP gives
 * the measure as a float, which this module does not hold (ADR-006 item 5), so it is measured outside it
 * (App\Console\LocalVolumeSpace), as DiskSpace is for the backup.
 */
interface VolumeSpace
{
    /**
     * @return array{free: int, total: int}|null
     */
    public function measure(string $path): ?array;
}
