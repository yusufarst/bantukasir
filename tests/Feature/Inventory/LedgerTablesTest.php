<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The eight tables of TASK-021 (AC-021-08; SCHEMA sections 3.2, 3.4, 3.6, 3.7, 5.7, 5.8, 9.4, 10
 * and 11, SCH-13; the migration 2026_11_02_000002_create_ledger_receiving_tables;
 * EXECUTION_CONTEXT section 10, a table; suites DB01, KEEP01, INV01 and SEARCH02): the constraint
 * family, written directly by the runtime role; the deferred family — a RECEIPT without its
 * evidence, a line without its movement, each failing at commit and leaving nothing; the
 * privilege family; the retention family, in which a command only adds to the facts; and the
 * indexes of SCHEMA section 11. The grants against the matrix are PrivilegeCheckTest's, for every
 * table.
 */
final class LedgerTablesTest extends DatabaseTestCase
{
    /** The eight tables, which hold no row until a test commits one. */
    private const array TABLES = ['stock_movements', 'stock_balances', 'receiving_documents', 'receiving_lines', 'acquisition_cost_evidence', 'inventory_valuations', 'inventory_posting_blocks', 'inventory_posting_block_reopenings'];

    /** The facts of these tables, class F: added, never changed or removed. */
    private const array FACTS = ['stock_movements', 'receiving_documents', 'receiving_lines', 'acquisition_cost_evidence', 'inventory_posting_blocks', 'inventory_posting_block_reopenings'];

    private int $owner = 0;

    private int $command = 0;

    private int $product = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category()]);
        $command = $this->observer()->table('products')->where('id', $this->product)->value('created_command_id');
        $this->command = is_int($command) ? $command : 0;
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
    }

    public function test_each_check_and_key_of_a_receiving_document_refuses_its_violation(): void
    {
        foreach ([
            'receiving_documents_reference_length' => [['reference' => ''], ['reference' => str_repeat('r', 101)]],
            'receiving_documents_line_count_range' => [['line_count' => 0], ['line_count' => 501]],
            'receiving_documents_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertDocument($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertDocument($c, ['actor_user_id' => 999999]), '23503', 'receiving_documents_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertDocument($c, ['command_id' => 999999]), '23503', 'receiving_documents_command_fk');

        foreach (['line_count', 'confirmed_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertDocument($c, [$column => null]), '23502');
        }

        // One document per command.
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertDocument($c);
            $this->insertDocument($c);
        }, '23505', 'receiving_documents_command_unique');
    }

    public function test_each_check_and_key_of_a_receiving_line_refuses_its_violation(): void
    {
        foreach ([
            'receiving_lines_line_no_range' => [['line_no' => 0], ['line_no' => 501]],
            'receiving_lines_quantity_range' => [['quantity' => '0'], ['quantity' => '-1'], ['quantity' => '100000000'], ['quantity' => 'NaN']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertDocument($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, 999999), '23503', 'receiving_lines_document_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertDocument($c), ['product_id' => 999999]), '23503', 'receiving_lines_product_fk');

        foreach (['line_no', 'product_id', 'quantity'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertDocument($c), [$column => null]), '23502');
        }

        // One line of each number per document.
        $this->assertWriteRefused(function (Connection $c): void {
            $document = $this->insertDocument($c);
            $this->insertLine($c, $document);
            $this->insertLine($c, $document);
        }, '23505', 'receiving_lines_document_line_unique');
    }

    public function test_each_check_of_a_movement_refuses_its_violation(): void
    {
        foreach ([
            'stock_movements_movement_type_allowed' => [['movement_type' => 'OTHER']],
            'stock_movements_product_sequence_positive' => [['product_sequence' => 0]],
            'stock_movements_quantity_range' => [['quantity' => '0', 'line_quantity' => '0'], ['quantity' => '100000000', 'line_quantity' => '100000000'], ['quantity' => 'NaN', 'line_quantity' => 'NaN']],
            'stock_movements_quantity_precision_check' => [['quantity' => '1.5', 'line_quantity' => '1.5']],
            'stock_movements_sign' => [['quantity' => '-1', 'line_quantity' => '-1']],
            // The one source, paired with its type.
            'stock_movements_one_source' => [['receiving_line_id' => null], ['sale_line_id' => 7], ['opening_line_id' => 7], ['manual_issue_line_id' => 7], ['adjustment_line_id' => 7], ['reversal_id' => 7], ['return_line_id' => 7]],
            'stock_movements_return_condition' => [['return_condition' => 'SALEABLE']],
            'stock_movements_opening_replacement_set' => [['opening_replacement' => false], ['opening_replacement' => true]],
            // SCH-13: the null of a column that a check involves passes nothing.
            'stock_movements_line_quantity' => [['line_quantity' => null], ['line_quantity' => '6']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $values), '23514', $constraint);
            }
        }

        // Each type with its own source, its own sign and its own columns (SCHEMA section 5.7).
        foreach ([
            'stock_movements_sign' => [
                $this->movementOf('MANUAL_ISSUE', '1', ['manual_issue_line_id' => 7, 'line_quantity' => '-1']),
                $this->movementOf('SALE_ISSUE', '1', ['sale_line_id' => 7, 'line_quantity' => '-1']),
                $this->movementOf('OPENING', '-1', ['opening_line_id' => 7, 'line_quantity' => '-1', 'opening_replacement' => false]),
                $this->movementOf('RETURN_RECEIPT', '-1', ['return_line_id' => 7, 'line_quantity' => '-1', 'return_condition' => 'SALEABLE']),
            ],
            'stock_movements_one_source' => [
                $this->movementOf('MANUAL_ISSUE', '-1', ['receiving_line_id' => 7, 'line_quantity' => '1']),
                $this->movementOf('SALE_ISSUE', '-1', ['manual_issue_line_id' => 7, 'line_quantity' => '1']),
                $this->movementOf('ADJUSTMENT', '1', ['reversal_id' => 7, 'line_quantity' => '1']),
                $this->movementOf('REVERSAL', '1', ['adjustment_line_id' => 7]),
                $this->movementOf('OPENING', '1', ['line_quantity' => '1', 'opening_replacement' => false]),
                $this->movementOf('RETURN_RECEIPT', '1', ['line_quantity' => '1', 'return_condition' => 'SALEABLE']),
            ],
            'stock_movements_return_condition' => [
                $this->movementOf('RETURN_RECEIPT', '1', ['return_line_id' => 7, 'line_quantity' => '1']),
                $this->movementOf('RETURN_RECEIPT', '1', ['return_line_id' => 7, 'line_quantity' => '1', 'return_condition' => 'NOT_SALEABLE']),
            ],
            'stock_movements_opening_replacement_set' => [
                $this->movementOf('OPENING', '1', ['opening_line_id' => 7, 'line_quantity' => '1']),
            ],
            // An original OPENING comes first, or it names what it replaces (BR-INV-26).
            'stock_movements_opening_first' => [
                $this->movementOf('OPENING', '1', ['opening_line_id' => 7, 'line_quantity' => '1', 'opening_replacement' => false, 'product_sequence' => 2]),
            ],
            'stock_movements_line_quantity' => [
                $this->movementOf('MANUAL_ISSUE', '-1', ['manual_issue_line_id' => 7, 'line_quantity' => '-1']),
                $this->movementOf('MANUAL_ISSUE', '-1', ['manual_issue_line_id' => 7]),
                $this->movementOf('REVERSAL', '1', ['reversal_id' => 7, 'line_quantity' => '1']),
            ],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertMovement($c, $values), '23514', $constraint);
            }
        }
    }

    public function test_each_key_of_a_movement_refuses_its_violation(): void
    {
        // The product's unit and precision, as its composite key holds them (BR-CAT-07).
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['base_unit' => 'kg']), '23503', 'stock_movements_product_unit_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['quantity_precision' => 1]), '23503', 'stock_movements_product_unit_fk');

        // The line's product and quantity: a movement moves exactly those.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['product_id' => $other]), '23503', 'stock_movements_receiving_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['quantity' => '6', 'line_quantity' => '6']), '23503', 'stock_movements_receiving_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['receiving_line_id' => 999999]), '23503', 'stock_movements_receiving_line_fk');

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['actor_user_id' => 999999]), '23503', 'stock_movements_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['command_id' => 999999]), '23503', 'stock_movements_command_fk');

        foreach (['product_id', 'base_unit', 'quantity_precision', 'product_sequence', 'movement_type', 'quantity', 'recorded_at', 'actor_user_id', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, [$column => null]), '23502');
        }

        // The posting order within a product, and one movement per source.
        $this->assertWriteRefused(function (Connection $c): void {
            $document = $this->insertDocument($c);
            $this->insertMovement($c, ['receiving_line_id' => $this->insertLine($c, $document)]);
            $this->insertMovement($c, ['receiving_line_id' => $this->insertLine($c, $document, ['line_no' => 2])]);
        }, '23505', 'stock_movements_product_sequence_unique');

        $this->assertWriteRefused(function (Connection $c): void {
            $line = $this->insertLine($c, $this->insertDocument($c));
            $this->insertMovement($c, ['receiving_line_id' => $line]);
            $this->insertMovement($c, ['receiving_line_id' => $line, 'product_sequence' => 2]);
        }, '23505', 'stock_movements_receiving_line_unique');

        // Nobody writes a presence key: the database computes it from the row.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, ['cost_evidence_presence' => 1]), '428C9');
    }

    public function test_the_presence_keys_are_computed_from_the_type_and_the_sign(): void
    {
        // TASK-028 completed the key of a Sale line, so the SALE_ISSUE is a real one, of a product of
        // its own, committed by RC-01's Action through the fixtures (EXC-45).
        $sold = $this->saleIssue();
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            // SCHEMA section 3.7. Within this one transaction the deferred keys wait, and it
            // rolls back; the keys of the other presence columns follow their tables (EXC-44).
            $adjustment = $this->insertAdjustmentLines($runtime);
            $receipt = $this->insertReceipt($runtime);
            // TASK-026 completed the key of a reversal, so the REVERSAL names a real one: the receipt's.
            $reversal = $runtime->table('reversals')->insertGetId([
                'original_movement_id' => $receipt, 'product_id' => $this->product, 'original_type' => 'RECEIPT', 'original_quantity' => '5', 'inverse_quantity' => '-5',
                'reason' => 'Salah catat', 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command,
            ]);
            $presences = [
                'RECEIPT' => $this->presenceOf($runtime, $receipt),
                'OPENING' => $this->presenceOf($runtime, $this->insertMovement($runtime, $this->movementOf('OPENING', '1', ['opening_line_id' => 7, 'line_quantity' => '1', 'opening_replacement' => true, 'product_sequence' => 2]))),
                // TASK-024 completed the key of an adjustment line, so the movements name real ones.
                'an increase' => $this->presenceOf($runtime, $this->insertMovement($runtime, $this->movementOf('ADJUSTMENT', '1', ['adjustment_line_id' => $adjustment[0], 'line_quantity' => '1', 'product_sequence' => 3]))),
                'a decrease' => $this->presenceOf($runtime, $this->insertMovement($runtime, $this->movementOf('ADJUSTMENT', '-1', ['adjustment_line_id' => $adjustment[1], 'line_quantity' => '-1', 'product_sequence' => 4]))),
                'SALE_ISSUE' => $this->presenceOf($runtime, $sold),
                // TASK-023 completed the key of a manual issue line, so the movement names a real one.
                'MANUAL_ISSUE' => $this->presenceOf($runtime, $this->insertMovement($runtime, $this->movementOf('MANUAL_ISSUE', '-1', ['manual_issue_line_id' => $this->insertIssueLine($runtime), 'line_quantity' => '1', 'product_sequence' => 6]))),
                'RETURN_RECEIPT' => $this->presenceOf($runtime, $this->insertMovement($runtime, $this->movementOf('RETURN_RECEIPT', '1', ['return_line_id' => 7, 'line_quantity' => '1', 'return_condition' => 'SALEABLE', 'product_sequence' => 7]))),
                'REVERSAL' => $this->presenceOf($runtime, $this->insertMovement($runtime, $this->movementOf('REVERSAL', '-5', ['reversal_id' => $reversal, 'product_sequence' => 8]))),
            ];
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame([
            'RECEIPT' => ['evidence'],
            'OPENING' => ['evidence'],
            'an increase' => ['evidence'],
            'a decrease' => ['issue'],
            'SALE_ISSUE' => ['issue'],
            'MANUAL_ISSUE' => ['issue'],
            'RETURN_RECEIPT' => ['return'],
            'REVERSAL' => [],
        ], $presences);
    }

    public function test_an_original_opening_comes_first_and_a_replacement_later(): void
    {
        // The check of BR-INV-26 that admits what it must: TASK-035 writes these rows.
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertMovement($runtime, $this->movementOf('OPENING', '1', ['opening_line_id' => 7, 'line_quantity' => '1', 'opening_replacement' => false]));
            $this->insertMovement($runtime, $this->movementOf('OPENING', '1', ['opening_line_id' => 8, 'line_quantity' => '1', 'opening_replacement' => true, 'product_sequence' => 2]));
            $this->assertSame(2, $runtime->table('stock_movements')->count());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_each_check_and_key_of_the_evidence_refuses_its_violation(): void
    {
        foreach ([
            'acquisition_cost_evidence_movement_type_allowed' => [['movement_type' => 'SALE_ISSUE']],
            'acquisition_cost_evidence_quantity_range' => [['quantity' => '0'], ['quantity' => '-1'], ['quantity' => '100000000'], ['quantity' => 'NaN']],
            'acquisition_cost_evidence_cost_status_allowed' => [['cost_status' => 'ZERO']],
            'acquisition_cost_evidence_total_amount_range' => [['total_amount' => '-1'], ['total_amount' => 'NaN']],
            // UNKNOWN is a status, never a number; KNOWN holds its amount (SCH-13).
            'acquisition_cost_evidence_status_amount' => [['total_amount' => null], ['cost_status' => 'UNKNOWN'], ['cost_status' => 'UNKNOWN', 'total_amount' => '0', 'zero_cost_note' => 'Catatan']],
            // An explicit zero carries its evidence or reason (BR-COST-04).
            'acquisition_cost_evidence_zero_has_note' => [['total_amount' => '0'], ['total_amount' => '0.000000']],
            'acquisition_cost_evidence_zero_cost_note_length' => [['zero_cost_note' => ''], ['zero_cost_note' => str_repeat('n', 1001)]],
            'acquisition_cost_evidence_reference_length' => [['reference' => ''], ['reference' => str_repeat('r', 101)]],
            'acquisition_cost_evidence_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, $this->insertReceipt($c), $values), '23514', $constraint);
            }
        }

        // Past the type's own range: the application refuses it first (ADR-006).
        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, $this->insertReceipt($c), ['total_amount' => '1000000000000']), '22003');

        // The evidence belongs to one positive increase of that product and quantity.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);

        foreach ([['product_id' => $other], ['quantity' => '6'], ['movement_type' => 'OPENING'], ['movement_type' => 'ADJUSTMENT']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, $this->insertReceipt($c), $values), '23503', 'acquisition_cost_evidence_movement_fk');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, 999999), '23503', 'acquisition_cost_evidence_movement_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, $this->insertReceipt($c), ['actor_user_id' => 999999]), '23503', 'acquisition_cost_evidence_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, $this->insertReceipt($c), ['command_id' => 999999]), '23503', 'acquisition_cost_evidence_command_fk');

        foreach (['product_id', 'movement_type', 'quantity', 'cost_status', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, $this->insertReceipt($c), [$column => null]), '23502');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertEvidence($c, null), '23502');

        // One evidence per movement.
        $this->assertWriteRefused(function (Connection $c): void {
            $movement = $this->insertReceipt($c);
            $this->insertEvidence($c, $movement);
            $this->insertEvidence($c, $movement);
        }, '23505', 'acquisition_cost_evidence_movement_unique');
    }

    public function test_the_evidence_admits_a_known_an_unknown_and_an_evidenced_zero_amount(): void
    {
        foreach ([
            ['cost_status' => 'KNOWN', 'total_amount' => '999999999999.999999'],
            ['cost_status' => 'UNKNOWN', 'total_amount' => null],
            ['cost_status' => 'KNOWN', 'total_amount' => '0', 'zero_cost_note' => 'Hadiah pemasok'],
        ] as $values) {
            $runtime = $this->runtime();
            $runtime->beginTransaction();

            try {
                $evidence = $this->insertEvidence($runtime, $this->insertReceipt($runtime), $values);
                $this->assertGreaterThan(0, $evidence);
            } finally {
                $runtime->rollBack();
            }
        }
    }

    public function test_each_check_and_key_of_a_balance_refuses_its_violation(): void
    {
        foreach ([
            'stock_balances_on_hand_range' => [['on_hand' => '-1'], ['on_hand' => 'NaN']],
            'stock_balances_last_sequence_positive' => [['last_sequence' => 0]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertBalance($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertBalance($c, ['on_hand' => '10000000000']), '22003');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertBalance($c, ['product_id' => 999999]), '23503', 'stock_balances_product_fk');

        foreach (['on_hand', 'last_sequence', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertBalance($c, [$column => null]), '23502');
        }

        // One balance per product, never below zero by an update either (BR-INV-04).
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertBalance($c);
            $this->insertBalance($c);
        }, '23505', 'stock_balances_pkey');

        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertBalance($c);
            $c->table('stock_balances')->where('product_id', $this->product)->update(['on_hand' => '-0.001']);
        }, '23514', 'stock_balances_on_hand_range');
    }

    public function test_each_check_and_key_of_a_valuation_refuses_its_violation(): void
    {
        foreach ([
            'inventory_valuations_value_status_allowed' => [['value_status' => 'ZERO', 'value' => null]],
            'inventory_valuations_value_range' => [['value' => '-1'], ['value' => 'NaN']],
            'inventory_valuations_publication_no_positive' => [['publication_no' => 0]],
            'inventory_valuations_last_sequence_positive' => [['last_sequence' => 0]],
            // UNKNOWN is never zero (SCHEMA section 3.2, SCH-13).
            'inventory_valuations_status_value' => [['value' => null], ['value_status' => 'UNKNOWN'], ['value_status' => 'UNKNOWN', 'value' => '0']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertValuation($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertValuation($c, ['value' => '1'.str_repeat('0', 15)]), '22003');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertValuation($c, ['product_id' => 999999]), '23503', 'inventory_valuations_product_fk');

        foreach (['value_status', 'publication_no', 'last_sequence', 'published_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertValuation($c, [$column => null]), '23502');
        }

        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertValuation($c);
            $this->insertValuation($c);
        }, '23505', 'inventory_valuations_pkey');
    }

    public function test_each_check_and_key_of_a_posting_block_and_its_reopening_refuses_its_violation(): void
    {
        foreach ([
            'inventory_posting_blocks_actor_kind_allowed' => [['actor_kind' => 'ANONYMOUS']],
            'inventory_posting_blocks_actor_process_format' => [['actor_process' => 'Reconciliation']],
            // Exactly one of the two, by the kind (SCH-13).
            'inventory_posting_blocks_actor' => [['actor_process' => null], ['actor_user_id' => $this->owner], ['actor_kind' => 'USER'], ['actor_kind' => 'USER', 'actor_user_id' => $this->owner]],
            'inventory_posting_blocks_detail_code_format' => [['detail_code' => 'mismatch'], ['detail_code' => ''], ['detail_code' => str_repeat('A', 65)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertBlock($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertBlock($c, ['product_id' => 999999]), '23503', 'inventory_posting_blocks_product_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertBlock($c, ['command_id' => 999999]), '23503', 'inventory_posting_blocks_command_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertBlock($c, ['actor_kind' => 'USER', 'actor_user_id' => 999999, 'actor_process' => null]), '23503', 'inventory_posting_blocks_actor_user_fk');

        foreach (['product_id', 'blocked_at', 'actor_kind', 'detail_code', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertBlock($c, [$column => null]), '23502');
        }

        foreach ([
            'inventory_posting_block_reopenings_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('a', 101)]],
            'inventory_posting_block_reopenings_reason_length' => [['reason' => ''], ['reason' => str_repeat('r', 1001)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReopening($c, $this->insertBlock($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReopening($c, 999999), '23503', 'inventory_posting_block_reopenings_block_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReopening($c, $this->insertBlock($c), ['actor_user_id' => 999999]), '23503', 'inventory_posting_block_reopenings_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReopening($c, $this->insertBlock($c), ['command_id' => 999999]), '23503', 'inventory_posting_block_reopenings_command_fk');

        foreach (['reopened_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReopening($c, $this->insertBlock($c), [$column => null]), '23502');
        }

        // A block is reopened at most once; a product may be blocked again after it.
        $this->assertWriteRefused(function (Connection $c): void {
            $block = $this->insertBlock($c);
            $this->insertReopening($c, $block);
            $this->insertReopening($c, $block);
        }, '23505', 'inventory_posting_block_reopenings_pkey');
    }

    public function test_a_receipt_without_its_evidence_fails_at_commit_and_leaves_nothing(): void
    {
        // AC-021-08, BR-INV-08: every statement is accepted, and the commit refuses them.
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertReceipt($c), 'stock_movements_cost_evidence_presence_fk', ['receiving_documents', 'receiving_lines', 'stock_movements']);

        // An OPENING needs its evidence too (SCHEMA section 5.7); an increase, now that its line's
        // key exists, is AdjustmentTablesTest's (TASK-024).
        $opening = $this->movementOf('OPENING', '1', ['opening_line_id' => 7, 'line_quantity' => '1', 'opening_replacement' => false]);
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertMovement($c, $opening), 'stock_movements_cost_evidence_presence_fk', ['stock_movements']);
    }

    public function test_a_receiving_line_without_its_movement_fails_at_commit_and_leaves_nothing(): void
    {
        // Every line posts its RECEIPT (SCHEMA section 5.7).
        $this->assertFailsAtCommit(fn (Connection $c) => $this->insertLine($c, $this->insertDocument($c)), 'receiving_lines_movement_fk', ['receiving_documents', 'receiving_lines']);
    }

    public function test_a_line_with_its_movement_and_its_evidence_commits_in_one_transaction(): void
    {
        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertEvidence($c, $this->insertReceipt($c));
        });

        $this->assertSame(1, $this->observer()->table('stock_movements')->count());
        $this->assertSame(1, $this->observer()->table('acquisition_cost_evidence')->count());
    }

    public function test_a_products_unit_and_precision_cannot_change_once_a_movement_references_them(): void
    {
        // RACE-05's end: the composite key with ON UPDATE RESTRICT, under every application check.
        $changed = TokoContoh::product('UBAH-01', ['categoryId' => TokoContoh::category('Ubah')]);
        $this->runtime()->table('products')->where('id', $changed)->update(['base_unit' => 'kg', 'quantity_precision' => 2]);
        $this->assertSame(['kg', 2], array_values((array) $this->observer()->table('products')->where('id', $changed)->first(['base_unit', 'quantity_precision'])));

        TokoContoh::stock($this->product, '5');

        // SQLSTATE 23001, restrict_violation: what the pinned PostgreSQL 18.6 answers for the key's
        // RESTRICT action, as this test proves (evidence reading 16).
        $this->assertWriteRefused(fn (Connection $c) => $c->table('products')->where('id', $this->product)->update(['base_unit' => 'kg']), '23001', 'stock_movements_product_unit_fk');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('products')->where('id', $this->product)->update(['quantity_precision' => 2]), '23001', 'stock_movements_product_unit_fk');

        // Any other column stays editable.
        $this->runtime()->table('products')->where('id', $this->product)->update(['name' => 'Nama Baru']);
        $this->assertSame('Nama Baru', $this->observer()->table('products')->where('id', $this->product)->value('name'));
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        TokoContoh::stock($this->product, '5');
        TokoContoh::reopenPostings(TokoContoh::postingBlock($this->product));
        $runtime = $this->runtime();
        $column = ['stock_movements' => 'recorded_at', 'receiving_documents' => 'confirmed_at', 'receiving_lines' => 'line_no', 'acquisition_cost_evidence' => 'recorded_at', 'inventory_posting_blocks' => 'blocked_at', 'inventory_posting_block_reopenings' => 'reopened_at'];

        // Class F: added, never changed or removed.
        foreach (self::FACTS as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column[$table] => $table === 'receiving_lines' ? 2 : now()]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
        }

        // Class P: only the listed columns, and never removed.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('stock_balances')->update(['product_id' => $this->product]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('stock_balances')->delete());
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('inventory_valuations')->update(['product_id' => $this->product]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('inventory_valuations')->delete());

        foreach (self::TABLES as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->truncate());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
            $this->assertGreaterThan(0, $this->backup()->table($table)->count(), $table);
        }

        // The runtime role takes no value from a sequence (SCH-44).
        foreach (['stock_movements_id_seq', 'receiving_documents_id_seq', 'receiving_lines_id_seq', 'acquisition_cost_evidence_id_seq', 'inventory_posting_blocks_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }
    }

    public function test_a_later_receiving_only_adds_to_the_facts_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02.
        TokoContoh::stock($this->product, '5');

        $this->assertRetained(['stock_movements', 'receiving_documents', 'receiving_lines', 'acquisition_cost_evidence'], function (): void {
            TokoContoh::stock($this->product, '7', '2000');
            TokoContoh::reopenPostings(TokoContoh::postingBlock($this->product));
            TokoContoh::stock($this->product, '1', '100');
        });

        $this->assertSame(3, $this->observer()->table('stock_movements')->where('product_id', $this->product)->count());
    }

    public function test_the_indexes_of_schema_section_11_exist(): void
    {
        $indexes = $this->observer()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', self::TABLES)->pluck('indexdef', 'indexname')->all();

        foreach ([
            // Stock history per product, and the replay's order.
            'stock_movements_product_sequence_unique' => 'CREATE UNIQUE INDEX stock_movements_product_sequence_unique ON public.stock_movements USING btree (product_id, product_sequence)',
            'stock_movements_receiving_line_unique' => 'CREATE UNIQUE INDEX stock_movements_receiving_line_unique ON public.stock_movements USING btree (receiving_line_id)',
            'acquisition_cost_evidence_movement_unique' => 'CREATE UNIQUE INDEX acquisition_cost_evidence_movement_unique ON public.acquisition_cost_evidence USING btree (movement_id)',
            // Every foreign-key column that no other key leads.
            'stock_movements_actor_user_id_index' => 'CREATE INDEX stock_movements_actor_user_id_index ON public.stock_movements USING btree (actor_user_id)',
            'stock_movements_command_id_index' => 'CREATE INDEX stock_movements_command_id_index ON public.stock_movements USING btree (command_id)',
            'stock_movements_cost_evidence_presence_index' => 'CREATE INDEX stock_movements_cost_evidence_presence_index ON public.stock_movements USING btree (cost_evidence_presence)',
            'receiving_documents_actor_user_id_index' => 'CREATE INDEX receiving_documents_actor_user_id_index ON public.receiving_documents USING btree (actor_user_id)',
            'receiving_lines_product_id_index' => 'CREATE INDEX receiving_lines_product_id_index ON public.receiving_lines USING btree (product_id)',
            'acquisition_cost_evidence_product_id_index' => 'CREATE INDEX acquisition_cost_evidence_product_id_index ON public.acquisition_cost_evidence USING btree (product_id)',
            'acquisition_cost_evidence_actor_user_id_index' => 'CREATE INDEX acquisition_cost_evidence_actor_user_id_index ON public.acquisition_cost_evidence USING btree (actor_user_id)',
            'acquisition_cost_evidence_command_id_index' => 'CREATE INDEX acquisition_cost_evidence_command_id_index ON public.acquisition_cost_evidence USING btree (command_id)',
            'inventory_posting_blocks_product_id_index' => 'CREATE INDEX inventory_posting_blocks_product_id_index ON public.inventory_posting_blocks USING btree (product_id)',
            'inventory_posting_blocks_actor_user_id_index' => 'CREATE INDEX inventory_posting_blocks_actor_user_id_index ON public.inventory_posting_blocks USING btree (actor_user_id)',
            'inventory_posting_blocks_command_id_index' => 'CREATE INDEX inventory_posting_blocks_command_id_index ON public.inventory_posting_blocks USING btree (command_id)',
            'inventory_posting_block_reopenings_actor_user_id_index' => 'CREATE INDEX inventory_posting_block_reopenings_actor_user_id_index ON public.inventory_posting_block_reopenings USING btree (actor_user_id)',
            'inventory_posting_block_reopenings_command_id_index' => 'CREATE INDEX inventory_posting_block_reopenings_command_id_index ON public.inventory_posting_block_reopenings USING btree (command_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }
    }

    public function test_no_table_holds_a_draft_or_a_location(): void
    {
        // AC-021-02, D-069 (a), BR-INV-17: a receiving draft lives in page memory, so the
        // tables of receiving are the document and its lines.
        $receiving = array_values(array_filter(self::sorted($this->observer()->table('pg_tables')->where('schemaname', 'public')->pluck('tablename')->all()), static fn (string $name): bool => str_contains($name, 'receiving')));
        $this->assertSame(['receiving_documents', 'receiving_lines'], $receiving);

        // BR-INV-01: one stock pool, with no place and no reservation in any column.
        foreach (self::sorted($this->observer()->table('information_schema.columns')->where('table_schema', 'public')->whereIn('table_name', self::TABLES)->pluck('column_name')->all()) as $column) {
            $this->assertDoesNotMatchRegularExpression('/(^|_)(draft|warehouse|location|outlet|branch|tenant|reserved)(_|$)/', $column);
        }

        // A line holds no cost: its cost is the evidence of its movement (D-047).
        $this->assertSame(['id', 'line_no', 'product_id', 'quantity', 'receiving_document_id'], self::sorted($this->observer()->table('information_schema.columns')->where('table_schema', 'public')->where('table_name', 'receiving_lines')->pluck('column_name')->all()));
    }

    /**
     * The presence keys that a movement holds.
     *
     * @return list<string>
     */
    private function presenceOf(Connection $c, int $movement): array
    {
        $row = (array) $c->table('stock_movements')->where('id', $movement)->first(['cost_evidence_presence', 'issue_allocation_presence', 'return_allocation_presence']);
        $held = [];

        foreach (['evidence' => 'cost_evidence_presence', 'issue' => 'issue_allocation_presence', 'return' => 'return_allocation_presence'] as $name => $column) {
            if ($row[$column] !== null) {
                $this->assertSame($movement, $row[$column]);
                $held[] = $name;
            }
        }

        return $held;
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
     * The columns of a movement of this type and quantity, with no receiving line.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function movementOf(string $type, string $quantity, array $values): array
    {
        return ['movement_type' => $type, 'quantity' => $quantity, 'receiving_line_id' => null, 'line_quantity' => null, ...$values];
    }

    /**
     * A document, a line and a RECEIPT written directly, the line's product and quantity those
     * of the row, unless $values names others for the movement.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReceipt(Connection $c, array $values = []): int
    {
        $line = $this->insertLine($c, $this->insertDocument($c));

        return $this->insertMovement($c, ['receiving_line_id' => $line, ...$values]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertDocument(Connection $c, array $values = []): int
    {
        return $c->table('receiving_documents')->insertGetId([
            'reference' => null, 'document_date' => null, 'line_count' => 1, 'confirmed_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
            'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertLine(Connection $c, int $document, array $values = []): int
    {
        return $c->table('receiving_lines')->insertGetId(['receiving_document_id' => $document, 'line_no' => 1, 'product_id' => $this->product, 'quantity' => '5', ...$values]);
    }

    /**
     * The SALE_ISSUE of one unit of a product of its own, sold by the Owner in a session of its own
     * through RC-01's Action (TASK-028): its internal key.
     */
    private function saleIssue(): int
    {
        $product = TokoContoh::product('JUAL-01', ['categoryId' => TokoContoh::category('Jual')]);
        TokoContoh::stock($product, '1');
        $session = TokoContoh::openSession('Hendra', TokoContoh::register());

        return TokoContoh::saleIssueOf(TokoContoh::sell('Hendra', $session, [['productId' => $product, 'quantity' => '1']]));
    }

    /**
     * A manual issue of one line of one unit of the product, written directly (TASK-023): the
     * line's key.
     */
    private function insertIssueLine(Connection $c): int
    {
        $issue = $c->table('manual_issues')->insertGetId([
            'reason_code' => 'DAMAGED', 'note' => null, 'line_count' => 1, 'confirmed_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra',
            'command_id' => $this->command,
        ]);

        return $c->table('manual_issue_lines')->insertGetId(['manual_issue_id' => $issue, 'line_no' => 1, 'product_id' => $this->product, 'quantity' => '1']);
    }

    /**
     * An adjustment of the product with an increase and a decrease of one unit, written directly
     * (TASK-024): the two lines' keys.
     *
     * @return array{0: int, 1: int}
     */
    private function insertAdjustmentLines(Connection $c): array
    {
        $adjustment = $c->table('adjustments')->insertGetId([
            'reason' => 'Hitung ulang', 'count_reference' => 'HIT-1', 'reference' => 'REF-1', 'line_count' => 2, 'confirmed_at' => now(), 'actor_user_id' => $this->owner,
            'actor_display_name' => 'Hendra', 'command_id' => $this->command,
        ]);

        return [
            $c->table('adjustment_lines')->insertGetId(['adjustment_id' => $adjustment, 'line_no' => 1, 'product_id' => $this->product, 'quantity' => '1']),
            $c->table('adjustment_lines')->insertGetId(['adjustment_id' => $adjustment, 'line_no' => 2, 'product_id' => $this->product, 'quantity' => '-1']),
        ];
    }

    /**
     * A movement written directly: a RECEIPT of 5 of the product, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertMovement(Connection $c, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 1, 'movement_type' => 'RECEIPT', 'quantity' => '5', 'line_quantity' => '5',
            'recorded_at' => now(), 'actor_user_id' => $this->owner, 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * Evidence of the RECEIPT of 5 that insertReceipt() writes, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertEvidence(Connection $c, ?int $movement, array $values = []): int
    {
        return $c->table('acquisition_cost_evidence')->insertGetId([
            'movement_id' => $movement, 'product_id' => $this->product, 'movement_type' => 'RECEIPT', 'quantity' => '5', 'cost_status' => 'KNOWN', 'total_amount' => '1000',
            'recorded_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertBalance(Connection $c, array $values = []): void
    {
        $c->table('stock_balances')->insert(['product_id' => $this->product, 'on_hand' => '5', 'last_sequence' => 1, 'updated_at' => now(), ...$values]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertValuation(Connection $c, array $values = []): void
    {
        $c->table('inventory_valuations')->insert(['product_id' => $this->product, 'value_status' => 'KNOWN', 'value' => '1000', 'publication_no' => 1, 'last_sequence' => 1, 'published_at' => now(), ...$values]);
    }

    /**
     * A block by the scheduled command, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertBlock(Connection $c, array $values = []): int
    {
        return $c->table('inventory_posting_blocks')->insertGetId([
            'product_id' => $this->product, 'blocked_at' => now(), 'actor_kind' => 'SCHEDULED_COMMAND', 'actor_user_id' => null, 'actor_process' => 'reconciliation',
            'detail_code' => 'BALANCE_MISMATCH', 'command_id' => $this->command, ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertReopening(Connection $c, int $block, array $values = []): void
    {
        $c->table('inventory_posting_block_reopenings')->insert([
            'block_id' => $block, 'reopened_at' => now(), 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'reason' => null, 'command_id' => $this->command, ...$values,
        ]);
    }
}
