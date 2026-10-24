<?php

namespace Tests\Harness\Probe;

use App\Database\CommandPoints;
use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Harness\Race\Scenario;

/**
 * A test-only command on test-only tables, for the self-tests of the harness
 * (TASK-004, AC-004-05). It has the shape of every command of TECHNICAL_RULES
 * section 7.1, in one transaction of the runtime role: the identity insert
 * (step 1), the update lock on a counter row (step 6), then two write steps —
 * the counter's increment and a log row — and the commit, calling each
 * CommandPoints point on the way.
 */
final class ProbeCommand implements Scenario
{
    public const array TABLES = ['harness_log', 'harness_counters', 'harness_identities'];

    public function __construct(private readonly CommandPoints $points) {}

    /**
     * Creates the probe's tables as the migration role, with one counter row
     * at zero, and grants the runtime role what the command needs.
     *
     * @return list<string> the tables, to drop after the test
     */
    public static function createTables(Connection $migration): array
    {
        $runtime = DatabaseRoles::of($migration)->runtime;

        $migration->statement('CREATE TABLE harness_identities (key text PRIMARY KEY)');
        $migration->statement('CREATE TABLE harness_counters (id bigint PRIMARY KEY, value bigint NOT NULL)');
        $migration->statement('CREATE TABLE harness_log (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, key text NOT NULL, value bigint NOT NULL)');
        $migration->statement('INSERT INTO harness_counters (id, value) VALUES (1, 0)');
        $migration->statement('GRANT SELECT, INSERT ON TABLE harness_identities, harness_log TO '.Identifier::quote($runtime));
        $migration->statement('GRANT SELECT, UPDATE (value) ON TABLE harness_counters TO '.Identifier::quote($runtime));

        return self::TABLES;
    }

    /**
     * @param  array<string, mixed>  $arguments  key: the identity; counter: the counter row
     * @return array{key: string, value: int}
     */
    public function run(array $arguments): array
    {
        $key = is_string($arguments['key'] ?? null) ? $arguments['key'] : 'probe';
        $counter = is_int($arguments['counter'] ?? null) ? $arguments['counter'] : 1;

        return DB::connection()->transaction(function (Connection $connection) use ($key, $counter): array {
            $connection->insert('insert into harness_identities (key) values (?)', [$key]);
            $this->points->afterIdentity();

            $locked = $connection->table('harness_counters')->where('id', $counter)->lock('for no key update')->value('value');
            $this->points->afterLock('6');

            $value = (is_int($locked) ? $locked : 0) + 1;
            $connection->update('update harness_counters set value = value + 1 where id = ?', [$counter]);
            $this->points->afterWrite(1);

            $connection->insert('insert into harness_log (key, value) values (?, ?)', [$key, $value]);
            $this->points->afterWrite(2);

            $this->points->beforeCommit();

            return ['key' => $key, 'value' => $value];
        });
    }
}
