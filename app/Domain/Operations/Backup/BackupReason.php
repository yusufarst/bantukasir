<?php

namespace App\Domain\Operations\Backup;

/**
 * Why a run started (SCHEMA section 5.12): the scheduler's, or `--reason=<code>`.
 */
enum BackupReason: string
{
    case SCHEDULED = 'SCHEDULED';
    case PRE_RELEASE = 'PRE_RELEASE';
    case ON_DEMAND = 'ON_DEMAND';
}
