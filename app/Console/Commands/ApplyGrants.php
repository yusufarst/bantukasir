<?php

namespace App\Console\Commands;

use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Database\Grants;
use App\Database\PrivilegeMatrix;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

/**
 * db:apply-grants (BACKUP_RECOVERY RB-01 step 9; TASK-004): the grants
 * procedure, which brings every table, sequence and the schema public to the
 * privilege matrix with the migration role. It changes privileges, so it
 * identifies its target positively first (ENVIRONMENTS ENV-04, OPERATIONS_RUNBOOK
 * OPR-02): the operator types the environment's name with --environment, and
 * that name, the configured one and the database's marker must be equal. It
 * runs only on the migration connection, in one transaction, and refuses
 * before any change when the schema differs from the matrix. --pretend prints
 * the statements without running them. Its arguments are typed options only
 * (THR-10); its text is for the operator, in English like the runbooks.
 */
final class ApplyGrants extends Command
{
    private const array ENVIRONMENTS = ['local', 'test', 'drill', 'staging', 'production'];

    protected $signature = 'db:apply-grants
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--database=pgsql_migration : The migration role\'s connection}
        {--pretend : Print the statements without running them}';

    protected $description = 'Apply the privilege matrix of SCHEMA section 10 as the migration role';

    public function handle(DatabaseManager $databases): int
    {
        $name = $this->option('database');
        $typed = $this->option('environment');
        $connection = $databases->connection(is_string($name) && $name !== '' ? $name : 'pgsql_migration');
        $pretend = $this->option('pretend') === true;

        try {
            $marker = EnvironmentMarker::require($connection, self::ENVIRONMENTS);

            if ($typed !== $marker) {
                throw new DatabaseRefused(sprintf('The typed environment %s is not the environment %s of this database (ENV-04).', json_encode($typed), json_encode($marker)));
            }

            $statements = (new Grants(PrivilegeMatrix::load()))->apply($connection, $pretend);
        } catch (DatabaseRefused $refusal) {
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }

        foreach ($statements as $statement) {
            $this->line($statement.';');
        }

        $this->info(sprintf('%s %d statement(s) on database %s, environment %s.', $pretend ? 'Would run' : 'Ran', count($statements), $connection->getDatabaseName(), $marker));

        return self::SUCCESS;
    }
}
