<?php

namespace Tests\Feature\Commands;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\Commands\Probe;
use Tests\Harness\DatabaseTestCase;

/**
 * The six tables of the Commands module and the key completed on
 * audit_events (AC-008-09; SCHEMA sections 3.4, 3.7, 5.3, 9.4 and 10; the
 * migration 2026_10_28_000001_create_command_tables; EXECUTION_CONTEXT
 * EXC-44, EXC-45 and section 10, a table; suites DB01 and KEEP01; TASK-008
 * scope item 1): the constraint family, written directly by the runtime
 * role; the deferred family — a COMMAND identity without its result cannot
 * commit, a fence can never have one; the privilege family; and the
 * retention family, in which the pipeline only adds rows to the tables of
 * class F and changes a receipt only in its outcome columns.
 */
final class CommandTablesTest extends DatabaseTestCase
{
    use CommandRequests;

    private const string INTENT = '11111111-1111-4111-8111-111111111111';

    private const string KEY = '22222222-2222-4222-8222-222222222222';

    private const string FINGERPRINT = "decode('abababababababababababababababababababababababababababababababab', 'hex')";

    private const string SHORT = "decode('00', 'hex')";

    private const string BYTES = "decode('cdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcd', 'hex')";

    /** A column of each table, for the privilege family's writes. */
    private const array COLUMNS = [
        'recovery_epoch' => 'set_at',
        'command_identities' => 'recorded_at',
        'command_results' => 'committed_at',
        'command_request_receipts' => 'received_at',
        'stale_intent_reports' => 'first_reported_at',
        'intent_reconciliations' => 'recorded_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    public function test_each_check_and_key_of_the_identities_refuses_its_violation(): void
    {
        $cases = [
            'command_identities_kind_allowed' => ['kind' => 'OTHER'],
            'command_identities_operation_format' => ['operation' => 'Sale.Checkout'],
            'command_identities_actor_kind_allowed' => ['actor_kind' => 'ANONYMOUS', 'actor_user_id' => null, 'actor_process' => 'reconciliation'],
            'command_identities_actor_process_format' => ['actor_kind' => 'SCHEDULED_COMMAND', 'actor_user_id' => null, 'actor_process' => 'Reconciliation'],
            'command_identities_actor' => ['actor_process' => 'reconciliation'],
            'command_identities_kind_shape' => ['operation_version' => null],
        ];

        foreach ($cases as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, $values), '23514', $constraint);
        }

        // Every part of a COMMAND's shape, and of a FENCE's.
        foreach ([['idempotency_key' => null], ['fingerprint' => null], ['fingerprint' => self::SHORT]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, $values), '23514', 'command_identities_kind_shape');
        }

        foreach ([['operation_version' => 1], ['idempotency_key' => self::KEY], ['fingerprint' => self::FINGERPRINT], ['actor_kind' => 'SCHEDULED_COMMAND', 'actor_user_id' => null, 'actor_process' => 'reconciliation']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, [...self::fence(), ...$values]), '23514', 'command_identities_kind_shape');
        }

        // (operation, intent) once, whatever the kind; (actor, operation, key) once.
        $this->runtime()->transaction(fn (Connection $c) => $this->insertResult($c, $this->insertIdentity($c)));
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, self::fence()), '23505', 'command_identities_intent_unique');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, ['source_intent_id' => (string) Str::uuid()]), '23505', 'command_identities_user_key');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, ['actor_user_id' => 999999, 'source_intent_id' => (string) Str::uuid()]), '23503', 'command_identities_actor_user_fk');

        // A scheduled command's key is unique by its process.
        $scheduled = ['actor_kind' => 'SCHEDULED_COMMAND', 'actor_user_id' => null, 'actor_process' => 'reconciliation'];
        $this->runtime()->transaction(fn (Connection $c) => $this->insertResult($c, $this->insertIdentity($c, [...$scheduled, 'source_intent_id' => (string) Str::uuid()])));
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIdentity($c, [...$scheduled, 'source_intent_id' => (string) Str::uuid()]), '23505', 'command_identities_process_key');
    }

    public function test_an_identity_without_its_result_cannot_commit_and_a_fence_never_has_one(): void
    {
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertIdentity($c), 'command_identities_result_presence_fk', ['command_identities', 'command_results']);

        // A fence commits alone and refuses a result.
        $fence = $this->runtime()->transaction(fn (Connection $c): int => $this->insertIdentity($c, self::fence()));
        $this->assertNull($this->observer()->table('command_identities')->where('id', $fence)->value('result_presence'));
        $this->assertWriteRefused(fn (Connection $c) => $this->insertResult($c, $fence), '23503', 'command_results_identity_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertResult($c, $fence, ['identity_kind' => 'FENCE']), '23514', 'command_results_identity_kind_command');

        $command = $this->runtime()->transaction(function (Connection $c): int {
            $id = $this->insertIdentity($c, ['source_intent_id' => (string) Str::uuid()]);
            $this->insertResult($c, $id);

            return $id;
        });
        $this->assertSame($command, $this->observer()->table('command_identities')->where('id', $command)->value('result_presence'));
        $this->assertWriteRefused(fn (Connection $c) => $this->insertResult($c, $command, ['result_type' => 'Sale']), '23514', 'command_results_result_type_format');
    }

    public function test_each_check_of_the_receipts_reports_reconciliations_and_epoch_refuses_its_violation(): void
    {
        $fence = $this->runtime()->transaction(fn (Connection $c): int => $this->insertIdentity($c, self::fence()));
        $agus = $this->people['Agus']['id'];
        $receipts = [
            'command_request_receipts_operation_format' => ['operation' => 'X'],
            'command_request_receipts_outcome_allowed' => ['outcome' => 'DONE', 'outcome_at' => now()],
            'command_request_receipts_outcome_time' => ['outcome' => 'COMMITTED'],
            'command_request_receipts_held_code_allowed' => ['held_code' => 'STALE_VERSION'],
            'command_request_receipts_held_has_code' => ['outcome' => 'HELD', 'outcome_at' => now()],
            'command_request_receipts_settled_pair' => ['outcome' => 'COMMITTED', 'outcome_at' => now(), 'settled_by_user_id' => $agus],
            'command_request_receipts_settled_outcome' => ['outcome' => 'REJECTED', 'outcome_at' => now(), 'settled_by_user_id' => $agus, 'settled_by_command_id' => $fence],
        ];

        foreach ($receipts as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $values), '23514', $constraint);
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['settled_by_command_id' => 999999, 'settled_by_user_id' => $agus, 'outcome' => 'COMMITTED', 'outcome_at' => now()]), '23503', 'command_request_receipts_settled_by_command_fk');

        $this->assertWriteRefused(fn (Connection $c) => $c->table('recovery_epoch')->insert(['id' => 2, 'epoch' => $c->raw(self::BYTES), 'set_at' => now()]), '23514', 'recovery_epoch_single');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('recovery_epoch')->where('id', 1)->update(['epoch' => $c->raw(self::SHORT)]), '23514', 'recovery_epoch_epoch_length');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('recovery_epoch')->insert(['id' => 1, 'epoch' => $c->raw(self::BYTES), 'set_at' => now()]), '23505');

        $report = $this->insertReport($this->runtime(), self::INTENT);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, self::INTENT), '23505', 'stale_intent_reports_intent_unique');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReport($c, (string) Str::uuid(), ['operation' => 'Sale']), '23514', 'stale_intent_reports_operation_format');

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $report, $fence, ['outcome' => 'COMMITTED']), '23514', 'intent_reconciliations_outcome_not_committed');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $report, $fence, ['operator_note' => '']), '23514', 'intent_reconciliations_operator_note_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $report, $fence, ['operator_note' => str_repeat('n', 1001)]), '23514', 'intent_reconciliations_operator_note_length');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $report, 999999), '23503', 'intent_reconciliations_fence_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, 999999, $fence), '23503', 'intent_reconciliations_report_fk');
        $this->insertReconciliation($this->runtime(), $report, $fence);
        $other = $this->insertReport($this->runtime(), (string) Str::uuid());
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $other, $fence), '23505', 'intent_reconciliations_fence_unique');

        // audit_events.command_id → command_identities (EXC-44).
        $this->assertWriteRefused(fn (Connection $c) => $c->table('audit_events')->insert(['occurred_at' => now(), 'category' => 'BUSINESS', 'action' => 'HARNESS_PROBE', 'actor_kind' => 'USER', 'actor_user_id' => $agus, 'actor_display_name' => 'Agus', 'command_id' => 999999, 'change_data' => '{}']), '23503', 'audit_events_command_fk');
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $this->as('Agus');
        $this->probe($this->envelope())->assertStatus(201);
        $runtime = $this->runtime();

        foreach (['command_identities', 'command_results', 'stale_intent_reports', 'intent_reconciliations'] as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([self::COLUMNS[$table] => now()]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
        }

        // A receipt: only its outcome columns change.
        foreach (['received_at' => now(), 'actor_user_id' => 1, 'idempotency_key' => self::KEY, 'operation' => 'sale.checkout'] as $column => $value) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table('command_request_receipts')->update([$column => $value]));
        }

        // The epoch: its value and time change, its row is never removed.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('recovery_epoch')->update(['id' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('recovery_epoch')->delete());

        foreach (self::COLUMNS as $table => $column) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->insert([$column => now()]));
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
        }
    }

    public function test_the_pipeline_only_adds_retained_rows_and_changes_a_receipt_only_in_its_outcome(): void
    {
        $this->as('Agus');
        $first = $this->envelope();
        $this->probe($first)->assertStatus(201);
        $retained = ['command_identities' => 'id', 'command_results' => 'command_id', 'stale_intent_reports' => 'id', 'audit_events' => 'id', 'harness_probe_facts' => 'id'];
        $before = [];

        foreach ($retained as $table => $key) {
            $before[$table] = $this->rowsAsJson($table, $key);
        }

        $receiptsBefore = $this->rowsAsJson('command_request_receipts', 'id');

        $this->probe($first)->assertStatus(200);
        $this->probe([...$first, 'payload' => ['note' => 'Lain']])->assertStatus(409);
        $this->probe($this->envelope(changes: ['recoveryEpoch' => str_repeat('0b', 32)]))->assertStatus(409);
        $this->check($first)->assertOk();
        $other = $this->envelope();
        $this->check($other)->assertOk();
        $this->probe($other)->assertStatus(409);

        foreach ($retained as $table => $key) {
            $after = $this->rowsAsJson($table, $key);
            $this->assertSame($before[$table], array_slice($after, 0, count($before[$table])), sprintf('The pipeline changed or removed a retained row of %s.', $table));
            $this->assertGreaterThanOrEqual(count($before[$table]), count($after));
        }

        $receiptsAfter = $this->rowsAsJson('command_request_receipts', 'id');
        $this->assertSame(array_map(self::identityOf(...), $receiptsBefore), array_map(self::identityOf(...), array_slice($receiptsAfter, 0, count($receiptsBefore))));
        $this->assertSame(Probe::OPERATION, $receiptsAfter[0]['operation']);
    }

    /**
     * Every row of the table, its binary columns as text, in key order, as
     * an independent session reads them.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsAsJson(string $table, string $key): array
    {
        $rows = [];

        foreach ($this->observer()->table($table, 't')->orderBy('t.'.$key)->selectRaw('to_jsonb(t.*)::text as row')->pluck('row') as $row) {
            $decoded = json_decode(is_string($row) ? $row : '{}', true, 8, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            $this->assertIsArray($decoded);
            $values = [];

            foreach ($decoded as $column => $value) {
                $values[(string) $column] = $value;
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fence(): array
    {
        return ['kind' => 'FENCE', 'operation_version' => null, 'idempotency_key' => null, 'fingerprint' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function identityOf(array $row): array
    {
        return array_intersect_key($row, array_flip(['id', 'actor_user_id', 'operation', 'source_intent_id', 'idempotency_key', 'received_at']));
    }

    /**
     * @param  array<string, mixed>  $values  column => value; the fingerprint as one of the SQL constants or null
     */
    private function insertIdentity(Connection $c, array $values = []): int
    {
        $fingerprint = array_key_exists('fingerprint', $values) ? $values['fingerprint'] : self::FINGERPRINT;
        unset($values['fingerprint']);

        return (int) $c->table('command_identities')->insertGetId([
            'kind' => 'COMMAND', 'operation' => 'sale.checkout', 'operation_version' => 1, 'source_intent_id' => self::INTENT,
            'idempotency_key' => self::KEY, 'actor_kind' => 'USER', 'actor_user_id' => $this->people['Agus']['id'], 'actor_process' => null,
            'fingerprint' => match ($fingerprint) {
                self::FINGERPRINT => $c->raw(self::FINGERPRINT),
                self::SHORT => $c->raw(self::SHORT),
                default => null,
            },
            'recovery_epoch' => $c->raw(self::BYTES), 'received_at' => now(), 'recorded_at' => now(),
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertResult(Connection $c, int $commandId, array $values = []): void
    {
        $c->table('command_results')->insert(['command_id' => $commandId, 'identity_kind' => 'COMMAND', 'result_type' => 'sale', 'result_ref' => 1, 'result_detail' => '{}', 'committed_at' => now(), ...$values]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertReceipt(Connection $c, array $values): void
    {
        $c->table('command_request_receipts')->insert([
            'actor_user_id' => $this->people['Agus']['id'], 'operation' => 'sale.checkout', 'source_intent_id' => self::INTENT, 'idempotency_key' => self::KEY,
            'recovery_epoch' => $c->raw(self::BYTES), 'received_at' => now(), 'outcome' => 'PENDING',
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertReport(Connection $c, string $intent, array $values = []): int
    {
        return (int) $c->table('stale_intent_reports')->insertGetId([
            'operation' => 'sale.checkout', 'source_intent_id' => $intent, 'actor_user_id' => $this->people['Agus']['id'],
            'reported_epoch' => $c->raw(self::BYTES), 'first_reported_at' => now(),
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertReconciliation(Connection $c, int $report, int $fence, array $values = []): void
    {
        $c->table('intent_reconciliations')->insert([
            'report_id' => $report, 'outcome' => 'NOT_COMMITTED', 'fence_command_id' => $fence,
            'decided_by_user_id' => $this->people['Hendra']['id'], 'operator_note' => 'Catatan pemilik', 'recorded_at' => now(),
            ...$values,
        ]);
    }
}
