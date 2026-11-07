<?php

namespace Tests\Feature\Cash;

use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The tables and the sequence of TASK-027 (AC-027-01, AC-027-04, AC-027-05; SCHEMA sections 3.5, 3.7,
 * 5.10, 6, 9.4, 10 and 11; SCH-13, SCH-14, SCH-20 to SCH-27; BR-CASH-04, BR-CASH-06; the migration
 * 2026_11_07_000002_create_cash_tables; EXECUTION_CONTEXT section 10, a table, and EXC-44; suites
 * DB01, KEEP01, SESSION02, SESSION03 and CONFIG01; TASK-027 section 7, L2): the constraint family,
 * written directly by the runtime role — the register's lengths and its code unique without regard
 * to letter case, the session's number exactly its business date and counter, the opening cash,
 * the status and the activity version, the unique and composite targets and the two partial unique
 * indexes of BR-CASH-06; the presence key computed by the database; the key of audit_events; the
 * privilege family, the sequence included; the retention family; and the indexes of section 11.
 * The deferred key of close_presence is TASK-030's, which creates cash_session_closes.
 */
final class CashTablesTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $command = 0;

    private int $register = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
        $this->register = TokoContoh::register();
        $id = $this->observer()->table('command_identities')->max('id');
        $this->command = is_int($id) ? $id : 0;
    }

    public function test_each_check_and_key_of_a_register_refuses_its_violation(): void
    {
        foreach ([
            'cash_registers_code_length' => [['code' => ''], ['code' => str_repeat('k', 33)]],
            'cash_registers_label_length' => [['label' => ''], ['label' => str_repeat('l', 101)]],
            'cash_registers_version_positive' => [['version' => 0]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertRegister($c, $values), '23514', $constraint);
            }
        }

        foreach (['code', 'label', 'active', 'version', 'created_at', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRegister($c, [$column => null]), '23502');
        }

        // SCH-14: the code is unique without regard to letter case, non-ASCII letters included; the
        // label is not unique.
        foreach (['laci-1', 'LACI-1', 'Laci-1'] as $code) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRegister($c, ['code' => $code]), '23505', 'cash_registers_code_ci_uniq');
        }

        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertRegister($c, ['code' => 'KASSE-Ä']);
            $this->insertRegister($c, ['code' => 'kasse-ä']);
        }, '23505', 'cash_registers_code_ci_uniq');

        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertRegister($c, ['code' => 'LACI-2', 'label' => 'Laci 1']);
        });
        $this->assertSame(2, $this->observer()->table('cash_registers')->where('label', 'Laci 1')->count());
    }

    public function test_each_check_and_key_of_a_session_refuses_its_violation(): void
    {
        foreach ([
            // SCH-22: the format, then exactly the business date and the counter, padded to six digits.
            'cash_sessions_number_format' => [['session_number' => '2026117-000001'], ['session_number' => '20261107-00001'], ['session_number' => '20261107000001']],
            'cash_sessions_number_matches' => [
                ['session_number' => '20261108-000001'],
                ['session_number' => '20261107-000002'],
                ['session_number' => '20261107-0000001'],
                ['session_number_seq' => 1234567, 'session_number' => '20261107-234567'],
            ],
            'cash_sessions_number_seq_positive' => [['session_number_seq' => 0, 'session_number' => '20261107-000000']],
            'cash_sessions_responsible_display_name_length' => [['responsible_display_name' => ''], ['responsible_display_name' => str_repeat('n', 101)]],
            // BR-CASH-04: zero or more, within the money limit.
            'cash_sessions_opening_cash_range' => [['opening_cash' => -1], ['opening_cash' => 1000000000000]],
            'cash_sessions_status_allowed' => [['status' => 'open'], ['status' => 'PAUSED']],
            'cash_sessions_activity_version_range' => [['activity_version' => -1]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertSession($c, $values), '23514', $constraint);
            }
        }

        foreach (['register_id' => 'cash_sessions_register_fk', 'responsible_user_id' => 'cash_sessions_responsible_user_fk', 'open_command_id' => 'cash_sessions_open_command_fk'] as $column => $key) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertSession($c, [$column => 999999]), '23503', $key);
        }

        foreach (['session_number', 'session_number_seq', 'opened_business_date', 'register_id', 'responsible_user_id', 'responsible_display_name', 'opening_cash', 'opened_at', 'open_command_id', 'status', 'activity_version', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertSession($c, [$column => null]), '23502');
        }

        // SCH-24: a counter is used once, whatever the date; a number is its date and its counter, so
        // a second one is refused by both; the opening command is unique.
        $commands = $this->commands(2);

        $this->assertWriteRefused(function (Connection $c) use ($commands): void {
            $this->insertSession($c, ['open_command_id' => $commands[0]]);
            $this->insertSession($c, ['opened_business_date' => '2026-11-08', 'session_number' => '20261108-000001', 'status' => 'CLOSED', 'open_command_id' => $commands[1]]);
        }, '23505', 'cash_sessions_number_seq_unique');

        $this->assertWriteRefused(function (Connection $c) use ($commands): void {
            $this->insertSession($c, ['open_command_id' => $commands[0]]);
            $this->insertSession($c, ['status' => 'CLOSED', 'open_command_id' => $commands[1]]);
        }, '23505');

        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertSession($c);
            $this->insertSession($c, ['session_number_seq' => 4, 'session_number' => '20261107-000004', 'status' => 'CLOSED']);
        }, '23505', 'cash_sessions_open_command_unique');

        // A counter past six digits takes more digits, never fewer (SCH-22). Committed last, so that
        // its opening command and its OPEN state do not answer the refusals above first.
        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertSession($c, ['session_number_seq' => 1234567, 'session_number' => '20261107-1234567']);
        });
        $this->assertSame(1, $this->observer()->table('cash_sessions')->where('session_number', '20261107-1234567')->count());
    }

    public function test_one_open_session_per_register_and_per_user_and_any_number_closed(): void
    {
        // BR-CASH-06; RACE-07's database guarantee: a second OPEN session of the register, or of the
        // user on another register, is refused; CLOSED sessions are not limited.
        $second = $this->secondRegister();
        $rina = $this->cashier();
        $commands = $this->commands(4);

        $this->assertWriteRefused(function (Connection $c) use ($commands, $rina): void {
            $this->insertSession($c, ['open_command_id' => $commands[0]]);
            $this->insertSession($c, ['session_number_seq' => 2, 'session_number' => '20261107-000002', 'responsible_user_id' => $rina, 'responsible_display_name' => 'Rina', 'open_command_id' => $commands[1]]);
        }, '23505', 'cash_sessions_one_open_per_register');

        $this->assertWriteRefused(function (Connection $c) use ($second, $commands): void {
            $this->insertSession($c, ['open_command_id' => $commands[0]]);
            $this->insertSession($c, ['session_number_seq' => 2, 'session_number' => '20261107-000002', 'register_id' => $second, 'open_command_id' => $commands[1]]);
        }, '23505', 'cash_sessions_one_open_per_user');

        $this->runtime()->transaction(function (Connection $c) use ($second, $commands, $rina): void {
            $this->insertSession($c, ['status' => 'CLOSED', 'open_command_id' => $commands[0]]);
            $this->insertSession($c, ['session_number_seq' => 2, 'session_number' => '20261107-000002', 'status' => 'CLOSED', 'open_command_id' => $commands[1]]);
            $this->insertSession($c, ['session_number_seq' => 3, 'session_number' => '20261107-000003', 'open_command_id' => $commands[2]]);
            $this->insertSession($c, ['session_number_seq' => 4, 'session_number' => '20261107-000004', 'register_id' => $second, 'responsible_user_id' => $rina, 'responsible_display_name' => 'Rina', 'open_command_id' => $commands[3]]);
        });
        $this->assertSame([2, 2], [$this->observer()->table('cash_sessions')->where('status', 'CLOSED')->count(), $this->observer()->table('cash_sessions')->where('status', 'OPEN')->count()]);
    }

    public function test_the_presence_key_is_computed_by_the_database_and_never_written(): void
    {
        // SCHEMA section 3.7: close_presence is the id exactly when the session is CLOSED. Its key to
        // cash_session_closes comes with that table (TASK-030).
        $commands = $this->commands(2);
        $this->runtime()->transaction(function (Connection $c) use ($commands): void {
            $this->insertSession($c, ['open_command_id' => $commands[0]]);
            $this->insertSession($c, ['session_number_seq' => 2, 'session_number' => '20261107-000002', 'status' => 'CLOSED', 'open_command_id' => $commands[1]]);
        });

        $rows = $this->observer()->table('cash_sessions')->orderBy('id')->get(['id', 'status', 'close_presence'])->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame([null, $rows[1]['id']], array_column($rows, 'close_presence'));

        $this->assertWriteRefused(fn (Connection $c) => $this->insertSession($c, ['session_number_seq' => 9, 'session_number' => '20261107-000009', 'status' => 'CLOSED', 'close_presence' => 1]), '428C9');
        $this->assertSame([], $this->observer()->table('pg_constraint')->where('conname', 'like', 'cash_sessions%close%')->where('contype', 'f')->pluck('conname')->all());
    }

    public function test_an_audit_event_names_only_a_session_that_exists(): void
    {
        // EXC-44: the key that TASK-006 left for this Task.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('audit_events')->insert([
            'occurred_at' => now(), 'category' => 'BUSINESS', 'action' => 'CASH_SESSION_OPEN', 'actor_kind' => 'USER', 'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra', 'cash_session_id' => 999999, 'change_data' => '{}',
        ]), '23503', 'audit_events_cash_session_fk');
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::openSession('Hendra', $this->register);
        $runtime = $this->runtime();

        // Class M: only the listed columns; never removed.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('cash_registers')->update(['created_at' => now()]));

        // Class FS: the state columns alone; every identity column, the number among them, stays.
        foreach ([
            'session_number' => '20261107-000099', 'session_number_seq' => 99, 'opened_business_date' => '2026-11-08', 'register_id' => $this->register,
            'responsible_user_id' => $this->owner, 'responsible_display_name' => 'Lain', 'opening_cash' => 5, 'opened_at' => now(), 'open_command_id' => $this->command,
        ] as $column => $value) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table('cash_sessions')->update([$column => $value]));
        }

        foreach (['cash_registers', 'cash_sessions'] as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->update(['updated_at' => now()]));
            $this->assertSame(1, $this->backup()->table($table)->count(), $table);
        }

        // The state columns are the runtime role's, so that the session's lock can be taken (SCHEMA
        // section 7).
        $runtime->transaction(static function (Connection $c): void {
            $c->table('cash_sessions')->lock('for no key update')->get();
            $c->table('cash_sessions')->update(['updated_at' => now()]);
        });

        // SCH-20, SCH-27, SCH-44: the number sequence is CACHE 1 and NO CYCLE; the runtime role takes
        // its values and never moves it; it takes nothing from the identity sequences.
        $this->assertNumberSequence('cash_session_number_seq');
        $this->assertIsInt($runtime->scalar("select pg_catalog.nextval('cash_session_number_seq')"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar("select pg_catalog.nextval('cash_sessions_id_seq')"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->scalar("select pg_catalog.nextval('cash_registers_id_seq')"));
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->scalar("select pg_catalog.nextval('cash_session_number_seq')"));
        $this->assertIsInt($this->backup()->scalar('select last_value from cash_session_number_seq'));
    }

    public function test_an_open_only_adds_a_session_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; a session is never removed and an opening
        // changes no register.
        $first = TokoContoh::openSession('Hendra', $this->register);

        $this->assertRetained(['cash_sessions', 'cash_registers'], function (): void {
            TokoContoh::openSession('Rina', $this->secondRegister());
        });

        $this->assertSame(2, $this->observer()->table('cash_sessions')->count());
        $this->assertSame('OPEN', $this->observer()->table('cash_sessions')->where('id', $first)->value('status'));
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', ['cash_registers', 'cash_sessions'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            'cash_registers_code_ci_uniq' => 'CREATE UNIQUE INDEX cash_registers_code_ci_uniq ON public.cash_registers USING btree (lower(code))',
            'cash_sessions_one_open_per_register' => "CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON public.cash_sessions USING btree (register_id) WHERE (status = 'OPEN'::text)",
            'cash_sessions_one_open_per_user' => "CREATE UNIQUE INDEX cash_sessions_one_open_per_user ON public.cash_sessions USING btree (responsible_user_id) WHERE (status = 'OPEN'::text)",
            'cash_sessions_opened_at_id_index' => 'CREATE INDEX cash_sessions_opened_at_id_index ON public.cash_sessions USING btree (opened_at, id)',
            'cash_sessions_responsible_opened_at_id_index' => 'CREATE INDEX cash_sessions_responsible_opened_at_id_index ON public.cash_sessions USING btree (responsible_user_id, opened_at, id)',
            'cash_sessions_register_id_index' => 'CREATE INDEX cash_sessions_register_id_index ON public.cash_sessions USING btree (register_id)',
            'cash_sessions_number_unique' => 'CREATE UNIQUE INDEX cash_sessions_number_unique ON public.cash_sessions USING btree (session_number)',
            'cash_sessions_number_seq_unique' => 'CREATE UNIQUE INDEX cash_sessions_number_seq_unique ON public.cash_sessions USING btree (session_number_seq)',
            'cash_sessions_open_command_unique' => 'CREATE UNIQUE INDEX cash_sessions_open_command_unique ON public.cash_sessions USING btree (open_command_id)',
            'cash_sessions_id_responsible_unique' => 'CREATE UNIQUE INDEX cash_sessions_id_responsible_unique ON public.cash_sessions USING btree (id, responsible_user_id)',
            'cash_sessions_id_status_unique' => 'CREATE UNIQUE INDEX cash_sessions_id_status_unique ON public.cash_sessions USING btree (id, status)',
            'cash_sessions_id_number_unique' => 'CREATE UNIQUE INDEX cash_sessions_id_number_unique ON public.cash_sessions USING btree (id, session_number)',
            'cash_sessions_report_target_unique' => 'CREATE UNIQUE INDEX cash_sessions_report_target_unique ON public.cash_sessions USING btree (id, session_number, opening_cash, opened_at, responsible_display_name)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }

        // The checks of SCH-22 use only immutable functions (SCHEMA section 6).
        $this->assertSame([], array_values(array_filter($this->mutableChecks(), static fn (string $check): bool => str_starts_with($check, 'cash_'))));
    }

    /**
     * A register written directly, LACI-1 unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertRegister(Connection $c, array $values = []): int
    {
        return $c->table('cash_registers')->insertGetId(['code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now(), ...$values]);
    }

    /**
     * The Owner's OPEN session 20261107-000001 on the first register, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertSession(Connection $c, array $values = []): int
    {
        return $c->table('cash_sessions')->insertGetId([
            'session_number' => '20261107-000001', 'session_number_seq' => 1, 'opened_business_date' => '2026-11-07', 'register_id' => $this->register,
            'responsible_user_id' => $this->owner, 'responsible_display_name' => 'Hendra', 'opening_cash' => 0, 'opened_at' => '2026-11-07 03:00:00+00',
            'open_command_id' => $this->command, 'status' => 'OPEN', 'activity_version' => 0, 'updated_at' => now(), ...$values,
        ]);
    }

    private function secondRegister(): int
    {
        $id = $this->observer()->table('cash_registers')->where('code', 'LACI-9')->value('id');

        return is_int($id) ? $id : TokoContoh::register('LACI-9', 'Laci 9');
    }

    private function cashier(): int
    {
        return TokoContoh::person('Rina')['id'];
    }

    /**
     * The identities of several commands, for the unique opening command of each session.
     *
     * @return list<int>
     */
    private function commands(int $count): array
    {
        $ids = [];

        for ($index = 0; $index < $count; $index++) {
            TokoContoh::category('Kategori '.$index);
            $id = $this->observer()->table('command_identities')->max('id');
            $ids[] = is_int($id) ? $id : 0;
        }

        return $ids;
    }
}
