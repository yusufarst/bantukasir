<?php

namespace Tests\Harness\Restore;

use App\Database\Identifier;
use App\Domain\Operations\Backup\BackupTools;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\Harness\Commands\Probe;
use Tests\Harness\TestDatabase;

/**
 * The restores of TEST_STRATEGY TS-24 (TASK-037 scope item 6; suites OPEN01, REC02, SESSION05): a test
 * dumps the test database as backup:run dumps it — the backup role, custom format, without owners or
 * privileges, with row security, without the data of the four tables of BACKUP_RECOVERY section 2 — goes
 * on changing it, then restores that dump over the test database and runs OP-04, so that the behaviour
 * after a restore is proven at L2 and L3 before the drills of L6.
 *
 * The restore is RB-01 step 9 on the test database, after the marker check of every connection (TS-07):
 * pg_restore as the migration role in one transaction, dropping and creating again every object of the
 * dump, the extension excepted, which the database keeps from its setup (ENVIRONMENTS ENV-33) and which
 * the migration role may not create; then the grants procedure, because the dump holds no privilege;
 * then the privilege test. The sessions of the harness end before and after it, so that no session holds
 * a lock or a plan of the tables it replaced. They are disconnected, never purged: each connection stays
 * the one object that the application's long-lived services hold — the password broker's token
 * repository, the session handler, the cache store — so that none of them reconnects later through the
 * database manager, which would end the session of a command's transaction in the middle of it.
 *
 * The test-only tables of the probe (tests/Harness/Commands/Probe.php) are no part of an installation:
 * the dump leaves them out, as the backup role reads none of them; the restore drops them first, because
 * their key on command_identities would keep pg_restore from dropping that table, and creates them again,
 * empty, after the privilege test, which holds the restored database to the matrix alone. Any other
 * test-only table is refused, because the restore would lose it.
 */
final class TestRestore
{
    private const string PG_DUMP = '/usr/lib/postgresql/18/bin/pg_dump';

    private const string PG_RESTORE = '/usr/lib/postgresql/18/bin/pg_restore';

    /**
     * Dumps the test database into a new file of this folder: its path.
     */
    public static function dump(string $folder): string
    {
        TestDatabase::requireTestDatabase();

        $file = rtrim($folder, '/').'/restore-'.bin2hex(random_bytes(6)).'.dump';
        $command = [
            self::PG_DUMP,
            ...self::target(TestDatabase::BACKUP),
            '--no-password',
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--enable-row-security',
            '--exclude-table=public.harness_*',
        ];

        foreach (BackupTools::EXCLUDED_TABLES as $table) {
            $command[] = '--exclude-table-data=public.'.$table;
        }

        $command[] = '--file='.$file;
        self::run($command, TestDatabase::BACKUP);

        return $file;
    }

    /**
     * Restores the dump over the test database, applies the grants and checks them.
     */
    public static function restore(string $dump): void
    {
        $harness = self::dropProbeTables();
        self::endSessions();

        $listing = Process::timeout(60)->run([self::PG_RESTORE, '--list', $dump])->throw()->output();
        $kept = array_filter(explode("\n", $listing), static fn (string $line): bool => preg_match('/ EXTENSION /', $line) !== 1);
        $list = $dump.'.list';
        file_put_contents($list, implode("\n", $kept)."\n");

        self::run([
            self::PG_RESTORE,
            ...self::target(TestDatabase::MIGRATION),
            '--no-password',
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--single-transaction',
            '--exit-on-error',
            '--use-list='.$list,
            $dump,
        ], TestDatabase::MIGRATION);

        self::endSessions();

        if (Artisan::call('db:apply-grants', ['--environment' => 'test']) !== 0) {
            throw new RuntimeException('The grants procedure failed after the restore: '.Artisan::output());
        }

        if (Artisan::call('db:check-privileges', ['--database' => TestDatabase::MIGRATION]) !== 0) {
            throw new RuntimeException('The privilege test failed after the restore: '.Artisan::output());
        }

        self::endSessions();

        if ($harness !== []) {
            Probe::ensureTables(DB::connection(TestDatabase::MIGRATION));
            self::endSessions();
        }

        TestDatabase::requireTestDatabase();
    }

    /**
     * Drops the probe's test-only tables before a restore, after the marker check, and returns their names;
     * refuses any other test-only table.
     *
     * @return list<string>
     */
    private static function dropProbeTables(): array
    {
        $migration = DB::connection(TestDatabase::MIGRATION);
        TestDatabase::requireTestDatabase($migration);
        $tables = [];

        foreach ($migration->select("select tablename from pg_catalog.pg_tables where schemaname = 'public' and pg_catalog.starts_with(tablename::text, 'harness_') order by tablename") as $row) {
            $name = ((array) $row)['tablename'] ?? null;

            if (is_string($name)) {
                $tables[] = $name;
            }
        }

        $other = array_diff($tables, Probe::TABLES);

        if ($other !== []) {
            throw new RuntimeException('A TS-24 restore keeps only the probe\'s test-only tables, and would lose '.implode(', ', $other).'.');
        }

        foreach ($tables as $table) {
            $migration->statement('DROP TABLE '.Identifier::quote($table));
        }

        return $tables;
    }

    /**
     * The connection options of a program for one connection of the harness.
     *
     * @return list<string>
     */
    private static function target(string $connection): array
    {
        $values = (array) config('database.connections.'.$connection);

        return [
            '--host='.self::text($values['host'] ?? null),
            '--port='.self::text($values['port'] ?? null),
            '--username='.self::text($values['username'] ?? null),
            '--dbname='.self::text($values['database'] ?? null),
        ];
    }

    /**
     * @param  list<string>  $command
     */
    private static function run(array $command, string $connection): void
    {
        $result = Process::timeout(120)->env(['PGPASSWORD' => self::text(config('database.connections.'.$connection.'.password'))])->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(basename($command[0]).' failed: '.$result->errorOutput());
        }
    }

    /**
     * Ends the sessions of the harness's three connections, keeping their objects, which reconnect at
     * their next statement; an observer of the test opened before the restore stays idle outside any
     * transaction, so it holds no lock, and the test opens a new one to read the restored data.
     */
    private static function endSessions(): void
    {
        foreach ([TestDatabase::RUNTIME, TestDatabase::MIGRATION, TestDatabase::BACKUP] as $name) {
            DB::disconnect($name);
        }
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
