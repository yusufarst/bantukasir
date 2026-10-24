<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRefused;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Tests\Harness\DatabaseTestCase;
use Throwable;

/**
 * The migration discipline (AC-004-02; EXECUTION_CONTEXT EXC-41, SCHEMA SCH-40,
 * SCH-50; App\Providers\DatabaseServiceProvider): a migration runs only as the
 * migration role, refused before its first statement otherwise; the
 * framework's destructive commands and the seeder are prohibited; every
 * migration names the migration connection and has no way down.
 */
final class MigrationDisciplineTest extends DatabaseTestCase
{
    private const string FIXTURES = 'tests/Fixtures/Migrations';

    public function test_the_runtime_role_cannot_run_or_even_read_the_migrations(): void
    {
        $before = $this->schemaState();

        $refusal = $this->failureOf(['--database' => 'pgsql', '--path' => self::FIXTURES.'/guard-default']);

        $this->assertInstanceOf(QueryException::class, $refusal);
        $this->assertSame('42501', (string) $refusal->getCode(), $refusal->getMessage());
        $this->assertStringContainsString('permission denied for table migrations', $refusal->getMessage());
        $this->assertSame($before, $this->schemaState());
    }

    public function test_another_role_that_can_read_the_migrations_is_refused_before_the_first_statement(): void
    {
        $this->expectRefused(['--database' => 'pgsql_backup', '--path' => self::FIXTURES.'/guard-default']);
    }

    public function test_a_migration_on_the_migration_connection_is_refused_when_its_record_is_written_by_another_role(): void
    {
        $this->expectRefused(['--database' => 'pgsql_backup', '--path' => self::FIXTURES.'/guard-migration-connection']);
    }

    public function test_the_destructive_commands_and_the_seeder_are_prohibited(): void
    {
        $this->runtime()->insert("insert into cache (key, value, expiration) values ('harness', 'kept', 1)");
        $before = $this->schemaState();

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed'] as $command) {
            $status = Artisan::call($command, ['--database' => 'pgsql_migration', '--force' => true]);

            $this->assertSame(1, $status, $command);
            $this->assertStringContainsString('prohibited', Artisan::output(), $command);
        }

        $this->assertSame($before, $this->schemaState());
        $this->assertSame('kept', $this->observer()->scalar("select value from cache where key = 'harness'"));
    }

    public function test_every_migration_names_the_migration_connection_and_has_no_way_down(): void
    {
        $files = glob(database_path('migrations/*.php')) ?: [];

        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $code = (string) file_get_contents($file);

            $this->assertStringContainsString("    protected \$connection = 'pgsql_migration';\n", $code, basename($file));
            $this->assertDoesNotMatchRegularExpression('/function\s+down\s*\(/', $code, basename($file));
        }
    }

    /**
     * @param  array<string, string>  $options
     */
    private function expectRefused(array $options): void
    {
        $before = $this->schemaState();

        $refusal = $this->failureOf($options);

        $this->assertInstanceOf(DatabaseRefused::class, $refusal);
        $this->assertStringContainsString('the operation needs the migration role of this database', $refusal->getMessage());
        $this->assertSame($before, $this->schemaState());
    }

    /**
     * What a run of migrate with these options threw; it must throw.
     *
     * @param  array<string, string>  $options
     */
    private function failureOf(array $options): Throwable
    {
        try {
            Artisan::call('migrate', [...$options, '--force' => true]);
        } catch (Throwable $failure) {
            return $failure;
        }

        $this->fail('A role other than the migration role ran the migrator.');
    }

    /**
     * The relations of the schema public and the migrations recorded, read by
     * an independent session.
     *
     * @return array{relations: list<mixed>, migrations: list<mixed>}
     */
    private function schemaState(): array
    {
        return [
            'relations' => array_column(array_map(fn (object $row): array => (array) $row, $this->observer()->select(
                "select c.relname from pg_catalog.pg_class c join pg_catalog.pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' order by c.relname"
            )), 'relname'),
            'migrations' => array_values($this->backup()->table('migrations')->orderBy('id')->pluck('migration')->all()),
        ];
    }
}
