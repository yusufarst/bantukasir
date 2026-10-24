<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRoles;
use App\Database\EnvironmentMarker;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;

/**
 * The setup script of ENVIRONMENTS ENV-30 as the test database shows it
 * (AC-004-01; database/setup/setup.sql, run by database/setup/local.sh for
 * the local and the test database, in local runs and in CI alike): the
 * marker, the encoding and locale (SCH-14), the three roles and their
 * settings (ENV-31), the time zone (ENV-33), the rights of PUBLIC (SCH-43)
 * and pg_trgm created by the migration role. The script's refusals and its
 * idempotency are proven at every start by database/setup/selftest.sh.
 */
final class SetupTest extends DatabaseTestCase
{
    public function test_the_test_database_carries_its_marker_encoding_and_locale(): void
    {
        $this->assertSame('test', EnvironmentMarker::of($this->runtime()));

        $database = (array) $this->runtime()->selectOne(
            'select pg_catalog.pg_encoding_to_char(encoding) as encoding, datlocprovider::text as provider, datcollate, datctype, datlocale from pg_catalog.pg_database where datname = pg_catalog.current_database()'
        );

        $this->assertSame(['encoding' => 'UTF8', 'provider' => 'c', 'datcollate' => 'en_US.utf8', 'datctype' => 'en_US.utf8', 'datlocale' => null], $database);
    }

    public function test_public_holds_no_right_on_the_database_and_the_roles_only_connect(): void
    {
        $privileges = (array) $this->runtime()->selectOne(<<<'SQL'
            select pg_catalog.has_database_privilege('public', pg_catalog.current_database(), 'CONNECT') as public_connect,
                   pg_catalog.has_database_privilege('public', pg_catalog.current_database(), 'TEMPORARY') as public_temporary,
                   pg_catalog.has_database_privilege('public', pg_catalog.current_database(), 'CREATE') as public_create
            SQL);

        $this->assertSame(['public_connect' => false, 'public_temporary' => false, 'public_create' => false], $privileges);

        $roles = DatabaseRoles::of($this->runtime());

        foreach ([$roles->migration, $roles->runtime, $roles->backup] as $role) {
            $rights = (array) $this->runtime()->selectOne(<<<'SQL'
                select pg_catalog.has_database_privilege(?, pg_catalog.current_database(), 'CONNECT') as connect,
                       pg_catalog.has_database_privilege(?, pg_catalog.current_database(), 'TEMPORARY') as temporary,
                       pg_catalog.has_database_privilege(?, pg_catalog.current_database(), 'CREATE') as create
                SQL, [$role, $role, $role]);

            $this->assertSame(['connect' => true, 'temporary' => false, 'create' => false], $rights, $role);
        }
    }

    public function test_the_three_roles_are_ordinary_login_roles_without_memberships(): void
    {
        $roles = DatabaseRoles::of($this->runtime());
        $rows = $this->runtime()->select(
            'select rolname, rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls, rolinherit from pg_catalog.pg_roles where rolname in (?, ?, ?) order by rolname',
            [$roles->migration, $roles->runtime, $roles->backup],
        );

        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $role = (array) $row;
            $this->assertSame([true, false, false, false, false, false, false], array_values(array_slice($role, 1)), (string) json_encode($role));
        }

        $this->assertSame(0, $this->runtime()->scalar(
            'select count(*) from pg_catalog.pg_auth_members where pg_catalog.pg_get_userbyid(roleid) in (?, ?, ?) or pg_catalog.pg_get_userbyid(member) in (?, ?, ?)',
            [$roles->migration, $roles->runtime, $roles->backup, $roles->migration, $roles->runtime, $roles->backup],
        ));
    }

    public function test_each_role_has_the_settings_of_env_31_and_the_database_runs_in_utc(): void
    {
        $show = fn (Connection $connection): array => (array) $connection->selectOne(<<<'SQL'
            select current_user = (select r.rolname from pg_catalog.pg_db_role_setting s
                                   join pg_catalog.pg_roles r on r.oid = s.setrole
                                   join pg_catalog.pg_database d on d.oid = s.setdatabase
                                   where d.datname = pg_catalog.current_database()
                                     and 'bantukasir.role=' || pg_catalog.current_setting('bantukasir.role') = any (s.setconfig)) as marked,
                   pg_catalog.current_setting('bantukasir.role') as kind,
                   pg_catalog.current_setting('lock_timeout') as lock_timeout,
                   pg_catalog.current_setting('statement_timeout') as statement_timeout,
                   pg_catalog.current_setting('idle_in_transaction_session_timeout') as idle_in_transaction,
                   pg_catalog.current_setting('transaction_timeout') as transaction_timeout,
                   pg_catalog.current_setting('TimeZone') as time_zone
            SQL);

        $this->assertSame(['marked' => true, 'kind' => 'runtime', 'lock_timeout' => '5s', 'statement_timeout' => '10s', 'idle_in_transaction' => '30s', 'transaction_timeout' => '30s', 'time_zone' => 'UTC'], $show($this->runtime()));
        $this->assertSame(['marked' => true, 'kind' => 'migration', 'lock_timeout' => '5s', 'statement_timeout' => '0', 'idle_in_transaction' => '30s', 'transaction_timeout' => '0', 'time_zone' => 'UTC'], $show($this->migration()));
        $this->assertSame(['marked' => true, 'kind' => 'backup', 'lock_timeout' => '0', 'statement_timeout' => '0', 'idle_in_transaction' => '30s', 'transaction_timeout' => '0', 'time_zone' => 'UTC'], $show($this->backup()));
    }

    public function test_the_role_settings_equal_the_configuration(): void
    {
        $this->assertSame([
            'migration' => ['bantukasir.role' => 'migration', 'lock_timeout' => '5s', 'idle_in_transaction_session_timeout' => '30s'],
            'runtime' => ['bantukasir.role' => 'runtime', 'lock_timeout' => '5s', 'statement_timeout' => '10s', 'idle_in_transaction_session_timeout' => '30s', 'transaction_timeout' => '30s'],
            'backup' => ['bantukasir.role' => 'backup', 'statement_timeout' => '0', 'idle_in_transaction_session_timeout' => '30s', 'transaction_timeout' => '0'],
        ], config('database.role_settings'));
        $this->assertSame('30s', config('database.limits.heavy_transaction_budget'));
    }

    public function test_no_role_of_the_application_can_change_the_marker_or_its_own_mark(): void
    {
        foreach ([$this->migration(), $this->runtime(), $this->backup()] as $connection) {
            $this->assertDenied($connection, fn (Connection $c) => $c->statement("COMMENT ON DATABASE bantukasir_test IS 'production'"));
            $this->assertDenied($connection, fn (Connection $c) => $c->statement("ALTER ROLE CURRENT_USER IN DATABASE bantukasir_test SET bantukasir.role TO 'migration'"));
            $this->assertDenied($connection, fn (Connection $c) => $c->statement('ALTER ROLE CURRENT_USER IN DATABASE bantukasir_test RESET bantukasir.role'));
        }

        $this->assertSame('test', EnvironmentMarker::of($this->observer()));
    }

    public function test_pg_trgm_is_created_by_the_migration_role_and_its_members_belong_to_the_bootstrap_superuser(): void
    {
        $roles = DatabaseRoles::of($this->runtime());
        $extension = (array) $this->runtime()->selectOne(<<<'SQL'
            select e.extversion, pg_catalog.pg_get_userbyid(e.extowner) as owner, n.nspname as schema,
                   (select count(*) from pg_catalog.pg_depend d
                    join pg_catalog.pg_proc p on p.oid = d.objid
                    where d.classid = 'pg_catalog.pg_proc'::regclass and d.refobjid = e.oid and d.deptype = 'e'
                      and p.proowner = (select oid from pg_catalog.pg_roles where rolsuper and oid = 10)) as functions_of_the_bootstrap_superuser
            from pg_catalog.pg_extension e
            join pg_catalog.pg_namespace n on n.oid = e.extnamespace
            where e.extname = 'pg_trgm'
            SQL);

        $this->assertSame('1.6', $extension['extversion']);
        $this->assertSame($roles->migration, $extension['owner']);
        $this->assertSame('public', $extension['schema']);
        $this->assertGreaterThan(0, $extension['functions_of_the_bootstrap_superuser']);
        $this->assertSame(true, $this->runtime()->scalar("select public.similarity('pensil', 'pensil 2B') > 0.3"));
    }

    public function test_the_script_takes_every_name_and_password_at_run_time_and_writes_none(): void
    {
        $script = (string) preg_replace('/^--.*$/m', '', (string) file_get_contents(base_path('database/setup/setup.sql')));

        preg_match_all('/\bPASSWORD\s+([^;\s]+)/', $script, $passwords);
        $this->assertSame(
            ['NULL', ":'bk_migration_password'", 'NULL', ":'bk_runtime_password'", 'NULL', ":'bk_backup_password'"],
            $passwords[1],
        );

        preg_match_all('/^\\\\getenv\s+(\S+)\s+(\S+)$/m', $script, $inputs);
        $this->assertSame([
            'BANTUKASIR_SETUP_DATABASE', 'BANTUKASIR_SETUP_MARKER',
            'BANTUKASIR_SETUP_MIGRATION_ROLE', 'BANTUKASIR_SETUP_RUNTIME_ROLE', 'BANTUKASIR_SETUP_BACKUP_ROLE',
            'BANTUKASIR_SETUP_MIGRATION_PASSWORD', 'BANTUKASIR_SETUP_RUNTIME_PASSWORD', 'BANTUKASIR_SETUP_BACKUP_PASSWORD',
        ], $inputs[2]);

        // The roles and databases come from those variables only: not even the
        // local names of database/setup/local.sh are in the script, and it
        // writes no file.
        $this->assertDoesNotMatchRegularExpression('/\bbk_(?:migration|runtime|backup)\b|\bbantukasir_test\b/', $script);
        $this->assertStringNotContainsString('\\o', $script);
        $this->assertStringNotContainsString('\\copy', $script);
        $this->assertStringNotContainsString('\\w', $script);
    }

    public function test_every_session_of_the_scripts_searches_pg_catalog_only(): void
    {
        // The migration role owns the schema public; a superuser session that
        // searched it could run the migration role's functions or operators
        // (review finding R-01). The executed proof is selftest.sh's section 3.
        foreach (['database/setup/setup.sql', 'database/setup/state.sql'] as $path) {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", (string) file_get_contents(base_path($path)))),
                fn (string $line): bool => $line !== '' && ! str_starts_with($line, '--'),
            ));
            $statements = array_values(array_filter($lines, fn (string $line): bool => ! str_starts_with($line, '\\')));
            $connects = 0;

            $this->assertSame('SET search_path = pg_catalog, pg_temp;', $statements[0] ?? null, $path.': its first statement');

            foreach ($lines as $index => $line) {
                if (str_starts_with($line, '\\connect ')) {
                    $connects++;
                    $this->assertSame('SET search_path = pg_catalog, pg_temp;', $lines[$index + 1] ?? null, $path.': after '.$line);
                }
            }

            $this->assertGreaterThan(0, $connects, $path);
        }

        $this->assertStringContainsString("-c 'SET search_path = pg_catalog, pg_temp'", (string) file_get_contents(base_path('database/setup/selftest.sh')));
    }

    public function test_local_ci_and_the_tests_run_the_same_script_with_the_pinned_psql(): void
    {
        $compose = (string) file_get_contents(base_path('compose.yaml'));
        $local = (string) file_get_contents(base_path('database/setup/local.sh'));

        preg_match_all('/image: (postgres:18\.6-trixie@sha256:[0-9a-f]{64})/', $compose, $images);
        $this->assertCount(2, $images[1], 'db and db-setup');
        $this->assertSame($images[1][0], $images[1][1]);
        $this->assertStringContainsString('entrypoint: ["sh", "/setup/local.sh"]', $compose);
        $this->assertStringContainsString('- ./database/setup:/setup:ro', $compose);
        $this->assertStringContainsString("db-setup:\n                condition: service_completed_successfully", $compose);

        $this->assertStringContainsString('psql -X -v ON_ERROR_STOP=1 -d postgres -f /setup/setup.sql', $local);
        $this->assertStringContainsString("\nsetup bantukasir local\nsetup bantukasir_test test\n", $local);
        $this->assertStringContainsString("\nsh /setup/selftest.sh\n", $local);
    }
}
