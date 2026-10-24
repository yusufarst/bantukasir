<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\TestDatabase;

/**
 * The reset of the test database and the guard of the fixture builder
 * (AC-004-06; TEST_STRATEGY TS-07, ENVIRONMENTS ENV-04): a reset runs only as
 * the migration role and only where both the database's marker and the
 * configured environment name are "test", and refuses before any statement
 * otherwise — also on the local database of the same cluster, whose marker is
 * "local". The fixture builder of "Toko Contoh" runs only where both are test
 * or local.
 */
final class TestDatabaseResetTest extends DatabaseTestCase
{
    public function test_a_reset_empties_the_tables_keeps_the_migrations_and_drops_harness_relations(): void
    {
        $this->runtime()->insert("insert into cache (key, value, expiration) values ('harness', 'a', 1)");
        $this->migration()->statement('CREATE TABLE harness_left_behind (id bigint PRIMARY KEY)');
        $this->migration()->statement('CREATE SEQUENCE harness_left_behind_seq');
        $this->migration()->statement('CREATE VIEW harness_left_behind_view AS SELECT 1 AS one');
        $migrations = $this->backup()->table('migrations')->pluck('migration')->all();

        TestDatabase::reset();

        $this->assertSame(0, $this->observer()->table('cache')->count());
        $this->assertSame($migrations, $this->backup()->table('migrations')->pluck('migration')->all());
        $this->assertSame(0, $this->observer()->scalar("select count(*) from pg_catalog.pg_class where relname like 'harness\\_left\\_behind%'"));
    }

    public function test_a_reset_is_refused_on_the_local_database_before_any_statement(): void
    {
        $local = $this->localMigrationConnection();
        $this->assertSame('local', EnvironmentMarker::of($local));

        $local->enableQueryLog();

        $this->assertRefused(fn () => TestDatabase::reset($local), 'The database marker "local" differs from the environment name "test"');

        foreach ($local->getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $query['query']);
        }
    }

    public function test_a_reset_is_refused_when_the_configured_environment_is_not_test(): void
    {
        config(['app.environment_name' => 'local']);
        $this->assertRefused(fn () => TestDatabase::reset(), 'The database marker "test" differs from the environment name "local"');

        config(['app.environment_name' => null]);
        $this->assertRefused(fn () => TestDatabase::reset(), 'No environment name is configured');
    }

    public function test_a_reset_is_refused_to_any_role_but_the_migration_role(): void
    {
        $this->assertRefused(fn () => TestDatabase::reset($this->runtime()), 'the operation needs the migration role');
        $this->assertRefused(fn () => TestDatabase::reset($this->backup()), 'the operation needs the migration role');
    }

    public function test_the_fixture_builder_runs_only_on_a_test_or_local_database(): void
    {
        $this->assertSame('test', TokoContoh::build($this->runtime()));

        config(['app.environment_name' => 'local']);
        $this->assertSame('local', TokoContoh::build($this->localMigrationConnection()));
        $this->assertRefused(fn () => TokoContoh::build($this->runtime()), 'differs from the environment name "local"');

        config(['app.environment_name' => 'production']);
        $this->assertRefused(fn () => TokoContoh::build($this->runtime()), 'differs from the environment name "production"');

        config(['app.environment_name' => 'test']);
        $this->assertRefused(fn () => EnvironmentMarker::require($this->runtime(), ['local']), 'The database marker "test" is not one of local');

        $this->assertSame(['Hendra' => ['SUPER_ADMIN'], 'Agus' => ['OPERATIONS_ADMIN'], 'Rina' => ['CASHIER'], 'Dewi' => ['CASHIER', 'OPERATIONS_ADMIN']], TokoContoh::PEOPLE);
    }

    private function localMigrationConnection(): Connection
    {
        config(['database.connections.harness_local' => ['database' => 'bantukasir'] + (array) config('database.connections.'.TestDatabase::MIGRATION)]);

        return DB::connection('harness_local');
    }

    private function assertRefused(\Closure $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('The operation ran: '.$message);
        } catch (DatabaseRefused $refusal) {
            $this->assertStringContainsString($message, $refusal->getMessage());
        }
    }

    protected function tearDown(): void
    {
        DB::purge('harness_local');

        parent::tearDown();
    }
}
