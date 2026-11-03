<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The two tables of TASK-024 and the key it completes on stock_movements (AC-024-02; SCHEMA
 * sections 3.2, 3.6, 3.7, 5.7, 9.4, 10 and 11; BR-INV-31 to BR-INV-33; the migration
 * 2026_11_03_000002_create_adjustment_tables; EXECUTION_CONTEXT section 10, a table, and EXC-44;
 * suites DB01, KEEP01 and INV03): the constraint family, written directly by the runtime role; the
 * deferred family — a line without its movement, an increase without its cost evidence and a
 * decrease without its allocation, each failing at commit and leaving nothing; the privilege
 * family, under which nothing is changed or removed; the retention family; and the indexes of
 * SCHEMA section 11. The grants against the matrix are PrivilegeCheckTest's, for every table.
 */
final class AdjustmentTablesTest extends DatabaseTestCase
{
    /** The two tables, both class F: added, never changed or removed. */
    private const array TABLES = ['adjustments', 'adjustment_lines'];

    private int $owner = 0;

    private int $command = 0;

    private int $product = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $category]);
        $this->command = $this->commandOf($this->product);
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
    }

    public function test_each_check_and_key_of_an_adjustment_refuses_its_violation(): void
    {
        // BR-INV-31: a reason, a count and a reference, all mandatory.
        foreach ([
            'adjustments_reason_length' => [['reason' => ''], ['reason' => str_repeat('r', 1001)]],
            'adjustments_count_reference_length' => [['count_reference' => ''], ['count_reference' => str_repeat('c', 101)]],
            'adjustments_reference_length' => [['reference' => ''], ['reference' => str_repeat('f', 101)]],
            'adjustments_line_count_range' => [['line_count' => 0], ['line_count' => 201]],
            'adjustments_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustment($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustment($c, ['actor_user_id' => 999999]), '23503', 'adjustments_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustment($c, ['command_id' => 999999]), '23503', 'adjustments_command_fk');

        foreach (['reason', 'count_reference', 'reference', 'line_count', 'confirmed_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustment($c, [$column => null]), '23502');
        }

        // One adjustment per command; the longest texts admitted.
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertAdjustment($c);
            $this->insertAdjustment($c);
        }, '23505', 'adjustments_command_unique');

        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertAdjustment($runtime, ['reason' => str_repeat('r', 1000), 'count_reference' => str_repeat('c', 100), 'reference' => str_repeat('f', 100), 'line_count' => 200]);
            $this->assertSame(1, $runtime->table('adjustments')->count());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_each_check_and_key_of_an_adjustment_line_and_of_its_movement_refuses_its_violation(): void
    {
        foreach ([
            'adjustment_lines_line_no_range' => [['line_no' => 0], ['line_no' => 201]],
            // Signed, never zero, at most the range of a movement either way (SCHEMA section 5.7).
            'adjustment_lines_quantity_range' => [['quantity' => '0'], ['quantity' => '100000000'], ['quantity' => '-100000000'], ['quantity' => 'NaN']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertAdjustment($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, 999999), '23503', 'adjustment_lines_adjustment_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertAdjustment($c), ['product_id' => 999999]), '23503', 'adjustment_lines_product_fk');

        foreach (['adjustment_id', 'line_no', 'product_id', 'quantity'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertAdjustment($c), [$column => null]), '23502');
        }

        $this->assertWriteRefused(function (Connection $c): void {
            $adjustment = $this->insertAdjustment($c);
            $this->insertLine($c, $adjustment);
            $this->insertLine($c, $adjustment);
        }, '23505', 'adjustment_lines_adjustment_line_unique');

        // EXC-44: the movement moves exactly its line's product and signed quantity, and names a line.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustmentMovement($c, $this->insertLine($c, $this->insertAdjustment($c)), ['product_id' => $other]), '23503', 'stock_movements_adjustment_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustmentMovement($c, $this->insertLine($c, $this->insertAdjustment($c)), ['quantity' => '2', 'line_quantity' => '2']), '23503', 'stock_movements_adjustment_line_fk');
        // A decrease line moves stock down, never up.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustmentMovement($c, $this->insertLine($c, $this->insertAdjustment($c), ['quantity' => '-1']), ['quantity' => '1', 'line_quantity' => '1']), '23503', 'stock_movements_adjustment_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAdjustmentMovement($c, 999999), '23503', 'stock_movements_adjustment_line_fk');

        // One movement per line.
        $this->assertWriteRefused(function (Connection $c): void {
            $line = $this->insertLine($c, $this->insertAdjustment($c));
            $this->insertAdjustmentMovement($c, $line);
            $this->insertAdjustmentMovement($c, $line, ['product_sequence' => 2]);
        }, '23505', 'stock_movements_adjustment_line_unique');
    }

    public function test_a_line_without_its_movement_and_a_movement_without_its_cost_record_fail_at_commit_and_leave_nothing(): void
    {
        // Every line posts its ADJUSTMENT (SCHEMA section 5.7).
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertLine($c, $this->insertAdjustment($c)), 'adjustment_lines_movement_fk', self::TABLES);

        // AC-024-02, BR-INV-08, BR-INV-32: an increase needs its cost evidence, a decrease its
        // issue allocation (SCHEMA section 3.7).
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertAdjustmentMovement($c, $this->insertLine($c, $this->insertAdjustment($c))), 'stock_movements_cost_evidence_presence_fk', [...self::TABLES, 'stock_movements']);
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertAdjustmentMovement($c, $this->insertLine($c, $this->insertAdjustment($c), ['quantity' => '-1']), ['quantity' => '-1', 'line_quantity' => '-1']), 'stock_movements_issue_allocation_presence_fk', [...self::TABLES, 'stock_movements']);
    }

    public function test_an_adjustment_with_its_lines_movements_evidence_and_allocation_commits_in_one_transaction(): void
    {
        // Through RC-09's Action: an increase with its evidence and a decrease with its allocation.
        TokoContoh::stock($this->product, '5', '5000');
        TokoContoh::adjust([
            ['productId' => $this->product, 'direction' => 'INCREASE', 'quantity' => '2', 'cost' => ['status' => 'KNOWN', 'amount' => '2000']],
            ['productId' => $this->product, 'direction' => 'DECREASE', 'quantity' => '3'],
        ]);

        $this->assertSame(1, $this->observer()->table('adjustments')->count());
        $this->assertSame(['2.000', '-3.000'], $this->observer()->table('adjustment_lines')->orderBy('line_no')->pluck('quantity')->all());
        $this->assertSame(1, $this->observer()->table('acquisition_cost_evidence')->where('movement_type', 'ADJUSTMENT')->count());
        $this->assertSame(1, $this->observer()->table('issue_cost_allocations')->where('issue_type', 'ADJUSTMENT')->count());
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::stock($this->product, '5');
        TokoContoh::adjust([['productId' => $this->product, 'direction' => 'DECREASE', 'quantity' => '1']]);
        $runtime = $this->runtime();
        $column = ['adjustments' => 'confirmed_at', 'adjustment_lines' => 'line_no'];

        // Class F: added, never changed or removed.
        foreach (self::TABLES as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column[$table] => $table === 'adjustment_lines' ? 2 : now()]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->truncate());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
            $this->assertGreaterThan(0, $this->backup()->table($table)->count(), $table);
        }

        $this->assertDenied($runtime, fn (Connection $c) => $c->table('adjustment_lines')->update(['quantity' => '-2']));

        // The runtime role takes no value from a sequence (SCH-44).
        foreach (['adjustments_id_seq', 'adjustment_lines_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }
    }

    public function test_a_later_adjustment_only_adds_to_the_facts_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; BR-INV-33: an adjustment never
        // removes or disguises an earlier fact.
        TokoContoh::stock($this->product, '9');
        TokoContoh::adjust([['productId' => $this->product, 'direction' => 'DECREASE', 'quantity' => '2']]);

        $this->assertRetained(['adjustments', 'adjustment_lines', 'stock_movements', 'acquisition_cost_evidence', 'issue_cost_allocations'], function (): void {
            TokoContoh::adjust([['productId' => $this->product, 'direction' => 'INCREASE', 'quantity' => '4', 'cost' => ['status' => 'UNKNOWN']]], 'Ditemukan di gudang', 'HIT-02', 'REF-02');
            TokoContoh::adjust([['productId' => $this->product, 'direction' => 'DECREASE', 'quantity' => '1']], 'Rusak saat hitung', 'HIT-03', 'REF-03');
        });

        $this->assertSame(3, $this->observer()->table('adjustments')->count());
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', [...self::TABLES, 'stock_movements'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            // One movement per line, and the line's key.
            'stock_movements_adjustment_line_unique' => 'CREATE UNIQUE INDEX stock_movements_adjustment_line_unique ON public.stock_movements USING btree (adjustment_line_id)',
            'adjustments_command_unique' => 'CREATE UNIQUE INDEX adjustments_command_unique ON public.adjustments USING btree (command_id)',
            'adjustment_lines_adjustment_line_unique' => 'CREATE UNIQUE INDEX adjustment_lines_adjustment_line_unique ON public.adjustment_lines USING btree (adjustment_id, line_no)',
            'adjustment_lines_id_product_quantity_unique' => 'CREATE UNIQUE INDEX adjustment_lines_id_product_quantity_unique ON public.adjustment_lines USING btree (id, product_id, quantity)',
            // Every foreign-key column that no other key leads.
            'adjustments_actor_user_id_index' => 'CREATE INDEX adjustments_actor_user_id_index ON public.adjustments USING btree (actor_user_id)',
            'adjustment_lines_product_id_index' => 'CREATE INDEX adjustment_lines_product_id_index ON public.adjustment_lines USING btree (product_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    public function test_an_adjustment_holds_no_cost_and_no_draft(): void
    {
        // BR-INV-32: the cost of a line is its movement's evidence or allocation; an adjustment
        // exists only once it is confirmed, so there is no draft table (RP-410's draft is in page
        // memory).
        $columns = fn (string $table): array => self::sorted($this->observer()->table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $table)->pluck('column_name')->all());

        $this->assertSame(['adjustment_id', 'id', 'line_no', 'product_id', 'quantity'], $columns('adjustment_lines'));
        $this->assertSame(['actor_display_name', 'actor_user_id', 'command_id', 'confirmed_at', 'count_reference', 'id', 'line_count', 'reason', 'reference'], $columns('adjustments'));
        $this->assertSame(['adjustment_lines', 'adjustments'], array_values(array_filter(self::sorted($this->observer()->table('pg_tables')->where('schemaname', 'public')->pluck('tablename')->all()), static fn (string $name): bool => str_contains($name, 'adjustment'))));
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
    private function insertAdjustment(Connection $c, array $values = []): int
    {
        return $c->table('adjustments')->insertGetId([
            'reason' => 'Hitung fisik', 'count_reference' => 'HIT-01', 'reference' => 'REF-01', 'line_count' => 1, 'confirmed_at' => now(), 'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * A line increasing the product by one unit, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertLine(Connection $c, int $adjustment, array $values = []): int
    {
        return $c->table('adjustment_lines')->insertGetId(['adjustment_id' => $adjustment, 'line_no' => 1, 'product_id' => $this->product, 'quantity' => '1', ...$values]);
    }

    /**
     * The ADJUSTMENT of a line increasing by one unit, at sequence 1 of the product, unless
     * $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertAdjustmentMovement(Connection $c, int $line, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 1, 'movement_type' => 'ADJUSTMENT', 'quantity' => '1',
            'adjustment_line_id' => $line, 'line_quantity' => '1', 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'command_id' => $this->command, ...$values,
        ]);
    }
}
