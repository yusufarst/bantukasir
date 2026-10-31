<?php

namespace App\Domain\Operations\Backup;

/**
 * What one run ended with. The diagnostic is never printed (BackupFailed).
 */
final readonly class BackupOutcome
{
    public function __construct(
        public string $backupId,
        public ?BackupFailure $failure,
        public int $filesUploaded = 0,
        public bool $pinged = false,
        public bool $aboveUsageThreshold = false,
        public string $diagnostic = '',
    ) {}
}
