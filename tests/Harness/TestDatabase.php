<?php

namespace Tests\Harness;

use App\Database\DatabaseRefused;
use App\Database\DatabaseRoles;
use App\Database\EnvironmentMarker;
use App\Database\Identifier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The test database (TEST_STRATEGY section 3, TS-07; TASK-004): bantukasir_test,
 * created by the same setup script as every other database (database/setup),
 * migrated by the same migrations as the migration role, and reset between
 * tests by the migration role, only after its marker and the configured
 * environment name both say "test". The runtime role, which the application
 * and the tests use, keeps its production privileges and never truncates.
 *
 * Test-only tables are named harness_*; a test creates and drops its own, and
 * the reset drops any that a failed run left behind.
 */
final class TestDatabase
{
    public const string RUNTIME = 'pgsql';

    public const string MIGRATION = 'pgsql_migration';

    public const string BACKUP = 'pgsql_backup';

    private static bool $migrated = false;

    /**
     * Migrates the test database once per process, as the migration role.
     *
     * @throws DatabaseRefused
     */
    public static function prepare(): void
    {
        if (self::$migrated) {
            return;
        }

        self::requireTestDatabase();

        $status = Artisan::call('migrate', ['--database' => self::MIGRATION, '--force' => true]);

        if ($status !== 0) {
            throw new RuntimeException('The migrations of the test database failed: '.Artisan::output());
        }

        self::$migrated = true;
    }

    /**
     * Empties every table but migrations, and drops every harness_* relation
     * and function, and every operator of a harness_* function, that a failed
     * test left behind, as the migration role, after the marker check (TS-07).
     *
     * @throws DatabaseRefused
     */
    public static function reset(?ConnectionInterface $migration = null): void
    {
        $migration = self::requireTestDatabase($migration);

        $relations = $migration->select(<<<'SQL'
            select c.relname, c.relkind
            from pg_catalog.pg_class c
            join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind in ('r', 'p', 'v', 'S') and c.relname <> 'migrations'
              and not exists (select from pg_catalog.pg_depend d
                              where d.classid = 'pg_catalog.pg_class'::regclass and d.objid = c.oid and d.deptype = 'e')
            order by c.relkind = 'S', c.relname
            SQL);

        foreach ($relations as $row) {
            $relation = (array) $row;
            $name = is_string($relation['relname']) ? $relation['relname'] : '';
            $harness = str_starts_with($name, 'harness_');

            match (true) {
                $harness && $relation['relkind'] === 'v' => $migration->statement('DROP VIEW IF EXISTS '.Identifier::quote($name).' CASCADE'),
                $harness && $relation['relkind'] === 'S' => $migration->statement('DROP SEQUENCE IF EXISTS '.Identifier::quote($name).' CASCADE'),
                $harness => $migration->statement('DROP TABLE IF EXISTS '.Identifier::quote($name).' CASCADE'),
                $relation['relkind'] === 'r' || $relation['relkind'] === 'p' => $migration->statement('TRUNCATE TABLE '.Identifier::quote($name).' RESTART IDENTITY CASCADE'),
                default => null,
            };
        }

        // The operators whose function is a harness_* function, then those
        // functions, whatever their arguments; in the test database only.
        $migration->statement(<<<'SQL'
            DO $$
            DECLARE
                r record;
            BEGIN
                FOR r IN
                    SELECT o.oid::pg_catalog.regoperator AS operator
                    FROM pg_catalog.pg_operator o
                    JOIN pg_catalog.pg_proc p ON p.oid OPERATOR(pg_catalog.=) o.oprcode
                    JOIN pg_catalog.pg_namespace n ON n.oid OPERATOR(pg_catalog.=) o.oprnamespace
                    WHERE n.nspname OPERATOR(pg_catalog.=) 'public'::name
                      AND pg_catalog.starts_with(p.proname::text, 'harness_')
                LOOP
                    EXECUTE 'DROP OPERATOR ' || r.operator::text;
                END LOOP;
                FOR r IN
                    SELECT p.oid::pg_catalog.regprocedure AS function
                    FROM pg_catalog.pg_proc p
                    JOIN pg_catalog.pg_namespace n ON n.oid OPERATOR(pg_catalog.=) p.pronamespace
                    WHERE n.nspname OPERATOR(pg_catalog.=) 'public'::name
                      AND pg_catalog.starts_with(p.proname::text, 'harness_')
                LOOP
                    EXECUTE 'DROP FUNCTION ' || r.function::text;
                END LOOP;
            END $$
            SQL);
    }

    /**
     * The migration role's connection to the test database, after both
     * checks: the marker and the configured name are "test", and the
     * connection is the migration role.
     *
     * @throws DatabaseRefused
     */
    public static function requireTestDatabase(?ConnectionInterface $migration = null): ConnectionInterface
    {
        $migration ??= DB::connection(self::MIGRATION);

        EnvironmentMarker::require($migration, ['test']);
        DatabaseRoles::requireCurrent($migration, 'migration');

        return $migration;
    }
}
