<?php

namespace App\Console\Commands;

use App\Domain\Operations\Backup\BackupReason;
use App\Domain\Operations\Backup\BackupRefused;
use App\Domain\Operations\Backup\BackupRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

/**
 * SC-03 `backup:run` (ROUTE_CONTRACTS section 7; BACKUP_RECOVERY section 4), in the
 * backup container: by its scheduler, or by the operator with --reason=<code>
 * before a release or whenever a fresh copy is needed. Its one option is a typed
 * code (THR-10). It takes its own no-overlap lock, because an operator's run does
 * not pass the scheduler's, for longer than a run can last.
 *
 * Its lines hold a word, the backup's identifier and a failure code, in English
 * like the runbooks; never a path, a key, an address or a message of the store.
 * The backup role can write nothing but backup_runs (SCH-42), so a run leaves no
 * audit event or security log entry: its row and these lines are its record.
 */
final class BackupRun extends Command
{
    private const string LOCK = 'backup:run';

    private const int LOCK_SECONDS = 2100;

    protected $signature = 'backup:run
        {--reason=SCHEDULED : Why the run starts: SCHEDULED, PRE_RELEASE or ON_DEMAND}';

    protected $description = 'Make one independent encrypted backup (SC-03)';

    public function handle(BackupRunner $runner): int
    {
        $option = $this->option('reason');
        $reason = is_string($option) ? BackupReason::tryFrom($option) : null;

        if ($reason === null) {
            $this->line('BACKUP_REFUSED reason');

            return SymfonyCommand::FAILURE;
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->line('BACKUP_ALREADY_RUNNING');

            return SymfonyCommand::FAILURE;
        }

        try {
            $outcome = $runner->run($reason);
        } catch (BackupRefused $refused) {
            $this->line('BACKUP_REFUSED '.$refused->getMessage());

            return SymfonyCommand::FAILURE;
        } catch (Throwable $thrown) {
            // The row itself could not be written, or the database went away: the
            // class says which, and its message may hold a path or an address.
            $this->line('BACKUP_RECORD_FAILED '.$thrown::class);

            return SymfonyCommand::FAILURE;
        } finally {
            $lock->release();
        }

        if ($outcome->failure !== null) {
            $this->line('BACKUP_FAILED '.$outcome->backupId.' '.$outcome->failure->value);

            return SymfonyCommand::FAILURE;
        }

        $this->line('BACKUP_SUCCEEDED '.$outcome->backupId.' files_uploaded='.$outcome->filesUploaded);

        if (! $outcome->pinged) {
            $this->line('BACKUP_PING_FAILED');
        }

        if ($outcome->aboveUsageThreshold) {
            $this->line('BACKUP_STORE_ABOVE_THRESHOLD');
        }

        return SymfonyCommand::SUCCESS;
    }
}
