<?php

namespace Tests\Feature\Costing;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The three tables of TASK-025 (AC-025-07; SCHEMA sections 3.2, 3.4, 3.6, 5.8, 9.4, 10 and 11;
 * D-034, D-063; BR-COST-20 to BR-COST-22, BR-COST-27; G-15, G-21; the migration
 * 2026_11_04_000001_create_cost_correction_tables; EXECUTION_CONTEXT section 10, a table; suites DB01
 * and KEEP01): the constraint family, written directly by the runtime role — a correction always
 * records a known new value and the effective value before it, an explicit zero its evidence or
 * reason; a mark names its cost source and its cause, once per refused intent; a clearance names its
 * mark's source and exactly the cause of its kind — the privilege family, under which nothing is
 * changed or removed; the retention family; and the indexes of SCHEMA section 11. The grants against
 * the matrix are PrivilegeCheckTest's, for every table.
 */
final class CostCorrectionTablesTest extends DatabaseTestCase
{
    /** The three tables, all class F: added, never changed or removed. */
    private const array TABLES = ['cost_corrections', 'cost_incomplete_marks', 'cost_incomplete_mark_clearances'];

    private int $owner = 0;

    private int $command = 0;

    private int $product = 0;

    /** The cost source of the product's receipt, its evidence and movement. */
    private int $evidence = 0;

    private int $movement = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category()]);
        TokoContoh::stock($this->product, '10', '10000');
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
        $this->command = self::number($this->observer()->table('products')->where('id', $this->product)->value('created_command_id'));
        $this->movement = self::number($this->observer()->table('stock_movements')->where('product_id', $this->product)->value('id'));
        $this->evidence = TokoContoh::evidenceOf($this->movement);
    }

    public function test_each_check_and_key_of_a_correction_refuses_its_violation(): void
    {
        foreach ([
            'cost_corrections_correction_no_positive' => [['correction_no' => 0]],
            'cost_corrections_old_status_allowed' => [['old_status' => 'ZERO']],
            'cost_corrections_old_amount_range' => [['old_amount' => '-1'], ['old_amount' => 'NaN']],
            'cost_corrections_new_amount_range' => [['new_amount' => '-1'], ['new_amount' => 'NaN']],
            // UNKNOWN is a status, never an amount (BR-COST-03).
            'cost_corrections_old_status_amount' => [['old_status' => 'KNOWN', 'old_amount' => null], ['old_status' => 'UNKNOWN', 'old_amount' => '1000']],
            // An explicit zero carries its evidence or reason (BR-COST-04).
            'cost_corrections_zero_has_note' => [['new_amount' => '0']],
            'cost_corrections_zero_cost_note_length' => [['zero_cost_note' => ''], ['zero_cost_note' => str_repeat('z', 1001)]],
            'cost_corrections_reason_length' => [['reason' => ''], ['reason' => str_repeat('r', 1001)]],
            'cost_corrections_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, $values), '23514', $constraint);
            }
        }

        // The correction names the evidence of its own product (SCHEMA section 5.8).
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['product_id' => $other]), '23503', 'cost_corrections_evidence_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['evidence_id' => 999999]), '23503', 'cost_corrections_evidence_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['actor_user_id' => 999999]), '23503', 'cost_corrections_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['command_id' => 999999]), '23503', 'cost_corrections_command_fk');

        foreach (['evidence_id', 'product_id', 'correction_no', 'old_status', 'new_amount', 'reason', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, [$column => null]), '23502');
        }

        // G-15: one correction of each number per evidence, and one per command.
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertCorrection($c);
            $this->insertCorrection($c, ['command_id' => $this->otherCommand()]);
        }, '23505', 'cost_corrections_evidence_correction_unique');
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertCorrection($c);
            $this->insertCorrection($c, ['correction_no' => 2]);
        }, '23505', 'cost_corrections_command_unique');

        // A completion of an UNKNOWN cost, an explicit zero with its note, and the longest texts are admitted.
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertCorrection($runtime, ['old_status' => 'UNKNOWN', 'old_amount' => null, 'new_amount' => '0', 'zero_cost_note' => str_repeat('z', 1000), 'reason' => str_repeat('r', 1000), 'actor_display_name' => str_repeat('a', 100)]);
            $this->assertSame(1, $runtime->table('cost_corrections')->count());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_each_check_and_key_of_a_mark_refuses_its_violation(): void
    {
        $other = TokoContoh::product('LAIN-02', ['categoryId' => TokoContoh::category('Lain')]);

        foreach ([
            'cost_incomplete_marks_rejected_operation_format' => [['rejected_operation' => 'Cost.Correction'], ['rejected_operation' => '']],
            'cost_incomplete_marks_rejection_code_allowed' => [['rejection_code' => 'LOCK_TIMEOUT'], ['rejection_code' => 'POSTING_BLOCKED']],
            'cost_incomplete_marks_corrections_seen_range' => [['corrections_seen' => -1]],
            'cost_incomplete_marks_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertMark($c, $values), '23514', $constraint);
            }
        }

        // The mark names its cost source and that source's movement (SCHEMA section 5.8).
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMark($c, ['evidence_movement_id' => 999999]), '23503', 'cost_incomplete_marks_evidence_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMark($c, ['product_id' => $other]), '23503', 'cost_incomplete_marks_evidence_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertMark($c, ['actor_user_id' => 999999]), '23503', 'cost_incomplete_marks_actor_user_fk');

        foreach (['evidence_id', 'product_id', 'evidence_movement_id', 'rejected_operation', 'rejected_source_intent_id', 'rejection_code', 'corrections_seen', 'recorded_at', 'actor_user_id', 'actor_display_name'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertMark($c, [$column => null]), '23502');
        }

        // One mark per refused intent (SCHEMA section 5.8).
        $intent = (string) Str::uuid();
        $this->assertWriteRefused(function (Connection $c) use ($intent): void {
            $this->insertMark($c, ['rejected_source_intent_id' => $intent]);
            $this->insertMark($c, ['rejected_source_intent_id' => $intent, 'rejection_code' => 'TIME_BUDGET']);
        }, '23505', 'cost_incomplete_marks_intent_unique');
    }

    public function test_each_check_and_key_of_a_clearance_refuses_its_violation(): void
    {
        $second = TokoContoh::evidenceOf($this->movementOf(TokoContoh::stock($this->product, '2', '2000')));

        // Exactly the cause of its kind: a later correction names that correction, a reversal its
        // reversal, the recovery procedure neither (SCHEMA section 5.8).
        foreach ([
            ['clearing_kind' => 'LATER_CORRECTION'],
            ['clearing_kind' => 'SOURCE_REVERSED'],
            ['clearing_kind' => 'RECOVERY_PROCEDURE', 'clearing_reversal_id' => 1],
        ] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), $values), '23514', 'cost_incomplete_mark_clearances_cause');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['clearing_kind' => 'EDITED']), '23514', 'cost_incomplete_mark_clearances_clearing_kind_allowed');

        // The actor of SCHEMA section 3.4: a user, or a named process, never both.
        foreach ([
            ['actor_kind' => 'USER', 'actor_user_id' => null, 'actor_process' => null],
            ['actor_kind' => 'OPERATOR_PROCEDURE', 'actor_user_id' => $this->owner],
        ] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), $values), '23514', 'cost_incomplete_mark_clearances_actor');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['actor_process' => 'Cost Recovery']), '23514', 'cost_incomplete_mark_clearances_actor_process_format');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['actor_display_name' => '']), '23514', 'cost_incomplete_mark_clearances_actor_display_name_length');
        // Only the kinds of SCHEMA section 3.4 that can act here, and a user that exists (review finding R-05).
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['actor_kind' => 'ANONYMOUS']), '23514', 'cost_incomplete_mark_clearances_actor_kind_allowed');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['actor_kind' => 'USER', 'actor_user_id' => 999999, 'actor_process' => null]), '23503', 'cost_incomplete_mark_clearances_actor_user_fk');

        // The clearance belongs to its mark's source; a correction that clears it is one of that source.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, 999999), '23503', 'cost_incomplete_mark_clearances_mark_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['evidence_movement_id' => 999999]), '23503', 'cost_incomplete_mark_clearances_mark_fk');
        $this->assertWriteRefused(function (Connection $c) use ($second): void {
            $correction = $this->insertCorrection($c, ['evidence_id' => $second]);
            $this->insertClearance($c, $this->insertMark($c), ['clearing_kind' => 'LATER_CORRECTION', 'clearing_correction_id' => $correction, 'actor_kind' => 'USER', 'actor_user_id' => $this->owner, 'actor_process' => null]);
        }, '23503', 'cost_incomplete_mark_clearances_correction_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), ['command_id' => 999999]), '23503', 'cost_incomplete_mark_clearances_command_fk');

        foreach (['evidence_id', 'evidence_movement_id', 'clearing_kind', 'cleared_at', 'actor_kind', 'actor_display_name'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertClearance($c, $this->insertMark($c), [$column => null]), '23502');
        }

        // One clearance per mark.
        $this->assertWriteRefused(function (Connection $c): void {
            $mark = $this->insertMark($c);
            $this->insertClearance($c, $mark);
            $this->insertClearance($c, $mark);
        }, '23505', 'cost_incomplete_mark_clearances_pkey');

        // A later correction of the same source clears it, written by the Owner's transaction.
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $correction = $this->insertCorrection($runtime);
            $this->insertClearance($runtime, $this->insertMark($runtime), ['clearing_kind' => 'LATER_CORRECTION', 'clearing_correction_id' => $correction, 'actor_kind' => 'USER', 'actor_user_id' => $this->owner, 'actor_process' => null, 'actor_display_name' => 'Hendra', 'command_id' => $this->command]);
            $this->assertSame(1, $runtime->table('cost_incomplete_mark_clearances')->count());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $runtime = $this->runtime();
        $runtime->transaction(function (Connection $c): void {
            $this->insertClearance($c, $this->insertMark($c));
            $this->insertCorrection($c);
        });
        $column = ['cost_corrections' => 'reason', 'cost_incomplete_marks' => 'rejection_code', 'cost_incomplete_mark_clearances' => 'clearing_kind'];
        $value = ['cost_corrections' => 'Lain', 'cost_incomplete_marks' => 'TIME_BUDGET', 'cost_incomplete_mark_clearances' => 'LATER_CORRECTION'];

        // Class F: added, never changed or removed; a mark is never cleared by editing (G-21).
        foreach (self::TABLES as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column[$table] => $value[$table]]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertSame(1, $this->backup()->table($table)->count(), $table);
        }

        // The runtime role takes no value from a sequence (SCH-44).
        foreach (['cost_corrections_id_seq', 'cost_incomplete_marks_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }
    }

    public function test_a_later_correction_only_adds_to_the_facts_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; BR-COST-20, BR-COST-21: the original
        // evidence and every earlier correction stay as they were.
        TokoContoh::correctCost($this->evidence, '11000');

        $this->assertRetained(['cost_corrections', 'acquisition_cost_evidence', 'stock_movements', 'audit_events'], function (): void {
            TokoContoh::correctCost($this->evidence, '12000', 'Nota kedua');
            TokoContoh::correctCost($this->evidence, '0', 'Hadiah pemasok', 'Sampel gratis');
        });

        $this->assertSame(['1|KNOWN|10000.000000|11000.000000', '2|KNOWN|11000.000000|12000.000000', '3|KNOWN|12000.000000|0.000000'], $this->observer()->table('cost_corrections')->orderBy('correction_no')
            ->selectRaw("correction_no || '|' || old_status || '|' || coalesce(old_amount::text, '') || '|' || new_amount::text as row")->pluck('row')->all());
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', self::TABLES)->pluck('indexdef', 'indexname')->all();

        foreach ([
            'cost_corrections_evidence_correction_unique' => 'CREATE UNIQUE INDEX cost_corrections_evidence_correction_unique ON public.cost_corrections USING btree (evidence_id, correction_no)',
            'cost_corrections_command_unique' => 'CREATE UNIQUE INDEX cost_corrections_command_unique ON public.cost_corrections USING btree (command_id)',
            'cost_incomplete_marks_intent_unique' => 'CREATE UNIQUE INDEX cost_incomplete_marks_intent_unique ON public.cost_incomplete_marks USING btree (rejected_operation, rejected_source_intent_id)',
            // The marks by product and by evidence (SCHEMA section 11).
            'cost_incomplete_marks_product_id_index' => 'CREATE INDEX cost_incomplete_marks_product_id_index ON public.cost_incomplete_marks USING btree (product_id)',
            'cost_incomplete_marks_evidence_id_index' => 'CREATE INDEX cost_incomplete_marks_evidence_id_index ON public.cost_incomplete_marks USING btree (evidence_id)',
            // Every foreign-key column that no other key leads.
            'cost_corrections_product_id_index' => 'CREATE INDEX cost_corrections_product_id_index ON public.cost_corrections USING btree (product_id)',
            'cost_corrections_actor_user_id_index' => 'CREATE INDEX cost_corrections_actor_user_id_index ON public.cost_corrections USING btree (actor_user_id)',
            'cost_incomplete_marks_actor_user_id_index' => 'CREATE INDEX cost_incomplete_marks_actor_user_id_index ON public.cost_incomplete_marks USING btree (actor_user_id)',
            'cost_incomplete_mark_clearances_correction_index' => 'CREATE INDEX cost_incomplete_mark_clearances_correction_index ON public.cost_incomplete_mark_clearances USING btree (clearing_correction_id)',
            'cost_incomplete_mark_clearances_actor_user_id_index' => 'CREATE INDEX cost_incomplete_mark_clearances_actor_user_id_index ON public.cost_incomplete_mark_clearances USING btree (actor_user_id)',
            'cost_incomplete_mark_clearances_command_id_index' => 'CREATE INDEX cost_incomplete_mark_clearances_command_id_index ON public.cost_incomplete_mark_clearances USING btree (command_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    private static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    /**
     * The movement of a receiving document's first line.
     */
    private function movementOf(int $document): int
    {
        return self::number($this->observer()->table('stock_movements as m')->join('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')->where('l.receiving_document_id', $document)->value('m.id'));
    }

    /**
     * Another command identity of the shop than the product's creation.
     */
    private function otherCommand(): int
    {
        return self::number($this->observer()->table('command_identities')->where('id', '<>', $this->command)->orderBy('id')->value('id'));
    }

    /**
     * The first correction of the receipt's evidence, Rp10.000 to Rp11.000, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertCorrection(Connection $c, array $values = []): int
    {
        return $c->table('cost_corrections')->insertGetId([
            'evidence_id' => $this->evidence, 'product_id' => $this->product, 'correction_no' => 1, 'old_status' => 'KNOWN', 'old_amount' => '10000', 'new_amount' => '11000',
            'zero_cost_note' => null, 'reason' => 'Nota pemasok', 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * A mark of the receipt's evidence, refused at the movement cap, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertMark(Connection $c, array $values = []): int
    {
        return $c->table('cost_incomplete_marks')->insertGetId([
            'evidence_id' => $this->evidence, 'product_id' => $this->product, 'evidence_movement_id' => $this->movement, 'rejected_operation' => 'cost_correction.confirm',
            'rejected_source_intent_id' => (string) Str::uuid(), 'rejection_code' => 'MOVEMENT_CAP', 'corrections_seen' => 0, 'recorded_at' => now(), 'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra', ...$values,
        ]);
    }

    /**
     * The clearance of a mark by the cost recovery procedure, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertClearance(Connection $c, int $mark, array $values = []): void
    {
        $c->table('cost_incomplete_mark_clearances')->insert([
            'mark_id' => $mark, 'evidence_id' => $this->evidence, 'evidence_movement_id' => $this->movement, 'clearing_kind' => 'RECOVERY_PROCEDURE', 'clearing_correction_id' => null,
            'clearing_reversal_id' => null, 'cleared_at' => now(), 'actor_kind' => 'OPERATOR_PROCEDURE', 'actor_user_id' => null, 'actor_process' => 'cost_recovery',
            'actor_display_name' => 'cost_recovery', 'command_id' => null, ...$values,
        ]);
    }
}
