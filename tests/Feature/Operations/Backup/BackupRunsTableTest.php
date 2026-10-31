<?php

namespace Tests\Feature\Operations\Backup;

use App\Database\DatabaseRoles;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;

/**
 * backup_runs (AC-013-06; SCHEMA sections 5.12 and 10, SCH-42; EXECUTION_CONTEXT
 * section 10, a table; suites DB01 and KEEP01): each check refuses its violation;
 * only the backup role inserts; the runtime role only reads; neither changes or
 * removes a row, and db:check-privileges agrees.
 */
final class BackupRunsTableTest extends DatabaseTestCase
{
    public function test_each_check_refuses_its_violation(): void
    {
        $checks = [
            'backup_runs_backup_id_format' => ['backup_id' => '20261031/../x'],
            'backup_runs_reason_allowed' => ['reason' => 'MANUAL'],
            'backup_runs_outcome_allowed' => ['outcome' => 'PARTIAL', 'failure_code' => null],
            'backup_runs_failure_code_allowed' => ['failure_code' => 'OTHER'],
            'backup_runs_tiers_allowed' => ['tiers' => 'weekly'],
            'backup_runs_finished_after_started' => ['finished_at' => '2026-10-30 00:00:00+00'],
            'backup_runs_failure_code_when_failed' => ['outcome' => 'SUCCEEDED'],
            'backup_runs_dump_bytes_not_negative' => ['dump_bytes' => -1],
            'backup_runs_files_uploaded_not_negative' => ['files_uploaded' => -1],
            'backup_runs_uploaded_bytes_not_negative' => ['uploaded_bytes' => -1],
            'backup_runs_store_bytes_not_negative' => ['store_bytes' => -1],
            'backup_runs_reverified_backup_id_format' => ['reverified_backup_id' => 'x'],
        ];

        foreach ($checks as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $c->table('backup_runs')->insert([...$this->failedRow(), ...$values]), '23514', $constraint, $this->backup());
        }

        // A failed outcome without its code; a success without what it records.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('backup_runs')->insert([...$this->failedRow(), 'failure_code' => null]), '23514', 'backup_runs_failure_code_when_failed', $this->backup());
        $this->assertWriteRefused(fn (Connection $c) => $c->table('backup_runs')->insert([...$this->failedRow(), 'outcome' => 'SUCCEEDED', 'failure_code' => null]), '23514', 'backup_runs_success_complete', $this->backup());

        foreach (['dump_object_sha256' => 'backup_runs_dump_object_sha256_length', 'manifest_object_sha256' => 'backup_runs_manifest_object_sha256_length'] as $column => $constraint) {
            $this->assertWriteRefused(fn (Connection $c) => $c->table('backup_runs')->insert([...$this->failedRow(), $column => $c->raw("decode('00', 'hex')")]), '23514', $constraint, $this->backup());
        }

        $this->backup()->table('backup_runs')->insert($this->failedRow());
        $this->assertWriteRefused(fn (Connection $c) => $c->table('backup_runs')->insert($this->failedRow()), '23505', 'backup_runs_backup_id_unique', $this->backup());
    }

    public function test_only_the_backup_role_inserts_and_no_role_of_the_application_changes_a_row(): void
    {
        $this->backup()->table('backup_runs')->insert($this->failedRow());

        $this->assertDenied($this->runtime(), fn (Connection $c) => $c->table('backup_runs')->insert([...$this->failedRow(), 'backup_id' => '20261031T000000Z-BBBBBBBB']));

        foreach ([$this->runtime(), $this->backup()] as $connection) {
            $this->assertDenied($connection, fn (Connection $c) => $c->table('backup_runs')->update(['reason' => 'ON_DEMAND']));
            $this->assertDenied($connection, fn (Connection $c) => $c->table('backup_runs')->delete());
            $this->assertDenied($connection, fn (Connection $c) => $c->table('backup_runs')->truncate());
        }

        $this->assertSame(1, $this->runtime()->table('backup_runs')->count());
        $this->assertSame(1, $this->backup()->table('backup_runs')->count());
    }

    public function test_the_actual_grants_are_those_of_schema_section_10(): void
    {
        $roles = DatabaseRoles::of($this->migration());
        $privileges = fn (string $role): array => array_map(
            fn (mixed $row): mixed => ((array) $row)['privilege_type'] ?? null,
            $this->migration()->select(
                'select privilege_type from information_schema.role_table_grants where table_name = ? and grantee = ? order by privilege_type',
                ['backup_runs', $role],
            ),
        );

        $this->assertSame(['SELECT'], $privileges($roles->runtime));
        $this->assertSame(['INSERT', 'SELECT'], $privileges($roles->backup));
    }

    /**
     * @return array<string, mixed>
     */
    private function failedRow(): array
    {
        return [
            'backup_id' => '20261031T000000Z-AAAAAAAA',
            'reason' => 'SCHEDULED',
            'started_at' => '2026-10-31 00:00:00+00',
            'finished_at' => '2026-10-31 00:01:00+00',
            'outcome' => 'FAILED',
            'failure_code' => 'NO_SPACE',
            'tiers' => '6h',
        ];
    }
}
