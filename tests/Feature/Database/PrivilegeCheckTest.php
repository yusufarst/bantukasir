<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRefused;
use App\Database\DatabaseRoles;
use App\Database\EnvironmentMarker;
use App\Database\Grants;
use App\Database\Identifier;
use App\Database\PrivilegeCheck;
use App\Database\PrivilegeMatrix;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Tests\Harness\DatabaseTestCase;

/**
 * db:check-privileges and the grants procedure (AC-004-04; RELEASE_PROCESS
 * RB-21 step 2, BACKUP_RECOVERY RB-01 step 9): the check passes on the
 * migrated test database, as any of the three roles; it fails on an induced
 * extra grant and on a missing one, naming each; the grants procedure removes
 * both and is idempotent; it refuses a schema that the matrix does not
 * describe before any change. Every test leaves the grants as the matrix says.
 */
final class PrivilegeCheckTest extends DatabaseTestCase
{
    private DatabaseRoles $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles = DatabaseRoles::of($this->runtime());
    }

    protected function tearDown(): void
    {
        $this->migration()->statement('DROP TABLE IF EXISTS harness_unknown');
        (new Grants(PrivilegeMatrix::load()))->apply($this->migration());

        parent::tearDown();
    }

    public function test_the_check_passes_on_the_migrated_database_as_every_role(): void
    {
        $check = new PrivilegeCheck(PrivilegeMatrix::load());

        $this->assertSame([], $check->run($this->runtime()));
        $this->assertSame([], $check->run($this->backup()));
        $this->assertSame([], $check->run($this->migration()));

        $this->assertSame(0, Artisan::call('db:check-privileges'));
        $this->assertStringContainsString('Privilege check passed: database bantukasir_test', Artisan::output());
    }

    public function test_the_check_fails_on_an_extra_grant_and_names_it(): void
    {
        $this->migration()->statement('GRANT SELECT ON TABLE migrations TO '.Identifier::quote($this->roles->runtime));
        $this->migration()->statement('GRANT TRUNCATE ON TABLE cache TO '.Identifier::quote($this->roles->runtime));
        $this->migration()->statement('GRANT INSERT ON TABLE cache_locks TO PUBLIC');
        $this->migration()->statement('GRANT UPDATE (batch) ON TABLE migrations TO '.Identifier::quote($this->roles->backup));
        $this->migration()->statement('GRANT USAGE ON SEQUENCE migrations_id_seq TO '.Identifier::quote($this->roles->runtime));

        $this->assertSame([
            'columns of table migrations: '.$this->roles->backup.' holds UPDATE (batch), not in SCHEMA section 10',
            'sequence migrations_id_seq: '.$this->roles->runtime.' holds USAGE, not in SCHEMA section 10',
            'table cache: '.$this->roles->runtime.' holds TRUNCATE, not in SCHEMA section 10',
            'table cache_locks: PUBLIC holds INSERT, not in SCHEMA section 10',
            'table migrations: '.$this->roles->runtime.' holds SELECT, not in SCHEMA section 10',
        ], $this->sortedFindings());

        $this->assertSame(1, Artisan::call('db:check-privileges'));
        $output = Artisan::output();
        $this->assertStringContainsString('table cache: '.$this->roles->runtime.' holds TRUNCATE', $output);
        $this->assertStringContainsString('Privilege check failed: 5 difference(s)', $output);
    }

    public function test_the_check_fails_on_a_missing_grant_and_names_it(): void
    {
        $this->migration()->statement('REVOKE DELETE ON TABLE cache FROM '.Identifier::quote($this->roles->runtime));
        $this->migration()->statement('REVOKE SELECT ON TABLE migrations FROM '.Identifier::quote($this->roles->backup));
        $this->migration()->statement('REVOKE USAGE ON SCHEMA public FROM '.Identifier::quote($this->roles->backup));

        $this->assertSame([
            'schema public: '.$this->roles->backup.' lacks USAGE',
            'table cache: '.$this->roles->runtime.' lacks DELETE',
            'table migrations: '.$this->roles->backup.' lacks SELECT',
        ], $this->sortedFindings());
    }

    public function test_the_check_fails_on_a_relation_or_routine_the_schema_does_not_have(): void
    {
        $this->harnessTable('harness_unknown');
        $this->migration()->statement('CREATE TABLE harness_unknown (id bigint PRIMARY KEY)');
        $this->migration()->statement('CREATE VIEW harness_unknown_view AS SELECT 1 AS one');
        $this->migration()->statement('CREATE FUNCTION harness_unknown_function() RETURNS integer LANGUAGE sql IMMUTABLE AS $$ SELECT 1 $$');
        $this->migration()->statement('CREATE SEQUENCE harness_unknown_sequence');

        try {
            $this->assertSame([
                'function harness_unknown_function(): the schema has no function (SCH-06)',
                'relation harness_unknown_view: of kind v, which the schema does not use',
                'sequence harness_unknown_sequence: owned by no column and not in the privilege matrix',
                'table harness_unknown: not in the privilege matrix (database/privileges.php)',
            ], $this->sortedFindings());
        } finally {
            $this->migration()->statement('DROP VIEW harness_unknown_view');
            $this->migration()->statement('DROP FUNCTION harness_unknown_function()');
            $this->migration()->statement('DROP SEQUENCE harness_unknown_sequence');
        }
    }

    public function test_the_check_fails_on_row_security_that_differs_from_the_matrix(): void
    {
        $migration = $this->migration();

        try {
            $migration->statement('ALTER TABLE security_log_entries FORCE ROW LEVEL SECURITY');
            $migration->statement('DROP POLICY security_log_entries_retention ON security_log_entries');
            $migration->statement('CREATE POLICY security_log_entries_retention ON security_log_entries AS PERMISSIVE FOR DELETE USING (true)');
            $migration->statement('DROP POLICY security_log_entries_read ON security_log_entries');
            $migration->statement('CREATE POLICY security_log_entries_extra ON security_log_entries AS PERMISSIVE FOR UPDATE TO '.Identifier::quote($this->roles->runtime).' USING (true)');
            $migration->statement('ALTER TABLE cache ENABLE ROW LEVEL SECURITY');
            $migration->statement('CREATE POLICY harness_cache ON cache AS RESTRICTIVE FOR SELECT USING (true)');

            $this->assertSame([
                'policy harness_cache on cache: not in the privilege matrix (database/privileges.php)',
                'policy security_log_entries_extra on security_log_entries: not in the privilege matrix (database/privileges.php)',
                'policy security_log_entries_read on security_log_entries: in the privilege matrix but missing',
                'policy security_log_entries_retention on security_log_entries: using is "true", expected "(occurred_at < (now() - \'8760:00:00\'::interval))"',
                'role '.$this->roles->runtime.': is named in the policy policy security_log_entries_extra on table public.security_log_entries, outside SCHEMA section 10',
                'table cache: row security enabled, expected disabled',
                'table security_log_entries: row security forced, which the schema never does',
            ], $this->sortedFindings());
        } finally {
            $migration->statement('DROP POLICY IF EXISTS harness_cache ON cache');
            $migration->statement('ALTER TABLE cache DISABLE ROW LEVEL SECURITY');
            $migration->statement('DROP POLICY IF EXISTS security_log_entries_extra ON security_log_entries');
            $migration->statement('DROP POLICY IF EXISTS security_log_entries_retention ON security_log_entries');
            $migration->statement('DROP POLICY IF EXISTS security_log_entries_read ON security_log_entries');
            $migration->statement('CREATE POLICY security_log_entries_read ON security_log_entries AS PERMISSIVE FOR SELECT USING (true)');
            $migration->statement('CREATE POLICY security_log_entries_retention ON security_log_entries AS PERMISSIVE FOR DELETE USING (occurred_at < now() - interval \'8760 hours\')');
            $migration->statement('ALTER TABLE security_log_entries NO FORCE ROW LEVEL SECURITY');
        }

        $this->assertSame([], $this->sortedFindings());
    }

    public function test_the_check_and_the_grants_procedure_refuse_a_table_or_sequence_of_the_matrix_that_is_missing(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'bantukasir-matrix-');
        $matrix = require database_path('privileges.php');
        $matrix['tables']['harness_absent'] = ['class' => 'F', 'runtime' => ['select' => true, 'insert' => true]];
        $matrix['sequences']['harness_absent_seq'] = ['runtime' => ['usage' => true]];
        file_put_contents($file, '<?php return '.var_export($matrix, true).';');

        try {
            $this->assertSame([
                'sequence harness_absent_seq: in the privilege matrix but missing',
                'table harness_absent: in the privilege matrix but missing',
            ], $this->sortedFindings(PrivilegeMatrix::load($file)));

            $acls = $this->acls();

            try {
                (new Grants(PrivilegeMatrix::load($file)))->apply($this->migration());
                $this->fail('The grants procedure ran on a schema the matrix does not describe.');
            } catch (DatabaseRefused $refusal) {
                $this->assertStringContainsString('Not in the matrix: none. Missing: table harness_absent, sequence harness_absent_seq.', $refusal->getMessage());
            }

            $this->assertSame($acls, $this->acls());
        } finally {
            unlink($file);
        }
    }

    public function test_operators_of_the_migration_role_in_public_cannot_change_a_result(): void
    {
        // Review finding R-02: the migration role owns public, so it could put
        // there operators that a catalogue query without a pinned search_path
        // would prefer; these would fail any query that ran them.
        $this->migration()->statement(<<<'SQL'
            CREATE FUNCTION harness_hijack_name(name, name) RETURNS boolean
                LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'hijacked by the migration role'; END $$
            SQL);
        $this->migration()->statement('CREATE OPERATOR public.!~ (LEFTARG = name, RIGHTARG = name, FUNCTION = harness_hijack_name)');
        $this->migration()->statement(<<<'SQL'
            CREATE FUNCTION harness_hijack_oid(oid, regclass) RETURNS boolean
                LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'hijacked by the migration role'; END $$
            SQL);
        $this->migration()->statement('CREATE OPERATOR public.= (LEFTARG = oid, RIGHTARG = regclass, FUNCTION = harness_hijack_oid)');

        try {
            // A query that searches public does run them.
            $this->assertWriteRefused(fn (Connection $c) => $c->select("select nspname from pg_catalog.pg_namespace where nspname !~ '^pg_'"), 'P0001');

            $this->assertSame([
                'function harness_hijack_name(name, name): the schema has no function (SCH-06)',
                'function harness_hijack_oid(oid, regclass): the schema has no function (SCH-06)',
                'operator public.!~(name,name): the schema has no operator (SCH-06)',
                'operator public.=(oid,regclass): the schema has no operator (SCH-06)',
            ], $this->sortedFindings());
            $this->assertEquals($this->roles, DatabaseRoles::of($this->runtime()));
            $this->assertSame('test', EnvironmentMarker::of($this->runtime()));
            $this->assertNotSame([], (new Grants(PrivilegeMatrix::load()))->apply($this->migration(), true));
        } finally {
            $this->migration()->statement('DROP OPERATOR public.!~ (name, name)');
            $this->migration()->statement('DROP OPERATOR public.= (oid, regclass)');
            $this->migration()->statement('DROP FUNCTION harness_hijack_name(name, name)');
            $this->migration()->statement('DROP FUNCTION harness_hijack_oid(oid, regclass)');
        }
    }

    public function test_the_check_fails_on_a_right_of_the_runtime_role_outside_schema_section_10(): void
    {
        $this->migration()->statement('CREATE FUNCTION harness_granted() RETURNS integer LANGUAGE sql IMMUTABLE AS $$ SELECT 1 $$');
        $this->migration()->statement('REVOKE ALL ON FUNCTION harness_granted() FROM PUBLIC');
        $this->migration()->statement('GRANT EXECUTE ON FUNCTION harness_granted() TO '.Identifier::quote($this->roles->runtime));

        try {
            $this->assertSame([
                'function harness_granted(): the schema has no function (SCH-06)',
                'role '.$this->roles->runtime.': holds rights on function public.harness_granted(), outside SCHEMA section 10',
            ], $this->sortedFindings());
        } finally {
            $this->migration()->statement('DROP FUNCTION harness_granted()');
        }
    }

    public function test_the_check_fails_on_a_changed_role_setting(): void
    {
        $this->runtime()->statement('ALTER ROLE CURRENT_USER IN DATABASE bantukasir_test RESET lock_timeout');

        try {
            $this->assertSame([
                'settings of role '.$this->roles->runtime.' in this database: lock_timeout is null, expected "5s"',
            ], $this->sortedFindings());
        } finally {
            // The role may set its own default back (ENV-31); the setup would too.
            $this->runtime()->statement("ALTER ROLE CURRENT_USER IN DATABASE bantukasir_test SET lock_timeout TO '5s'");
        }

        $this->assertSame([], $this->sortedFindings());
    }

    public function test_the_grants_procedure_removes_the_differences_and_is_idempotent(): void
    {
        $this->migration()->statement('GRANT SELECT ON TABLE migrations TO '.Identifier::quote($this->roles->runtime));
        $this->migration()->statement('GRANT INSERT ON TABLE cache_locks TO PUBLIC');
        $this->migration()->statement('REVOKE DELETE ON TABLE cache FROM '.Identifier::quote($this->roles->runtime));
        $this->migration()->statement('GRANT UPDATE (batch) ON TABLE migrations TO '.Identifier::quote($this->roles->backup));
        $this->assertNotSame([], $this->sortedFindings());

        $this->assertSame(0, Artisan::call('db:apply-grants', ['--environment' => 'test']));
        $this->assertStringContainsString('Ran ', Artisan::output());
        $this->assertSame([], $this->sortedFindings());

        $acls = $this->acls();
        $statements = (new Grants(PrivilegeMatrix::load()))->apply($this->migration());

        $this->assertSame($acls, $this->acls());
        $this->assertSame([], $this->sortedFindings());
        $this->assertContains('REVOKE ALL ON TABLE public."cache" FROM PUBLIC, "'.$this->roles->runtime.'", "'.$this->roles->backup.'"', $statements);
        $this->assertContains('GRANT DELETE ON TABLE public."cache" TO "'.$this->roles->runtime.'"', $statements);
        $this->assertContains('GRANT SELECT ON SEQUENCE public."migrations_id_seq" TO "'.$this->roles->backup.'"', $statements);
    }

    public function test_pretend_prints_the_statements_and_changes_nothing(): void
    {
        $this->migration()->statement('GRANT SELECT ON TABLE migrations TO '.Identifier::quote($this->roles->runtime));

        $this->assertSame(0, Artisan::call('db:apply-grants', ['--environment' => 'test', '--pretend' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('REVOKE ALL ON TABLE public."migrations" FROM PUBLIC', $output);
        $this->assertStringContainsString('Would run', $output);
        $this->assertSame(['table migrations: '.$this->roles->runtime.' holds SELECT, not in SCHEMA section 10'], $this->sortedFindings());
    }

    public function test_the_grants_procedure_needs_the_typed_environment_of_the_database(): void
    {
        $this->migration()->statement('GRANT SELECT ON TABLE migrations TO '.Identifier::quote($this->roles->runtime));
        $acls = $this->acls();

        foreach ([[], ['--environment' => 'production'], ['--environment' => 'local']] as $options) {
            $this->assertSame(1, Artisan::call('db:apply-grants', $options));
            $this->assertStringContainsString('is not the environment "test" of this database (ENV-04)', Artisan::output());
        }

        config(['app.environment_name' => 'local']);
        $this->assertSame(1, Artisan::call('db:apply-grants', ['--environment' => 'local']));
        $this->assertStringContainsString('The database marker "test" differs from the environment name "local"', Artisan::output());
        config(['app.environment_name' => 'test']);

        $this->assertSame($acls, $this->acls());
    }

    public function test_the_grants_procedure_refuses_another_role_and_an_unknown_table_before_any_change(): void
    {
        $this->assertSame(1, Artisan::call('db:apply-grants', ['--environment' => 'test', '--database' => 'pgsql']));
        $this->assertStringContainsString('needs the migration role of this database', Artisan::output());

        $this->harnessTable('harness_unknown');
        $this->migration()->statement('CREATE TABLE harness_unknown (id bigint PRIMARY KEY)');
        $this->migration()->statement('GRANT SELECT ON TABLE migrations TO '.Identifier::quote($this->roles->runtime));
        $acls = $this->acls();

        try {
            (new Grants(PrivilegeMatrix::load()))->apply($this->migration());
            $this->fail('The grants procedure ran on a schema the matrix does not describe.');
        } catch (DatabaseRefused $refusal) {
            $this->assertStringContainsString('Not in the matrix: table harness_unknown. Missing: none.', $refusal->getMessage());
        }

        $this->assertSame($acls, $this->acls());
    }

    /**
     * @return list<string>
     */
    private function sortedFindings(?PrivilegeMatrix $matrix = null): array
    {
        $findings = (new PrivilegeCheck($matrix ?? PrivilegeMatrix::load()))->run($this->observer());
        sort($findings);

        return $findings;
    }

    /**
     * @return array<string, string>
     */
    private function acls(): array
    {
        $acls = [];

        foreach ($this->observer()->select(<<<'SQL'
            select c.relname, coalesce(c.relacl::text, '-') as acl
            from pg_catalog.pg_class c join pg_catalog.pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public'
            union all
            select 'schema public', coalesce(nspacl::text, '-') from pg_catalog.pg_namespace where nspname = 'public'
            order by 1
            SQL) as $row) {
            $values = (array) $row;
            $acls[(string) $values['relname']] = (string) $values['acl'];
        }

        return $acls;
    }
}
