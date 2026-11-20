<?php

namespace App\Console;

use App\Domain\Operations\VolumeSpace;

/**
 * VolumeSpace on the container's own file system: a path that does not exist, a file system that answers
 * nothing and a total of zero or less than the free space are no measurement.
 */
final class LocalVolumeSpace implements VolumeSpace
{
    public function measure(string $path): ?array
    {
        if (! is_dir($path)) {
            return null;
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false) {
            return null;
        }

        $free = (int) $free;
        $total = (int) $total;

        return $total <= 0 || $free < 0 || $free > $total ? null : ['free' => $free, 'total' => $total];
    }
}
