<?php

namespace Tests\Feature\Cash;

use App\Database\PrivilegeCheck;
use App\Database\PrivilegeMatrix;
use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The two tables of TASK-030 and the key it completes (AC-030-04, AC-030-06; SCHEMA sections 5.10, 6,
 * 9.4, 10 and 11; BR-CASH-12, BR-CASH-17 to BR-CASH-20; G-05, G-17; the migration
 * 2026_11_11_000001_create_cash_close_tables; EXECUTION_CONTEXT section 10, a table, and EXC-44; suites
 * DB01, KEEP01, SESSION04 and SESSION06; TASK-030 section 7, L2): the constraint family, written
 * directly by the runtime role — a close only of a CLOSED session, its kind, actor, reason, cutoff and
 * amounts, its variance their difference, one per session and per command; a report tied to its session
 * and to its close as recorded, with its identity, template, register and totals, expected cash and the
 * variance their arithmetic; the deferred family — a close without its report, and a CLOSED session
 * without its close, each failing at commit and leaving nothing; the privilege family, a CLOSED session
 * never OPEN again; the retention family; and the indexes of section 11.
 */
final class CashCloseTablesTest extends DatabaseTestCase
{
    /** The two tables, class F: added, never changed or removed. */
    private const array TABLES = ['cash_session_closes', 'cash_session_reports'];

    private const string OPENED_AT = '2026-11-11 01:00:00+00';

    private const string CLOSED_AT = '2026-11-11 10:00:00+00';

    private int $owner = 0;

    private int $profile = 0;

    private int $register = 0;

    /** @var list<int> the identities of commands, for the sessions and closes written directly */
    private array $commands = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = TokoContoh::shop();
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
        $this->register = TokoContoh::register();

        foreach (range(1, 3) as $index) {
            TokoContoh::category('Perintah '.(string) $index);
            $this->commands[] = self::number($this->watcher()->table('command_identities')->max('id'));
        }
    }

    public function test_each_check_and_key_of_a_close_refuses_its_violation(): void
    {
        foreach ([
            'cash_session_closes_status_closed' => [['session_status' => 'OPEN']],
            'cash_session_closes_kind_allowed' => [['close_kind' => 'own'], ['close_kind' => 'ASSISTED']],
            'cash_session_closes_actor_display_name_length' => [['close_actor_display_name' => ''], ['close_actor_display_name' => str_repeat('n', 101)]],
            'cash_session_closes_reason_length' => [['close_reason' => ''], ['close_reason' => str_repeat('r', 1001)]],
            'cash_session_closes_activity_version_range' => [['activity_version_at_close' => -1]],
            // BR-CASH-12: each amount zero or more within the money limit, the variance their difference,
            // so that only the range is refused.
            'cash_session_closes_physical_cash_range' => [['physical_cash' => -1, 'variance' => -1], ['physical_cash' => 1000000000000, 'variance' => 1000000000000]],
            'cash_session_closes_expected_cash_range' => [['expected_cash' => -1, 'variance' => 1], ['expected_cash' => 1000000000000, 'variance' => -1000000000000]],
            'cash_session_closes_variance_difference' => [['variance' => 1], ['physical_cash' => 118000, 'expected_cash' => 120000, 'variance' => 2000]],
            // An Owner-assisted close names its reason (RC-04, TASK-032).
            'cash_session_closes_assisted_reason' => [['close_kind' => 'OWNER_ASSISTED']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertClose($c, $this->insertSession($c), $values), '23514', $constraint);
            }
        }

        // BR-CASH-17, BR-CASH-19: the close names its session as CLOSED, so an OPEN session, like one that
        // does not exist, has none.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClose($c, $this->insertSession($c, ['status' => 'OPEN'])), '23503', 'cash_session_closes_session_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClose($c, 999999), '23503', 'cash_session_closes_session_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClose($c, $this->insertSession($c), ['close_actor_user_id' => 999999]), '23503', 'cash_session_closes_actor_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClose($c, $this->insertSession($c), ['command_id' => 999999]), '23503', 'cash_session_closes_command_fk');

        foreach (['session_id', 'session_status', 'closed_at', 'close_kind', 'close_actor_user_id', 'close_actor_display_name', 'activity_version_at_close', 'physical_cash', 'expected_cash', 'variance', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertClose($c, $this->insertSession($c), [$column => null]), '23502');
        }

        // One close per session, and one per command (BR-CASH-17, RACE-09).
        $this->assertWriteRefused(function (Connection $c): void {
            $session = $this->insertSession($c);
            $this->insertClose($c, $session);
            $this->insertClose($c, $session, ['command_id' => $this->commands[2]]);
        }, '23505', 'cash_session_closes_pkey');

        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertClose($c, $this->insertSession($c));
            $this->insertClose($c, $this->insertSession($c, ['session_number_seq' => 902, 'session_number' => '20261111-000902', 'open_command_id' => $this->commands[2]]));
        }, '23505', 'cash_session_closes_command_unique');

        $this->assertSame(['activity_version_at_close', 'close_actor_display_name', 'close_actor_user_id', 'close_kind', 'close_reason', 'closed_at', 'command_id', 'expected_cash', 'physical_cash', 'session_id', 'session_status', 'variance'], $this->columnsOf('cash_session_closes'));
    }

    public function test_each_check_and_key_of_a_report_refuses_its_violation(): void
    {
        foreach ([
            // ARC-45: a template of the code base names its version.
            'cash_session_reports_template_version_format' => [['template_version' => ''], ['template_version' => 'Session report 1'], ['template_version' => str_repeat('a', 65)]],
            'cash_session_reports_register_code_length' => [['register_code' => ''], ['register_code' => str_repeat('k', 33)]],
            'cash_session_reports_register_label_length' => [['register_label' => ''], ['register_label' => str_repeat('l', 101)]],
            'cash_session_reports_sale_count_range' => [['completed_sale_count' => -1]],
            'cash_session_reports_sale_value_range' => [['completed_sale_value' => -1]],
            // Expected cash kept the opening cash plus CASH minus the drawer-funded refunds (G-05), so that
            // only the range is refused.
            'cash_session_reports_cash_applied_range' => [['cash_applied' => -1, 'expected_cash' => -1, 'variance' => 1]],
            'cash_session_reports_bank_transfer_applied_range' => [['bank_transfer_applied' => -1]],
            'cash_session_reports_drawer_refund_total_range' => [['drawer_refund_total' => -1, 'expected_cash' => 1, 'variance' => -1]],
            'cash_session_reports_drawer_refunds_array' => [['drawer_refunds' => '{}'], ['drawer_refunds' => '"Refund"'], ['drawer_refunds' => '7']],
            'cash_session_reports_source_ids_object' => [['source_ids' => '[]'], ['source_ids' => '"7"']],
            'cash_session_reports_close_reason_length' => [['close_reason' => ''], ['close_reason' => str_repeat('r', 1001)]],
            'cash_session_reports_notes_length' => [['notes' => ''], ['notes' => str_repeat('c', 1001)]],
            // BR-CASH-12, G-05: BANK_TRANSFER never changes expected cash; the variance is the count minus it.
            'cash_session_reports_expected_cash_derived' => [['expected_cash' => 1, 'variance' => -1], ['bank_transfer_applied' => 5, 'expected_cash' => 5, 'variance' => -5]],
            'cash_session_reports_variance_difference' => [['variance' => 1]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->closedSession($c), $values), '23514', $constraint);
            }
        }

        // G-17: the session's number, opening cash, opened time and responsible name are the session's,
        // and the close as recorded — its time, cutoff, count and actor — is the close's.
        foreach ([['session_number' => '20261111-000999'], ['opening_cash' => 5, 'drawer_refund_total' => 5], ['opened_at' => '2026-11-11 02:00:00+00'], ['responsible_display_name' => 'Rina']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->closedSession($c), $values), '23503', 'cash_session_reports_session_fk');
        }

        foreach ([['closed_at' => '2026-11-11 11:00:00+00'], ['cutoff_activity_version' => 1], ['physical_cash' => 3, 'variance' => 3], ['close_actor_display_name' => 'Rina']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->closedSession($c), $values), '23503', 'cash_session_reports_close_fk');
        }

        // A report of a session without its close (BR-CASH-17); the identity by reference to a revision
        // and its logo (SCHEMA section 5.10).
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->insertSession($c)), '23503', 'cash_session_reports_close_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->closedSession($c), ['business_profile_revision_id' => 999999]), '23503', 'cash_session_reports_profile_revision_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->closedSession($c), ['logo_file_id' => 999999]), '23503', 'cash_session_reports_logo_file_fk');

        foreach ([
            'session_id', 'session_number', 'opening_cash', 'opened_at', 'responsible_display_name', 'business_profile_revision_id', 'template_version', 'register_code',
            'register_label', 'closed_at', 'cutoff_activity_version', 'physical_cash', 'expected_cash', 'variance', 'close_actor_display_name', 'completed_sale_count',
            'completed_sale_value', 'cash_applied', 'bank_transfer_applied', 'drawer_refund_total', 'drawer_refunds', 'source_ids',
        ] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, $this->closedSession($c), [$column => null]), '23502');
        }

        // One report per session.
        $this->assertWriteRefused(function (Connection $c): void {
            $session = $this->closedSession($c);
            $this->insertReport($c, $session);
            $this->insertReport($c, $session);
        }, '23505', 'cash_session_reports_pkey');

        // It holds no cost, margin or profit, and the shop identity by reference (BR-CASH-20).
        $this->assertSame([
            'bank_transfer_applied', 'business_profile_revision_id', 'cash_applied', 'close_actor_display_name', 'close_reason', 'closed_at', 'completed_sale_count',
            'completed_sale_value', 'cutoff_activity_version', 'drawer_refund_total', 'drawer_refunds', 'expected_cash', 'logo_file_id', 'notes', 'opened_at', 'opening_cash',
            'physical_cash', 'register_code', 'register_label', 'responsible_display_name', 'session_id', 'session_number', 'source_ids', 'template_version', 'variance',
        ], $this->columnsOf('cash_session_reports'));
    }

    public function test_a_close_without_its_report_or_a_closed_session_without_its_close_fails_at_commit_and_leaves_nothing(): void
    {
        // BR-CASH-17, EXC-44: every statement is accepted, and the commit refuses what is missing.
        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->closedSession($c);
        }, 'cash_session_closes_report_fk', ['cash_sessions', ...self::TABLES]);

        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->insertSession($c);
        }, 'cash_sessions_close_presence_fk', ['cash_sessions', ...self::TABLES]);

        // The session CLOSED, its close and its report commit together.
        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertReport($c, $this->closedSession($c));
        });

        foreach (['cash_sessions', ...self::TABLES] as $table) {
            $this->assertSame(1, $this->watcher()->table($table)->count(), $table);
        }
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $session = TokoContoh::closeSession(TokoContoh::openSession('Hendra', $this->register));
        $runtime = $this->runtime();

        // Class F: a close and its report are added, never changed or removed (BR-CASH-19, ARC-70).
        foreach (['cash_session_closes' => ['physical_cash' => 1], 'cash_session_reports' => ['notes' => 'Catatan']] as $table => $change) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update($change));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->update($change));
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertSame(1, $this->backup()->table($table)->count(), $table);
        }

        // There is no reopening (BR-CASH-19; AC-030-06): the runtime role writes a session's state, and the
        // close's key, ON UPDATE RESTRICT, refuses a CLOSED session that would become OPEN again.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('cash_sessions')->where('id', $session)->update(['status' => 'OPEN', 'updated_at' => now()]), '23503', 'cash_session_closes_session_fk');
        $this->assertSame('CLOSED', $this->watcher()->table('cash_sessions')->where('id', $session)->value('status'));

        // The grants equal the matrix of database/privileges.php, every table's (db:check-privileges).
        $this->assertSame([], (new PrivilegeCheck(PrivilegeMatrix::load()))->run($runtime));
    }

    public function test_a_later_close_only_adds_its_close_and_report_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; a close changes no earlier close or report.
        TokoContoh::closeSession(TokoContoh::openSession('Hendra', $this->register));

        $this->assertRetained(self::TABLES, function (): void {
            TokoContoh::closeSession(TokoContoh::openSession('Hendra', $this->register));
            TokoContoh::closeSession(TokoContoh::openSession('Rina', TokoContoh::register('LACI-2', 'Laci 2')));
        }, 'session_id');

        $this->assertSame([3, 3], [$this->watcher()->table('cash_session_closes')->count(), $this->watcher()->table('cash_session_reports')->count()]);
    }

    public function test_the_indexes_of_schema_section_11_exist_the_keys_are_as_declared_and_the_checks_are_immutable(): void
    {
        $indexes = $this->watcher()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', self::TABLES)->pluck('indexdef', 'indexname')->all();

        foreach ([
            'cash_session_closes_pkey' => 'CREATE UNIQUE INDEX cash_session_closes_pkey ON public.cash_session_closes USING btree (session_id)',
            'cash_session_closes_command_unique' => 'CREATE UNIQUE INDEX cash_session_closes_command_unique ON public.cash_session_closes USING btree (command_id)',
            'cash_session_closes_report_target_unique' => 'CREATE UNIQUE INDEX cash_session_closes_report_target_unique ON public.cash_session_closes USING btree (session_id, closed_at, activity_version_at_close, physical_cash, expected_cash, variance, close_actor_display_name)',
            'cash_session_closes_actor_index' => 'CREATE INDEX cash_session_closes_actor_index ON public.cash_session_closes USING btree (close_actor_user_id)',
            'cash_session_reports_pkey' => 'CREATE UNIQUE INDEX cash_session_reports_pkey ON public.cash_session_reports USING btree (session_id)',
            'cash_session_reports_profile_revision_index' => 'CREATE INDEX cash_session_reports_profile_revision_index ON public.cash_session_reports USING btree (business_profile_revision_id)',
            'cash_session_reports_logo_file_id_index' => 'CREATE INDEX cash_session_reports_logo_file_id_index ON public.cash_session_reports USING btree (logo_file_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }

        // The two keys that go both ways are deferred to the commit; the key that holds the session
        // CLOSED is immediate, and refuses any change of the session's status (BR-CASH-19).
        $keys = $this->watcher()->table('pg_constraint')->whereIn('conname', ['cash_session_closes_report_fk', 'cash_session_closes_session_fk', 'cash_sessions_close_presence_fk'])->orderBy('conname')->get(['conname', 'condeferrable', 'condeferred', 'confupdtype']);
        $this->assertSame([
            ['cash_session_closes_report_fk', true, true, 'a'],
            ['cash_session_closes_session_fk', false, false, 'r'],
            ['cash_sessions_close_presence_fk', true, true, 'a'],
        ], $keys->map(static fn (object $row): array => array_values((array) $row))->all());

        // The checks use only immutable functions (SCHEMA section 6).
        $this->assertSame([], array_values(array_filter($this->mutableChecks(), static fn (string $check): bool => str_starts_with($check, 'cash_session_'))));
    }

    /**
     * The Owner's session 20261111-000901 on the register, CLOSED, written directly, unless $values says
     * otherwise; its counter is far from the ones the sequence gives.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertSession(Connection $c, array $values = []): int
    {
        return $c->table('cash_sessions')->insertGetId([
            'session_number' => '20261111-000901', 'session_number_seq' => 901, 'opened_business_date' => '2026-11-11', 'register_id' => $this->register,
            'responsible_user_id' => $this->owner, 'responsible_display_name' => 'Hendra', 'opening_cash' => 0, 'opened_at' => self::OPENED_AT,
            'open_command_id' => $this->commands[0], 'status' => 'CLOSED', 'activity_version' => 0, 'updated_at' => now(), ...$values,
        ]);
    }

    /**
     * The Owner's own close of the session, counted Rp0 against an expected Rp0, unless $values says
     * otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertClose(Connection $c, int $session, array $values = []): void
    {
        $c->table('cash_session_closes')->insert([
            'session_id' => $session, 'session_status' => 'CLOSED', 'closed_at' => self::CLOSED_AT, 'close_kind' => 'OWN', 'close_actor_user_id' => $this->owner,
            'close_actor_display_name' => 'Hendra', 'close_reason' => null, 'activity_version_at_close' => 0, 'physical_cash' => 0, 'expected_cash' => 0, 'variance' => 0,
            'command_id' => $this->commands[1], ...$values,
        ]);
    }

    /**
     * The session CLOSED with its close.
     */
    private function closedSession(Connection $c): int
    {
        $session = $this->insertSession($c);
        $this->insertClose($c, $session);

        return $session;
    }

    /**
     * The report of the session and its close, with no Sale and no refund, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReport(Connection $c, int $session, array $values = []): void
    {
        $c->table('cash_session_reports')->insert([
            'session_id' => $session, 'session_number' => '20261111-000901', 'opening_cash' => 0, 'opened_at' => self::OPENED_AT, 'responsible_display_name' => 'Hendra',
            'business_profile_revision_id' => $this->profile, 'logo_file_id' => null, 'template_version' => 'session_report.1', 'register_code' => 'LACI-1',
            'register_label' => 'Laci 1', 'closed_at' => self::CLOSED_AT, 'cutoff_activity_version' => 0, 'physical_cash' => 0, 'expected_cash' => 0, 'variance' => 0,
            'close_actor_display_name' => 'Hendra', 'completed_sale_count' => 0, 'completed_sale_value' => 0, 'cash_applied' => 0, 'bank_transfer_applied' => 0,
            'drawer_refund_total' => 0, 'drawer_refunds' => '[]', 'source_ids' => '{"saleIds": [], "paymentIds": [], "drawerRefundIds": []}', 'close_reason' => null,
            'notes' => null, ...$values,
        ]);
    }

    /**
     * The table's columns, sorted.
     *
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        $columns = array_values(array_map(static fn (mixed $column): string => is_string($column) ? $column : '', $this->watcher()->table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $table)->pluck('column_name')->all()));
        sort($columns);

        return $columns;
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    private static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
