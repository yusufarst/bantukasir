<?php

namespace App\Domain\Operations\Backup;

use RuntimeException;

/**
 * A step of the run failed with its code. The diagnostic stays in memory for the
 * tests' assertion messages: the command prints only the code, and the row holds
 * only the code (AC-013-06).
 */
final class BackupFailed extends RuntimeException
{
    public function __construct(public readonly BackupFailure $failure, public readonly string $diagnostic = '')
    {
        parent::__construct($failure->value);
    }
}
