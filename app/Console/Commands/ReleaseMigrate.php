<?php

namespace App\Console\Commands;

use App\Database\DatabaseRefused;
use App\Database\DatabaseRoles;
use App\Domain\Operations\Release\Preflight;
use App\Domain\Operations\Release\Verification;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

/**
 * The body of the migration job (ADR-011 item 7; RELEASE_PROCESS RB-20 step 8,
 * REL-20; TASK-014 scope item 4), which docker/app/migration-job.sh starts in a
 * one-off container of the release image, with the migration role's credential
 * supplied at run time and the only connection it uses: the preflight, then the
 * migrations, then the verification queries, in that order, each stopping the job.
 * One job at a time on a database, by an advisory lock of its session. It has the
 * options of release:preflight, and its lines are the release record's evidence.
 */
final class ReleaseMigrate extends Command
{
    /** The advisory lock of the job, one per database (TR-RUN-04). */
    private const int LOCK = 8_014_001;

    protected $signature = 'release:migrate
        {--environment= : The name of the environment, typed by the operator; it must equal the configuration and the database marker}
        {--migrations= : The release record\'s list of migrations, by name, separated by commas, or "none"}
        {--destructive-approvals= : The decisions that the release record names under REL-06, separated by commas}
        {--backup-schedule-paused : The operator confirms that the backup container\'s schedule is paused}
        {--first-installation : The operator says that this database is new and empty, so that it has no backup yet}';

    protected $description = 'The migration job: the release preflight, the migrations, then the verification queries of REL-20';

    public function handle(DatabaseManager $databases, Verification $verification): int
    {
        try {
            $connection = $databases->connection(Preflight::CONNECTION);
            DatabaseRoles::requireCurrent($connection, 'migration');

            if ($connection->scalar('select pg_catalog.pg_try_advisory_lock(?::bigint)', [self::LOCK]) !== true) {
                $this->line('JOB_REFUSED ALREADY_RUNNING');

                return SymfonyCommand::FAILURE;
            }
        } catch (DatabaseRefused $refused) {
            $this->line('JOB_REFUSED ROLE '.$refused->getMessage());

            return SymfonyCommand::FAILURE;
        } catch (Throwable $thrown) {
            // The database cannot be read: its message may name the server.
            $this->line('JOB_REFUSED DATABASE '.$thrown::class);

            return SymfonyCommand::FAILURE;
        }

        try {
            return $this->steps($connection, $verification);
        } finally {
            $connection->scalar('select pg_catalog.pg_advisory_unlock(?::bigint)', [self::LOCK]);
        }
    }

    private function steps(Connection $connection, Verification $verification): int
    {
        $this->line('JOB_STEP preflight');

        if ($this->call('release:preflight', $this->preflightOptions()) !== SymfonyCommand::SUCCESS) {
            $this->line('JOB_REFUSED PREFLIGHT no statement ran');

            return SymfonyCommand::FAILURE;
        }

        $before = $verification->counts($connection);

        $this->line('JOB_STEP migrations');

        try {
            $migrated = $this->call('migrate', ['--database' => Preflight::CONNECTION, '--force' => true]);
        } catch (Throwable $thrown) {
            // A migration that failed rolled back in its own transaction (RB-22).
            $this->line('JOB_FAILED MIGRATIONS '.$thrown->getMessage());

            return SymfonyCommand::FAILURE;
        }

        if ($migrated !== SymfonyCommand::SUCCESS) {
            $this->line('JOB_FAILED MIGRATIONS');

            return SymfonyCommand::FAILURE;
        }

        $this->line('JOB_STEP verification');

        return $this->verify($connection, $verification, $before);
    }

    /**
     * @param  array<string, int>  $before
     */
    private function verify(Connection $connection, Verification $verification, array $before): int
    {
        $after = $verification->counts($connection);

        foreach ($after as $table => $count) {
            $this->line(sprintf('VERIFY_COUNT %s before=%d after=%d', $table, $before[$table] ?? 0, $count));
        }

        $destructiveApproved = is_string($this->option('destructive-approvals')) && $this->option('destructive-approvals') !== '';
        $findings = $verification->findings($connection, $before, $after, $destructiveApproved);

        foreach ($findings as $finding) {
            $this->line('VERIFY_FAILED '.$finding);
        }

        $privileges = $this->call('db:check-privileges', ['--database' => Preflight::CONNECTION]);

        // reconciliation:run belongs to TASK-036, which sets how it runs here.
        if (array_key_exists('reconciliation:run', Artisan::all())) {
            $reconciled = $this->call('reconciliation:run');
        } else {
            $this->line('VERIFY_NOT_AVAILABLE reconciliation:run (TASK-036)');
            $reconciled = SymfonyCommand::SUCCESS;
        }

        if ($findings !== [] || $privileges !== SymfonyCommand::SUCCESS || $reconciled !== SymfonyCommand::SUCCESS) {
            $this->line('JOB_FAILED VERIFICATION the migrations are applied; follow RB-22');

            return SymfonyCommand::FAILURE;
        }

        $this->line('JOB_PASSED');

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function preflightOptions(): array
    {
        return array_filter([
            '--environment' => $this->option('environment'),
            '--migrations' => $this->option('migrations'),
            '--destructive-approvals' => $this->option('destructive-approvals'),
            '--backup-schedule-paused' => $this->option('backup-schedule-paused'),
            '--first-installation' => $this->option('first-installation'),
        ], static fn (mixed $value): bool => $value !== null && $value !== false);
    }
}
