<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Database\Connection;
use LogicException;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The tables of TASK-035 and the keys it completes (AC-035-07; SCHEMA sections 5.7, 9.4, 10 and 11;
 * BR-INV-25 to BR-INV-27, BR-INV-47, BR-INV-48; D-072, D-087; G-22, G-24; the migration
 * 2026_11_15_000001_create_opening_stock_tables; EXECUTION_CONTEXT section 10, a table, and EXC-44;
 * suites DB01, KEEP01 and OPEN01): the constraint family, written directly by the runtime role — an
 * entry of 1 to 100 rows once per command, a positive line once per row and one original line per
 * product, a replacement that names the reversed line of its own product, that line's OPENING and its
 * reversal, at most once; the OPENING's key to its line; a zero count withdrawn at most once; a restore
 * hold and its one reconciliation; the deferred family, a line without its OPENING failing at commit and
 * leaving nothing; the privilege family of the six tables; the retention family of the corrections
 * before the release; and the indexes of SCHEMA section 11.
 */
final class OnboardingTablesTest extends DatabaseTestCase
{
    /** A recovery epoch of 16 bytes, in the hex format of bytea. */
    private const string EPOCH = '\x00112233445566778899aabbccddeeff';

    private int $owner = 0;

    private int $command = 0;

    private int $otherCommand = 0;

    private int $category = 0;

    private int $product = 0;

    private int $other = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $this->other = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis']);
        $observer = $this->observer();
        $this->command = self::key($observer->table('products')->where('id', $this->product)->value('created_command_id'));
        $this->otherCommand = self::key($observer->table('products')->where('id', $this->other)->value('created_command_id'));
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
    }

    public function test_each_check_and_key_of_an_entry_refuses_its_violation(): void
    {
        foreach ([
            // BR-INV-25: 1 to 100 rows.
            'opening_stock_entries_row_count_range' => [['row_count' => 0], ['row_count' => 101]],
            'opening_stock_entries_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry($c, ['actor_user_id' => 999999]), '23503', 'opening_stock_entries_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry($c, ['command_id' => 999999]), '23503', 'opening_stock_entries_command_fk');

        foreach (['row_count', 'confirmed_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEntry($c, [$column => null]), '23502');
        }

        // One entry per command.
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertEntry($c);
            $this->insertEntry($c);
        }, '23505', 'opening_stock_entries_command_unique');
    }

    public function test_each_check_and_key_of_an_opening_line_refuses_its_violation(): void
    {
        foreach ([
            'opening_stock_lines_row_no_range' => [['row_no' => 0], ['row_no' => 101]],
            // BR-INV-25: a positive quantity; a zero count is no line (BR-INV-27).
            'opening_stock_lines_quantity_range' => [['quantity' => '0'], ['quantity' => '-1'], ['quantity' => '100000000']],
            'opening_stock_lines_count_reference_length' => [['count_reference' => ''], ['count_reference' => str_repeat('c', 101)]],
            // D-087: a replacement names the line it replaces, that line's OPENING and the reason, all three or none.
            'opening_stock_lines_replacement_complete' => [['replaces_line_id' => 1], ['replaces_movement_id' => 1], ['replacement_reason' => 'Hitung ulang'], ['replaces_line_id' => 1, 'replaces_movement_id' => 1]],
            'opening_stock_lines_replacement_reason_length' => [
                ['replaces_line_id' => 1, 'replaces_movement_id' => 1, 'replacement_reason' => ''],
                ['replaces_line_id' => 1, 'replaces_movement_id' => 1, 'replacement_reason' => str_repeat('r', 1001)],
            ],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, 999999), '23503', 'opening_stock_lines_entry_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c), ['product_id' => 999999]), '23503', 'opening_stock_lines_product_fk');

        foreach (['opening_stock_entry_id', 'row_no', 'product_id', 'quantity', 'count_reference'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c), [$column => null]), '23502');
        }

        // A row once per entry, and one original line per product (BR-INV-26; G-22).
        $this->assertWriteRefused(function (Connection $c): void {
            $entry = $this->insertEntry($c, ['row_count' => 2]);
            $this->insertLine($c, $entry);
            $this->insertLine($c, $entry, ['product_id' => $this->other]);
        }, '23505', 'opening_stock_lines_entry_row_unique');
        $this->assertWriteRefused(function (Connection $c): void {
            $entry = $this->insertEntry($c, ['row_count' => 2]);
            $this->insertLine($c, $entry);
            $this->insertLine($c, $entry, ['row_no' => 2]);
        }, '23505', 'opening_lines_one_original');
    }

    public function test_an_opening_moves_its_lines_product_and_quantity_and_a_replacement_names_a_reversed_line_once(): void
    {
        // EXC-44: the OPENING's key to its line — the line's product, its quantity and its kind.
        foreach ([['quantity' => '4', 'line_quantity' => '4'], ['product_id' => $this->other], ['opening_replacement' => true], ['opening_line_id' => 999999]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertOpening($c, $this->insertLine($c, $this->insertEntry($c)), $values), '23503', 'stock_movements_opening_line_fk');
        }

        // Two OPENINGs reversed during onboarding (RC-10; D-087) and one that stands.
        TokoContoh::startOnboarding();
        $reversed = TokoContoh::openingStock($this->product, '5');
        TokoContoh::reverse($reversed);
        $line = TokoContoh::openingLineOf($reversed);
        $otherReversed = TokoContoh::openingStock($this->other, '3');
        TokoContoh::reverse($otherReversed);
        $third = TokoContoh::product('TINTA-01', ['categoryId' => $this->category, 'name' => 'Tinta']);
        $standing = TokoContoh::openingStock($third, '2');
        $replacement = ['replaces_line_id' => $line, 'replaces_movement_id' => $reversed, 'replacement_reason' => 'Hitung ulang'];

        // G-24: the line it replaces is of its own product; the OPENING it names is that line's, and reversed.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c), [...$replacement, 'product_id' => $third]), '23503', 'opening_stock_lines_replaces_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c), [...$replacement, 'replaces_movement_id' => $otherReversed]), '23503', 'opening_stock_lines_replaces_movement_fk');
        $this->assertWriteRefused(
            fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c), ['product_id' => $third, 'replaces_line_id' => TokoContoh::openingLineOf($standing), 'replaces_movement_id' => $standing, 'replacement_reason' => 'Hitung ulang']),
            '23503',
            'opening_stock_lines_replaces_reversal_fk',
        );

        // BR-INV-48: a line is replaced at most once.
        $this->assertWriteRefused(function (Connection $c) use ($replacement): void {
            $this->insertLine($c, $this->insertEntry($c), $replacement);
            $this->insertLine($c, $this->insertEntry($c, ['command_id' => $this->otherCommand]), $replacement);
        }, '23505', 'opening_stock_lines_replaces_line_unique');
    }

    public function test_each_check_and_key_of_a_zero_count_and_its_withdrawal_refuses_its_violation(): void
    {
        foreach ([
            'onboarding_zero_counts_count_reference_length' => [['count_reference' => ''], ['count_reference' => str_repeat('c', 101)]],
            'onboarding_zero_counts_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertZeroCount($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertZeroCount($c, ['product_id' => 999999]), '23503', 'onboarding_zero_counts_product_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertZeroCount($c, ['actor_user_id' => 999999]), '23503', 'onboarding_zero_counts_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertZeroCount($c, ['command_id' => 999999]), '23503', 'onboarding_zero_counts_command_fk');

        foreach (['product_id', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertZeroCount($c, [$column => null]), '23502');
        }

        foreach ([
            'onboarding_zero_count_withdrawals_reason_length' => [['reason' => ''], ['reason' => str_repeat('r', 1001)]],
            'onboarding_zero_count_withdrawals_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertWithdrawal($c, $this->insertZeroCount($c), $values), '23514', $constraint);
            }
        }

        // The withdrawal names its zero count with that count's product.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertWithdrawal($c, $this->insertZeroCount($c), ['product_id' => $this->other]), '23503', 'onboarding_zero_count_withdrawals_zero_count_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertWithdrawal($c, 999999), '23503', 'onboarding_zero_count_withdrawals_zero_count_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertWithdrawal($c, $this->insertZeroCount($c), ['actor_user_id' => 999999]), '23503', 'onboarding_zero_count_withdrawals_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertWithdrawal($c, $this->insertZeroCount($c), ['command_id' => 999999]), '23503', 'onboarding_zero_count_withdrawals_command_fk');

        foreach (['product_id', 'reason', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertWithdrawal($c, $this->insertZeroCount($c), [$column => null]), '23502');
        }

        // D-087: a zero count is withdrawn at most once, and a command withdraws one.
        $this->assertWriteRefused(function (Connection $c): void {
            $zeroCount = $this->insertZeroCount($c);
            $this->insertWithdrawal($c, $zeroCount);
            $this->insertWithdrawal($c, $zeroCount, ['command_id' => $this->otherCommand]);
        }, '23505', 'onboarding_zero_count_withdrawals_pkey');
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertWithdrawal($c, $this->insertZeroCount($c));
            $this->insertWithdrawal($c, $this->insertZeroCount($c));
        }, '23505', 'onboarding_zero_count_withdrawals_command_unique');
    }

    public function test_each_check_and_key_of_a_restore_hold_and_its_reconciliation_refuses_its_violation(): void
    {
        // D-072: a hold of restored data that showed onboarding not started or running.
        foreach ([['restored_state' => 'RELEASED'], ['restored_state' => 'active']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertHold($c, $values), '23514', 'onboarding_restore_holds_restored_state_allowed');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertHold($c, ['recovery_epoch' => '\x'.str_repeat('ab', 15)]), '23514', 'onboarding_restore_holds_recovery_epoch_length');

        foreach (['restored_state', 'recovery_epoch', 'set_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertHold($c, [$column => null]), '23502');
        }

        foreach ([
            'onboarding_restore_reconciliations_outcome_allowed' => [['outcome' => 'RELEASED'], ['outcome' => 'never_released']],
            'onboarding_restore_reconciliations_operator_note_length' => [['operator_note' => ''], ['operator_note' => str_repeat('n', 1001)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $this->insertHold($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, 999999), '23503', 'onboarding_restore_reconciliations_hold_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $this->insertHold($c), ['decided_by_user_id' => 999999]), '23503', 'onboarding_restore_reconciliations_decided_by_user_fk');

        foreach (['outcome', 'decided_by_user_id', 'operator_note', 'recorded_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReconciliation($c, $this->insertHold($c), [$column => null]), '23502');
        }

        // A hold is reconciled once (G-26).
        $this->assertWriteRefused(function (Connection $c): void {
            $hold = $this->insertHold($c);
            $this->insertReconciliation($c, $hold);
            $this->insertReconciliation($c, $hold, ['outcome' => 'RELEASED_BEFORE_RESTORE']);
        }, '23505', 'onboarding_restore_reconciliations_pkey');
    }

    public function test_a_line_without_its_opening_fails_at_commit_and_leaves_nothing(): void
    {
        // The deferred key of a line to its OPENING (BR-INV-25): each positive row posts one.
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertLine($c, $this->insertEntry($c)), 'opening_stock_lines_movement_fk', ['opening_stock_entries', 'opening_stock_lines']);
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::startOnboarding();
        TokoContoh::openingStock($this->product, '5');
        TokoContoh::withdrawZeroCount(TokoContoh::zeroCount($this->other));
        $runtime = $this->runtime();
        $hold = $this->insertHold($runtime);
        $this->insertReconciliation($runtime, $hold);

        // Class F: added, never changed or removed; the backup role reads them.
        foreach ([
            'opening_stock_entries' => ['row_count', 1, 2],
            'opening_stock_lines' => ['row_no', 1, 1],
            'onboarding_zero_counts' => ['count_reference', 'HIT', 1],
            'onboarding_zero_count_withdrawals' => ['reason', 'Lain', 1],
            'onboarding_restore_holds' => ['restored_state', 'ACTIVE', 1],
            'onboarding_restore_reconciliations' => ['operator_note', 'Lain', 1],
        ] as $table => [$column, $value, $rows]) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column => $value]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertSame($rows, $this->backup()->table($table)->count(), $table);
        }

        foreach (['opening_stock_entries_id_seq', 'opening_stock_lines_id_seq', 'onboarding_zero_counts_id_seq', 'onboarding_restore_holds_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }
    }

    public function test_the_corrections_before_the_release_only_add_to_the_facts(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; BR-INV-47, BR-INV-48: a withdrawal, a
        // reversal and a replacement only add; the original OPENING, its zero count and its evidence stay.
        TokoContoh::startOnboarding();
        $opening = TokoContoh::openingStock($this->product, '5');
        $zeroCount = TokoContoh::zeroCount($this->other);

        $this->assertRetained(['opening_stock_entries', 'opening_stock_lines', 'onboarding_zero_counts', 'stock_movements', 'acquisition_cost_evidence'], function () use ($opening, $zeroCount): void {
            TokoContoh::withdrawZeroCount($zeroCount);
            TokoContoh::openingStock($this->other, '2');
            TokoContoh::reverse($opening);
            TokoContoh::replaceOpening(TokoContoh::openingLineOf($opening), '4');
        });

        $this->assertSame(['OPENING', 'REVERSAL', 'OPENING'], $this->observer()->table('stock_movements')->where('product_id', $this->product)->orderBy('product_sequence')->pluck('movement_type')->all());
        $this->assertSame(1, $this->observer()->table('onboarding_zero_count_withdrawals')->where('zero_count_id', $zeroCount)->count());
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $tables = ['opening_stock_entries', 'opening_stock_lines', 'onboarding_zero_counts', 'onboarding_zero_count_withdrawals', 'onboarding_restore_reconciliations'];
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', $tables)->pluck('indexdef', 'indexname')->all();

        foreach ([
            'opening_stock_entries_command_unique' => 'CREATE UNIQUE INDEX opening_stock_entries_command_unique ON public.opening_stock_entries USING btree (command_id)',
            'opening_stock_entries_actor_user_id_index' => 'CREATE INDEX opening_stock_entries_actor_user_id_index ON public.opening_stock_entries USING btree (actor_user_id)',
            'opening_stock_lines_entry_row_unique' => 'CREATE UNIQUE INDEX opening_stock_lines_entry_row_unique ON public.opening_stock_lines USING btree (opening_stock_entry_id, row_no)',
            'opening_stock_lines_movement_target_unique' => 'CREATE UNIQUE INDEX opening_stock_lines_movement_target_unique ON public.opening_stock_lines USING btree (id, product_id, quantity, is_replacement)',
            'opening_stock_lines_id_product_unique' => 'CREATE UNIQUE INDEX opening_stock_lines_id_product_unique ON public.opening_stock_lines USING btree (id, product_id)',
            'opening_stock_lines_replaces_line_unique' => 'CREATE UNIQUE INDEX opening_stock_lines_replaces_line_unique ON public.opening_stock_lines USING btree (replaces_line_id)',
            'opening_lines_one_original' => 'CREATE UNIQUE INDEX opening_lines_one_original ON public.opening_stock_lines USING btree (product_id) WHERE (replaces_line_id IS NULL)',
            'opening_stock_lines_product_id_index' => 'CREATE INDEX opening_stock_lines_product_id_index ON public.opening_stock_lines USING btree (product_id)',
            'opening_stock_lines_replaces_movement_id_index' => 'CREATE INDEX opening_stock_lines_replaces_movement_id_index ON public.opening_stock_lines USING btree (replaces_movement_id)',
            'onboarding_zero_counts_id_product_unique' => 'CREATE UNIQUE INDEX onboarding_zero_counts_id_product_unique ON public.onboarding_zero_counts USING btree (id, product_id)',
            'onboarding_zero_counts_product_id_index' => 'CREATE INDEX onboarding_zero_counts_product_id_index ON public.onboarding_zero_counts USING btree (product_id)',
            'onboarding_zero_counts_actor_user_id_index' => 'CREATE INDEX onboarding_zero_counts_actor_user_id_index ON public.onboarding_zero_counts USING btree (actor_user_id)',
            'onboarding_zero_counts_command_id_index' => 'CREATE INDEX onboarding_zero_counts_command_id_index ON public.onboarding_zero_counts USING btree (command_id)',
            'onboarding_zero_count_withdrawals_command_unique' => 'CREATE UNIQUE INDEX onboarding_zero_count_withdrawals_command_unique ON public.onboarding_zero_count_withdrawals USING btree (command_id)',
            'onboarding_zero_count_withdrawals_actor_user_id_index' => 'CREATE INDEX onboarding_zero_count_withdrawals_actor_user_id_index ON public.onboarding_zero_count_withdrawals USING btree (actor_user_id)',
            'onboarding_restore_reconciliations_decided_by_user_id_index' => 'CREATE INDEX onboarding_restore_reconciliations_decided_by_user_id_index ON public.onboarding_restore_reconciliations USING btree (decided_by_user_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    /**
     * An entry of one row by the Owner, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertEntry(Connection $c, array $values = []): int
    {
        return $c->table('opening_stock_entries')->insertGetId([
            'row_count' => 1, 'confirmed_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * The original line of 5 of the pen at row 1 of an entry, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertLine(Connection $c, int $entry, array $values = []): int
    {
        return $c->table('opening_stock_lines')->insertGetId([
            'opening_stock_entry_id' => $entry, 'row_no' => 1, 'product_id' => $this->product, 'quantity' => '5', 'count_reference' => 'HIT-01', ...$values,
        ]);
    }

    /**
     * The original OPENING of 5 of the pen at its first place, of a line, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertOpening(Connection $c, int $line, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 1, 'movement_type' => 'OPENING', 'quantity' => '5',
            'opening_line_id' => $line, 'line_quantity' => '5', 'opening_replacement' => false, 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * A zero count of the pen, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertZeroCount(Connection $c, array $values = []): int
    {
        return $c->table('onboarding_zero_counts')->insertGetId([
            'product_id' => $this->product, 'count_reference' => null, 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * The withdrawal of a zero count of the pen, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertWithdrawal(Connection $c, int $zeroCount, array $values = []): void
    {
        $c->table('onboarding_zero_count_withdrawals')->insert([
            'zero_count_id' => $zeroCount, 'product_id' => $this->product, 'reason' => 'Salah hitung', 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
            'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * A restore hold of restored data that showed onboarding running, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertHold(Connection $c, array $values = []): int
    {
        return $c->table('onboarding_restore_holds')->insertGetId(['restored_state' => 'ACTIVE', 'recovery_epoch' => self::EPOCH, 'set_at' => now(), ...$values]);
    }

    /**
     * The Owner's reconciliation of a hold, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReconciliation(Connection $c, int $hold, array $values = []): void
    {
        $c->table('onboarding_restore_reconciliations')->insert([
            'hold_id' => $hold, 'outcome' => 'NEVER_RELEASED', 'decided_by_user_id' => $this->owner, 'operator_note' => 'Belum pernah diakhiri', 'recorded_at' => now(), ...$values,
        ]);
    }

    private static function key(mixed $value): int
    {
        return is_int($value) ? $value : throw new LogicException('A key read from the database is a whole number.');
    }
}
