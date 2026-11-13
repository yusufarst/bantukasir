<?php

namespace Tests\Unit\Database;

use App\Database\PrivilegeMatrix;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The privilege matrix (TASK-004; SCHEMA sections 4 and 10, EXC-43): the
 * committed matrix reads as SCHEMA section 10 says for the tables of TASK-004,
 * and a matrix whose runtime privileges exceed a table's class, or whose shape
 * is wrong, is refused when it is read — so a later Task cannot grant the
 * runtime role UPDATE or DELETE on a retained fact through the matrix.
 */
final class PrivilegeMatrixTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_the_committed_matrix_holds_the_tables_of_task_004_task_006_and_task_008(): void
    {
        $matrix = PrivilegeMatrix::load();

        $this->assertSame(['acquisition_cost_evidence', 'adjustment_lines', 'adjustments', 'audit_events', 'backup_runs', 'barcode_active_mappings', 'barcode_mapping_retirements', 'barcode_mappings', 'barcode_tokens', 'business_profile_current', 'business_profile_revisions', 'cache', 'cache_locks', 'cash_registers', 'cash_session_closes', 'cash_session_reports', 'cash_session_review_notes', 'cash_sessions', 'categories', 'command_identities', 'command_request_receipts', 'command_results', 'cost_corrections', 'cost_incomplete_mark_clearances', 'cost_incomplete_marks', 'csv_product_imports', 'intent_reconciliations', 'inventory_posting_block_reopenings', 'inventory_posting_blocks', 'inventory_valuations', 'issue_cost_allocation_projections', 'issue_cost_allocations', 'manual_issue_lines', 'manual_issues', 'migrations', 'onboarding_releases', 'onboarding_state', 'password_reset_tokens', 'payments', 'products', 'receipt_snapshots', 'receiving_documents', 'receiving_lines', 'recovery_epoch', 'refund_lines', 'refunds', 'return_cost_allocation_projections', 'return_cost_allocations', 'return_lines', 'reversals', 'sale_correction_totals', 'sale_corrections', 'sale_line_correction_totals', 'sale_lines', 'sales', 'security_log_entries', 'selling_price_revisions', 'sessions', 'stale_intent_reports', 'stock_balances', 'stock_movements', 'stored_files', 'users'], array_keys($matrix->tables()));
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $matrix->tablePrivileges('cache', 'runtime'));
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $matrix->tablePrivileges('cache_locks', 'runtime'));
        $this->assertSame([], $matrix->tablePrivileges('migrations', 'runtime'));
        $this->assertSame(['SELECT'], $matrix->tablePrivileges('migrations', 'backup'));
        $this->assertSame([], $matrix->updateColumns('cache'));
        // TASK-027 and TASK-028 (SCH-20, SCH-44): the two number sequences, USAGE only.
        $this->assertSame(['cash_session_number_seq' => true, 'sale_number_seq' => true], $matrix->sequences());

        // TASK-006 (SCHEMA section 10).
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('users', 'runtime'));
        $this->assertSame(['display_name', 'email', 'password', 'role_operations_admin', 'role_cashier', 'status', 'deactivated_at', 'anonymized_at', 'version', 'updated_at'], $matrix->updateColumns('users'));
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $matrix->tablePrivileges('sessions', 'runtime'));
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $matrix->tablePrivileges('password_reset_tokens', 'runtime'));
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('audit_events', 'runtime'));
        $this->assertSame(['SELECT', 'INSERT', 'DELETE'], $matrix->tablePrivileges('security_log_entries', 'runtime'));

        // TASK-008 (SCHEMA section 10).
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('recovery_epoch', 'runtime'));
        $this->assertSame(['epoch', 'set_at'], $matrix->updateColumns('recovery_epoch'));

        foreach (['command_identities', 'command_results', 'stale_intent_reports', 'intent_reconciliations'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
        }

        $this->assertSame(['SELECT', 'INSERT', 'DELETE'], $matrix->tablePrivileges('command_request_receipts', 'runtime'));
        $this->assertSame(['outcome', 'outcome_at', 'held_code', 'settled_by_command_id', 'settled_by_user_id'], $matrix->updateColumns('command_request_receipts'));

        // TASK-010 (SCHEMA section 10).
        foreach (['business_profile_revisions', 'stored_files', 'onboarding_releases'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
        }

        $this->assertSame(['revision_id', 'changed_at'], $matrix->updateColumns('business_profile_current'));
        $this->assertSame(['state', 'version', 'changed_at', 'changed_by_user_id'], $matrix->updateColumns('onboarding_state'));

        // TASK-013 (SCHEMA section 10, SCH-42).
        $this->assertSame(['SELECT'], $matrix->tablePrivileges('backup_runs', 'runtime'));
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('backup_runs', 'backup'));

        // TASK-016 (SCHEMA section 10).
        foreach (['barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
        }

        $this->assertSame(['SELECT', 'INSERT', 'DELETE'], $matrix->tablePrivileges('barcode_active_mappings', 'runtime'));
        $this->assertSame([], $matrix->updateColumns('barcode_active_mappings'));

        // TASK-021 (SCHEMA section 10).
        foreach (['stock_movements', 'receiving_documents', 'receiving_lines', 'acquisition_cost_evidence', 'inventory_posting_blocks', 'inventory_posting_block_reopenings'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
            $this->assertSame(['SELECT'], $matrix->tablePrivileges($table, 'backup'), $table);
        }

        $this->assertSame(['on_hand', 'last_sequence', 'updated_at'], $matrix->updateColumns('stock_balances'));
        $this->assertSame(['value_status', 'value', 'publication_no', 'last_sequence', 'published_at'], $matrix->updateColumns('inventory_valuations'));

        // TASK-023 (SCHEMA section 10).
        foreach (['manual_issues', 'manual_issue_lines', 'issue_cost_allocations'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
            $this->assertSame(['SELECT'], $matrix->tablePrivileges($table, 'backup'), $table);
        }

        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('issue_cost_allocation_projections', 'runtime'));
        $this->assertSame(['current_status', 'current_amount', 'publication_no', 'updated_at'], $matrix->updateColumns('issue_cost_allocation_projections'));

        // TASK-024 (SCHEMA section 10).
        foreach (['adjustments', 'adjustment_lines'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
            $this->assertSame(['SELECT'], $matrix->tablePrivileges($table, 'backup'), $table);
        }

        // TASK-025 (SCHEMA section 10): facts, never updated or deleted.
        foreach (['cost_corrections', 'cost_incomplete_marks', 'cost_incomplete_mark_clearances'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
            $this->assertSame(['SELECT'], $matrix->tablePrivileges($table, 'backup'), $table);
        }

        // TASK-026 (SCHEMA section 10): a fact, never updated or deleted.
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('reversals', 'runtime'));
        $this->assertSame([], $matrix->updateColumns('reversals'));
        $this->assertSame(['SELECT'], $matrix->tablePrivileges('reversals', 'backup'));

        // TASK-027 (SCHEMA section 10): the registers' listed columns, and the sessions' state
        // columns alone.
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('cash_registers', 'runtime'));
        $this->assertSame(['code', 'label', 'active', 'version', 'updated_at'], $matrix->updateColumns('cash_registers'));
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('cash_sessions', 'runtime'));
        $this->assertSame(['status', 'activity_version', 'updated_at'], $matrix->updateColumns('cash_sessions'));

        // TASK-028 (SCHEMA section 10): the Sale's facts, never updated or deleted, and its running
        // totals, whose amounts alone change.
        foreach (['sales', 'sale_lines', 'payments', 'receipt_snapshots'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
            $this->assertSame(['SELECT'], $matrix->tablePrivileges($table, 'backup'), $table);
        }

        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('sale_correction_totals', 'runtime'));
        $this->assertSame(['refunded_amount', 'updated_at'], $matrix->updateColumns('sale_correction_totals'));
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('sale_line_correction_totals', 'runtime'));
        $this->assertSame(['refunded_amount', 'returned_quantity', 'updated_at'], $matrix->updateColumns('sale_line_correction_totals'));

        // TASK-030 (SCHEMA section 10): the close of a session and its report, facts never updated
        // or deleted; TASK-032: the Owner's review notes, likewise.
        foreach (['cash_session_closes', 'cash_session_reports', 'cash_session_review_notes'] as $table) {
            $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges($table, 'runtime'), $table);
            $this->assertSame([], $matrix->updateColumns($table), $table);
            $this->assertSame(['SELECT'], $matrix->tablePrivileges($table, 'backup'), $table);
        }

        // TASK-033 (SCHEMA sections 5.8 and 5.9, 10): the corrections, refunds, returns and return
        // allocations are facts; the return projection changes only its value, as the issue's does.
        foreach (['sale_corrections', 'refunds', 'refund_lines', 'return_lines', 'return_cost_allocations'] as $table) {
            $this->assertSame(['class' => 'F', 'runtime' => ['SELECT', 'INSERT'], 'update' => [], 'backup' => ['SELECT']], ['class' => $matrix->table($table)['class'], 'runtime' => $matrix->tablePrivileges($table, 'runtime'), 'update' => $matrix->updateColumns($table), 'backup' => $matrix->tablePrivileges($table, 'backup')], $table);
        }

        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('return_cost_allocation_projections', 'runtime'));
        $this->assertSame(['current_status', 'current_amount', 'publication_no', 'updated_at'], $matrix->updateColumns('return_cost_allocation_projections'));

        foreach (array_keys($matrix->tables()) as $table) {
            $this->assertSame($table === 'security_log_entries', $matrix->policies($table) !== [], $table);
        }

        $this->assertSame([
            'security_log_entries_add' => ['command' => 'INSERT', 'using' => null, 'check' => 'true'],
            'security_log_entries_read' => ['command' => 'SELECT', 'using' => 'true', 'check' => null],
            'security_log_entries_retention' => ['command' => 'DELETE', 'using' => "(occurred_at < (now() - '8760:00:00'::interval))", 'check' => null],
        ], $matrix->policies('security_log_entries'));
    }

    public function test_row_security_belongs_to_a_table_of_class_l_with_a_delete_policy(): void
    {
        $retention = ['retention' => ['command' => 'DELETE', 'using' => '(true)', 'check' => null]];

        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['select' => true, 'delete' => true]]], 'only such a table, has row security');
        $this->assertRefused(['t' => ['class' => 'E', 'runtime' => ['select' => true], 'row_security' => $retention]], 'only such a table, has row security');
        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['select' => true], 'row_security' => ['read' => ['command' => 'SELECT', 'using' => 'true', 'check' => null]]]], 'needs a DELETE policy');
        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['select' => true], 'row_security' => ['all' => ['command' => 'ALL', 'using' => 'true', 'check' => null]]]], 'command (SELECT, INSERT or DELETE)');
        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['select' => true], 'row_security' => ['add' => ['command' => 'INSERT', 'using' => 'true', 'check' => null], ...$retention]]], 'WITH CHECK expression for INSERT');
        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['select' => true], 'row_security' => ['retention' => ['command' => 'DELETE', 'using' => null, 'check' => 'true']]]], 'WITH CHECK expression for INSERT');
        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['select' => true], 'row_security' => ['Retention' => $retention['retention']]]], 'Not a name of this schema');
    }

    public function test_a_matrix_within_the_classes_is_read(): void
    {
        $matrix = $this->matrix([
            'users' => ['class' => 'M', 'runtime' => ['select' => true, 'insert' => true, 'update' => ['display_name', 'version']]],
            'stock_movements' => ['class' => 'F', 'runtime' => ['select' => true, 'insert' => true]],
            'barcode_active_mappings' => ['class' => 'P', 'runtime' => ['select' => true, 'insert' => true, 'delete' => true]],
            'security_log_entries' => ['class' => 'L', 'runtime' => ['select' => true, 'insert' => true, 'delete' => true], 'row_security' => ['retention' => ['command' => 'DELETE', 'using' => '(true)', 'check' => null]]],
            'backup_runs' => ['class' => 'F', 'runtime' => ['select' => true], 'backup' => ['insert' => true]],
        ], ['sale_number_seq' => ['runtime' => ['usage' => true]]]);

        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('users', 'runtime'));
        $this->assertSame(['display_name', 'version'], $matrix->updateColumns('users'));
        $this->assertSame(['SELECT', 'INSERT'], $matrix->tablePrivileges('backup_runs', 'backup'));
        $this->assertSame(['SELECT'], $matrix->tablePrivileges('stock_movements', 'backup'));
        $this->assertSame(['sale_number_seq' => true], $matrix->sequences());
    }

    public function test_runtime_privileges_beyond_the_class_are_refused(): void
    {
        $this->assertRefused(['t' => ['class' => 'F', 'runtime' => ['select' => true, 'update' => true]]], 'exceed what its class F allows');
        $this->assertRefused(['t' => ['class' => 'F', 'runtime' => ['select' => true, 'update' => ['a']]]], 'exceed what its class F allows');
        $this->assertRefused(['t' => ['class' => 'F', 'runtime' => ['select' => true, 'delete' => true]]], 'exceed what its class F allows');
        $this->assertRefused(['t' => ['class' => 'FS', 'runtime' => ['update' => true]]], 'exceed what its class FS allows');
        $this->assertRefused(['t' => ['class' => 'M', 'runtime' => ['delete' => true]]], 'exceed what its class M allows');
        $this->assertRefused(['t' => ['class' => 'S', 'runtime' => ['update' => true]]], 'exceed what its class S allows');
        $this->assertRefused(['t' => ['class' => 'L', 'runtime' => ['update' => ['a']]]], 'exceed what its class L allows');
        $this->assertRefused(['t' => ['class' => 'P', 'runtime' => ['update' => true]]], 'exceed what its class P allows');
        $this->assertRefused(['t' => ['class' => null, 'runtime' => ['select' => true]]], 'has no class');
    }

    public function test_a_wrong_shape_is_refused(): void
    {
        $this->assertRefused(['t' => ['class' => 'X', 'runtime' => []]], 'not one of SCHEMA section 4');
        $this->assertRefused(['t' => ['runtime' => []]], 'needs "class" and "runtime"');
        $this->assertRefused(['t' => ['class' => 'E', 'runtime' => ['truncate' => true]]], 'select, insert, update and delete only');
        $this->assertRefused(['t' => ['class' => 'E', 'runtime' => ['select' => false]]], 'is true or absent');
        $this->assertRefused(['t' => ['class' => 'M', 'runtime' => ['update' => []]]], 'a non-empty list without repeats');
        $this->assertRefused(['t' => ['class' => 'M', 'runtime' => ['update' => ['a', 'a']]]], 'a non-empty list without repeats');
        $this->assertRefused(['t' => ['class' => 'E', 'runtime' => [], 'backup' => ['delete' => true]]], 'at most insert');
        $this->assertRefused(['restore_drills' => ['class' => 'F', 'runtime' => ['select' => true], 'backup' => ['insert' => true]]], 'inserts into backup_runs only');
        $this->assertRefused(['T' => ['class' => 'E', 'runtime' => []]], 'Not a name of this schema');
        $this->assertRefused(['t' => ['class' => 'M', 'runtime' => ['update' => ['Name']]]], 'Not a name of this schema');
        $this->assertRefused([], 'needs "runtime" with at most "usage"', ['s' => ['runtime' => ['select' => true]]]);
    }

    /**
     * @param  array<mixed>  $tables
     * @param  array<mixed>  $sequences
     */
    private function matrix(array $tables, array $sequences = []): PrivilegeMatrix
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'bantukasir-matrix-');
        $this->files[] = $file;
        file_put_contents($file, '<?php return '.var_export(['tables' => $tables, 'sequences' => $sequences], true).';');

        return PrivilegeMatrix::load($file);
    }

    /**
     * @param  array<mixed>  $tables
     * @param  array<mixed>  $sequences
     */
    private function assertRefused(array $tables, string $message, array $sequences = []): void
    {
        try {
            $this->matrix($tables, $sequences);
            $this->fail('Accepted: '.json_encode($tables));
        } catch (InvalidArgumentException $refusal) {
            $this->assertStringContainsString($message, $refusal->getMessage());
        }
    }
}
