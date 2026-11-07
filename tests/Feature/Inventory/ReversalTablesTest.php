<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The table of TASK-026 and the keys it completes (AC-026-01; SCHEMA sections 3.6, 5.7, 5.8, 9.4, 10
 * and 11; BR-INV-34 to BR-INV-36; the migration 2026_11_07_000001_create_reversals_table;
 * EXECUTION_CONTEXT section 10, a table, and EXC-44; suites DB01, KEEP01 and INV03): the constraint
 * family, written directly by the runtime role — the source's type, the exact inverse, once per source;
 * the REVERSAL's key to its reversal and the clearance's key to the reversal of its own source; the
 * deferred family, a reversal without its REVERSAL failing at commit and leaving nothing; the privilege
 * family; the retention family; and the indexes of SCHEMA section 11.
 */
final class ReversalTablesTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $command = 0;

    private int $product = 0;

    /** The RECEIPT of 5 that the rows reverse. */
    private int $receipt = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category()]);
        $id = $this->observer()->table('products')->where('id', $this->product)->value('created_command_id');
        $this->command = is_int($id) ? $id : 0;
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
        $this->receipt = TokoContoh::movementOf(TokoContoh::stock($this->product, '5', '5000'));
    }

    public function test_each_check_and_key_of_a_reversal_refuses_its_violation(): void
    {
        foreach ([
            // BR-INV-36: an OPENING, a RECEIPT or a MANUAL_ISSUE only.
            'reversals_original_type_allowed' => [['original_type' => 'ADJUSTMENT'], ['original_type' => 'SALE_ISSUE'], ['original_type' => 'RETURN_RECEIPT'], ['original_type' => 'REVERSAL']],
            // BR-INV-34: the exact inverse.
            'reversals_inverse_exact' => [['inverse_quantity' => '-4'], ['inverse_quantity' => '5']],
            'reversals_reason_length' => [['reason' => ''], ['reason' => str_repeat('r', 1001)]],
            'reversals_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReversal($c, $values), '23514', $constraint);
            }
        }

        // The key to the source copies its product, type and quantity (BR-INV-34).
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);

        foreach ([['product_id' => $other], ['original_type' => 'MANUAL_ISSUE'], ['original_quantity' => '4', 'inverse_quantity' => '-4'], ['original_movement_id' => 999999]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReversal($c, $values), '23503', 'reversals_original_fk');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReversal($c, ['actor_user_id' => 999999]), '23503', 'reversals_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReversal($c, ['command_id' => 999999]), '23503', 'reversals_command_fk');

        foreach (['original_movement_id', 'product_id', 'original_type', 'original_quantity', 'inverse_quantity', 'reason', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReversal($c, [$column => null]), '23502');
        }

        // BR-INV-35, RACE-10: a source is reversed at most once.
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertReversal($c);
            $this->insertReversal($c);
        }, '23505', 'reversals_original_movement_unique');
    }

    public function test_the_reversal_movement_and_the_clearance_name_their_reversal(): void
    {
        // EXC-44: the REVERSAL moves exactly the reversal's product and inverse quantity.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMovement($c, $this->insertReversal($c), ['quantity' => '-4']), '23503', 'stock_movements_reversal_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMovement($c, 999999), '23503', 'stock_movements_reversal_fk');
        $this->assertWriteRefused(function (Connection $c): void {
            $reversal = $this->insertReversal($c);
            $this->insertMovement($c, $reversal);
            $this->insertMovement($c, $reversal, ['product_sequence' => 3]);
        }, '23505', 'stock_movements_reversal_unique');

        // SCHEMA section 5.8: a clearance SOURCE_REVERSED names the reversal of its mark's own source.
        $evidence = TokoContoh::evidenceOf($this->receipt);
        $second = TokoContoh::movementOf(TokoContoh::stock($this->product, '5', '5000'));
        $this->assertWriteRefused(function (Connection $c) use ($evidence, $second): void {
            $reversal = $this->insertReversal($c, ['original_movement_id' => $second]);
            $this->insertMovement($c, $reversal, ['product_sequence' => 3]);
            $this->insertClearance($c, $this->insertMark($c, $evidence), $evidence, $reversal);
        }, '23503', 'cost_incomplete_mark_clearances_reversal_fk');
    }

    public function test_a_reversal_without_its_movement_fails_at_commit_and_leaves_nothing(): void
    {
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertReversal($c), 'reversals_movement_fk', ['reversals', 'stock_movements']);
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::reverse($this->receipt);
        $runtime = $this->runtime();

        // Class F: added, never changed or removed.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('reversals')->update(['reason' => 'Lain']));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('reversals')->delete());
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('reversals')->truncate());
        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table('reversals')->delete());
        $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', ['reversals_id_seq']));
        $this->assertSame(1, $this->backup()->table('reversals')->count());
    }

    public function test_a_reversal_only_adds_to_the_facts_and_leaves_its_source(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; BR-INV-34: the original stays.
        $this->assertRetained(['stock_movements', 'acquisition_cost_evidence', 'receiving_lines'], function (): void {
            TokoContoh::reverse($this->receipt);
        });

        $this->assertSame(['RECEIPT', 'REVERSAL'], $this->observer()->table('stock_movements')->where('product_id', $this->product)->orderBy('product_sequence')->pluck('movement_type')->all());
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', ['reversals', 'cost_incomplete_mark_clearances'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            'reversals_original_movement_unique' => 'CREATE UNIQUE INDEX reversals_original_movement_unique ON public.reversals USING btree (original_movement_id)',
            'reversals_command_unique' => 'CREATE UNIQUE INDEX reversals_command_unique ON public.reversals USING btree (command_id)',
            'reversals_id_product_inverse_unique' => 'CREATE UNIQUE INDEX reversals_id_product_inverse_unique ON public.reversals USING btree (id, product_id, inverse_quantity)',
            'reversals_id_original_movement_unique' => 'CREATE UNIQUE INDEX reversals_id_original_movement_unique ON public.reversals USING btree (id, original_movement_id)',
            'reversals_actor_user_id_index' => 'CREATE INDEX reversals_actor_user_id_index ON public.reversals USING btree (actor_user_id)',
            'reversals_product_id_index' => 'CREATE INDEX reversals_product_id_index ON public.reversals USING btree (product_id)',
            'cost_incomplete_mark_clearances_reversal_index' => 'CREATE INDEX cost_incomplete_mark_clearances_reversal_index ON public.cost_incomplete_mark_clearances USING btree (clearing_reversal_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    /**
     * The reversal of the receipt of 5, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReversal(Connection $c, array $values = []): int
    {
        return $c->table('reversals')->insertGetId([
            'original_movement_id' => $this->receipt, 'product_id' => $this->product, 'original_type' => 'RECEIPT', 'original_quantity' => '5', 'inverse_quantity' => '-5',
            'reason' => 'Salah catat', 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * The REVERSAL of a reversal of 5, at sequence 2 of the product, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertMovement(Connection $c, int $reversal, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 2, 'movement_type' => 'REVERSAL', 'quantity' => '-5',
            'reversal_id' => $reversal, 'line_quantity' => null, 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'command_id' => $this->command, ...$values,
        ]);
    }

    private function insertMark(Connection $c, int $evidence): int
    {
        return $c->table('cost_incomplete_marks')->insertGetId([
            'evidence_id' => $evidence, 'product_id' => $this->product, 'evidence_movement_id' => $this->receipt, 'rejected_operation' => 'cost_correction.confirm',
            'rejected_source_intent_id' => '00000000-0000-4000-8000-000000000001', 'rejection_code' => 'MOVEMENT_CAP', 'corrections_seen' => 0, 'recorded_at' => now(),
            'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
        ]);
    }

    private function insertClearance(Connection $c, int $mark, int $evidence, int $reversal): void
    {
        $c->table('cost_incomplete_mark_clearances')->insert([
            'mark_id' => $mark, 'evidence_id' => $evidence, 'evidence_movement_id' => $this->receipt, 'clearing_kind' => 'SOURCE_REVERSED', 'clearing_correction_id' => null,
            'clearing_reversal_id' => $reversal, 'cleared_at' => now(), 'actor_kind' => 'USER', 'actor_user_id' => $this->owner, 'actor_process' => null,
            'actor_display_name' => 'Hendra', 'command_id' => null,
        ]);
    }
}
