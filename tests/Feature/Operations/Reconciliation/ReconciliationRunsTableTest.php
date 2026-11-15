<?php

namespace Tests\Feature\Operations\Reconciliation;

use App\Database\DatabaseRoles;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;

/**
 * reconciliation_runs (AC-036-07; SCHEMA sections 5.12 and 10; EXECUTION_CONTEXT section 10, a table;
 * suites DB01 and KEEP01): each check refuses its violation; the runtime role inserts and reads; neither
 * the runtime nor the backup role changes or removes a row; the backup role reads; the actual grants are
 * those of SCHEMA section 10.
 */
final class ReconciliationRunsTableTest extends DatabaseTestCase
{
    public function test_each_check_refuses_its_violation(): void
    {
        $checks = [
            'reconciliation_runs_outcome_allowed' => ['outcome' => 'PARTIAL'],
            'reconciliation_runs_products_checked_not_negative' => ['products_checked' => -1],
            'reconciliation_runs_blocks_written_not_negative' => ['blocks_written' => -1],
            'reconciliation_runs_other_differences_not_negative' => ['other_differences' => -1],
            'reconciliation_runs_scope_allowed' => ['scope' => 'SALE'],
            'reconciliation_runs_run_trigger_allowed' => ['run_trigger' => 'MANUAL'],
            'reconciliation_runs_finished_after_started' => ['finished_at' => '2026-11-15 19:59:59+00'],
        ];

        foreach ($checks as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $c->table('reconciliation_runs')->insert([...self::row(), ...$values]), '23514', $constraint, $this->runtime());
        }

        $this->runtime()->table('reconciliation_runs')->insert(self::row());
        $this->assertSame(1, $this->observer()->table('reconciliation_runs')->count());
    }

    public function test_the_runtime_role_inserts_and_no_role_of_the_application_changes_a_row(): void
    {
        $this->runtime()->table('reconciliation_runs')->insert(self::row());

        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table('reconciliation_runs')->insert(self::row()));

        foreach ([$this->runtime(), $this->backup()] as $connection) {
            $this->assertDenied($connection, fn (Connection $c) => $c->table('reconciliation_runs')->update(['outcome' => 'CLEAN']));
            $this->assertDenied($connection, fn (Connection $c) => $c->table('reconciliation_runs')->delete());
            $this->assertDenied($connection, fn (Connection $c) => $c->table('reconciliation_runs')->truncate());
        }

        $this->assertSame(1, $this->runtime()->table('reconciliation_runs')->count());
        $this->assertSame(1, $this->backup()->table('reconciliation_runs')->count());
    }

    public function test_the_actual_grants_are_those_of_schema_section_10(): void
    {
        $roles = DatabaseRoles::of($this->migration());
        $privileges = fn (string $role): array => array_map(
            fn (mixed $row): mixed => ((array) $row)['privilege_type'] ?? null,
            $this->migration()->select(
                'select privilege_type from information_schema.role_table_grants where table_name = ? and grantee = ? order by privilege_type',
                ['reconciliation_runs', $role],
            ),
        );

        $this->assertSame(['INSERT', 'SELECT'], $privileges($roles->runtime));
        $this->assertSame(['SELECT'], $privileges($roles->backup));
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(): array
    {
        return [
            'started_at' => '2026-11-15 20:00:00+00',
            'finished_at' => '2026-11-15 20:01:00+00',
            'outcome' => 'MISMATCH',
            'products_checked' => 3,
            'blocks_written' => 1,
            'other_differences' => 1,
            'scope' => 'ALL',
            'run_trigger' => 'SCHEDULE',
        ];
    }
}
