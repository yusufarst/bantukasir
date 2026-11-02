<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The four tables of TASK-023 and the two keys it completes on stock_movements (AC-023-04; SCHEMA
 * sections 3.2, 3.6, 3.7, 5.7, 5.8, 9.4, 10 and 11, SCH-13; ADR-008 sections 1 and 2; G-13, G-14;
 * the migration 2026_11_03_000001_create_manual_issue_tables; EXECUTION_CONTEXT section 10, a
 * table, and EXC-44; suites DB01, KEEP01, INV02 and FIN01): the constraint family, written directly
 * by the runtime role; the deferred family — an issue movement without its allocation, an
 * allocation without its projection, a line without its movement, each failing at commit and
 * leaving nothing; the privilege family, under which a fact is never changed and a projection
 * only in its own columns; the retention family; and the indexes of SCHEMA section 11. The grants
 * against the matrix are PrivilegeCheckTest's, for every table.
 */
final class IssueTablesTest extends DatabaseTestCase
{
    /** The four tables. */
    private const array TABLES = ['manual_issues', 'manual_issue_lines', 'issue_cost_allocations', 'issue_cost_allocation_projections'];

    /** Their facts, class F: added, never changed or removed. */
    private const array FACTS = ['manual_issues', 'manual_issue_lines', 'issue_cost_allocations'];

    private int $owner = 0;

    private int $command = 0;

    private int $product = 0;

    /** @var list<int> the identities of other commands, for several issues in one transaction */
    private array $commands = [];

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $category]);
        $this->command = $this->commandOf($this->product);

        foreach (range(1, 5) as $index) {
            $this->commands[] = $this->commandOf(TokoContoh::product('CMD-0'.(string) $index, ['categoryId' => $category]));
        }

        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
    }

    public function test_each_check_and_key_of_a_manual_issue_refuses_its_violation(): void
    {
        foreach ([
            'manual_issues_reason_code_allowed' => [['reason_code' => 'SALE'], ['reason_code' => 'damaged'], ['reason_code' => '']],
            'manual_issues_note_length' => [['note' => ''], ['note' => str_repeat('n', 1001)]],
            'manual_issues_line_count_range' => [['line_count' => 0], ['line_count' => 201]],
            'manual_issues_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertIssue($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertIssue($c, ['actor_user_id' => 999999]), '23503', 'manual_issues_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIssue($c, ['command_id' => 999999]), '23503', 'manual_issues_command_fk');

        foreach (['reason_code', 'line_count', 'confirmed_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertIssue($c, [$column => null]), '23502');
        }

        // One issue per command; each reason of BR-INV-19, the longest note and none, admitted.
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertIssue($c);
            $this->insertIssue($c);
        }, '23505', 'manual_issues_command_unique');

        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            foreach (['DAMAGED', 'INTERNAL_USE', 'LOST', 'SAMPLE', 'OTHER'] as $index => $reason) {
                $this->insertIssue($runtime, ['reason_code' => $reason, 'note' => $index === 0 ? str_repeat('n', 1000) : null, 'command_id' => $this->commands[$index]]);
            }

            $this->assertSame(5, $runtime->table('manual_issues')->count());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_each_check_and_key_of_a_manual_issue_line_and_of_its_movement_refuses_its_violation(): void
    {
        foreach ([
            'manual_issue_lines_line_no_range' => [['line_no' => 0], ['line_no' => 201]],
            'manual_issue_lines_quantity_range' => [['quantity' => '0'], ['quantity' => '-1'], ['quantity' => '100000000'], ['quantity' => 'NaN']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertIssue($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, 999999), '23503', 'manual_issue_lines_issue_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertIssue($c), ['product_id' => 999999]), '23503', 'manual_issue_lines_product_fk');

        foreach (['manual_issue_id', 'line_no', 'product_id', 'quantity'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertIssue($c), [$column => null]), '23502');
        }

        $this->assertWriteRefused(function (Connection $c): void {
            $issue = $this->insertIssue($c);
            $this->insertLine($c, $issue);
            $this->insertLine($c, $issue);
        }, '23505', 'manual_issue_lines_issue_line_unique');

        // EXC-44: the movement moves exactly its line's product and quantity, and names a line.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIssueMovement($c, $this->insertLine($c, $this->insertIssue($c)), ['product_id' => $other]), '23503', 'stock_movements_manual_issue_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIssueMovement($c, $this->insertLine($c, $this->insertIssue($c)), ['quantity' => '-2', 'line_quantity' => '2']), '23503', 'stock_movements_manual_issue_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertIssueMovement($c, 999999), '23503', 'stock_movements_manual_issue_line_fk');

        // One movement per line.
        $this->assertWriteRefused(function (Connection $c): void {
            $line = $this->insertLine($c, $this->insertIssue($c));
            $this->insertIssueMovement($c, $line);
            $this->insertIssueMovement($c, $line, ['product_sequence' => 2]);
        }, '23505', 'stock_movements_manual_issue_line_unique');
    }

    public function test_each_check_and_key_of_an_allocation_fact_refuses_its_violation(): void
    {
        foreach ([
            'issue_cost_allocations_issue_type_allowed' => [['issue_type' => 'RECEIPT']],
            'issue_cost_allocations_movement_quantity_range' => [['movement_quantity' => '1', 'issued_quantity' => '-1'], ['movement_quantity' => '0', 'issued_quantity' => '0'], ['movement_quantity' => '-100000000', 'issued_quantity' => '100000000']],
            'issue_cost_allocations_issued_quantity' => [['issued_quantity' => '2'], ['issued_quantity' => '-1']],
            // Set exactly for a SALE_ISSUE (SCH-13: its null passes nothing).
            'issue_cost_allocations_sale_line' => [['sale_line_id' => 7], ['issue_type' => 'SALE_ISSUE']],
            'issue_cost_allocations_at_commit_status_allowed' => [['at_commit_status' => 'CANCELLED', 'at_commit_amount' => null], ['at_commit_status' => 'ZERO', 'at_commit_amount' => null]],
            // UNKNOWN is never zero: a status of its own, with no amount (BR-DOM-08).
            'issue_cost_allocations_status_amount' => [['at_commit_amount' => null], ['at_commit_status' => 'UNKNOWN'], ['at_commit_status' => 'UNKNOWN', 'at_commit_amount' => '0']],
            // The column's own scale bounds it above; the check refuses a negative and NaN.
            'issue_cost_allocations_at_commit_amount_range' => [['at_commit_amount' => '-0.000001'], ['at_commit_amount' => 'NaN']],
            'issue_cost_allocations_based_on_publication_positive' => [['based_on_publication_no' => 0]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, $this->issueMovement($c), $values), '23514', $constraint);
            }
        }

        // The copied type, quantity and position are those of the movement (SCHEMA section 5.8).
        foreach ([['product_id' => TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')])], ['issue_type' => 'ADJUSTMENT'], ['movement_quantity' => '-2', 'issued_quantity' => '2'], ['product_sequence' => 2]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, $this->issueMovement($c), $values), '23503', 'issue_cost_allocations_movement_fk');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, 999999), '23503', 'issue_cost_allocations_movement_fk');

        // A SALE_ISSUE allocation names its movement's own SaleLine (the key created here; its key
        // to sale_lines is TASK-028's). The SaleLine's table does not exist yet, so the movement is
        // written with a number the key of TASK-028 will check.
        $this->assertWriteRefused(function (Connection $c): void {
            $movement = $this->insertMovement($c, ['movement_type' => 'SALE_ISSUE', 'quantity' => '-1', 'sale_line_id' => 7, 'line_quantity' => '1']);
            $this->insertAllocation($c, $movement, ['issue_type' => 'SALE_ISSUE', 'sale_line_id' => 8]);
        }, '23503', 'issue_cost_allocations_movement_sale_line_fk');

        foreach (['movement_id', 'product_id', 'issue_type', 'movement_quantity', 'product_sequence', 'issued_quantity', 'at_commit_status', 'based_on_publication_no', 'recorded_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, $this->issueMovement($c), [$column => null]), '23502');
        }

        // The largest allocation, the value's whole range, is admitted (TECHNICAL_RULES section 11.1).
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertAllocation($runtime, $this->issueMovement($runtime), ['at_commit_amount' => '999999999999999.999999']);
            $this->assertSame('999999999999999.999999', $runtime->table('issue_cost_allocations')->value('at_commit_amount'));
        } finally {
            $runtime->rollBack();
        }

        // One allocation per movement.
        $this->assertWriteRefused(function (Connection $c): void {
            $movement = $this->issueMovement($c);
            $this->insertAllocation($c, $movement);
            $this->insertAllocation($c, $movement);
        }, '23505', 'issue_cost_allocations_movement_unique');
    }

    public function test_each_check_and_key_of_a_projection_refuses_its_violation(): void
    {
        foreach ([
            'issue_cost_allocation_projections_current_status_allowed' => [['current_status' => 'ZERO', 'current_amount' => null]],
            // Only a manual issue is ever cancelled by a reversal (BR-COST-32, BR-INV-36).
            'issue_cost_allocation_projections_cancelled_manual' => [['issue_type' => 'ADJUSTMENT', 'current_status' => 'CANCELLED', 'current_amount' => null]],
            'issue_cost_allocation_projections_status_amount' => [['current_amount' => null], ['current_status' => 'UNKNOWN'], ['current_status' => 'CANCELLED']],
            'issue_cost_allocation_projections_current_amount_range' => [['current_amount' => '-0.000001'], ['current_amount' => 'NaN']],
            'issue_cost_allocation_projections_publication_no_positive' => [['publication_no' => 0]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertProjection($c, $this->insertAllocation($c, $this->issueMovement($c)), $values), '23514', $constraint);
            }
        }

        // The projection's type is its fact's; it names one fact, once.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProjection($c, $this->insertAllocation($c, $this->issueMovement($c)), ['issue_type' => 'ADJUSTMENT']), '23503', 'issue_cost_allocation_projections_allocation_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertProjection($c, 999999), '23503', 'issue_cost_allocation_projections_allocation_fk');
        $this->assertWriteRefused(function (Connection $c): void {
            $allocation = $this->insertAllocation($c, $this->issueMovement($c));
            $this->insertProjection($c, $allocation);
            $this->insertProjection($c, $allocation);
        }, '23505', 'issue_cost_allocation_projections_pkey');

        foreach (['issue_type', 'current_status', 'publication_no', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertProjection($c, $this->insertAllocation($c, $this->issueMovement($c)), [$column => null]), '23502');
        }

        // KNOWN, UNKNOWN and, for a manual issue, CANCELLED are admitted (TASK-026 writes the last).
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            foreach ([['current_status' => 'KNOWN', 'current_amount' => '0'], ['current_status' => 'UNKNOWN', 'current_amount' => null], ['current_status' => 'CANCELLED', 'current_amount' => null]] as $index => $values) {
                $this->insertProjection($runtime, $this->insertAllocation($runtime, $this->issueMovement($runtime, $index + 1)), $values);
            }

            $this->assertSame(['KNOWN', 'UNKNOWN', 'CANCELLED'], $runtime->table('issue_cost_allocation_projections')->orderBy('allocation_id')->pluck('current_status')->all());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_an_issue_movement_without_its_allocation_fails_at_commit_and_leaves_nothing(): void
    {
        // AC-023-04, BR-INV-08, G-13: every statement is accepted, and the commit refuses them.
        $this->assertFailsAtCommit(fn (Connection $c) => $this->issueMovement($c), 'stock_movements_issue_allocation_presence_fk', ['manual_issues', 'manual_issue_lines', 'stock_movements']);

        // A SALE_ISSUE and a negative ADJUSTMENT need theirs too (SCHEMA section 3.7).
        foreach ([
            ['movement_type' => 'SALE_ISSUE', 'quantity' => '-1', 'sale_line_id' => 7, 'line_quantity' => '1'],
            ['movement_type' => 'ADJUSTMENT', 'quantity' => '-1', 'adjustment_line_id' => 7, 'line_quantity' => '-1'],
        ] as $values) {
            $this->assertFailsAtCommit(fn (Connection $c) => $this->insertMovement($c, $values), 'stock_movements_issue_allocation_presence_fk', ['stock_movements']);
        }
    }

    public function test_an_allocation_without_its_projection_and_a_line_without_its_movement_fail_at_commit(): void
    {
        // G-14: every fact has its projection, inserted with it.
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertAllocation($c, $this->issueMovement($c)), 'issue_cost_allocations_projection_fk', ['manual_issues', 'manual_issue_lines', 'stock_movements', 'issue_cost_allocations']);

        // Every line posts its MANUAL_ISSUE (SCHEMA section 5.7).
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertLine($c, $this->insertIssue($c)), 'manual_issue_lines_movement_fk', ['manual_issues', 'manual_issue_lines']);
    }

    public function test_an_issue_with_its_line_movement_allocation_and_projection_commits_in_one_transaction(): void
    {
        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertProjection($c, $this->insertAllocation($c, $this->issueMovement($c)));
        });

        foreach (self::TABLES as $table) {
            $this->assertSame(1, $this->observer()->table($table)->count(), $table);
        }
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::stock($this->product, '5');
        TokoContoh::issue([['productId' => $this->product, 'quantity' => '2']]);
        $runtime = $this->runtime();
        $column = ['manual_issues' => 'confirmed_at', 'manual_issue_lines' => 'line_no', 'issue_cost_allocations' => 'recorded_at'];

        // Class F: added, never changed or removed — an allocation fact above all (D-060).
        foreach (self::FACTS as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column[$table] => $table === 'manual_issue_lines' ? 2 : now()]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
        }

        $this->assertDenied($runtime, fn (Connection $c) => $c->table('issue_cost_allocations')->update(['at_commit_amount' => '1']));

        // Class P: only the columns of a replay's publication, and never removed (ADR-008 section 4.3).
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('issue_cost_allocation_projections')->update(['allocation_id' => 999999]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('issue_cost_allocation_projections')->update(['issue_type' => 'MANUAL_ISSUE']));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('issue_cost_allocation_projections')->delete());

        $runtime->beginTransaction();

        try {
            $this->assertSame(1, $runtime->table('issue_cost_allocation_projections')->update(['current_status' => 'UNKNOWN', 'current_amount' => null, 'publication_no' => 9, 'updated_at' => now()]));
        } finally {
            $runtime->rollBack();
        }

        foreach (self::TABLES as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->truncate());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
            $this->assertGreaterThan(0, $this->backup()->table($table)->count(), $table);
        }

        // The runtime role takes no value from a sequence (SCH-44).
        foreach (['manual_issues_id_seq', 'manual_issue_lines_id_seq', 'issue_cost_allocations_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }
    }

    public function test_a_later_issue_only_adds_to_the_facts_and_leaves_every_earlier_one_and_its_projection(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; ADR-008 section 4.2: an ordinary
        // posting writes no earlier projection.
        TokoContoh::stock($this->product, '9');
        TokoContoh::issue([['productId' => $this->product, 'quantity' => '2']]);

        $this->assertRetained(['manual_issues', 'manual_issue_lines', 'issue_cost_allocations', 'stock_movements'], function (): void {
            TokoContoh::issue([['productId' => $this->product, 'quantity' => '3']], 'LOST');
            TokoContoh::stock($this->product, '1', '100');
            TokoContoh::issue([['productId' => $this->product, 'quantity' => '1']], 'OTHER', 'Hilang di gudang');
        });

        $this->assertRetained(['issue_cost_allocation_projections'], function (): void {
            TokoContoh::issue([['productId' => $this->product, 'quantity' => '1']]);
        }, 'allocation_id');

        $this->assertSame(4, $this->observer()->table('issue_cost_allocations')->count());
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', [...self::TABLES, 'stock_movements'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            // The replay's lookup of an allocation by its movement, and one movement per line.
            'issue_cost_allocations_movement_unique' => 'CREATE UNIQUE INDEX issue_cost_allocations_movement_unique ON public.issue_cost_allocations USING btree (movement_id)',
            'stock_movements_manual_issue_line_unique' => 'CREATE UNIQUE INDEX stock_movements_manual_issue_line_unique ON public.stock_movements USING btree (manual_issue_line_id)',
            'manual_issues_command_unique' => 'CREATE UNIQUE INDEX manual_issues_command_unique ON public.manual_issues USING btree (command_id)',
            'manual_issue_lines_issue_line_unique' => 'CREATE UNIQUE INDEX manual_issue_lines_issue_line_unique ON public.manual_issue_lines USING btree (manual_issue_id, line_no)',
            'issue_cost_allocation_projections_pkey' => 'CREATE UNIQUE INDEX issue_cost_allocation_projections_pkey ON public.issue_cost_allocation_projections USING btree (allocation_id)',
            // Every foreign-key column that no other key leads.
            'manual_issues_actor_user_id_index' => 'CREATE INDEX manual_issues_actor_user_id_index ON public.manual_issues USING btree (actor_user_id)',
            'manual_issue_lines_product_id_index' => 'CREATE INDEX manual_issue_lines_product_id_index ON public.manual_issue_lines USING btree (product_id)',
            'stock_movements_issue_allocation_presence_index' => 'CREATE INDEX stock_movements_issue_allocation_presence_index ON public.stock_movements USING btree (issue_allocation_presence)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    public function test_an_issue_holds_no_cost_and_no_draft(): void
    {
        // BR-INV-21, ADR-008 section 2: the cost of a line is the allocation of its movement; an
        // issue exists only once it is confirmed, so there is no draft table (BR-INV-17's rule,
        // which RP-408 follows).
        $columns = fn (string $table): array => self::sorted($this->observer()->table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $table)->pluck('column_name')->all());

        $this->assertSame(['id', 'line_no', 'manual_issue_id', 'product_id', 'quantity'], $columns('manual_issue_lines'));
        $this->assertSame(['actor_display_name', 'actor_user_id', 'command_id', 'confirmed_at', 'id', 'line_count', 'note', 'reason_code'], $columns('manual_issues'));
        $this->assertSame(['manual_issue_lines', 'manual_issues'], array_values(array_filter(self::sorted($this->observer()->table('pg_tables')->where('schemaname', 'public')->pluck('tablename')->all()), static fn (string $name): bool => str_contains($name, 'manual_issue'))));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        $names = array_values(array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $values));
        sort($names);

        return $names;
    }

    /**
     * The identity of the command that created a product.
     */
    private function commandOf(int $product): int
    {
        $id = $this->observer()->table('products')->where('id', $product)->value('created_command_id');

        return is_int($id) ? $id : 0;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertIssue(Connection $c, array $values = []): int
    {
        return $c->table('manual_issues')->insertGetId([
            'reason_code' => 'DAMAGED', 'note' => null, 'line_count' => 1, 'confirmed_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
            'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * A line of one unit of the product, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertLine(Connection $c, int $issue, array $values = []): int
    {
        return $c->table('manual_issue_lines')->insertGetId(['manual_issue_id' => $issue, 'line_no' => 1, 'product_id' => $this->product, 'quantity' => '1', ...$values]);
    }

    /**
     * The MANUAL_ISSUE of a line of one unit, at this sequence of the product, unless $values
     * says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertIssueMovement(Connection $c, int $line, array $values = []): int
    {
        return $this->insertMovement($c, ['movement_type' => 'MANUAL_ISSUE', 'quantity' => '-1', 'manual_issue_line_id' => $line, 'line_quantity' => '1', ...$values]);
    }

    /**
     * An issue, its line and its MANUAL_ISSUE of one unit, at this sequence of the product.
     */
    private function issueMovement(Connection $c, int $sequence = 1): int
    {
        return $this->insertIssueMovement($c, $this->insertLine($c, $this->insertIssue($c, ['command_id' => $sequence === 1 ? $this->command : $this->commands[$sequence - 2]])), ['product_sequence' => $sequence]);
    }

    /**
     * A movement written directly, with no source but what $values gives.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertMovement(Connection $c, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 1, 'recorded_at' => now(), 'actor_user_id' => $this->owner,
            'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * The KNOWN allocation of the MANUAL_ISSUE of one unit at sequence 1, unless $values says
     * otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertAllocation(Connection $c, int $movement, array $values = []): int
    {
        $sequence = $c->table('stock_movements')->where('id', $movement)->value('product_sequence');

        return $c->table('issue_cost_allocations')->insertGetId([
            'movement_id' => $movement, 'product_id' => $this->product, 'issue_type' => 'MANUAL_ISSUE', 'movement_quantity' => '-1', 'product_sequence' => is_int($sequence) ? $sequence : 1,
            'issued_quantity' => '1', 'sale_line_id' => null, 'at_commit_status' => 'KNOWN', 'at_commit_amount' => '1000', 'based_on_publication_no' => 1, 'recorded_at' => now(), ...$values,
        ]);
    }

    /**
     * The projection of an allocation equal to the KNOWN one that insertAllocation() writes,
     * unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertProjection(Connection $c, int $allocation, array $values = []): void
    {
        $c->table('issue_cost_allocation_projections')->insert([
            'allocation_id' => $allocation, 'issue_type' => 'MANUAL_ISSUE', 'current_status' => 'KNOWN', 'current_amount' => '1000', 'publication_no' => 2, 'updated_at' => now(), ...$values,
        ]);
    }
}
