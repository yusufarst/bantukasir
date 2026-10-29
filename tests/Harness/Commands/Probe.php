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
 * harness only, registered in the testing environment and, for the browser
 * suite, in the local environment (TASK-009 scope item 8), that goes through
 * the whole pipeline — its route and middleware, the request receipt, the
 * FormRequest base, the Action base — and writes two facts and an audit
 * event on test-only tables. Every real command proves the same again for
 * itself.
 *
 * Its family is that of the Owner's review note (INTERACTION_CONTRACTS
 * section 1.4), a family with a label and a panel text in COPY_DECK section
 * 14.1 whose only command, `review_note.add`, no Task has built yet: so the
 * command client's panels, its block of NC-13 and the rows of UXP-006 show
 * the probe with real words (TASK-009 evidence, the readings), and no real
 * command is blocked by a probe's unresolved request.
 */
final class Probe
{
    public const string OPERATION = 'testing.probe';

    public const string FAMILY = 'review_note';

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
        self::filled($migration);

        return self::TABLES;
    }

    /**
     * The probe's tables on the local database of the browser suite, created
     * once and kept, so that each browser test finds them (TASK-009 scope
     * item 8; tests/Harness/Browser/FixturesController.php).
     */
    public static function ensureTables(Connection $migration): void
    {
        $migration->statement('CREATE TABLE IF NOT EXISTS harness_probe_counters (id bigint PRIMARY KEY, value bigint NOT NULL)');
        $migration->statement('CREATE TABLE IF NOT EXISTS harness_probe_facts (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, command_id bigint NOT NULL REFERENCES command_identities (id), note text NOT NULL)');
        self::filled($migration);
    }

    /** The counter at zero once, and the runtime role's grants. */
    private static function filled(Connection $migration): void
    {
        $migration->statement('INSERT INTO harness_probe_counters (id, value) VALUES (1, 0) ON CONFLICT (id) DO NOTHING');
        $migration->statement('GRANT SELECT, INSERT ON TABLE harness_probe_facts TO '.Identifier::quote(DatabaseRoles::of($migration)->runtime));
        $migration->statement('GRANT SELECT, UPDATE (value) ON TABLE harness_probe_counters TO '.Identifier::quote(DatabaseRoles::of($migration)->runtime));
    }
}
