<?php

namespace Tests\Feature\Audit;

use App\Database\DatabaseRoles;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;

/**
 * The tables of the Audit module (AC-006-07, AC-006-08; SCHEMA sections 5.4,
 * 9.4 and 10; the migration 2026_10_26_000002_create_audit_tables): the
 * constraint family for `audit_events` and `security_log_entries`, written
 * directly by the runtime role; the privilege family; and the retention
 * family, with the database's enforcement of the security log's retention of
 * 365 days (OPEN_ITEMS T-23): a DELETE by the runtime role of an entry younger
 * than 365 days removes nothing, an older entry is removable, and nothing
 * removes or rewrites an audit event. Neither table has a deferred key.
 */
final class AuditTablesTest extends DatabaseTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtime()->table('users')->insert([
            'display_name' => 'Rina', 'email' => 'rina@contoh.test', 'password' => null,
            'role_super_admin' => false, 'role_operations_admin' => false, 'role_cashier' => true,
            'status' => 'ACTIVE', 'version' => 1, 'created_at' => '2026-10-17 09:00:00+00', 'updated_at' => '2026-10-17 09:00:00+00',
        ]);
        $id = $this->runtime()->scalar('select id from users');
        $this->assertIsInt($id);
        $this->userId = $id;
    }

    public function test_an_audit_event_of_each_actor_kind_is_accepted(): void
    {
        $this->insertEvent();
        $this->insertEvent(['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_user_id' => null, 'actor_process' => 'bootstrap', 'actor_display_name' => 'bootstrap', 'operator_note' => 'Catatan operator']);
        $this->insertEvent(['actor_kind' => 'SCHEDULED_COMMAND', 'actor_user_id' => null, 'actor_process' => 'housekeeping', 'actor_display_name' => 'housekeeping']);
        $this->insertEvent(['subject_type' => 'user', 'subject_id' => $this->userId, 'reference' => 'R-1', 'reason' => 'Alasan', 'owner_only_data' => '{"cost": "1.000000"}']);

        $this->assertSame(4, $this->observer()->table('audit_events')->count());
    }

    public function test_each_check_of_audit_events_refuses_its_violation(): void
    {
        $cases = [
            'audit_events_category_allowed' => ['category' => 'OTHER'],
            'audit_events_action_format' => ['action' => 'link_consume'],
            'audit_events_actor_kind_allowed' => ['actor_kind' => 'ANONYMOUS', 'actor_user_id' => null, 'actor_process' => 'bootstrap'],
            'audit_events_actor_process_format' => ['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_user_id' => null, 'actor_process' => 'Bootstrap'],
            'audit_events_actor_display_name_length' => ['actor_display_name' => ''],
            'audit_events_operator_note_length' => ['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_user_id' => null, 'actor_process' => 'bootstrap', 'operator_note' => ''],
            'audit_events_subject_type_format' => ['subject_type' => 'User', 'subject_id' => 1],
            'audit_events_reference_length' => ['reference' => str_repeat('r', 101)],
            'audit_events_reason_length' => ['reason' => str_repeat('a', 1001)],
            'audit_events_operator_note_actor' => ['operator_note' => 'only a procedure notes'],
            'audit_events_subject_pair' => ['subject_type' => 'user'],
        ];

        foreach ($cases as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEvent($values, $c), '23514', $constraint);
        }

        // The actor columns go together in every branch, a null included (SCH-13).
        $actors = [
            ['actor_kind' => 'USER', 'actor_user_id' => null],
            ['actor_kind' => 'USER', 'actor_process' => 'bootstrap'],
            ['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_process' => null, 'actor_user_id' => null],
            ['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_process' => 'bootstrap'],
            ['actor_kind' => 'SCHEDULED_COMMAND', 'actor_process' => null, 'actor_user_id' => null],
        ];

        foreach ($actors as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEvent($values, $c), '23514', 'audit_events_actor');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvent(['subject_id' => 7], $c), '23514', 'audit_events_subject_pair');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvent(['actor_user_id' => $this->userId + 1000], $c), '23503', 'audit_events_actor_user_fk');

        foreach (['occurred_at', 'category', 'action', 'actor_kind', 'actor_display_name', 'change_data'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEvent([$column => null], $c), '23502');
        }

        $this->insertEvent(['reference' => str_repeat('r', 100), 'reason' => str_repeat('a', 1000), 'actor_display_name' => str_repeat('n', 100)]);
        $this->assertSame(1, $this->observer()->table('audit_events')->count());
    }

    public function test_the_runtime_role_only_adds_audit_events(): void
    {
        $this->insertEvent();
        $runtime = $this->runtime();
        $roles = DatabaseRoles::of($runtime);

        $this->assertDenied($runtime, fn (Connection $c) => $c->update("update audit_events set action = 'OTHER'"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->delete('delete from audit_events'));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('audit_events')->truncate());
        $this->assertSame(['SELECT', 'INSERT'], $this->privileges($roles->runtime, 'audit_events'));
        $this->assertSame(['SELECT'], $this->privileges($roles->backup, 'audit_events'));
        $this->assertSame(1, $this->backup()->table('audit_events')->count());
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->insert('insert into audit_events default values'));
    }

    public function test_each_check_of_security_log_entries_refuses_its_violation(): void
    {
        $this->insertEntry();
        $this->insertEntry(['actor_kind' => 'USER', 'actor_user_id' => $this->userId, 'subject_user_id' => $this->userId]);
        $this->insertEntry(['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_process' => 'owner_recovery', 'client_address' => null, 'route_name' => null, 'event_type' => 'OPERATOR_PROCEDURE_RUN']);

        $cases = [
            'security_log_entries_event_type_allowed' => ['event_type' => 'SIGNED_IN'],
            'security_log_entries_actor_process_format' => ['actor_kind' => 'SCHEDULED_COMMAND', 'actor_process' => 'house-keeping'],
            'security_log_entries_request_id_length' => ['request_id' => str_repeat('x', 65)],
            'security_log_entries_route_name_length' => ['route_name' => ''],
            'security_log_entries_outcome_code_format' => ['outcome_code' => 'refused'],
        ];

        foreach ($cases as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry($values, $c), '23514', $constraint);
        }

        // A kind outside the list fails both security_log_entries_actor_kind_allowed
        // and the actor check, whose branches name every allowed kind; the
        // database reports the first it evaluates.
        $refusal = $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry(['actor_kind' => 'SYSTEM'], $c), '23514');
        $this->assertMatchesRegularExpression('/"security_log_entries_actor(_kind_allowed)?"/', $refusal->getMessage());

        $actors = [
            ['actor_kind' => 'ANONYMOUS', 'actor_user_id' => $this->userId],
            ['actor_kind' => 'ANONYMOUS', 'actor_process' => 'bootstrap'],
            ['actor_kind' => 'USER'],
            ['actor_kind' => 'USER', 'actor_user_id' => $this->userId, 'actor_process' => 'bootstrap'],
            ['actor_kind' => 'OPERATOR_PROCEDURE'],
            ['actor_kind' => 'SCHEDULED_COMMAND', 'actor_user_id' => $this->userId, 'actor_process' => 'housekeeping'],
        ];

        foreach ($actors as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry($values, $c), '23514', 'security_log_entries_actor');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry(['subject_user_id' => $this->userId + 1000], $c), '23503', 'security_log_entries_subject_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry(['client_address' => 'not an address'], $c), '22P02');

        foreach (['occurred_at', 'event_type', 'actor_kind', 'request_id', 'outcome_code'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry([$column => null], $c), '23502');
        }

        $this->assertSame(3, $this->observer()->table('security_log_entries')->count());
    }

    public function test_the_runtime_role_removes_a_security_log_entry_only_after_365_days(): void
    {
        // The database's clock decides, as the housekeeping of TASK-038 will.
        $this->insertEntry(['occurred_at' => $this->databaseTime("now() - interval '366 days'"), 'request_id' => 'older']);
        $this->insertEntry(['occurred_at' => $this->databaseTime("now() - interval '8760 hours' - interval '1 minute'"), 'request_id' => 'just-older']);
        $this->insertEntry(['occurred_at' => $this->databaseTime("now() - interval '8760 hours' + interval '1 minute'"), 'request_id' => 'just-younger']);
        $this->insertEntry(['occurred_at' => $this->databaseTime("now() - interval '30 days'"), 'request_id' => 'younger']);
        $this->insertEntry(['request_id' => 'today']);

        // A removal of every entry removes only the old ones, and refuses nothing.
        $this->assertRemovedOnly(
            'security_log_entries',
            fn (array $row): bool => in_array($row['request_id'], ['older', 'just-older'], true),
            fn () => $this->assertSame(2, $this->runtime()->delete('delete from security_log_entries')),
        );

        // A removal aimed at a younger entry matches no row and removes nothing.
        $this->assertSame(0, $this->runtime()->delete("delete from security_log_entries where request_id = 'just-younger'"));
        $this->assertSame(0, $this->runtime()->delete("delete from security_log_entries where occurred_at > now() - interval '1 day'"));
        $this->assertSame(['just-younger', 'today', 'younger'], $this->observer()->table('security_log_entries')->orderBy('request_id')->pluck('request_id')->all());

        // Nothing else changes or empties the log.
        $runtime = $this->runtime();
        $this->assertDenied($runtime, fn (Connection $c) => $c->update("update security_log_entries set occurred_at = now() - interval '400 days'"));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('security_log_entries')->truncate());
        $this->assertSame(3, $this->observer()->table('security_log_entries')->count());
    }

    public function test_the_retention_holds_for_every_session_time_zone(): void
    {
        $this->insertEntry(['occurred_at' => $this->databaseTime("now() - interval '8760 hours' + interval '1 minute'"), 'request_id' => 'just-younger']);
        $runtime = $this->runtime();

        // Under a time zone with daylight saving, a day can be 23 hours; the
        // policy counts hours, so the entry stays.
        $runtime->beginTransaction();

        try {
            $runtime->statement("set local time zone 'America/New_York'");
            $this->assertSame(0, $runtime->delete('delete from security_log_entries'));
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(1, $this->observer()->table('security_log_entries')->count());
    }

    public function test_the_security_log_grants_and_its_row_security(): void
    {
        $this->insertEntry();
        $roles = DatabaseRoles::of($this->runtime());

        $this->assertSame(['SELECT', 'INSERT', 'DELETE'], $this->privileges($roles->runtime, 'security_log_entries'));
        $this->assertSame(['SELECT'], $this->privileges($roles->backup, 'security_log_entries'));
        $this->assertSame(1, $this->runtime()->table('security_log_entries')->count());
        $this->assertSame(1, $this->backup()->table('security_log_entries')->count());
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->delete('delete from security_log_entries'));

        $row = (array) $this->observer()->selectOne("select relrowsecurity, relforcerowsecurity from pg_catalog.pg_class where oid = 'public.security_log_entries'::regclass");
        $this->assertSame(['relrowsecurity' => true, 'relforcerowsecurity' => false], $row);
    }

    public function test_the_checks_of_the_audit_tables_are_immutable(): void
    {
        $mutable = array_filter($this->mutableChecks(), fn (string $check): bool => str_starts_with($check, 'audit_events.') || str_starts_with($check, 'security_log_entries.'));

        $this->assertSame([], array_values($mutable));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertEvent(array $values = [], ?Connection $connection = null): void
    {
        ($connection ?? $this->runtime())->table('audit_events')->insert([
            'occurred_at' => '2026-10-17 09:00:00+00',
            'category' => 'SECURITY',
            'action' => 'LINK_CONSUME',
            'actor_kind' => 'USER',
            'actor_user_id' => $this->userId,
            'actor_process' => null,
            'actor_display_name' => 'Rina',
            'change_data' => '{}',
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertEntry(array $values = [], ?Connection $connection = null): void
    {
        ($connection ?? $this->runtime())->table('security_log_entries')->insert([
            'occurred_at' => $this->databaseTime('now()'),
            'event_type' => 'SIGN_IN_FAILED',
            'actor_kind' => 'ANONYMOUS',
            'actor_user_id' => null,
            'actor_process' => null,
            'subject_user_id' => null,
            'request_id' => 'harness-request',
            'route_name' => 'login.store',
            'outcome_code' => 'REFUSED',
            'client_address' => '192.0.2.10',
            ...$values,
        ]);
    }

    /**
     * A time computed by the database from its own clock, as text.
     */
    private function databaseTime(string $expression): string
    {
        $time = match ($expression) {
            'now()' => $this->observer()->scalar('select now()::text'),
            "now() - interval '366 days'" => $this->observer()->scalar("select (now() - interval '366 days')::text"),
            "now() - interval '30 days'" => $this->observer()->scalar("select (now() - interval '30 days')::text"),
            "now() - interval '8760 hours' - interval '1 minute'" => $this->observer()->scalar("select (now() - interval '8760 hours' - interval '1 minute')::text"),
            "now() - interval '8760 hours' + interval '1 minute'" => $this->observer()->scalar("select (now() - interval '8760 hours' + interval '1 minute')::text"),
            default => null,
        };

        $this->assertIsString($time);

        return $time;
    }

    /**
     * @return list<string>
     */
    private function privileges(string $role, string $table): array
    {
        $held = [];

        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER', 'MAINTAIN'] as $privilege) {
            if ($this->runtime()->scalar('select pg_catalog.has_table_privilege(?, ?, ?)', [$role, $table, $privilege]) === true) {
                $held[] = $privilege;
            }
        }

        return $held;
    }
}
