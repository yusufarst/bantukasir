<?php

namespace Tests\Feature\Sales;

use App\Database\PrivilegeCheck;
use App\Database\PrivilegeMatrix;
use Illuminate\Database\Connection;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The six tables of TASK-033 and the keys it completes (AC-033-06; SCHEMA sections 3.2, 3.5 to 3.7,
 * 5.7 to 5.9, 9.4, 10 and 11; SCH-13; BR-RFD-03 to BR-RFD-07, BR-RFD-11; BR-INV-39; BR-COST-17,
 * BR-COST-19; ADR-008 section 3; D-045, D-061; G-06; the migration
 * 2026_11_13_000002_create_sale_correction_tables; EXECUTION_CONTEXT section 10, a table, and EXC-44;
 * suites DB01, KEEP01 and RET01; TASK-033 section 7, L2): the constraint family, written directly by
 * the runtime role — a correction of a Sale with its reason and its actor; a refund above zero with
 * exactly one source and its reference; refund and return lines of the same Sale; a return line's
 * condition, and its presence key; the return allocation linked to the SALE_ISSUE allocation of the
 * same line and product, of a saleable line only, and its projection; a RETURN_RECEIPT that moves
 * exactly its saleable line — the deferred family, the privilege family, the retention family and the
 * indexes of section 11. Each table's rows of real Sales come from RC-01's Action (EXC-45).
 */
final class CorrectionTablesTest extends DatabaseTestCase
{
    /** The six tables. */
    private const array TABLES = ['sale_corrections', 'refunds', 'refund_lines', 'return_lines', 'return_cost_allocations', 'return_cost_allocation_projections'];

    /** Their facts, class F: added, never changed or removed. */
    private const array FACTS = ['sale_corrections', 'refunds', 'refund_lines', 'return_lines', 'return_cost_allocations'];

    private int $owner = 0;

    private int $session = 0;

    private int $product = 0;

    /** Two units of the product sold for Rp15.000 in one line: the Sale, its line and its issue allocation. */
    private int $sale = 0;

    private int $line = 0;

    private int $issue = 0;

    /** Another Sale of one unit of the same product: its line and issue allocation. */
    private int $otherSale = 0;

    private int $otherLine = 0;

    private int $otherIssue = 0;

    /** @var list<int> the identities of commands, one per correction written directly */
    private array $commands = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->session = TokoContoh::openSession('Hendra', TokoContoh::register());
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'sellingPrice' => '7500']);
        TokoContoh::stock($this->product, '10', '10000');
        $this->sale = TokoContoh::sell('Hendra', $this->session, [['productId' => $this->product, 'quantity' => '2']]);
        $this->otherSale = TokoContoh::sell('Hendra', $this->session, [['productId' => $this->product, 'quantity' => '1']]);
        $this->line = TokoContoh::saleLinesOf($this->sale)[0] ?? 0;
        $this->otherLine = TokoContoh::saleLinesOf($this->otherSale)[0] ?? 0;
        $this->issue = $this->issueOf($this->line);
        $this->otherIssue = $this->issueOf($this->otherLine);

        foreach (range(1, 3) as $index) {
            TokoContoh::category('Perintah '.(string) $index);
            $this->commands[] = self::number($this->watcher()->table('command_identities')->max('id'));
        }
    }

    public function test_each_check_and_key_of_a_correction_refuses_its_violation(): void
    {
        foreach ([
            // BR-RFD-02, BR-RFD-12: a mandatory reason of 1 to 1,000 characters (TECHNICAL_RULES 11.3).
            'sale_corrections_reason_length' => [['reason' => ''], ['reason' => str_repeat('r', 1001)]],
            'sale_corrections_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('n', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['sale_id' => 999999]), '23503', 'sale_corrections_sale_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['actor_user_id' => 999999]), '23503', 'sale_corrections_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, ['command_id' => 999999]), '23503', 'sale_corrections_command_fk');

        foreach (['sale_id', 'business_date', 'reason', 'recorded_at', 'actor_user_id', 'actor_display_name', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertCorrection($c, [$column => null]), '23502');
        }

        // One correction per command (BR-CMD-02).
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertCorrection($c);
            $this->insertCorrection($c);
        }, '23505', 'sale_corrections_command_unique');

        // Several corrections of one Sale are admitted, each with its own command (BR-RFD-04).
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertCorrection($runtime, ['reason' => str_repeat('r', 1000)]);
            $this->insertCorrection($runtime, ['command_id' => $this->commands[1]]);
            $this->assertSame(2, $runtime->table('sale_corrections')->where('sale_id', $this->sale)->count());
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(['actor_display_name', 'actor_user_id', 'business_date', 'command_id', 'id', 'reason', 'recorded_at', 'sale_id'], $this->columnsOf('sale_corrections'));
    }

    public function test_each_check_and_key_of_a_refund_refuses_its_violation(): void
    {
        foreach ([
            // A refund refunds money: above zero, a stored amount at most (SCHEMA section 3.2).
            'refunds_amount_range' => [['amount' => 0], ['amount' => -1], ['amount' => 1000000000000]],
            // BR-RFD-05: every payout with its reference of 1 to 100 characters.
            'refunds_payout_reference_length' => [['payout_reference' => ''], ['payout_reference' => str_repeat('r', 101)]],
            // BR-RFD-05, BR-RFD-06: CASH from a drawer or the Owner's funds, BANK_TRANSFER from neither;
            // no other method (D-077), and a null passes nothing (SCH-13).
            'refunds_payout_funding' => [
                ['payout_method' => 'STATIC_QRIS'],
                ['payout_method' => 'CASH'],
                ['payout_method' => 'CASH', 'funding_source' => 'BANK'],
                ['funding_source' => 'OWNER_FUNDS'],
            ],
            // D-045: exactly one source; a drawer-funded refund names its session, no other does.
            'refunds_one_source' => [
                ['payout_method' => 'CASH', 'funding_source' => 'DRAWER'],
                ['payout_method' => 'CASH', 'funding_source' => 'OWNER_FUNDS', 'cash_session_id' => $this->session],
                ['cash_session_id' => $this->session],
            ],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertRefund($c, $this->insertCorrection($c), $values), '23514', $constraint);
            }
        }

        // The refund belongs to its correction's Sale; a drawer names a session that exists.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRefund($c, $this->insertCorrection($c), ['sale_id' => $this->otherSale]), '23503', 'refunds_correction_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRefund($c, 999999), '23503', 'refunds_correction_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRefund($c, $this->insertCorrection($c), ['payout_method' => 'CASH', 'funding_source' => 'DRAWER', 'cash_session_id' => 999999]), '23503', 'refunds_cash_session_fk');

        foreach (['correction_id', 'sale_id', 'amount', 'payout_method', 'payout_reference'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRefund($c, $this->insertCorrection($c), [$column => null]), '23502');
        }

        // At most one refund per correction (BR-RFD-04).
        $this->assertWriteRefused(function (Connection $c): void {
            $correction = $this->insertCorrection($c);
            $this->insertRefund($c, $correction);
            $this->insertRefund($c, $correction);
        }, '23505', 'refunds_correction_unique');

        // Each source of BR-RFD-06 is admitted, with the largest amount and the longest reference.
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertRefund($runtime, $this->insertCorrection($runtime), ['amount' => 999999999999, 'payout_reference' => str_repeat('r', 100)]);
            $this->insertRefund($runtime, $this->insertCorrection($runtime, ['command_id' => $this->commands[1]]), ['payout_method' => 'CASH', 'funding_source' => 'OWNER_FUNDS']);
            $this->insertRefund($runtime, $this->insertCorrection($runtime, ['command_id' => $this->commands[2]]), ['payout_method' => 'CASH', 'funding_source' => 'DRAWER', 'cash_session_id' => $this->session]);
            $this->assertSame(['BANK_TRANSFER|', 'CASH|DRAWER', 'CASH|OWNER_FUNDS'], array_values($runtime->table('refunds')->orderByRaw("payout_method || '|' || coalesce(funding_source, '')")->selectRaw("payout_method || '|' || coalesce(funding_source, '') as source")->pluck('source')->all()));
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(['amount', 'cash_session_id', 'correction_id', 'funding_source', 'id', 'payout_method', 'payout_reference', 'sale_id'], $this->columnsOf('refunds'));
    }

    public function test_each_check_and_key_of_a_refund_line_refuses_its_violation(): void
    {
        foreach ([['amount' => 0], ['amount' => -1], ['amount' => 1000000000000]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRefundLine($c, $this->insertRefund($c, $this->insertCorrection($c)), $values), '23514', 'refund_lines_amount_range');
        }

        // Every line belongs to the corrected Sale (SCHEMA section 5.9): the refund's, and the Sale line's.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRefundLine($c, $this->insertRefund($c, $this->insertCorrection($c)), ['sale_id' => $this->otherSale, 'sale_line_id' => $this->otherLine]), '23503', 'refund_lines_refund_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRefundLine($c, $this->insertRefund($c, $this->insertCorrection($c)), ['sale_line_id' => $this->otherLine]), '23503', 'refund_lines_sale_line_fk');

        foreach (['refund_id', 'sale_id', 'sale_line_id', 'amount'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRefundLine($c, $this->insertRefund($c, $this->insertCorrection($c)), [$column => null]), '23502');
        }

        // One line of a refund per Sale line.
        $this->assertWriteRefused(function (Connection $c): void {
            $refund = $this->insertRefund($c, $this->insertCorrection($c));
            $this->insertRefundLine($c, $refund);
            $this->insertRefundLine($c, $refund);
        }, '23505', 'refund_lines_refund_line_unique');

        $this->assertSame(['amount', 'id', 'refund_id', 'sale_id', 'sale_line_id'], $this->columnsOf('refund_lines'));
    }

    public function test_each_check_and_key_of_a_return_line_refuses_its_violation(): void
    {
        foreach ([
            'return_lines_quantity_range' => [['quantity' => '0'], ['quantity' => '-1'], ['quantity' => '100000000'], ['quantity' => 'NaN']],
            // SCHEMA section 5.9: SALEABLE or NOT_SALEABLE, nothing else.
            'return_lines_condition_allowed' => [['condition' => 'DAMAGED'], ['condition' => 'saleable']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnLine($c, $this->insertCorrection($c), $values), '23514', $constraint);
            }
        }

        // The correction's Sale, and the returned product is the Sale line's (SCHEMA section 5.9).
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnLine($c, $this->insertCorrection($c), ['sale_id' => $this->otherSale, 'sale_line_id' => $this->otherLine]), '23503', 'return_lines_correction_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnLine($c, $this->insertCorrection($c), ['sale_line_id' => $this->otherLine]), '23503', 'return_lines_sale_line_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnLine($c, $this->insertCorrection($c), ['product_id' => $other, 'condition' => 'NOT_SALEABLE']), '23503', 'return_lines_sale_line_fk');

        foreach (['correction_id', 'sale_id', 'sale_line_id', 'product_id', 'quantity', 'condition'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnLine($c, $this->insertCorrection($c), [$column => null]), '23502');
        }

        // One line per Sale line and condition in a correction; nobody writes the presence key (SCHEMA
        // section 3.7).
        $this->assertWriteRefused(function (Connection $c): void {
            $correction = $this->insertCorrection($c);
            $this->insertReturnLine($c, $correction, ['condition' => 'NOT_SALEABLE']);
            $this->insertReturnLine($c, $correction, ['condition' => 'NOT_SALEABLE']);
        }, '23505', 'return_lines_correction_line_condition_unique');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnLine($c, $this->insertCorrection($c), ['movement_presence' => 1]), '428C9');

        // BR-INV-39: the presence key is the line's own key exactly when it is saleable; both conditions
        // of one Sale line in one correction are admitted.
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $correction = $this->insertCorrection($runtime);
            $saleable = $this->insertReturnLine($runtime, $correction);
            $damaged = $this->insertReturnLine($runtime, $correction, ['condition' => 'NOT_SALEABLE', 'quantity' => '1']);
            $this->assertSame([$saleable, null], [$runtime->table('return_lines')->where('id', $saleable)->value('movement_presence'), $runtime->table('return_lines')->where('id', $damaged)->value('movement_presence')]);
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(['condition', 'correction_id', 'id', 'movement_presence', 'product_id', 'quantity', 'sale_id', 'sale_line_id'], $this->columnsOf('return_lines'));
    }

    public function test_a_return_receipt_moves_exactly_a_saleable_line_and_its_allocation_names_the_issue_of_that_line(): void
    {
        // EXC-44, BR-INV-39: the key of stock_movements that TASK-021 left for this Task, so that a
        // RETURN_RECEIPT moves exactly its saleable line's product and quantity.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);

        foreach ([['product_id' => $other], ['quantity' => '2', 'line_quantity' => '2'], ['return_line_id' => 999999]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnReceipt($c, $this->insertReturnLine($c, $this->insertCorrection($c)), $values), '23503', 'stock_movements_return_line_fk');
        }

        // A not-saleable line posts no RETURN_RECEIPT: its condition is not the movement's.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReturnReceipt($c, $this->insertReturnLine($c, $this->insertCorrection($c), ['condition' => 'NOT_SALEABLE'])), '23503', 'stock_movements_return_line_fk');

        foreach ([
            // BR-COST-19: only a saleable return line has a return allocation.
            'return_cost_allocations_saleable' => [['return_condition' => 'NOT_SALEABLE']],
            'return_cost_allocations_returned_quantity_range' => [['returned_quantity' => '0'], ['returned_quantity' => '100000000']],
            'return_cost_allocations_at_commit_status_allowed' => [['at_commit_status' => 'CANCELLED', 'at_commit_amount' => null]],
            'return_cost_allocations_at_commit_amount_range' => [['at_commit_amount' => '-1']],
            'return_cost_allocations_based_on_publication_positive' => [['based_on_publication_no' => 0]],
            // UNKNOWN is a status, never an amount (BR-COST-03; SCH-13).
            'return_cost_allocations_status_amount' => [['at_commit_amount' => null], ['at_commit_status' => 'UNKNOWN']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, ...$this->returnReceipt($c), values: $values), '23514', $constraint);
            }
        }

        // D-061: the link names the SALE_ISSUE allocation of the same Sale line and product; the fact is
        // its movement's and its return line's, with their product, quantity and place.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, ...$this->returnReceipt($c), values: ['issue_allocation_id' => $this->otherIssue]), '23503', 'return_cost_allocations_issue_allocation_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, ...$this->returnReceipt($c), values: ['product_sequence' => 9]), '23503', 'return_cost_allocations_movement_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, ...$this->returnReceipt($c), values: ['sale_line_id' => $this->otherLine, 'issue_allocation_id' => $this->otherIssue]), '23503', 'return_cost_allocations_return_line_fk');
        $this->assertWriteRefused(function (Connection $c): void {
            [$movement] = $this->returnReceipt($c);
            [, $returnLine] = $this->returnReceipt($c, ['product_sequence' => 5], $this->commands[1]);
            $this->insertAllocation($c, $movement, $returnLine);
        }, '23503', 'return_cost_allocations_movement_line_fk');

        foreach (['return_line_id', 'movement_id', 'product_id', 'sale_line_id', 'return_condition', 'issue_allocation_id', 'returned_quantity', 'product_sequence', 'at_commit_status', 'based_on_publication_no', 'recorded_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, ...$this->returnReceipt($c), values: [$column => null]), '23502');
        }

        // One return allocation per return line and per movement.
        $this->assertWriteRefused(function (Connection $c): void {
            [$movement, $returnLine] = $this->returnReceipt($c);
            $this->insertAllocation($c, $movement, $returnLine);
            $this->insertAllocation($c, $movement, $returnLine);
        }, '23505');

        foreach ([
            // A return projection is never CANCELLED: no reversal cancels a RETURN_RECEIPT (BR-INV-36).
            'return_cost_allocation_projections_current_status_allowed' => [['current_status' => 'CANCELLED', 'current_amount' => null]],
            'return_cost_allocation_projections_current_amount_range' => [['current_amount' => '-1']],
            'return_cost_allocation_projections_publication_no_positive' => [['publication_no' => 0]],
            'return_cost_allocation_projections_status_amount' => [['current_amount' => null], ['current_status' => 'UNKNOWN']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertProjection($c, $this->insertAllocation($c, ...$this->returnReceipt($c)), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertProjection($c, 999999), '23503', 'return_cost_allocation_projections_allocation_fk');
        $this->assertWriteRefused(function (Connection $c): void {
            $allocation = $this->insertAllocation($c, ...$this->returnReceipt($c));
            $this->insertProjection($c, $allocation);
            $this->insertProjection($c, $allocation);
        }, '23505', 'return_cost_allocation_projections_pkey');

        $this->assertSame(['at_commit_amount', 'at_commit_status', 'based_on_publication_no', 'id', 'issue_allocation_id', 'movement_id', 'product_id', 'product_sequence', 'recorded_at', 'return_condition', 'return_line_id', 'returned_quantity', 'sale_line_id'], $this->columnsOf('return_cost_allocations'));
        $this->assertSame(['allocation_id', 'current_amount', 'current_status', 'publication_no', 'updated_at'], $this->columnsOf('return_cost_allocation_projections'));
    }

    public function test_a_saleable_return_without_its_movement_allocation_or_projection_fails_at_commit_and_leaves_nothing(): void
    {
        // BR-INV-39, BR-INV-08; ADR-008 section 1: every statement is accepted, and the commit refuses
        // what is missing.
        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->insertReturnLine($c, $this->insertCorrection($c));
        }, 'return_lines_movement_fk', self::TABLES);

        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->returnReceipt($c);
        }, 'stock_movements_return_allocation_presence_fk', self::TABLES);

        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->insertAllocation($c, ...$this->returnReceipt($c));
        }, 'return_cost_allocations_projection_fk', self::TABLES);

        $this->assertSame(0, $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->count());

        // A not-saleable return posts nothing and needs nothing more (BR-INV-39).
        $this->runtime()->transaction(function (Connection $c): void {
            $this->insertReturnLine($c, $this->insertCorrection($c), ['condition' => 'NOT_SALEABLE']);
        });
        $this->assertSame([1, 1, 0], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('return_lines')->count(), $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->count()]);
    }

    public function test_a_correction_with_all_its_records_commits_in_one_transaction(): void
    {
        $this->runtime()->transaction(function (Connection $c): void {
            $correction = $this->insertCorrection($c);
            $this->insertRefundLine($c, $this->insertRefund($c, $correction));
            [$movement, $returnLine] = $this->returnReceipt($c, [], null, $correction);
            $this->insertProjection($c, $this->insertAllocation($c, $movement, $returnLine));
        });

        foreach (self::TABLES as $table) {
            $this->assertSame(1, $this->watcher()->table($table)->count(), $table);
        }

        $this->assertSame(['RETURN_RECEIPT', 'SALEABLE', 'SALEABLE'], [$this->watcher()->table('stock_movements')->where('product_sequence', 4)->where('product_id', $this->product)->value('movement_type'), $this->watcher()->table('return_lines')->value('condition'), $this->watcher()->table('return_cost_allocations')->value('return_condition')]);
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        // A real correction through RC-06's Action, so that every table holds a row (EXC-45).
        $this->corrected();
        $runtime = $this->runtime();
        $column = ['sale_corrections' => 'reason', 'refunds' => 'amount', 'refund_lines' => 'amount', 'return_lines' => 'quantity', 'return_cost_allocations' => 'at_commit_amount'];
        $value = ['sale_corrections' => 'Lain', 'refunds' => 1, 'refund_lines' => 1, 'return_lines' => '1', 'return_cost_allocations' => '1'];

        // Class F: a correction and its records are added, never changed or removed (SCH-02).
        foreach (self::FACTS as $table) {
            $this->assertSame(1, $runtime->table($table)->count(), $table);
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column[$table] => $value[$table]]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
        }

        // Class P: only a projection's value changes, by a replay's publication; never its key, and
        // nothing is removed (ADR-008 section 4.3).
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('return_cost_allocation_projections')->update(['allocation_id' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('return_cost_allocation_projections')->delete());
        $runtime->beginTransaction();

        try {
            $runtime->table('return_cost_allocation_projections')->lock('for no key update')->get();
            $this->assertSame(1, $runtime->table('return_cost_allocation_projections')->update(['current_status' => 'UNKNOWN', 'current_amount' => null, 'publication_no' => 9, 'updated_at' => now()]));
        } finally {
            $runtime->rollBack();
        }

        foreach (self::TABLES as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->truncate());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
        }

        // The runtime role takes nothing from the identity sequences (SCH-44).
        foreach (['sale_corrections_id_seq', 'refunds_id_seq', 'refund_lines_id_seq', 'return_lines_id_seq', 'return_cost_allocations_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }

        // The grants equal the matrix of database/privileges.php, every table's (db:check-privileges).
        $this->assertSame([], (new PrivilegeCheck(PrivilegeMatrix::load()))->run($runtime));
    }

    public function test_a_later_correction_only_adds_to_the_facts_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; BR-RFD-12. A later correction changes no
        // earlier correction, refund, return, movement or allocation, and no Sale fact.
        $this->corrected();

        $this->assertRetained([...self::FACTS, 'stock_movements', 'issue_cost_allocations', 'sales', 'sale_lines', 'payments', 'receipt_snapshots'], function (): void {
            TokoContoh::correct($this->sale, [['saleLineId' => $this->line, 'returnedNotSaleable' => '1']]);
        }, 'id');

        // An ordinary correction rewrites no earlier projection (ADR-008 section 4.2).
        $this->assertRetained(['return_cost_allocation_projections'], function (): void {
            TokoContoh::correct($this->otherSale, [['saleLineId' => $this->otherLine, 'returnedSaleable' => '1']]);
        }, 'allocation_id');

        $this->assertSame([3, 2], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('return_cost_allocations')->count()]);
    }

    public function test_the_indexes_of_schema_section_11_exist_and_the_keys_and_checks_are_as_declared(): void
    {
        $indexes = $this->watcher()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', [...self::TABLES, 'stock_movements'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            // SCHEMA section 11: the corrections of a Sale and by posting date (BR-COST-36); the
            // drawer-funded refunds of a session (G-05; TASK-034); the returns of an issue (ADR-008).
            'sale_corrections_sale_id_index' => 'CREATE INDEX sale_corrections_sale_id_index ON public.sale_corrections USING btree (sale_id)',
            'sale_corrections_business_date_index' => 'CREATE INDEX sale_corrections_business_date_index ON public.sale_corrections USING btree (business_date)',
            'refunds_drawer_session_index' => "CREATE INDEX refunds_drawer_session_index ON public.refunds USING btree (cash_session_id) WHERE (funding_source = 'DRAWER'::text)",
            'return_cost_allocations_issue_allocation_id_index' => 'CREATE INDEX return_cost_allocations_issue_allocation_id_index ON public.return_cost_allocations USING btree (issue_allocation_id)',
            // The unique keys.
            'sale_corrections_command_unique' => 'CREATE UNIQUE INDEX sale_corrections_command_unique ON public.sale_corrections USING btree (command_id)',
            'refunds_correction_unique' => 'CREATE UNIQUE INDEX refunds_correction_unique ON public.refunds USING btree (correction_id)',
            'return_cost_allocations_return_line_unique' => 'CREATE UNIQUE INDEX return_cost_allocations_return_line_unique ON public.return_cost_allocations USING btree (return_line_id)',
            'return_cost_allocations_movement_unique' => 'CREATE UNIQUE INDEX return_cost_allocations_movement_unique ON public.return_cost_allocations USING btree (movement_id)',
            // Every foreign-key column that no other key leads.
            'sale_corrections_actor_user_id_index' => 'CREATE INDEX sale_corrections_actor_user_id_index ON public.sale_corrections USING btree (actor_user_id)',
            'refunds_sale_id_index' => 'CREATE INDEX refunds_sale_id_index ON public.refunds USING btree (sale_id)',
            'refund_lines_sale_line_id_index' => 'CREATE INDEX refund_lines_sale_line_id_index ON public.refund_lines USING btree (sale_line_id)',
            'return_lines_sale_line_id_index' => 'CREATE INDEX return_lines_sale_line_id_index ON public.return_lines USING btree (sale_line_id)',
            'return_lines_movement_presence_index' => 'CREATE INDEX return_lines_movement_presence_index ON public.return_lines USING btree (movement_presence)',
            'stock_movements_return_allocation_presence_index' => 'CREATE INDEX stock_movements_return_allocation_presence_index ON public.stock_movements USING btree (return_allocation_presence)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }

        // The keys that go both ways are deferred to the commit; the key of a RETURN_RECEIPT to its line
        // is immediate (SCHEMA sections 3.6, 3.7 and 5.7).
        $keys = array_values(array_map(static fn (object $row): array => array_values((array) $row), $this->watcher()->table('pg_constraint')
            ->whereIn('conname', ['return_cost_allocations_projection_fk', 'return_lines_movement_fk', 'stock_movements_return_allocation_presence_fk', 'stock_movements_return_line_fk'])
            ->orderBy('conname')->get(['conname', 'condeferrable', 'condeferred'])->all()));
        $this->assertSame([
            ['return_cost_allocations_projection_fk', true, true],
            ['return_lines_movement_fk', true, true],
            ['stock_movements_return_allocation_presence_fk', true, true],
            ['stock_movements_return_line_fk', false, false],
        ], $keys);

        // Every check of the six tables uses only immutable functions (SCHEMA SCH-52).
        $this->assertSame([], array_values(array_filter($this->mutableChecks(), static fn (string $check): bool => str_starts_with($check, 'sale_corrections.') || str_starts_with($check, 'refund') || str_starts_with($check, 'return_'))));
    }

    /**
     * The Owner's correction of the Sale through RC-06's Action: Rp7.500 refunded by bank transfer and
     * one unit returned saleable (EXC-45).
     */
    private function corrected(): int
    {
        return TokoContoh::correct($this->sale, [['saleLineId' => $this->line, 'refundAmount' => '7500', 'returnedSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
    }

    /**
     * A correction of the Sale written directly, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertCorrection(Connection $c, array $values = []): int
    {
        return $c->table('sale_corrections')->insertGetId([
            'sale_id' => $this->sale, 'business_date' => '2026-11-13', 'reason' => 'Barang dikembalikan pembeli', 'recorded_at' => now(),
            'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'command_id' => $this->commands[0], ...$values,
        ]);
    }

    /**
     * A refund of Rp7.500 by bank transfer, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertRefund(Connection $c, int $correction, array $values = []): int
    {
        return $c->table('refunds')->insertGetId([
            'correction_id' => $correction, 'sale_id' => $this->sale, 'amount' => 7500, 'payout_method' => 'BANK_TRANSFER', 'funding_source' => null,
            'cash_session_id' => null, 'payout_reference' => 'TRF-1', ...$values,
        ]);
    }

    /**
     * A refund line of Rp7.500 on the Sale's line, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertRefundLine(Connection $c, int $refund, array $values = []): int
    {
        return $c->table('refund_lines')->insertGetId(['refund_id' => $refund, 'sale_id' => $this->sale, 'sale_line_id' => $this->line, 'amount' => 7500, ...$values]);
    }

    /**
     * A saleable return line of one unit of the Sale's line, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReturnLine(Connection $c, int $correction, array $values = []): int
    {
        return $c->table('return_lines')->insertGetId(['correction_id' => $correction, 'sale_id' => $this->sale, 'sale_line_id' => $this->line, 'product_id' => $this->product, 'quantity' => '1', 'condition' => 'SALEABLE', ...$values]);
    }

    /**
     * The RETURN_RECEIPT of a return line, the fourth movement of the product, unless $values says
     * otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReturnReceipt(Connection $c, int $returnLine, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 4, 'movement_type' => 'RETURN_RECEIPT', 'quantity' => '1',
            'return_line_id' => $returnLine, 'line_quantity' => '1', 'return_condition' => 'SALEABLE', 'recorded_at' => now(), 'actor_user_id' => $this->owner,
            'command_id' => $this->commands[0], ...$values,
        ]);
    }

    /**
     * A saleable return line of one unit, in this correction or a new one with this command, and its
     * RETURN_RECEIPT: the movement's key and the return line's.
     *
     * @param  array<string, mixed>  $movement
     * @return array{0: int, 1: int}
     */
    private function returnReceipt(Connection $c, array $movement = [], ?int $command = null, ?int $correction = null): array
    {
        $correction ??= $this->insertCorrection($c, $command === null ? [] : ['command_id' => $command]);
        $returnLine = $this->insertReturnLine($c, $correction);

        return [$this->insertReturnReceipt($c, $returnLine, $movement), $returnLine];
    }

    /**
     * The return allocation of a RETURN_RECEIPT of one unit, KNOWN Rp1.000, linked to the Sale line's
     * issue allocation, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertAllocation(Connection $c, int $movement, int $returnLine, array $values = []): int
    {
        return $c->table('return_cost_allocations')->insertGetId([
            'return_line_id' => $returnLine, 'movement_id' => $movement, 'product_id' => $this->product, 'sale_line_id' => $this->line, 'return_condition' => 'SALEABLE',
            'issue_allocation_id' => $this->issue, 'returned_quantity' => '1', 'product_sequence' => 4, 'at_commit_status' => 'KNOWN', 'at_commit_amount' => '1000',
            'based_on_publication_no' => 3, 'recorded_at' => now(), ...$values,
        ]);
    }

    /**
     * The projection of a return allocation, equal to its KNOWN fact, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertProjection(Connection $c, int $allocation, array $values = []): void
    {
        $c->table('return_cost_allocation_projections')->insert(['allocation_id' => $allocation, 'current_status' => 'KNOWN', 'current_amount' => '1000', 'publication_no' => 4, 'updated_at' => now(), ...$values]);
    }

    /**
     * The SALE_ISSUE allocation of a Sale line.
     */
    private function issueOf(int $line): int
    {
        return self::number($this->watcher()->table('issue_cost_allocations')->where('sale_line_id', $line)->value('id'));
    }

    /**
     * The table's columns, sorted.
     *
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        $columns = array_values(array_map(static fn (mixed $column): string => is_string($column) ? $column : '', $this->watcher()->table('information_schema.columns')->where('table_schema', 'public')->where('table_name', $table)->pluck('column_name')->all()));
        sort($columns);

        return $columns;
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    private static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
