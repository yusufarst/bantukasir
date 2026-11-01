<?php

namespace App\Domain\Operations\Release;

use App\Database\DatabaseRefused;
use App\Database\DatabaseRoles;
use App\Database\EnvironmentMarker;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;

/**
 * The release preflight (RELEASE_PROCESS REL-23; ADR-011 item 7; TASK-014 scope
 * item 3), read only and as the migration role, whose credential is the only one
 * the migration job holds: it writes nothing, so a refusal stops the job before
 * its first statement. Each refusal is a code and a line for the operator, which
 * holds no secret. After the identification every other condition is judged, so
 * that one run reports them all.
 */
final class Preflight
{
    public const string CONNECTION = 'pgsql_migration';

    /** The names of ENV-04. */
    private const array ENVIRONMENTS = ['local', 'test', 'drill', 'staging', 'production'];

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly Migrator $migrator,
    ) {}

    /**
     * The refusals, by code; an empty list means the job may go on.
     *
     * @return array<string, string>
     */
    public function refusals(PreflightInput $input): array
    {
        $connection = $this->databases->connection(self::CONNECTION);

        try {
            DatabaseRoles::requireCurrent($connection, 'migration');
            $marker = EnvironmentMarker::require($connection, self::ENVIRONMENTS);
        } catch (DatabaseRefused $refused) {
            return ['ENVIRONMENT' => $refused->getMessage()];
        }

        if ($input->environment !== $marker) {
            return ['ENVIRONMENT' => sprintf('The typed environment %s is not the environment %s of this database (ENV-04).', json_encode($input->environment), json_encode($marker))];
        }

        $pending = $this->pendingMigrations();

        return array_filter([
            'BACKUP' => $this->backupRefusal($connection, $marker, $input),
            'MIGRATIONS' => $this->listRefusal($pending, $input),
            'DESTRUCTIVE' => $this->destructiveRefusal($pending, $input),
            'SYNCHRONOUS_COMMIT' => $this->synchronousCommitRefusal($connection),
            'BACKUP_SCHEDULE' => $input->backupSchedulePaused ? null : 'The operator has not confirmed that the backup schedule is paused (REL-23).',
        ], static fn (?string $refusal): bool => $refusal !== null);
    }

    /**
     * The migrations that have not run, by name, in their order.
     *
     * @return array<string, string> their files, by name
     */
    private function pendingMigrations(): array
    {
        /** @var array<string, string> $pending */
        $pending = $this->migrator->usingConnection(self::CONNECTION, function (): array {
            $ran = $this->migrator->repositoryExists() ? $this->migrator->getRepository()->getRan() : [];
            $files = $this->migrator->getMigrationFiles([database_path('migrations'), ...$this->migrator->paths()]);

            return array_filter($files, static fn (string $name): bool => ! in_array($name, $ran, true), ARRAY_FILTER_USE_KEY);
        });

        return $pending;
    }

    /**
     * REL-04: in the environments of config/release.php a successful backup less
     * than one hour old. The first installation has nothing to back up, and the
     * backup's own table does not exist until its migrations run (RB-23 step 9
     * takes the first backup after them), so the operator may say so, and that is
     * accepted only for a database without a table (reading of TASK-014).
     */
    private function backupRefusal(Connection $connection, string $marker, PreflightInput $input): ?string
    {
        if ($input->firstInstallation) {
            $empty = $connection->scalar("select not exists (select from pg_catalog.pg_tables where schemaname operator(pg_catalog.=) 'public')");

            return $empty === true ? null : 'The first installation is typed, but this database already holds tables.';
        }

        if (! in_array($marker, config()->array('release.backup_required_in'), true)) {
            return null;
        }

        if ($connection->scalar("select pg_catalog.to_regclass('public.backup_runs') is not null") !== true) {
            return 'This database has no table of backup runs, so no successful backup can be shown (REL-23).';
        }

        $fresh = $connection->scalar("select exists (select from public.backup_runs where outcome = 'SUCCEEDED' and finished_at > pg_catalog.now() - interval '1 hour')");

        return $fresh === true ? null : 'No successful backup less than one hour old exists (REL-04, REL-23).';
    }

    /**
     * @param  array<string, string>  $pending
     */
    private function listRefusal(array $pending, PreflightInput $input): ?string
    {
        $notListed = array_values(array_diff(array_keys($pending), $input->migrations));
        $notPending = array_values(array_diff($input->migrations, array_keys($pending)));

        if ($notListed === [] && $notPending === []) {
            return null;
        }

        return sprintf(
            'The pending migrations are not the release record\'s list. Pending, not listed: %s. Listed, not pending: %s.',
            $notListed === [] ? 'none' : implode(', ', $notListed),
            $notPending === [] ? 'none' : implode(', ', $notPending),
        );
    }

    /**
     * REL-06: a pending migration that is marked destructive, or holds a
     * destructive statement, needs its approval reference, and the release record
     * must name that approval.
     *
     * @param  array<string, string>  $pending
     */
    private function destructiveRefusal(array $pending, PreflightInput $input): ?string
    {
        $refused = [];

        foreach ($pending as $name => $file) {
            $code = (string) file_get_contents($file);

            if (! MigrationReview::isDestructive($code)) {
                continue;
            }

            $references = MigrationReview::approvals($code);

            if ($references === [] || array_diff($references, $input->approvals) !== []) {
                $refused[] = $name;
            }
        }

        return $refused === [] ? null : sprintf('Destructive without its approval reference in the migration and in the release record (REL-06): %s.', implode(', ', $refused));
    }

    /**
     * ENV-32: on in this session, and no database or role setting of this
     * database turns it off for the application's sessions.
     */
    private function synchronousCommitRefusal(Connection $connection): ?string
    {
        if ($connection->scalar("select pg_catalog.current_setting('synchronous_commit')") !== 'on') {
            return 'synchronous_commit is not on (ENV-32).';
        }

        $overrides = $connection->select(<<<'SQL'
            select c.setting
            from pg_catalog.pg_db_role_setting s
            cross join lateral pg_catalog.unnest(s.setconfig) as c(setting)
            where (s.setdatabase operator(pg_catalog.=) 0
                   or s.setdatabase operator(pg_catalog.=) (select d.oid from pg_catalog.pg_database d where d.datname operator(pg_catalog.=) pg_catalog.current_database()))
              and pg_catalog.starts_with(c.setting, 'synchronous_commit=')
              and c.setting operator(pg_catalog.<>) 'synchronous_commit=on'
            SQL);

        return $overrides === [] ? null : 'A role or database setting turns synchronous_commit off (ENV-32).';
    }
}
