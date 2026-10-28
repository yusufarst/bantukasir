<?php

namespace Tests\Harness\Commands;

use App\Actions\Commands\CommandActions;
use App\Database\DatabaseRoles;
use App\Database\Identifier;
use App\Domain\Commands\Operations;
use Illuminate\Database\Connection;

/**
 * The probe operation of the command platform (TASK-008 scope item 9;
 * EXECUTION_CONTEXT section 10): `testing.probe`, a command of the test
 * harness only, registered in the testing environment, that goes through the
 * whole pipeline — its route and middleware, the request receipt, the
 * FormRequest base, the Action base — and writes two facts and an audit
 * event on test-only tables. Every real command proves the same again for
 * itself.
 */
final class Probe
{
    public const string OPERATION = 'testing.probe';

    public const string FAMILY = 'testing';

    public const string PATH = '/commands/testing.probe';

    /** @var list<string> */
    public const array TABLES = ['harness_probe_facts', 'harness_probe_counters'];

    public static function register(): void
    {
        Operations::allowForTesting(self::OPERATION, self::FAMILY);
        CommandActions::allowForTesting(self::OPERATION, ProbeAction::class);
    }

    /**
     * Creates the probe's tables as the migration role, with its counter at
     * zero, and grants the runtime role what the probe needs.
     *
     * @return list<string> the tables, to drop after the test
     */
    public static function createTables(Connection $migration): array
    {
        $migration->statement('CREATE TABLE harness_probe_counters (id bigint PRIMARY KEY, value bigint NOT NULL)');
        $migration->statement('CREATE TABLE harness_probe_facts (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, command_id bigint NOT NULL REFERENCES command_identities (id), note text NOT NULL)');
        $migration->statement('INSERT INTO harness_probe_counters (id, value) VALUES (1, 0)');
        $migration->statement('GRANT SELECT, INSERT ON TABLE harness_probe_facts TO '.Identifier::quote(DatabaseRoles::of($migration)->runtime));
        $migration->statement('GRANT SELECT, UPDATE (value) ON TABLE harness_probe_counters TO '.Identifier::quote(DatabaseRoles::of($migration)->runtime));

        return self::TABLES;
    }
}
