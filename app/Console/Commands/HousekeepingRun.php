<?php

namespace App\Console\Commands;

use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Domain\Operations\Housekeeping\Housekeeping;
use App\Domain\Operations\Housekeeping\HousekeepingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

/**
 * SC-02 `housekeeping:run` (ROUTE_CONTRACTS SC-02 and section 7; ENVIRONMENTS sections 13 and 14;
 * SECURITY_MODEL SEC-ACC-07, SEC-LOG-05 and section 14; TR-TX-05; TR-RUN-04; THR-10; TASK-038 scope
 * item 1), in the application container. Its scheduler runs it hourly (App\Console\ProcessSchedule); no
 * HTTP route reaches it. It takes no option and no argument: its bounds come from config/housekeeping.php
 * only, and no stored value reaches a process, a path outside its disks or a statement's text.
 *
 * Before it removes anything, the database's marker must equal the configured environment name
 * (ENVIRONMENTS ENV-04), because a file is removed when the database names it nowhere: never against
 * another environment's database. It takes its own no-overlap lock besides the scheduler's, for longer
 * than a run lasts. After the run, completed or not, its security log entry names the scheduled command
 * `housekeeping` (SECURITY_MODEL section 14). It changes no business data, so it writes no audit event.
 * Its lines hold the counts only.
 */
final class HousekeepingRun extends Command
{
    public const string PROCESS = 'housekeeping';

    private const string LOCK = 'housekeeping:run';

    /** Below the hour of the next run, far above a run's bounded batches. */
    private const int LOCK_SECONDS = 3300;

    protected $signature = 'housekeeping:run';

    protected $description = 'SC-02: remove expired links, resolved receipts after 30 days, security log entries after 365 days, unreferenced stored files after their grace period and temporary CSV files after 24 hours';

    public function handle(Housekeeping $housekeeping, SecurityLog $securityLog): int
    {
        try {
            EnvironmentMarker::require(DB::connection(), OperatorProcedure::ENVIRONMENTS);
        } catch (DatabaseRefused $refusal) {
            $this->line('HOUSEKEEPING_REFUSED '.$refusal->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $runId = (string) Str::uuid();
        $actor = SecurityActor::scheduledCommand(self::PROCESS);

        try {
            $settings = HousekeepingSettings::fromConfig();
        } catch (InvalidArgumentException $refusal) {
            $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, $actor, SecurityOutcome::Refused, $runId);
            $this->line('HOUSEKEEPING_REFUSED '.$refusal->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->line('HOUSEKEEPING_ALREADY_RUNNING');

            return SymfonyCommand::FAILURE;
        }

        try {
            $summary = $housekeeping->run($settings);
        } catch (Throwable $failure) {
            // Its class says what failed; its message may hold a path.
            report($failure);
            $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, $actor, SecurityOutcome::Refused, $runId);
            $this->line('HOUSEKEEPING_FAILED '.$failure::class);

            return SymfonyCommand::FAILURE;
        } finally {
            $lock->release();
        }

        $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, $actor, SecurityOutcome::Succeeded, $runId);
        $counts = $summary->counts();
        $this->line('HOUSEKEEPING_COMPLETED '.implode(' ', array_map(static fn (string $kind, int $count): string => $kind.'='.$count, array_keys($counts), $counts)));
        // The scheduler keeps no output (ProcessSchedule), so the counts go to the log too.
        Log::info('housekeeping.run', $counts);

        return SymfonyCommand::SUCCESS;
    }
}
