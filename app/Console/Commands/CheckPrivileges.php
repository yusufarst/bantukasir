<?php

namespace App\Console\Commands;

use App\Database\PrivilegeCheck;
use App\Database\PrivilegeMatrix;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

/**
 * db:check-privileges (RELEASE_PROCESS RB-21 step 2; TASK-004): the read-only
 * comparison of the database's catalogue with SCHEMA section 10 and the
 * privilege matrix. It writes nothing, so the operator runs it with the
 * application's own connection; it exits 1 and lists every difference when
 * there is one. Its text is for the operator, in English like the runbooks;
 * no user of the shop sees it.
 */
final class CheckPrivileges extends Command
{
    protected $signature = 'db:check-privileges
        {--database= : The connection to read the catalogue with (default: the application\'s)}';

    protected $description = 'Compare the database\'s privileges with SCHEMA section 10 (read only)';

    public function handle(DatabaseManager $databases): int
    {
        $name = $this->option('database');
        $connection = $databases->connection(is_string($name) && $name !== '' ? $name : null);
        $findings = (new PrivilegeCheck(PrivilegeMatrix::load()))->run($connection);

        if ($findings === []) {
            $this->info(sprintf('Privilege check passed: database %s matches SCHEMA section 10.', $connection->getDatabaseName()));

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->line($finding);
        }

        $this->error(sprintf('Privilege check failed: %d difference(s) from SCHEMA section 10.', count($findings)));

        return self::FAILURE;
    }
}
