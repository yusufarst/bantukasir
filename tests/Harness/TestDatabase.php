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
 * Before every migration and every reset, each connection of the harness —
 * migration, runtime and backup — must be its own marked role on a database
 * whose marker and the configured name are "test", and the runtime and backup
 * connections must reach the same database as the migration connection: the
 * runtime connection alone reads DB_URL, which overrides its host, port and
 * database (config/database.php), so the migration connection's marker alone
 * proves nothing about where the tests write (evidence section 10). Every
 * observer and race worker is held to the same database.
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

    private static ?string $identity = null;

    /**
     * Migrates the test database once per process, as the migration role,
     * after the check of every connection, which runs at every call.
     *
     * @throws DatabaseRefused
     */
    public static function prepare(): void
    {
        self::requireTestDatabase();

        if (self::$migrated) {
            return;
        }

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
     * The migration role's connection to the test database, after the checks
     * of every connection of the harness, which only read: the migration
     * connection (the given one, or the harness's) is the migration role on a
     * database whose marker and the configured name are "test"; the runtime
     * and backup connections are their own roles on that same database. The
     * database's identity is then recorded for the observers and the race
     * workers.
     *
     * @throws DatabaseRefused
     */
    public static function requireTestDatabase(?ConnectionInterface $migration = null): ConnectionInterface
    {
        $migration ??= DB::connection(self::MIGRATION);

        $identity = self::verify($migration, 'migration');
        self::verify(DB::connection(self::RUNTIME), 'runtime', $identity);
        self::verify(DB::connection(self::BACKUP), 'backup', $identity);

        self::$identity = $identity;

        return $migration;
    }

    /**
     * Refuses unless the connection is the role of this kind on the test
     * database: the database's marker and the configured environment name are
     * both "test", the connection's role is the one marked with this kind
     * (DatabaseRoles), and, when an identity is given, the database is that
     * one. It only reads, and returns the database's identity.
     *
     * @param  'migration'|'runtime'|'backup'  $kind
     *
     * @throws DatabaseRefused
     */
    public static function verify(ConnectionInterface $connection, string $kind, ?string $identity = null): string
    {
        EnvironmentMarker::require($connection, ['test']);
        DatabaseRoles::requireCurrent($connection, $kind);

        $actual = self::identity($connection);

        if ($identity !== null && $actual !== $identity) {
            throw new DatabaseRefused(sprintf(
                'The %s connection reaches the database %s, not the test database %s (TS-07).',
                $kind,
                $actual,
                $identity,
            ));
        }

        return $actual;
    }

    /**
     * The identity of the connection's database: its cluster's system
     * identifier, its OID and its name, as "system/oid/name". Two connections
     * reach the same database exactly when they give the same identity,
     * whatever host name, address or port each used.
     *
     * @throws DatabaseRefused
     */
    public static function identity(ConnectionInterface $connection): string
    {
        // pg_catalog's functions and operator by name: nothing of the schema
        // public can change the answer.
        $identity = $connection->scalar(<<<'SQL'
            select pg_catalog.concat_ws('/', (pg_catalog.pg_control_system()).system_identifier, d.oid, d.datname)
            from pg_catalog.pg_database d
            where d.datname operator(pg_catalog.=) pg_catalog.current_database()
            SQL);

        if (! is_string($identity) || preg_match('/\A\d+\/\d+\/.+\z/', $identity) !== 1) {
            throw new DatabaseRefused('The identity of the database could not be read.');
        }

        return $identity;
    }

    /**
     * The identity of the test database as the last check of this process
     * recorded it (requireTestDatabase, which every test on the database runs
     * before it starts).
     *
     * @throws DatabaseRefused when no check has passed in this process
     */
    public static function verifiedIdentity(): string
    {
        if (self::$identity === null) {
            throw new DatabaseRefused('No connection of the harness has been checked in this process (TestDatabase::requireTestDatabase).');
        }

        return self::$identity;
    }
}
