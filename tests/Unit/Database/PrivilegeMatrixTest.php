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

        $this->assertSame(['audit_events', 'cache', 'cache_locks', 'command_identities', 'command_request_receipts', 'command_results', 'intent_reconciliations', 'migrations', 'password_reset_tokens', 'recovery_epoch', 'security_log_entries', 'sessions', 'stale_intent_reports', 'users'], array_keys($matrix->tables()));
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $matrix->tablePrivileges('cache', 'runtime'));
        $this->assertSame(['SELECT', 'INSERT', 'UPDATE', 'DELETE'], $matrix->tablePrivileges('cache_locks', 'runtime'));
        $this->assertSame([], $matrix->tablePrivileges('migrations', 'runtime'));
        $this->assertSame(['SELECT'], $matrix->tablePrivileges('migrations', 'backup'));
        $this->assertSame([], $matrix->updateColumns('cache'));
        $this->assertSame([], $matrix->sequences());

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
