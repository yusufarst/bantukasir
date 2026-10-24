<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRoles;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;

/**
 * The privilege family for the tables of TASK-004 and for the schema itself
 * (AC-004-02; SCHEMA sections 9.4 and 10, SCH-40 to SCH-43; TEST_STRATEGY
 * section 5): the runtime role is refused DDL, TRUNCATE, temporary tables and
 * every privilege on migrations; the backup role only reads; the migration
 * role owns everything but can change neither the database nor its marker.
 * Each refusal is the database's own (SQLSTATE 42501).
 */
final class PrivilegeFamilyTest extends DatabaseTestCase
{
    public function test_the_runtime_role_is_refused_every_kind_of_ddl(): void
    {
        $runtime = $this->runtime();

        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE TABLE harness_runtime_table (id bigint)'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE SEQUENCE harness_runtime_sequence'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE VIEW harness_runtime_view AS SELECT 1 AS one'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE FUNCTION harness_runtime_function() RETURNS integer LANGUAGE sql AS $$ SELECT 1 $$'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE SCHEMA harness_runtime_schema'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE TEMPORARY TABLE harness_runtime_temporary (id bigint)'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE EXTENSION IF NOT EXISTS citext'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('ALTER TABLE cache ADD COLUMN harness_extra text'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('ALTER TABLE cache OWNER TO CURRENT_USER'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('CREATE INDEX harness_runtime_index ON cache (value)'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('COMMENT ON TABLE cache IS \'changed\''));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('DROP TABLE cache'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->statement('DROP TABLE migrations'));
    }

    public function test_the_runtime_role_is_refused_truncate_everywhere(): void
    {
        foreach (['cache', 'cache_locks', 'migrations'] as $table) {
            $this->assertDenied($this->runtime(), fn (Connection $c) => $c->table($table)->truncate());
        }
    }

    public function test_the_runtime_role_holds_nothing_on_migrations_and_its_sequence(): void
    {
        $runtime = $this->runtime();

        $this->assertDenied($runtime, fn (Connection $c) => $c->select('select * from migrations'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->insert("insert into migrations (migration, batch) values ('harness', 1)"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->update('update migrations set batch = batch'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->delete('delete from migrations'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->select("select pg_catalog.nextval('migrations_id_seq')"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->select("select pg_catalog.setval('migrations_id_seq', 1)"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->select('select last_value from migrations_id_seq'));
    }

    public function test_the_runtime_role_holds_exactly_select_insert_update_and_delete_on_the_cache_tables(): void
    {
        $roles = DatabaseRoles::of($this->runtime());

        foreach (['cache', 'cache_locks', 'migrations'] as $table) {
            $held = [];

            foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER', 'MAINTAIN'] as $privilege) {
                if ($this->runtime()->scalar('select pg_catalog.has_table_privilege(?, ?, ?)', [$roles->runtime, $table, $privilege]) === true) {
                    $held[] = $privilege;
                }
            }

            $this->assertSame($table === 'migrations' ? [] : ['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $held, $table);
        }

        $this->runtime()->insert("insert into cache (key, value, expiration) values ('harness', 'a', 1)");
        $this->runtime()->update("update cache set value = 'b' where key = 'harness'");
        $this->assertSame('b', $this->observer()->scalar("select value from cache where key = 'harness'"));
        $this->runtime()->delete("delete from cache where key = 'harness'");
        $this->assertSame(0, $this->observer()->table('cache')->count());
    }

    public function test_a_grant_by_the_runtime_role_grants_nothing(): void
    {
        $roles = DatabaseRoles::of($this->runtime());

        $this->runtime()->statement('GRANT SELECT ON TABLE cache TO PUBLIC');

        $this->assertFalse($this->runtime()->scalar("select pg_catalog.has_table_privilege('public', 'cache', 'SELECT')"));
        $this->assertFalse($this->runtime()->scalar('select pg_catalog.has_table_privilege(?, ?, ?)', [$roles->backup, 'cache', 'INSERT']));
    }

    public function test_the_backup_role_only_reads(): void
    {
        $backup = $this->backup();

        $this->runtime()->insert("insert into cache (key, value, expiration) values ('harness', 'a', 1)");

        foreach (['cache', 'cache_locks', 'migrations'] as $table) {
            $this->assertSame($this->migration()->table($table)->count(), $backup->table($table)->count(), $table);
            $this->assertDenied($backup, fn (Connection $c) => $c->table($table)->truncate());
        }

        $this->assertIsInt($backup->scalar('select last_value from migrations_id_seq'));
        $this->assertDenied($backup, fn (Connection $c) => $c->insert("insert into cache (key, value, expiration) values ('harness', 'a', 1)"));
        $this->assertDenied($backup, fn (Connection $c) => $c->update('update cache set value = value'));
        $this->assertDenied($backup, fn (Connection $c) => $c->delete('delete from cache'));
        $this->assertDenied($backup, fn (Connection $c) => $c->insert("insert into migrations (migration, batch) values ('harness', 1)"));
        $this->assertDenied($backup, fn (Connection $c) => $c->select("select pg_catalog.nextval('migrations_id_seq')"));
        $this->assertDenied($backup, fn (Connection $c) => $c->statement('CREATE TABLE harness_backup_table (id bigint)'));
        $this->assertDenied($backup, fn (Connection $c) => $c->statement('CREATE TEMPORARY TABLE harness_backup_temporary (id bigint)'));
    }

    public function test_the_migration_role_owns_the_schema_but_not_the_database(): void
    {
        $roles = DatabaseRoles::of($this->runtime());
        $owners = (array) $this->runtime()->selectOne(<<<'SQL'
            select (select pg_catalog.pg_get_userbyid(nspowner) from pg_catalog.pg_namespace where nspname = 'public') as schema,
                   (select pg_catalog.pg_get_userbyid(relowner) from pg_catalog.pg_class where oid = 'public.migrations'::regclass) as migrations,
                   (select pg_catalog.pg_get_userbyid(relowner) from pg_catalog.pg_class where oid = 'public.cache'::regclass) as cache,
                   (select pg_catalog.pg_get_userbyid(datdba) from pg_catalog.pg_database where datname = pg_catalog.current_database()) as database
            SQL);

        $this->assertSame($roles->migration, $owners['schema']);
        $this->assertSame($roles->migration, $owners['migrations']);
        $this->assertSame($roles->migration, $owners['cache']);
        $this->assertNotContains($owners['database'], [$roles->migration, $roles->runtime, $roles->backup]);

        $migration = $this->migration();
        $this->assertDenied($migration, fn (Connection $c) => $c->statement('CREATE SCHEMA harness_migration_schema'));
        $this->assertDenied($migration, fn (Connection $c) => $c->statement('CREATE TEMPORARY TABLE harness_migration_temporary (id bigint)'));
        $this->assertDenied($migration, fn (Connection $c) => $c->statement('ALTER DATABASE bantukasir_test SET lock_timeout TO 0'));
        $this->assertDenied($migration, fn (Connection $c) => $c->statement('CREATE EXTENSION IF NOT EXISTS citext'));
    }

    public function test_public_holds_nothing_on_the_schema(): void
    {
        $this->assertSame(
            ['usage' => false, 'create' => false],
            (array) $this->runtime()->selectOne("select pg_catalog.has_schema_privilege('public', 'public', 'USAGE') as usage, pg_catalog.has_schema_privilege('public', 'public', 'CREATE') as create"),
        );
    }
}
