<?php

namespace App\Console\Commands;

use App\Domain\Operations\Release\Preflight;
use App\Domain\Operations\Release\PreflightInput;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

/**
 * `release:preflight` (RELEASE_PROCESS REL-23; ADR-011 item 7; TASK-014 scope item
 * 3): what the migration job runs first, and the operator may run alone. It only
 * reads, as the migration role, so it leaves no audit event or security log entry,
 * like backup:run: these lines are its record, which the release record keeps. Its
 * options are typed by the operator and never reach a shell or a path (THR-10). Its
 * text is for the operator, in English like the runbooks, and holds no secret.
 */
final class ReleasePreflight extends Command
{
    private const string MIGRATION_NAME = '/\A[0-9A-Za-z_]{1,200}\z/';

    private const string DECISION = '/\AD-\d{3,}\z/';

    /** What the release record's list says when it lists no migration. */
    private const string NO_MIGRATIONS = 'none';

    protected $signature = 'release:preflight
        {--environment= : The name of the environment, typed by the operator; it must equal the configuration and the database marker}
        {--migrations= : The release record\'s list of migrations, by name, separated by commas, or "none"}
        {--destructive-approvals= : The decisions that the release record names under REL-06, separated by commas}
        {--backup-schedule-paused : The operator confirms that the backup container\'s schedule is paused}
        {--first-installation : The operator says that this database is new, so that it has no backup yet; refused unless the catalog shows it truly empty}';

    protected $description = 'Refuse a release before its first statement unless the conditions of REL-23 hold (read only)';

    public function handle(Preflight $preflight): int
    {
        $environment = $this->option('environment');
        $migrations = self::listOf($this->option('migrations'), self::MIGRATION_NAME, self::NO_MIGRATIONS);
        $approvals = self::listOf($this->option('destructive-approvals') ?? '', self::DECISION, '');

        if (! is_string($environment) || $environment === '' || $migrations === null || $approvals === null) {
            $this->line('PREFLIGHT_REFUSED OPTIONS --environment and --migrations are required, each in its form.');

            return SymfonyCommand::FAILURE;
        }

        try {
            $refusals = $preflight->refusals(new PreflightInput(
                $environment,
                $migrations,
                $approvals,
                $this->option('backup-schedule-paused') === true,
                $this->option('first-installation') === true,
            ));
        } catch (Throwable $thrown) {
            // The database cannot be read: its message may name the server.
            $this->line('PREFLIGHT_REFUSED DATABASE '.$thrown::class);

            return SymfonyCommand::FAILURE;
        }

        foreach ($refusals as $code => $refusal) {
            $this->line("PREFLIGHT_REFUSED {$code} {$refusal}");
        }

        if ($refusals !== []) {
            return SymfonyCommand::FAILURE;
        }

        if ($this->option('first-installation') === true) {
            $this->line('PREFLIGHT_FIRST_INSTALLATION the catalog shows this database truly empty; no backup is required');
        }

        $this->line(sprintf('PREFLIGHT_PASSED %s migrations=%d', $environment, count($migrations)));

        return SymfonyCommand::SUCCESS;
    }

    /**
     * A typed list, or null when it is missing or an item is not in its form.
     *
     * @return list<string>|null
     */
    private static function listOf(mixed $value, string $form, string $empty): ?array
    {
        if (! is_string($value) || ($value === '' && $empty !== '')) {
            return null;
        }

        if ($value === $empty) {
            return [];
        }

        $items = array_map(trim(...), explode(',', $value));

        foreach ($items as $item) {
            if (preg_match($form, $item) !== 1) {
                return null;
            }
        }

        return $items;
    }
}
