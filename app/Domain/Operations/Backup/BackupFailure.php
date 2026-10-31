<?php

namespace App\Domain\Operations\Backup;

/**
 * The failure codes of backup:run (SCHEMA section 5.12; BACKUP_RECOVERY section
 * 4; OPERATIONS_RUNBOOK RB-09). The code is all that a run records of a failure:
 * never a path, a key or a message of the store.
 */
enum BackupFailure: string
{
    case NO_SPACE = 'NO_SPACE';
    case DUMP_INVALID = 'DUMP_INVALID';
    case FILE_MISSING = 'FILE_MISSING';
    case FILE_CORRUPT = 'FILE_CORRUPT';
    case STORE_REFUSED = 'STORE_REFUSED';
    case STORE_AUTH = 'STORE_AUTH';
    case REMOTE_MISMATCH = 'REMOTE_MISMATCH';
    case TIMEOUT = 'TIMEOUT';
}
