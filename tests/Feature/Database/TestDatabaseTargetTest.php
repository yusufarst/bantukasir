<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\TestDatabase;

/**
 * The target of every connection of the harness (TEST_STRATEGY TS-07,
 * ENVIRONMENTS ENV-04; the Owner's change request on TASK-004's pull request,
 * evidence section 10): before any write or reset, the migration, runtime and
 * backup connections, and every observer, must each be its own marked role on
 * a database whose marker and the configured environment name are "test", and
 * all of them the same database as the migration connection; otherwise the
 * harness refuses, reading only.
 *
 * The runtime connection is the only one that reads DB_URL
 * (config/database.php), and a URL overrides its host, port and database, so
 * a DB_URL in the environment could take the runtime connection, and every
 * observer copied from it, to another database while the migration
 * connection, which the reset checks, stays on the test database. phpunit.xml
 * leaves DB_URL empty; these tests set it in the process's environment and
 * read the configuration again, as a boot with that environment would.
 */
final class TestDatabaseTargetTest extends DatabaseTestCase
{
    private const string VARIABLE = 'DB_URL';

    /** @var array{server: ?string, env: ?string, getenv: string|false}|null */
    private ?array $saved = null;

    public function test_phpunit_leaves_db_url_empty_so_that_every_connection_reaches_the_same_database(): void
    {
        $this->assertSame('', getenv(self::VARIABLE));
        $this->assertEmpty(config('database.connections.'.TestDatabase::RUNTIME.'.url'));
        $this->assertArrayNotHasKey('url', (array) config('database.connections.'.TestDatabase::MIGRATION));
        $this->assertArrayNotHasKey('url', (array) config('database.connections.'.TestDatabase::BACKUP));

        $identity = TestDatabase::identity($this->migration());
        $this->assertSame($identity, TestDatabase::identity($this->runtime()));
        $this->assertSame($identity, TestDatabase::identity($this->backup()));
        $this->assertSame($identity, TestDatabase::identity($this->observer()));
        $this->assertSame($identity, TestDatabase::verifiedIdentity());
        $this->assertStringEndsWith('/bantukasir_test', $identity);
    }

    public function test_a_db_url_to_a_non_test_database_is_refused_before_any_write_or_reset(): void
    {
        $this->useDatabaseUrl('bantukasir');

        // What the URL does: the runtime connection reaches the local database
        // of the same cluster, while the migration and backup connections stay
        // on the test database, whose marker alone the reset used to check.
        $this->assertSame('bantukasir', DB::connection(TestDatabase::RUNTIME)->scalar('select pg_catalog.current_database()'));
        $this->assertSame('local', EnvironmentMarker::of(DB::connection(TestDatabase::RUNTIME)));
        $this->assertSame('test', EnvironmentMarker::require(DB::connection(TestDatabase::MIGRATION), ['test']));
        $this->assertSame('test', EnvironmentMarker::of(DB::connection(TestDatabase::BACKUP)));

        $queries = $this->recordQueries();
        $refused = 'The database marker "local" differs from the environment name "test"';

        $this->assertRefused(fn () => TestDatabase::reset(), $refused);
        $this->assertRefused(fn () => TestDatabase::prepare(), $refused);
        $this->assertRefused(fn () => TestDatabase::requireTestDatabase(), $refused);
        $this->assertRefused(fn () => $this->observer(), $refused);

        $this->assertReadsOnly($queries());
        $this->assertContains(TestDatabase::RUNTIME, array_column($queries(), 'connection'), 'The runtime connection was not checked.');
    }

    public function test_a_db_url_to_the_test_database_itself_is_accepted(): void
    {
        $this->useDatabaseUrl('bantukasir_test');

        TestDatabase::prepare();
        TestDatabase::reset();

        $this->assertSame('bantukasir_test', DB::connection(TestDatabase::RUNTIME)->scalar('select pg_catalog.current_database()'));
        $this->assertSame(TestDatabase::verifiedIdentity(), TestDatabase::identity($this->observer()));
    }

    public function test_each_connection_must_be_its_own_role_on_the_database_of_the_migration_connection(): void
    {
        $identity = TestDatabase::identity($this->migration());

        $this->assertSame($identity, TestDatabase::verify($this->runtime(), 'runtime', $identity));
        $this->assertSame($identity, TestDatabase::verify($this->backup(), 'backup', $identity));
        $this->assertSame($identity, TestDatabase::verify($this->migration(), 'migration'));

        $queries = $this->recordQueries();

        $this->assertRefused(fn () => TestDatabase::verify($this->runtime(), 'runtime', '1/1/bantukasir_test'), 'The runtime connection reaches the database '.$identity.', not the test database 1/1/bantukasir_test (TS-07).');
        $this->assertRefused(fn () => TestDatabase::verify($this->backup(), 'runtime', $identity), 'the operation needs the runtime role');
        $this->assertRefused(fn () => TestDatabase::verify($this->runtime(), 'backup', $identity), 'the operation needs the backup role');

        config(['app.environment_name' => 'local']);
        $this->assertRefused(fn () => TestDatabase::verify($this->runtime(), 'runtime', $identity), 'The database marker "test" differs from the environment name "local"');
        config(['app.environment_name' => 'test']);

        $this->assertReadsOnly($queries());
    }

    protected function tearDown(): void
    {
        if ($this->saved !== null) {
            $this->restore(self::VARIABLE, $this->saved);
            $this->saved = null;
            $this->reloadDatabaseConfiguration();
        }

        parent::tearDown();
    }

    /**
     * Sets DB_URL in the process's environment to the runtime role on the
     * named database of the test database's server, and reads the database
     * configuration again with it; tearDown restores both.
     */
    private function useDatabaseUrl(string $database): void
    {
        $runtime = (array) config('database.connections.'.TestDatabase::RUNTIME);
        $url = sprintf(
            'pgsql://%s@%s:%s/%s',
            rawurlencode(self::text($runtime['username'] ?? null)),
            self::text($runtime['host'] ?? null),
            self::text($runtime['port'] ?? null),
            $database,
        );

        $this->saved ??= [
            'server' => isset($_SERVER[self::VARIABLE]) && is_string($_SERVER[self::VARIABLE]) ? $_SERVER[self::VARIABLE] : null,
            'env' => isset($_ENV[self::VARIABLE]) && is_string($_ENV[self::VARIABLE]) ? $_ENV[self::VARIABLE] : null,
            'getenv' => getenv(self::VARIABLE),
        ];

        $_SERVER[self::VARIABLE] = $url;
        $_ENV[self::VARIABLE] = $url;
        putenv(self::VARIABLE.'='.$url);
        $this->reloadDatabaseConfiguration();

        $this->assertSame($url, config('database.connections.'.TestDatabase::RUNTIME.'.url'));
    }

    private function reloadDatabaseConfiguration(): void
    {
        config(['database' => require config_path('database.php')]);

        foreach ([TestDatabase::RUNTIME, TestDatabase::MIGRATION, TestDatabase::BACKUP] as $connection) {
            DB::purge($connection);
        }
    }

    /**
     * @param  array{server: ?string, env: ?string, getenv: string|false}  $saved
     */
    private function restore(string $name, array $saved): void
    {
        if ($saved['server'] === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $saved['server'];
        }

        if ($saved['env'] === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $saved['env'];
        }

        putenv($saved['getenv'] === false ? $name : $name.'='.$saved['getenv']);
    }

    /**
     * Records every statement that any connection runs from now on.
     *
     * @return Closure(): list<array{connection: string, sql: string}>
     */
    private function recordQueries(): Closure
    {
        $queries = [];

        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$queries): void {
            $queries[] = ['connection' => $query->connectionName, 'sql' => $query->sql];
        });

        return function () use (&$queries): array {
            return $queries;
        };
    }

    /**
     * @param  list<array{connection: string, sql: string}>  $queries
     */
    private function assertReadsOnly(array $queries): void
    {
        $this->assertNotEmpty($queries);

        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $query['sql'], sprintf('The %s connection ran a statement that is not a read.', $query['connection']));
        }
    }

    private function assertRefused(Closure $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('The operation ran: '.$message);
        } catch (DatabaseRefused $refusal) {
            $this->assertStringContainsString($message, $refusal->getMessage());
        }
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : self::fail('The configuration holds '.get_debug_type($value));
    }
}
