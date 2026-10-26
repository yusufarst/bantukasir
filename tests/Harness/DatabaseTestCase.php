<?php

namespace Tests\Harness;

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

/**
 * The base of every test on the database (TEST_STRATEGY sections 3 to 5;
 * TASK-004). Before each test every connection of the harness is checked to
 * reach the test database, which is then migrated, once per process, and
 * reset (TestDatabase). The test's own writes go through the
 * runtime role, as the application's do (SCH-53); its assertions read through
 * an independent connection, a separate PostgreSQL session that sees only
 * what was committed (TS-03); nothing runs inside a wrapping transaction.
 *
 * It holds the helpers of the test families that every later table uses:
 * constraint, deferred, privilege, retention and numbering (TEST_STRATEGY
 * section 5, SCHEMA section 9.4).
 */
abstract class DatabaseTestCase extends TestCase
{
    /** @var list<string> */
    private array $testTables = [];

    /** @var list<string> */
    private array $observers = [];

    protected function setUp(): void
    {
        parent::setUp();

        TestDatabase::prepare();
        TestDatabase::reset();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->testTables) as $table) {
            $this->migration()->statement('DROP TABLE IF EXISTS '.Identifier::quote($table).' CASCADE');
        }

        // Every session the test opened ends with it: the framework leaves
        // them to the garbage collector, and the check of every connection
        // before each test opens the backup connection too.
        foreach ([...$this->observers, TestDatabase::RUNTIME, TestDatabase::MIGRATION, TestDatabase::BACKUP] as $connection) {
            DB::purge($connection);
        }

        $this->testTables = [];
        $this->observers = [];

        parent::tearDown();
    }

    protected function runtime(): Connection
    {
        return DB::connection(TestDatabase::RUNTIME);
    }

    protected function migration(): Connection
    {
        return DB::connection(TestDatabase::MIGRATION);
    }

    protected function backup(): Connection
    {
        return DB::connection(TestDatabase::BACKUP);
    }

    protected function roles(): DatabaseRoles
    {
        return DatabaseRoles::of($this->runtime());
    }

    /**
     * A new session of the runtime role, independent of every other
     * connection of the test, for reading what was committed; refused unless
     * it reaches the test database that the harness checked before the test
     * (TestDatabase::verify).
     */
    protected function observer(): Connection
    {
        $name = 'observer_'.count($this->observers);
        config(['database.connections.'.$name => config('database.connections.'.TestDatabase::RUNTIME)]);
        $this->observers[] = $name;

        $observer = DB::connection($name);
        TestDatabase::verify($observer, 'runtime', TestDatabase::verifiedIdentity());

        return $observer;
    }

    /**
     * A value read from the database that the test expects to be a text, such
     * as a column read with value().
     */
    protected function readText(mixed $value): string
    {
        $this->assertIsString($value);

        return $value;
    }

    /**
     * Registers a test-only table, which the test creates as the migration
     * role with constant SQL, to be dropped after the test. Its name starts
     * with harness_ (TestDatabase).
     */
    protected function harnessTable(string $name): string
    {
        $this->assertStringStartsWith('harness_', $name);
        $this->testTables[] = $name;

        return $name;
    }

    // Constraint family: a violating row, written directly by the runtime role,
    // is refused by the named constraint (SCHEMA section 9.4).

    /**
     * @param  Closure(Connection): mixed  $write
     */
    protected function assertWriteRefused(Closure $write, string $sqlstate, ?string $constraint = null, ?Connection $connection = null): QueryException
    {
        $connection ??= $this->runtime();
        $refusal = $this->refusal($connection, $write);

        $this->assertNotNull($refusal, sprintf('The write was accepted; expected SQLSTATE %s%s.', $sqlstate, $constraint === null ? '' : ' from '.$constraint));
        $this->assertSame($sqlstate, (string) $refusal->getCode(), $refusal->getMessage());

        if ($constraint !== null) {
            $this->assertStringContainsString('"'.$constraint.'"', $refusal->getMessage());
        }

        return $refusal;
    }

    // Privilege family: the statement is refused for lack of privilege.

    /**
     * @param  Closure(Connection): mixed  $statement
     */
    protected function assertDenied(Connection $connection, Closure $statement): void
    {
        $this->assertWriteRefused($statement, '42501', null, $connection);
    }

    // Deferred family: the transaction omits a required row; every statement
    // succeeds, the commit fails, and nothing is left.

    /**
     * @param  Closure(Connection): mixed  $work
     * @param  list<string>  $tables  the tables that must hold no row afterwards
     */
    protected function assertFailsAtCommit(Closure $work, string $constraint, array $tables): void
    {
        $committing = false;

        try {
            $this->runtime()->transaction(function (Connection $connection) use ($work, &$committing): void {
                $work($connection);
                $committing = true;
            });
            $this->fail(sprintf('The commit succeeded; expected the deferred key %s to refuse it.', $constraint));
        } catch (QueryException|PDOException $refusal) {
            // The framework's commit lets PDO's exception through unwrapped.
            $this->assertTrue($committing, 'A statement failed before the commit: '.$refusal->getMessage());
            $this->assertSame('23503', (string) $refusal->getCode(), $refusal->getMessage());
            $this->assertStringContainsString('"'.$constraint.'"', $refusal->getMessage());
        }

        $this->assertSame(0, $this->runtime()->transactionLevel());
        $this->assertNothingIn($tables);
    }

    /**
     * @param  list<string>  $tables
     */
    protected function assertNothingIn(array $tables): void
    {
        $observer = $this->observer();

        foreach ($tables as $table) {
            $this->assertSame(0, $observer->table($table)->count(), sprintf('The table %s holds a row.', $table));
        }
    }

    // Retention family: a path changes no retained row (SCHEMA section 9.4).

    /**
     * Every row of a table, in key order, as read by an independent session.
     *
     * @return list<array<string, mixed>>
     */
    protected function snapshot(string $table, string $key = 'id'): array
    {
        $rows = [];

        foreach ($this->observer()->table($table)->orderBy($key)->get() as $row) {
            $values = [];

            foreach ((array) $row as $column => $value) {
                $values[(string) $column] = $value;
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * Runs the path and asserts that it deleted and changed no row of the
     * retained tables; it may only add rows.
     *
     * @param  list<string>  $tables
     * @param  Closure(): mixed  $path
     */
    protected function assertRetained(array $tables, Closure $path, string $key = 'id'): void
    {
        $before = [];

        foreach ($tables as $table) {
            $before[$table] = $this->snapshot($table, $key);
        }

        $path();

        foreach ($tables as $table) {
            $after = $this->snapshot($table, $key);
            $kept = array_slice($after, 0, count($before[$table]));

            $this->assertSame($before[$table], $kept, sprintf('The path deleted or changed a retained row of %s.', $table));
        }
    }

    /**
     * Runs a cleanup and asserts that it removed exactly the rows that its
     * rule allows and changed no other.
     *
     * @param  Closure(array<string, mixed>): bool  $removable
     * @param  Closure(): mixed  $cleanup
     */
    protected function assertRemovedOnly(string $table, Closure $removable, Closure $cleanup, string $key = 'id'): void
    {
        $before = $this->snapshot($table, $key);
        $cleanup();
        $after = $this->snapshot($table, $key);

        $this->assertSame(array_values(array_filter($before, fn (array $row): bool => ! $removable($row))), $after);
    }

    // Constraint family: every check constraint of the schema is immutable.
    // PostgreSQL does not refuse a check that calls a stable or volatile
    // function (SCHEMA SCH-52), but it refuses such an expression in an index:
    // each check's expression is tried as an index, as the migration role, in
    // a transaction that is rolled back.

    /**
     * The checks of the schema public whose expressions are not immutable, as
     * "table.constraint".
     *
     * @return list<string>
     */
    protected function mutableChecks(): array
    {
        $migration = $this->migration();
        $migration->beginTransaction();

        try {
            $migration->statement(<<<'SQL'
                DO $$
                DECLARE
                    c record;
                    mutable text[] := '{}';
                BEGIN
                    FOR c IN
                        SELECT rel.relname, con.conname, pg_catalog.pg_get_expr(con.conbin, con.conrelid) AS expression
                        FROM pg_catalog.pg_constraint con
                        JOIN pg_catalog.pg_class rel ON rel.oid = con.conrelid
                        JOIN pg_catalog.pg_namespace n ON n.oid = rel.relnamespace
                        WHERE n.nspname = 'public' AND con.contype = 'c'
                        ORDER BY rel.relname, con.conname
                    LOOP
                        BEGIN
                            EXECUTE pg_catalog.format('CREATE INDEX harness_immutability_probe ON %I ((%s))', c.relname, c.expression);
                            EXECUTE 'DROP INDEX harness_immutability_probe';
                        EXCEPTION WHEN invalid_object_definition THEN
                            mutable := mutable || (c.relname || '.' || c.conname);
                        END;
                    END LOOP;
                    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'immutability: ' || pg_catalog.array_to_string(mutable, ',');
                END $$
                SQL);
        } catch (QueryException $result) {
            if (preg_match('/immutability: ([a-z0-9_.,]*)/', $result->getMessage(), $match) === 1) {
                return $match[1] === '' ? [] : explode(',', $match[1]);
            }

            throw $result;
        } finally {
            $migration->rollBack();
        }

        $this->fail('The immutability probe gave no result.');
    }

    // Numbering family: a document-number sequence is CACHE 1 and NO CYCLE,
    // and the runtime role may use it but never move it (SCH-20, SCH-27).

    protected function assertNumberSequence(string $sequence): void
    {
        $row = (array) $this->observer()->selectOne(
            'select data_type::text as data_type, start_value, increment_by, cache_size, cycle from pg_catalog.pg_sequences where schemaname = ? and sequencename = ?',
            ['public', $sequence],
        );

        $this->assertSame(['data_type' => 'bigint', 'start_value' => 1, 'increment_by' => 1, 'cache_size' => 1, 'cycle' => false], $row);
        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->select('select pg_catalog.setval(?, 1)', [$sequence]));
    }

    /**
     * @param  Closure(Connection): mixed  $write
     */
    private function refusal(Connection $connection, Closure $write): ?QueryException
    {
        $connection->beginTransaction();

        try {
            $write($connection);
        } catch (QueryException $refusal) {
            return $refusal;
        } finally {
            $connection->rollBack();
        }

        return null;
    }
}
