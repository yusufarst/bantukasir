<?php

namespace Tests\Feature\Sales;

use App\Database\PrivilegeCheck;
use App\Database\PrivilegeMatrix;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The six tables and the sequence of TASK-028 and the keys it completes (AC-028-09; SCHEMA sections
 * 3.2, 3.5 to 3.7, 5.7 to 5.9, 6, 9.4, 10 and 11; SCH-13, SCH-20 to SCH-27; BR-SALE-05, BR-SALE-06,
 * BR-SALE-09, BR-SALE-17, BR-PAY-01 to BR-PAY-03, BR-DOC-01, BR-RFD-03; D-067, D-077; G-01, G-17; the
 * migration 2026_11_08_000001_create_sales_tables; EXECUTION_CONTEXT section 10, a table, and EXC-44;
 * suites DB01, KEEP01, POS01, POS02 and PAYEXT01; TASK-028 section 7, L2): the constraint family,
 * written directly by the runtime role — the Sale's number, exactly its business date and counter,
 * its session of its actor, its amounts and lengths; a line at a real revision of its own product
 * and amount, its total rounded once, one line per product; one Payment, CASH or BANK_TRANSFER with
 * its amounts; the receipt tied to its Sale, session, Payment and revision; the running totals within
 * their bounds; a SALE_ISSUE that moves exactly its line, and its allocation, its movement's line; the
 * deferred family — a Sale without its Payment, receipt or running total, a line without its
 * SALE_ISSUE and a SALE_ISSUE without its allocation, each failing at commit and leaving nothing; the
 * privilege family, the sequence included; the retention family; and the indexes of section 11.
 */
final class SalesTablesTest extends DatabaseTestCase
{
    /** The six tables. */
    private const array TABLES = ['sales', 'sale_lines', 'payments', 'receipt_snapshots', 'sale_correction_totals', 'sale_line_correction_totals'];

    /** Their facts, class F: added, never changed or removed. */
    private const array FACTS = ['sales', 'sale_lines', 'payments', 'receipt_snapshots'];

    private int $owner = 0;

    private int $profile = 0;

    private int $session = 0;

    private string $sessionNumber = '';

    private int $product = 0;

    private int $revision = 0;

    /** @var list<int> the identities of commands, one per Sale written directly */
    private array $commands = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = TokoContoh::shop();
        $this->owner = User::query()->where('role_super_admin', true)->firstOrFail()->id;
        $this->session = TokoContoh::openSession('Hendra', TokoContoh::register());
        $this->sessionNumber = $this->readText($this->watcher()->table('cash_sessions')->where('id', $this->session)->value('session_number'));
        $category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $category, 'sellingPrice' => '7500']);
        $this->revision = self::number($this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id'));

        foreach (range(1, 3) as $index) {
            TokoContoh::category('Perintah '.(string) $index);
            $this->commands[] = self::number($this->watcher()->table('command_identities')->max('id'));
        }
    }

    public function test_each_check_and_key_of_a_sale_refuses_its_violation(): void
    {
        foreach ([
            // SCH-22: the format, then exactly the business date and the counter, padded to six digits.
            'sales_number_format' => [['sale_number' => '2026118-000001'], ['sale_number' => '20261108-00001'], ['sale_number' => '20261108000001']],
            'sales_number_matches' => [
                ['sale_number' => '20261109-000001'],
                ['sale_number' => '20261108-000002'],
                ['sale_number' => '20261108-0000001'],
                ['sale_number_seq' => 1234567, 'sale_number' => '20261108-234567'],
            ],
            'sales_number_seq_positive' => [['sale_number_seq' => 0, 'sale_number' => '20261108-000000']],
            'sales_actor_display_name_length' => [['actor_display_name' => ''], ['actor_display_name' => str_repeat('n', 101)]],
            'sales_total_amount_range' => [['total_amount' => -1], ['total_amount' => 1000000000000]],
            // TECHNICAL_RULES section 11.2: one to 200 lines.
            'sales_line_count_range' => [['line_count' => 0], ['line_count' => 201]],
            'sales_customer_name_length' => [['customer_name' => ''], ['customer_name' => str_repeat('c', 101)]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertSale($c, $values), '23514', $constraint);
            }
        }

        // BR-SALE-02, G-04: a Sale belongs to a session whose responsible user is its actor.
        $rina = TokoContoh::person('Rina')['id'];
        $this->assertWriteRefused(fn (Connection $c) => $this->insertSale($c, ['actor_user_id' => $rina, 'actor_display_name' => 'Rina']), '23503', 'sales_cash_session_actor_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertSale($c, ['cash_session_id' => 999999]), '23503', 'sales_cash_session_actor_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertSale($c, ['command_id' => 999999]), '23503', 'sales_command_fk');

        foreach (['sale_number', 'sale_number_seq', 'business_date', 'committed_at', 'cash_session_id', 'actor_user_id', 'actor_display_name', 'total_amount', 'line_count', 'command_id'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertSale($c, [$column => null]), '23502');
        }

        // SCH-24: a counter is used once, whatever the date, and so is a number; one Sale per command
        // (BR-SALE-18).
        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertSale($c);
            $this->insertSale($c, ['business_date' => '2026-11-09', 'sale_number' => '20261109-000001', 'command_id' => $this->commands[1]]);
        }, '23505', 'sales_number_seq_unique');

        $this->assertWriteRefused(function (Connection $c): void {
            $this->insertSale($c);
            $this->insertSale($c, ['sale_number_seq' => 2, 'sale_number' => '20261108-000002']);
        }, '23505', 'sales_command_unique');

        // A counter past six digits takes more digits; the largest total and no customer name are
        // admitted; a Sale has no status of any kind (BR-SALE-01).
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertSale($runtime, ['sale_number_seq' => 1234567, 'sale_number' => '20261108-1234567', 'total_amount' => 999999999999]);
            $this->assertSame(1, $runtime->table('sales')->where('sale_number', '20261108-1234567')->count());
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(['actor_display_name', 'actor_user_id', 'business_date', 'cash_session_id', 'command_id', 'committed_at', 'customer_name', 'id', 'line_count', 'sale_number', 'sale_number_seq', 'total_amount'], $this->columnsOf('sales'));
    }

    public function test_each_check_and_key_of_a_sale_line_refuses_its_violation(): void
    {
        foreach ([
            'sale_lines_line_no_range' => [['line_no' => 0], ['line_no' => 201]],
            // A line total consistent with its price and quantity, so that only the range is refused.
            'sale_lines_unit_price_range' => [['unit_price' => 1000000000000, 'quantity' => '0.001', 'line_total' => 1000000000]],
            'sale_lines_quantity_range' => [['quantity' => '0', 'line_total' => 0], ['quantity' => '100000000', 'line_total' => 750000000000]],
            'sale_lines_line_total_range' => [['line_total' => -1]],
            // BR-SALE-06, BR-NUM-01: half-up once, never truncated or rounded twice.
            'sale_lines_line_total_rounded' => [['line_total' => 15001], ['quantity' => '1.5', 'line_total' => 11249], ['quantity' => '0.001', 'line_total' => 7]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertSale($c), $values), '23514', $constraint);
            }
        }

        // NaN passes no range (SCHEMA section 3.2).
        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertSale($c), ['quantity' => 'NaN']), '23514');

        // BR-SALE-05, D-036: the applied price is a real revision of this product with this amount.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain'), 'sellingPrice' => '7500']);
        $otherRevision = self::number($this->watcher()->table('products')->where('id', $other)->value('current_price_revision_id'));

        foreach ([['selling_price_revision_id' => $otherRevision], ['unit_price' => 7000, 'line_total' => 14000], ['selling_price_revision_id' => 999999]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertSale($c), $values), '23503', 'sale_lines_price_revision_fk');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, 999999), '23503', 'sale_lines_sale_fk');

        foreach (['sale_id', 'line_no', 'product_id', 'selling_price_revision_id', 'unit_price', 'quantity', 'line_total'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLine($c, $this->insertSale($c), [$column => null]), '23502');
        }

        // One line per number, and one line per product (D-067): a product is never on two lines.
        $this->assertWriteRefused(function (Connection $c) use ($other, $otherRevision): void {
            $sale = $this->insertSale($c);
            $this->insertLine($c, $sale);
            $this->insertLine($c, $sale, ['product_id' => $other, 'selling_price_revision_id' => $otherRevision]);
        }, '23505', 'sale_lines_sale_line_unique');

        $this->assertWriteRefused(function (Connection $c): void {
            $sale = $this->insertSale($c);
            $this->insertLine($c, $sale);
            $this->insertLine($c, $sale, ['line_no' => 2, 'quantity' => '1', 'line_total' => 7500]);
        }, '23505', 'sale_lines_sale_product_unique');

        // 1.5 units at Rp3.333 is Rp4.999,5, which rounds to Rp5.000 (BR-SALE-06): admitted.
        $weighed = TokoContoh::product('BERAS-01', ['categoryId' => TokoContoh::category('Beras'), 'baseUnit' => 'kg', 'quantityPrecision' => 3, 'sellingPrice' => '3333']);
        $weighedRevision = self::number($this->watcher()->table('products')->where('id', $weighed)->value('current_price_revision_id'));
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertLine($runtime, $this->insertSale($runtime), ['product_id' => $weighed, 'selling_price_revision_id' => $weighedRevision, 'unit_price' => 3333, 'quantity' => '1.5', 'line_total' => 5000]);
            $this->assertSame(['1.500', 5000], array_values((array) $runtime->table('sale_lines')->first(['quantity', 'line_total'])));
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(['id', 'line_no', 'line_total', 'product_id', 'quantity', 'sale_id', 'selling_price_revision_id', 'unit_price'], $this->columnsOf('sale_lines'));
    }

    public function test_each_check_and_key_of_a_payment_refuses_its_violation(): void
    {
        $transfer = ['method' => 'BANK_TRANSFER', 'tendered_amount' => null, 'change_amount' => null];

        foreach ([
            // BR-PAY-01, D-077: CASH and BANK_TRANSFER alone; STATIC_QRIS is no V1 method.
            'payments_method_allowed' => [['method' => 'STATIC_QRIS'], ['method' => 'QRIS'], ['method' => 'cash'], ['method' => 'MIXED']],
            'payments_applied_amount_range' => [['applied_amount' => -1]],
            'payments_tendered_amount_range' => [['tendered_amount' => 1000000000000, 'change_amount' => 999999985000]],
            'payments_change_amount_range' => [['tendered_amount' => 10000, 'change_amount' => -5000]],
            'payments_reference_length' => [[...$transfer, 'reference' => ''], [...$transfer, 'reference' => str_repeat('r', 101)]],
            // BR-PAY-02, BR-PAY-03: CASH with its tendered amount of at least the total, the change
            // that is the difference and no reference; BANK_TRANSFER with neither amount.
            'payments_method_amounts' => [
                ['tendered_amount' => null],
                ['tendered_amount' => 10000, 'change_amount' => 0],
                ['change_amount' => 4000],
                ['change_amount' => null],
                ['reference' => 'TRF-1'],
                ['method' => 'BANK_TRANSFER'],
                ['method' => 'BANK_TRANSFER', 'tendered_amount' => null],
                ['method' => 'BANK_TRANSFER', 'change_amount' => null],
            ],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertPayment($c, $this->insertSale($c), $values), '23514', $constraint);
            }
        }

        // The applied amount is the Sale total: neither less nor more, a partial payment included.
        foreach ([['applied_amount' => 14000, 'change_amount' => 6000], ['applied_amount' => 16000, 'change_amount' => 4000]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertPayment($c, $this->insertSale($c), $values), '23503', 'payments_sale_total_fk');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertPayment($c, 999999), '23503', 'payments_sale_total_fk');

        foreach (['sale_id', 'method', 'applied_amount'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertPayment($c, $this->insertSale($c), [$column => null]), '23502');
        }

        // Exactly one Payment: no split, second or mixed payment (BR-PAY-01).
        $this->assertWriteRefused(function (Connection $c) use ($transfer): void {
            $sale = $this->insertSale($c);
            $this->insertPayment($c, $sale);
            $this->insertPayment($c, $sale, $transfer);
        }, '23505', 'payments_sale_unique');

        // A transfer of the exact total with or without its reference is admitted; it has no
        // pending state, no fee and no provider (BR-PAY-05, BR-PAY-07).
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $this->insertPayment($runtime, $this->insertSale($runtime), [...$transfer, 'reference' => str_repeat('r', 100)]);
            $this->insertPayment($runtime, $this->insertSale($runtime, ['sale_number_seq' => 2, 'sale_number' => '20261108-000002', 'command_id' => $this->commands[1]]), $transfer);
            $this->assertSame(2, $runtime->table('payments')->where('method', 'BANK_TRANSFER')->count());
        } finally {
            $runtime->rollBack();
        }

        $this->assertSame(['applied_amount', 'change_amount', 'id', 'method', 'reference', 'sale_id', 'tendered_amount'], $this->columnsOf('payments'));
    }

    public function test_each_check_and_key_of_a_receipt_snapshot_refuses_its_violation(): void
    {
        $transfer = ['payment_method' => 'BANK_TRANSFER', 'tendered_amount' => null, 'change_amount' => null];

        foreach ([
            'receipt_snapshots_cashier_display_name_length' => [['cashier_display_name' => ''], ['cashier_display_name' => str_repeat('n', 101)]],
            // ARC-45: a template of the code base names its version.
            'receipt_snapshots_template_version_format' => [['template_version' => ''], ['template_version' => 'Receipt 1'], ['template_version' => str_repeat('a', 65)]],
            'receipt_snapshots_customer_name_length' => [['customer_name' => ''], ['customer_name' => str_repeat('c', 101)]],
            'receipt_snapshots_lines_array' => [['lines' => '{}'], ['lines' => '"Pulpen"'], ['lines' => '7']],
            // CASH prints its tendered amount and change, and nothing else does (DC-01).
            'receipt_snapshots_cash_amounts' => [['tendered_amount' => null], ['tendered_amount' => null, 'change_amount' => null], ['change_amount' => null], ['payment_method' => 'BANK_TRANSFER'], [...$transfer, 'change_amount' => 5000]],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), $values), '23514', $constraint);
            }
        }

        // G-17: the printed number, total and cashier are the Sale's, its session number the
        // session's, its method and cash amounts the Payment's.
        foreach ([['sale_number' => '20261108-000002'], ['total_amount' => 15001], ['cashier_display_name' => 'Rina']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), $values), '23503', 'receipt_snapshots_sale_fk');
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), ['cash_session_number' => '20261108-999999']), '23503', 'receipt_snapshots_session_number_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), $transfer), '23503', 'receipt_snapshots_payment_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), ['tendered_amount' => 25000, 'change_amount' => 10000]), '23503', 'receipt_snapshots_cash_payment_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), ['business_profile_revision_id' => 999999]), '23503', 'receipt_snapshots_profile_revision_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), ['logo_file_id' => 999999]), '23503', 'receipt_snapshots_logo_file_fk');

        foreach (['sale_id', 'sale_number', 'total_amount', 'cash_session_id', 'cashier_display_name', 'business_profile_revision_id', 'template_version', 'issued_at', 'cash_session_number', 'payment_method', 'lines'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertReceipt($c, $this->paidSale($c), [$column => null]), '23502');
        }

        // One receipt per Sale; a reprint adds only its audit event (BR-DOC-04).
        $this->assertWriteRefused(function (Connection $c): void {
            $sale = $this->paidSale($c);
            $this->insertReceipt($c, $sale);
            $this->insertReceipt($c, $sale);
        }, '23505', 'receipt_snapshots_sale_unique');

        // It holds no cost or profit (BR-DOC-01, BR-COST-08), and its shop identity by reference.
        $this->assertSame(['business_profile_revision_id', 'cash_session_id', 'cash_session_number', 'cashier_display_name', 'change_amount', 'customer_name', 'id', 'issued_at', 'lines', 'logo_file_id', 'payment_method', 'sale_id', 'sale_number', 'template_version', 'tendered_amount', 'total_amount'], $this->columnsOf('receipt_snapshots'));
    }

    public function test_each_check_and_key_of_the_running_totals_refuses_its_violation(): void
    {
        // BR-RFD-03, SCH-33: the money and quantity bounds hold in the database, whatever corrects them.
        foreach (['sale_correction_totals_refunded_range' => [['refunded_amount' => -1], ['refunded_amount' => 15001]]] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertTotals($c, $this->insertSale($c), $values), '23514', $constraint);
            }
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertTotals($c, $this->insertSale($c), ['sale_total' => 14000, 'refunded_amount' => 0]), '23503', 'sale_correction_totals_sale_fk');
        $this->assertWriteRefused(function (Connection $c): void {
            $sale = $this->insertSale($c);
            $this->insertTotals($c, $sale);
            $this->insertTotals($c, $sale);
        }, '23505', 'sale_correction_totals_pkey');

        foreach ([
            'sale_line_correction_totals_refunded_range' => [['refunded_amount' => -1], ['refunded_amount' => 15001]],
            'sale_line_correction_totals_returned_range' => [['returned_quantity' => '-1'], ['returned_quantity' => '2.001'], ['returned_quantity' => 'NaN']],
        ] as $constraint => $cases) {
            foreach ($cases as $values) {
                $this->assertWriteRefused(fn (Connection $c) => $this->insertLineTotals($c, $this->insertLine($c, $this->insertSale($c)), $values), '23514', $constraint);
            }
        }

        foreach ([['line_total' => 14000], ['issued_quantity' => '3']] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLineTotals($c, $this->insertLine($c, $this->insertSale($c)), $values), '23503', 'sale_line_correction_totals_line_fk');
        }

        foreach (['sale_total', 'refunded_amount', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertTotals($c, $this->insertSale($c), [$column => null]), '23502');
        }

        foreach (['line_total', 'issued_quantity', 'refunded_amount', 'returned_quantity', 'updated_at'] as $column) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertLineTotals($c, $this->insertLine($c, $this->insertSale($c)), [$column => null]), '23502');
        }

        // The whole bound is admitted: everything refunded, everything returned.
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $sale = $this->insertSale($runtime);
            $this->insertTotals($runtime, $sale, ['refunded_amount' => 15000]);
            $this->insertLineTotals($runtime, $this->insertLine($runtime, $sale), ['refunded_amount' => 15000, 'returned_quantity' => '2']);
            $this->assertSame(1, $runtime->table('sale_line_correction_totals')->where('returned_quantity', '2')->count());
        } finally {
            $runtime->rollBack();
        }
    }

    public function test_a_sale_issue_moves_exactly_its_line_and_its_allocation_names_that_line(): void
    {
        // EXC-44, BR-SALE-09: the key of stock_movements that TASK-021 left for this Task, so that a
        // SALE_ISSUE moves exactly its line's product and quantity, and names a line that exists.
        $other = TokoContoh::product('LAIN-01', ['categoryId' => TokoContoh::category('Lain')]);

        foreach ([['product_id' => $other], ['quantity' => '-3', 'line_quantity' => '3'], ['sale_line_id' => 999999]] as $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertSaleIssue($c, $this->insertLine($c, $this->paidSale($c)), $values), '23503', 'stock_movements_sale_line_fk');
        }

        // ADR-008 section 2: a Sale's allocation names its movement's own line (the key of TASK-023,
        // moved here from IssueTablesTest now that a line exists): another line of the same Sale,
        // with the same quantity, is refused.
        $otherRevision = self::number($this->watcher()->table('products')->where('id', $other)->value('current_price_revision_id'));
        $this->assertWriteRefused(function (Connection $c) use ($other, $otherRevision): void {
            $sale = $this->paidSale($c);
            $line = $this->insertLine($c, $sale);
            $second = $this->insertLine($c, $sale, ['line_no' => 2, 'product_id' => $other, 'selling_price_revision_id' => $otherRevision]);
            $this->insertAllocation($c, $this->insertSaleIssue($c, $line), $second);
        }, '23503', 'issue_cost_allocations_movement_sale_line_fk');

        // The key of issue_cost_allocations to the line's quantity (TASK-028) is the last of a chain:
        // the allocation's quantity is its movement's, which is its line's, so every row that would
        // break it breaks an earlier key first; it is proven to exist with its columns.
        $this->assertWriteRefused(fn (Connection $c) => $this->insertAllocation($c, $this->insertSaleIssue($c, $this->insertLine($c, $this->paidSale($c))), 999999), '23503');
        $this->assertSame([
            ['issue_cost_allocations_sale_line_fk', 'issue_cost_allocations', 'sale_lines', '{sale_line_id,issued_quantity}', '{id,quantity}'],
            ['stock_movements_sale_line_fk', 'stock_movements', 'sale_lines', '{sale_line_id,product_id,line_quantity}', '{id,product_id,quantity}'],
        ], $this->foreignKeys(['issue_cost_allocations_sale_line_fk', 'stock_movements_sale_line_fk']));
    }

    public function test_a_sale_without_its_payment_receipt_or_running_total_fails_at_commit_and_leaves_nothing(): void
    {
        // BR-SALE-17, AC-028-01: every statement is accepted, and the commit refuses what is missing.
        $this->assertFailsAtCommit(function (Connection $c): void {
            $sale = $this->paidSale($c);
            $this->insertReceipt($c, $sale);
        }, 'sales_correction_totals_fk', self::TABLES);

        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->insertTotals($c, $this->paidSale($c));
        }, 'sales_receipt_fk', self::TABLES);

        // Without its Payment a Sale has no receipt either, whose key needs the Payment: the commit
        // refuses both.
        $this->assertCommitRefused(function (Connection $c): void {
            $this->insertTotals($c, $this->insertSale($c));
        }, ['sales_payment_fk', 'sales_receipt_fk']);

        // Every line posts its SALE_ISSUE (BR-SALE-09), and every SALE_ISSUE its allocation (BR-INV-08).
        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->insertLine($c, $this->completeSale($c));
        }, 'sale_lines_movement_fk', [...self::TABLES, 'stock_movements']);

        $this->assertFailsAtCommit(function (Connection $c): void {
            $this->insertSaleIssue($c, $this->insertLine($c, $this->completeSale($c)));
        }, 'stock_movements_issue_allocation_presence_fk', [...self::TABLES, 'stock_movements', 'issue_cost_allocations']);
    }

    public function test_a_sale_with_all_its_records_commits_in_one_transaction(): void
    {
        $this->runtime()->transaction(function (Connection $c): void {
            $line = $this->insertLine($c, $this->completeSale($c));
            $this->insertProjection($c, $this->insertAllocation($c, $this->insertSaleIssue($c, $line), $line));
        });

        foreach (['sales', 'sale_lines', 'payments', 'receipt_snapshots', 'sale_correction_totals'] as $table) {
            $this->assertSame(1, $this->watcher()->table($table)->count(), $table);
        }

        $this->assertSame(['SALE_ISSUE', 'SALE_ISSUE'], [$this->watcher()->table('stock_movements')->value('movement_type'), $this->watcher()->table('issue_cost_allocations')->value('issue_type')]);
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $sale = $this->soldOnce();
        $runtime = $this->runtime();
        $column = ['sales' => 'total_amount', 'sale_lines' => 'quantity', 'payments' => 'applied_amount', 'receipt_snapshots' => 'customer_name'];
        $value = ['sales' => 1, 'sale_lines' => '1', 'payments' => 1, 'receipt_snapshots' => 'Lain'];

        // Class F: a Sale and its records are added, never changed or removed (ARC-70).
        foreach (self::FACTS as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column[$table] => $value[$table]]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
        }

        // Class P: only the amounts of a correction change, and nothing is removed; the row's key and
        // its bound never change (SCHEMA section 5.9).
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('sale_correction_totals')->update(['sale_total' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('sale_correction_totals')->update(['sale_id' => $sale]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('sale_correction_totals')->delete());

        $runtime->beginTransaction();

        try {
            // The row a refund or return locks, as the original Sale's guard (SCHEMA section 7).
            $runtime->table('sale_correction_totals')->where('sale_id', $sale)->lock('for no key update')->get();
            $this->assertSame(1, $runtime->table('sale_correction_totals')->where('sale_id', $sale)->update(['refunded_amount' => 1, 'updated_at' => now()]));

            $line = self::number($runtime->table('sale_lines')->where('sale_id', $sale)->value('id'));
            $runtime->table('sale_line_correction_totals')->insert(['sale_line_id' => $line, 'line_total' => 7500, 'issued_quantity' => '1', 'refunded_amount' => 0, 'returned_quantity' => '0', 'updated_at' => now()]);
            $this->assertSame(1, $runtime->table('sale_line_correction_totals')->where('sale_line_id', $line)->update(['refunded_amount' => 1, 'returned_quantity' => '1', 'updated_at' => now()]));
        } finally {
            $runtime->rollBack();
        }

        $this->assertDenied($runtime, fn (Connection $c) => $c->table('sale_line_correction_totals')->update(['line_total' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('sale_line_correction_totals')->update(['issued_quantity' => '1']));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('sale_line_correction_totals')->delete());

        foreach (self::TABLES as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->truncate());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
        }

        // SCH-20, SCH-27, SCH-44: the number sequence is CACHE 1 and NO CYCLE; the runtime role takes
        // its values and never moves it; it takes nothing from the identity sequences.
        $this->assertNumberSequence('sale_number_seq');
        $this->assertIsInt($runtime->scalar("select pg_catalog.nextval('sale_number_seq')"));

        foreach (['sales_id_seq', 'sale_lines_id_seq', 'payments_id_seq', 'receipt_snapshots_id_seq'] as $sequence) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->select('select pg_catalog.nextval(?)', [$sequence]));
        }

        $this->assertDenied($this->backup(), fn (Connection $c) => $c->scalar("select pg_catalog.nextval('sale_number_seq')"));
        $this->assertIsInt($this->backup()->scalar('select last_value from sale_number_seq'));

        // The grants equal the matrix of database/privileges.php, every table's (db:check-privileges).
        $this->assertSame([], (new PrivilegeCheck(PrivilegeMatrix::load()))->run($runtime));
    }

    public function test_a_later_sale_only_adds_to_the_facts_and_leaves_every_earlier_one(): void
    {
        // The retention family: SCHEMA section 9.4, SCH-02; a Sale changes no earlier Sale, line,
        // Payment, receipt, movement or allocation fact.
        $this->soldOnce();

        $this->assertRetained([...self::FACTS, 'stock_movements', 'issue_cost_allocations'], function (): void {
            $this->soldOnce();
        }, 'id');

        // An ordinary posting writes no earlier projection (ADR-008 section 4.2), nor an earlier
        // Sale's running total.
        $this->assertRetained(['issue_cost_allocation_projections'], function (): void {
            $this->soldOnce();
        }, 'allocation_id');

        $this->assertRetained(['sale_correction_totals'], function (): void {
            $this->soldOnce();
        }, 'sale_id');

        $this->assertSame(4, $this->watcher()->table('sales')->count());
    }

    public function test_the_indexes_of_schema_section_11_exist_and_the_checks_are_immutable(): void
    {
        $indexes = $this->watcher()->table('pg_indexes')->where('schemaname', 'public')->whereIn('tablename', [...self::TABLES, 'issue_cost_allocations'])->pluck('indexdef', 'indexname')->all();

        foreach ([
            'sales_committed_at_id_index' => 'CREATE INDEX sales_committed_at_id_index ON public.sales USING btree (committed_at, id)',
            'sales_cash_session_id_index' => 'CREATE INDEX sales_cash_session_id_index ON public.sales USING btree (cash_session_id)',
            'sales_actor_committed_at_id_index' => 'CREATE INDEX sales_actor_committed_at_id_index ON public.sales USING btree (actor_user_id, committed_at, id)',
            'sales_business_date_index' => 'CREATE INDEX sales_business_date_index ON public.sales USING btree (business_date)',
            'sales_number_unique' => 'CREATE UNIQUE INDEX sales_number_unique ON public.sales USING btree (sale_number)',
            'sales_number_seq_unique' => 'CREATE UNIQUE INDEX sales_number_seq_unique ON public.sales USING btree (sale_number_seq)',
            'sales_command_unique' => 'CREATE UNIQUE INDEX sales_command_unique ON public.sales USING btree (command_id)',
            'sale_lines_sale_id_index' => 'CREATE INDEX sale_lines_sale_id_index ON public.sale_lines USING btree (sale_id)',
            'sale_lines_price_revision_index' => 'CREATE INDEX sale_lines_price_revision_index ON public.sale_lines USING btree (selling_price_revision_id)',
            'sale_lines_sale_product_unique' => 'CREATE UNIQUE INDEX sale_lines_sale_product_unique ON public.sale_lines USING btree (sale_id, product_id)',
            'payments_sale_unique' => 'CREATE UNIQUE INDEX payments_sale_unique ON public.payments USING btree (sale_id)',
            'receipt_snapshots_sale_unique' => 'CREATE UNIQUE INDEX receipt_snapshots_sale_unique ON public.receipt_snapshots USING btree (sale_id)',
            'receipt_snapshots_profile_revision_index' => 'CREATE INDEX receipt_snapshots_profile_revision_index ON public.receipt_snapshots USING btree (business_profile_revision_id)',
            'receipt_snapshots_logo_file_id_index' => 'CREATE INDEX receipt_snapshots_logo_file_id_index ON public.receipt_snapshots USING btree (logo_file_id)',
            'receipt_snapshots_cash_session_id_index' => 'CREATE INDEX receipt_snapshots_cash_session_id_index ON public.receipt_snapshots USING btree (cash_session_id)',
            'sale_correction_totals_pkey' => 'CREATE UNIQUE INDEX sale_correction_totals_pkey ON public.sale_correction_totals USING btree (sale_id)',
            'sale_line_correction_totals_pkey' => 'CREATE UNIQUE INDEX sale_line_correction_totals_pkey ON public.sale_line_correction_totals USING btree (sale_line_id)',
            'issue_cost_allocations_sale_line_id_index' => 'CREATE INDEX issue_cost_allocations_sale_line_id_index ON public.issue_cost_allocations USING btree (sale_line_id)',
        ] as $name => $definition) {
            $this->assertSame($definition, $indexes[$name] ?? null, $name);
        }

        // The checks of SCH-22 and of the line total use only immutable functions (SCHEMA section 6).
        $this->assertSame([], array_values(array_filter($this->mutableChecks(), static fn (string $check): bool => str_starts_with($check, 'sale') || str_starts_with($check, 'payments') || str_starts_with($check, 'receipt_snapshots'))));
    }

    /**
     * The deferred family where a transaction misses two required rows: every statement is accepted,
     * the commit is refused by one of the named keys, and nothing is left.
     *
     * @param  Closure(Connection): mixed  $work
     * @param  list<string>  $constraints
     */
    private function assertCommitRefused(Closure $work, array $constraints): void
    {
        $committing = false;

        try {
            $this->runtime()->transaction(function (Connection $connection) use ($work, &$committing): void {
                $work($connection);
                $committing = true;
            });
            $this->fail('The commit succeeded; a deferred key refuses it.');
        } catch (QueryException|PDOException $refusal) {
            $this->assertTrue($committing, 'A statement failed before the commit: '.$refusal->getMessage());
            $this->assertSame('23503', (string) $refusal->getCode(), $refusal->getMessage());
            $this->assertMatchesRegularExpression('/"(?:'.implode('|', $constraints).')"/', $refusal->getMessage());
        }

        $this->assertSame(0, $this->runtime()->transactionLevel());
        $this->assertNothingIn(self::TABLES);
    }

    /**
     * One unit of the product, received at Rp1.000 and sold by the Owner in the session through
     * RC-01's Action (EXECUTION_CONTEXT EXC-45): the Sale's internal key.
     */
    private function soldOnce(): int
    {
        TokoContoh::stock($this->product, '1');

        return TokoContoh::sell('Hendra', $this->session, [['productId' => $this->product, 'quantity' => '1']]);
    }

    /**
     * The Owner's Sale 20261108-000001 of Rp15.000 in one line, written directly, unless $values says
     * otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertSale(Connection $c, array $values = []): int
    {
        return $c->table('sales')->insertGetId([
            'sale_number' => '20261108-000001', 'sale_number_seq' => 1, 'business_date' => '2026-11-08', 'committed_at' => '2026-11-08 03:00:00+00',
            'cash_session_id' => $this->session, 'actor_user_id' => $this->owner, 'actor_display_name' => 'Hendra', 'total_amount' => 15000, 'line_count' => 1,
            'customer_name' => null, 'command_id' => $this->commands[0], ...$values,
        ]);
    }

    /**
     * A line of two units of the product at Rp7.500, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertLine(Connection $c, int $sale, array $values = []): int
    {
        return $c->table('sale_lines')->insertGetId([
            'sale_id' => $sale, 'line_no' => 1, 'product_id' => $this->product, 'selling_price_revision_id' => $this->revision, 'unit_price' => 7500,
            'quantity' => '2', 'line_total' => 15000, ...$values,
        ]);
    }

    /**
     * The CASH Payment of Rp15.000 with Rp20.000 tendered, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertPayment(Connection $c, int $sale, array $values = []): void
    {
        $c->table('payments')->insert(['sale_id' => $sale, 'method' => 'CASH', 'applied_amount' => 15000, 'tendered_amount' => 20000, 'change_amount' => 5000, 'reference' => null, ...$values]);
    }

    /**
     * The Sale with its Payment.
     */
    private function paidSale(Connection $c): int
    {
        $sale = $this->insertSale($c);
        $this->insertPayment($c, $sale);

        return $sale;
    }

    /**
     * The receipt snapshot of the Sale and its Payment, with no lines, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertReceipt(Connection $c, int $sale, array $values = []): void
    {
        $c->table('receipt_snapshots')->insert([
            'sale_id' => $sale, 'sale_number' => '20261108-000001', 'total_amount' => 15000, 'cash_session_id' => $this->session, 'cashier_display_name' => 'Hendra',
            'business_profile_revision_id' => $this->profile, 'logo_file_id' => null, 'template_version' => 'receipt.1', 'issued_at' => '2026-11-08 03:00:00+00',
            'cash_session_number' => $this->sessionNumber, 'payment_method' => 'CASH', 'tendered_amount' => 20000, 'change_amount' => 5000, 'customer_name' => null,
            'lines' => '[]', ...$values,
        ]);
    }

    /**
     * The Sale's running total, nothing refunded, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertTotals(Connection $c, int $sale, array $values = []): void
    {
        $c->table('sale_correction_totals')->insert(['sale_id' => $sale, 'sale_total' => 15000, 'refunded_amount' => 0, 'updated_at' => now(), ...$values]);
    }

    /**
     * The Sale with its Payment, receipt and running total.
     */
    private function completeSale(Connection $c): int
    {
        $sale = $this->paidSale($c);
        $this->insertReceipt($c, $sale);
        $this->insertTotals($c, $sale);

        return $sale;
    }

    /**
     * A line's running total, nothing refunded or returned, unless $values says otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertLineTotals(Connection $c, int $line, array $values = []): void
    {
        $c->table('sale_line_correction_totals')->insert(['sale_line_id' => $line, 'line_total' => 15000, 'issued_quantity' => '2', 'refunded_amount' => 0, 'returned_quantity' => '0', 'updated_at' => now(), ...$values]);
    }

    /**
     * The SALE_ISSUE of a line of two units, first in the product's sequence, unless $values says
     * otherwise.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertSaleIssue(Connection $c, int $line, array $values = []): int
    {
        return $c->table('stock_movements')->insertGetId([
            'product_id' => $this->product, 'base_unit' => 'pcs', 'quantity_precision' => 0, 'product_sequence' => 1, 'movement_type' => 'SALE_ISSUE', 'quantity' => '-2',
            'sale_line_id' => $line, 'line_quantity' => '2', 'recorded_at' => now(), 'actor_user_id' => $this->owner, 'command_id' => $this->commands[0], ...$values,
        ]);
    }

    /**
     * The UNKNOWN allocation of a SALE_ISSUE of two units, naming this line.
     */
    private function insertAllocation(Connection $c, int $movement, int $line): int
    {
        return $c->table('issue_cost_allocations')->insertGetId([
            'movement_id' => $movement, 'product_id' => $this->product, 'issue_type' => 'SALE_ISSUE', 'movement_quantity' => '-2', 'product_sequence' => 1, 'issued_quantity' => '2',
            'sale_line_id' => $line, 'at_commit_status' => 'UNKNOWN', 'at_commit_amount' => null, 'based_on_publication_no' => 1, 'recorded_at' => now(),
        ]);
    }

    /**
     * The projection of an allocation, equal to the UNKNOWN fact.
     */
    private function insertProjection(Connection $c, int $allocation): void
    {
        $c->table('issue_cost_allocation_projections')->insert(['allocation_id' => $allocation, 'issue_type' => 'SALE_ISSUE', 'current_status' => 'UNKNOWN', 'current_amount' => null, 'publication_no' => 2, 'updated_at' => now()]);
    }

    /**
     * Each foreign key by name: its table, the table it targets and the columns on each side, in order.
     *
     * @param  list<string>  $names
     * @return list<list<mixed>>
     */
    private function foreignKeys(array $names): array
    {
        $rows = $this->watcher()->select(<<<'SQL'
            select c.conname,
                (select r.relname from pg_catalog.pg_class r where r.oid = c.conrelid) as source,
                (select r.relname from pg_catalog.pg_class r where r.oid = c.confrelid) as target,
                (select pg_catalog.array_agg(a.attname order by k.ord) from pg_catalog.unnest(c.conkey) with ordinality as k(attnum, ord)
                    join pg_catalog.pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.attnum)::text as columns,
                (select pg_catalog.array_agg(a.attname order by k.ord) from pg_catalog.unnest(c.confkey) with ordinality as k(attnum, ord)
                    join pg_catalog.pg_attribute a on a.attrelid = c.confrelid and a.attnum = k.attnum)::text as targets
            from pg_catalog.pg_constraint c
            where c.contype = 'f' and c.conname = any (?::text[])
            order by c.conname
            SQL, ['{'.implode(',', $names).'}']);
        $keys = [];

        foreach ($rows as $row) {
            $keys[] = array_values((array) $row);
        }

        return $keys;
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
