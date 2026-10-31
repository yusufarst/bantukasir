<?php

namespace App\Console;

use App\Domain\Operations\RequiredSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * The scheduled commands of each process role (ENVIRONMENTS section 13; ADR-010
 * item 6; TASK-012 scope item 2): role web registers SC-01, SC-02 and SC-04, role
 * backup only SC-03, and no other role any. The time zone is the scheduler's,
 * Asia/Jakarta (config/app.php). Each runs without overlap (TR-RUN-04) under the
 * scheduler's lock, in the role's cache store (config/cache.php), which a lock
 * left by a killed run leaves after an hour.
 *
 * The commands are added by their Tasks — TASK-036 SC-01, TASK-038 SC-02, TASK-013
 * SC-03 and TASK-039 SC-04 — so an entry whose command does not exist yet is
 * registered and skipped when due, rather than failing each time.
 *
 * The scheduler discards a command's output, so SC-03's lines, which RB-09 reads,
 * go to a file of the backup container. They hold no secret (BackupRun).
 */
final class ProcessSchedule
{
    public const string BACKUP_LOG = 'logs/backup-run.log';

    private const int LOCK_MINUTES = 60;

    /** Role, command and cron expression, by the identifier of ENVIRONMENTS section 13. */
    public const array COMMANDS = [
        'SC-01' => [RequiredSettings::WEB, 'reconciliation:run', '0 3 * * *'],
        'SC-02' => [RequiredSettings::WEB, 'housekeeping:run', '0 * * * *'],
        'SC-03' => [RequiredSettings::BACKUP, 'backup:run', '0 0,6,12,18 * * *'],
        'SC-04' => [RequiredSettings::WEB, 'monitor:heartbeat', '*/5 * * * *'],
    ];

    public static function register(Schedule $schedule, mixed $role): void
    {
        foreach (self::COMMANDS as $identifier => [$commandRole, $command, $cron]) {
            if ($commandRole !== $role) {
                continue;
            }

            $event = $schedule->command($command)
                ->cron($cron)
                ->description($identifier)
                ->withoutOverlapping(self::LOCK_MINUTES)
                ->when(static fn (): bool => array_key_exists($command, Artisan::all()));

            if ($identifier === 'SC-03') {
                $event->appendOutputTo(storage_path(self::BACKUP_LOG));
            }
        }
    }
}
