<?php

namespace App\Queries\Audit;

use App\Domain\Access\Role;
use App\Domain\Catalog\Barcodes\Symbology;
use App\Domain\Commands\IntentReconciliations;
use App\Domain\Costing\CostIncompleteMarks;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\StockLedger;
use App\Domain\Operations\Reconciliation\ProductReconciliation;
use App\Domain\Sales\Corrections\Payout;
use App\Domain\Sales\PaymentMethods;
use App\Queries\Inventory\Column as Read;
use Illuminate\Support\Facades\DB;

/**
 * What "Perubahan" of an expanded row of Riwayat aktivitas shows (COPY_DECK section 11.15, the change
 * control of TASK-051 approved at gate GATE-CC3, D-138; ROUTE_CONTRACTS RP-612; UXI-378; OPEN_ITEMS T-56).
 * For each action code, the rows that section 11.15.3 names, in its order, built from the data the event
 * already holds: each row reaches the page as its copy key and its value, never as a stored field name
 * (ACV-01). A value is a kind the page formats — money, a variance, a cost, a quantity in the product's base
 * unit and precision, a signed quantity, a date, a count, a text — or the copy keys of a stored code, which
 * the page joins (ACV-03, ACV-04); a code without a label in section 11.15.2 is not shown, and a row whose
 * value the event does not hold is left out. The rows and columns marked "Pemilik" come from the event's
 * Owner-only data and are built only when the reader is the Owner (ACV-02; CAP-32, BR-AUD-02, D-047); the
 * reason stays under "Alasan" and is never repeated here. A product, a category or a user is shown by its
 * name now, read once per page by its keys (ACV-04, ACV-05). It reads and writes no audit record itself.
 *
 * @phpstan-type Value array{kind: string, text: string|null, keys: list<string>, precision: int|null, unit: string|null}
 * @phpstan-type Change array{label: string, kind: string, value: Value|null, from: Value|null, to: Value|null, columns: list<string>, lines: list<list<Value|null>>}
 * @phpstan-type Product array{name: string, unit: string, precision: int}
 * @phpstan-type Names array{product: array<int, Product>, category: array<int, string>, user: array<int, string>}
 * @phpstan-type Event array{action: string, change: array<array-key, mixed>, owner: array<array-key, mixed>|null, product: int|null}
 */
final class ChangeRows
{
    /** The prefix of every row and column label (COPY_DECK section 11.15.2). */
    private const string FIELD = 'audit.change.field.';

    private const string COLUMN = 'audit.change.column.';

    private const string VALUE = 'audit.change.value.';

    /** Payment and payout methods (`sales.payment.*`), by the names of their one place (ARC-30). */
    private const array METHODS = [PaymentMethods::CASH => 'sales.payment.method_cash', PaymentMethods::BANK_TRANSFER => 'sales.payment.method_transfer'];

    /** The source of a refund's cash. */
    private const array SOURCES = [Payout::DRAWER => self::VALUE.'source_drawer', Payout::OWNER_FUNDS => 'sales.refund.source_owner'];

    /** Movement types (COPY_DECK section 9.1). */
    private const array MOVEMENT_TYPES = [
        StockLedger::OPENING => 'inventory.type.opening', StockLedger::RECEIPT => 'inventory.type.receipt', StockLedger::SALE_ISSUE => 'inventory.type.sale_issue',
        StockLedger::MANUAL_ISSUE => 'inventory.type.manual_issue', StockLedger::ADJUSTMENT => 'inventory.type.adjustment', StockLedger::REVERSAL => 'inventory.type.reversal',
        StockLedger::RETURN_RECEIPT => 'inventory.type.return_receipt',
    ];

    /** The reasons of a manual issue (COPY_DECK section 9.1). */
    private const array ISSUE_REASONS = [
        'DAMAGED' => 'inventory.issue_reason.damaged', 'INTERNAL_USE' => 'inventory.issue_reason.internal_use', 'LOST' => 'inventory.issue_reason.lost',
        'SAMPLE' => 'inventory.issue_reason.sample', 'OTHER' => 'inventory.issue_reason.other',
    ];

    private const array ONBOARDING_STATES = [OnboardingState::NOT_STARTED => self::VALUE.'onboarding_not_started', OnboardingState::ACTIVE => self::VALUE.'onboarding_active', OnboardingState::RELEASED => self::VALUE.'onboarding_released'];

    private const array RESTORE_SUBJECTS = ['intent' => self::VALUE.'restore_subject_intent', 'onboarding_hold' => self::VALUE.'restore_subject_hold'];

    private const array RESTORE_OUTCOMES = [
        IntentReconciliations::NOT_COMMITTED => self::VALUE.'restore_not_committed', OnboardingState::RELEASED_BEFORE_RESTORE => self::VALUE.'restore_released_before',
        OnboardingState::NEVER_RELEASED => self::VALUE.'restore_never_released',
    ];

    /** The causes of a cost mark (BR-COST-27). */
    private const array MARK_CAUSES = [CostIncompleteMarks::MOVEMENT_CAP => self::VALUE.'mark_movement_cap', CostIncompleteMarks::TIME_BUDGET => self::VALUE.'mark_time_budget', CostIncompleteMarks::UNSAFE_HISTORY => self::VALUE.'mark_unsafe_history'];

    /** The detail codes of a reconciliation block (BR-INV-45). */
    private const array MISMATCHES = [
        ProductReconciliation::BALANCE => self::VALUE.'mismatch_balance', ProductReconciliation::VALUATION => self::VALUE.'mismatch_valuation',
        ProductReconciliation::ISSUE_PROJECTION => self::VALUE.'mismatch_issue_projection', ProductReconciliation::RETURN_PROJECTION => self::VALUE.'mismatch_return_projection',
        ProductReconciliation::HISTORY => self::VALUE.'mismatch_history',
    ];

    private const array CORRECTION_KINDS = ['MOVE' => self::VALUE.'correction_move', 'REATTACH' => self::VALUE.'correction_reattach'];

    private const array SYMBOLOGIES = [
        Symbology::Ean13->value => self::VALUE.'symbology_ean_13', Symbology::Ean8->value => self::VALUE.'symbology_ean_8',
        Symbology::UpcA->value => self::VALUE.'symbology_upc_a', Symbology::UpcE->value => self::VALUE.'symbology_upc_e',
    ];

    private const array RETIRE_REASONS = ['MISASSIGNMENT' => 'catalog.barcode.reason_misassignment', 'OTHER' => 'catalog.barcode.reason_other'];

    private const array RECEIPT_SIZES = ['58' => 'sales.receipt.size_58', '80' => 'sales.receipt.size_80', 'A4' => 'sales.receipt.size_a4'];

    /** The roles, in the order of section 3. */
    private const array ROLES = [Role::SuperAdmin->value => 'access.role.super_admin', Role::OperationsAdmin->value => 'access.role.operations_admin', Role::Cashier->value => 'access.role.cashier'];

    private const array LINK_KINDS = ['FIRST_PASSWORD' => self::VALUE.'link_first', 'RESET' => self::VALUE.'link_reset'];

    private const array REBUILT_SUBJECTS = ['product' => self::VALUE.'rebuilt_product', 'sale' => self::VALUE.'rebuilt_sale', 'token' => self::VALUE.'rebuilt_token'];

    /** The fields of a BusinessProfile, in the order of section 11.15.2. */
    private const array PROFILE_FIELDS = [
        'shopName' => 'config.profile.shop_name', 'address' => 'config.profile.address', 'contact' => 'config.profile.contact', 'email' => 'config.profile.email',
        'documentHeader' => 'config.profile.header_text', 'documentFooter' => 'config.profile.footer_text', 'documentIdentity' => 'config.profile.document_label',
        'accentToken' => 'config.profile.accent', 'logo' => 'config.profile.logo',
    ];

    private const array ACCOUNT_DETAILS = ['displayName' => 'access.account.name', 'email' => 'access.account.email'];

    /** The fields of a product edit, in the order of section 11.15.3. */
    private const array PRODUCT_FIELDS = ['name' => 'name', 'categoryId' => 'category', 'brand' => 'brand', 'baseUnit' => 'unit', 'quantityPrecision' => 'precision', 'minimumStock' => 'min_stock'];

    /** The fields of a cash register. */
    private const array REGISTER_FIELDS = ['code' => 'code', 'label' => 'label', 'active' => 'status'];

    /** A register's `active`, true then false. */
    private const array ACTIVE = [self::VALUE.'status_active', self::VALUE.'status_inactive'];

    /**
     * Every copy key that a row may carry: its labels and the labels of the values (COPY_DECK section
     * 11.15.2), each of which lang/id holds (the L1 test of TASK-051).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [self::VALUE.'empty', self::VALUE.'yes', self::VALUE.'no', 'costing.status.unknown'];

        foreach ([self::METHODS, self::SOURCES, self::MOVEMENT_TYPES, self::ISSUE_REASONS, self::ONBOARDING_STATES, self::RESTORE_SUBJECTS, self::RESTORE_OUTCOMES, self::MARK_CAUSES, self::MISMATCHES, self::CORRECTION_KINDS, self::SYMBOLOGIES, self::RETIRE_REASONS, self::RECEIPT_SIZES, self::ROLES, self::LINK_KINDS, self::REBUILT_SUBJECTS, self::PROFILE_FIELDS, self::ACCOUNT_DETAILS, self::ACTIVE] as $map) {
            foreach ($map as $key) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * The names that the rows of one page of events need, each read once by its keys (ACV-05).
     *
     * @param  list<Event>  $events
     * @return Names
     */
    public static function names(array $events): array
    {
        $products = [];
        $categories = [];
        $users = [];

        foreach ($events as $event) {
            $change = $event['change'];
            $products[] = $event['product'];

            foreach ([...self::items($change, 'lines'), ...self::items($change, 'rows')] as $line) {
                $products[] = self::int($line, 'productId');
            }

            $products[] = self::int($change, 'fromProductId');
            $products[] = self::int($change, 'toProductId');
            $categories[] = self::int($change, 'categoryId');
            $categories[] = self::int(self::map($change, 'from'), 'categoryId');
            $categories[] = self::int(self::map($change, 'to'), 'categoryId');

            foreach (array_keys(self::map($change, 'rowsByCategory')) as $category) {
                $categories[] = is_int($category) ? $category : null;
            }

            $users[] = self::int($change, 'outgoing');
            $users[] = self::int($change, 'incoming');
        }

        $named = [];

        foreach (self::read('products', ['id', 'name', 'base_unit', 'quantity_precision'], $products) as $row) {
            $named[Read::int($row, 'id')] = ['name' => Read::text($row, 'name'), 'unit' => Read::text($row, 'base_unit'), 'precision' => Read::int($row, 'quantity_precision')];
        }

        return [
            'product' => $named,
            'category' => self::texts(self::read('categories', ['id', 'name'], $categories), 'name'),
            'user' => self::texts(self::read('users', ['id', 'display_name'], $users), 'display_name'),
        ];
    }

    /**
     * The rows under "Perubahan" of one event, in the order of section 11.15.3; empty when it has none.
     *
     * @param  Event  $event
     * @param  Names  $names
     * @return list<Change>
     */
    public static function of(array $event, bool $owner, array $names): array
    {
        $c = $event['change'];
        $o = $owner ? ($event['owner'] ?? []) : null;
        $product = $event['product'] === null ? null : ($names['product'][$event['product']] ?? null);

        /** @var list<Change|null> $rows */
        $rows = match ($event['action']) {
            'SALE_CHECKOUT' => self::sale($c, $o, $names),
            'CASH_SESSION_OPEN' => [self::value('register', self::text(self::str($c, 'register'))), self::value('opening_cash', self::money(self::str($c, 'openingCash')))],
            'CASH_SESSION_CLOSE', 'CASH_SESSION_CLOSE_ASSISTED' => self::close($c),
            'SALE_CORRECTION' => self::correction($c, $o, $names),
            'RECEIVING_CONFIRM' => [
                self::value('document_date', self::date(self::str($c, 'documentDate'))),
                self::value('line_count', self::count(self::int($c, 'lineCount'))),
                self::stockLines($c, $o, 'lines', 'lineNo', $names, false),
            ],
            'MANUAL_ISSUE_CONFIRM' => [
                self::value('issue_reason', self::code(self::str($c, 'reasonCode'), self::ISSUE_REASONS)),
                self::value('note', self::text(self::str($c, 'note'))),
                self::value('line_count', self::count(self::int($c, 'lineCount'))),
                self::stockLines($c, $o, 'lines', 'lineNo', $names, false),
            ],
            'ADJUSTMENT_CONFIRM' => [
                self::value('count_reference', self::text(self::str($c, 'countReference'))),
                self::value('line_count', self::count(self::int($c, 'lineCount'))),
                self::stockLines($c, $o, 'lines', 'lineNo', $names, true),
            ],
            'REVERSAL_CONFIRM' => [
                self::value('reversed_type', self::code(self::str($c, 'originalType'), self::MOVEMENT_TYPES)),
                self::value('reversed_quantity', self::quantity(self::str($c, 'originalQuantity'), $product, true)),
                self::value('inverse_quantity', self::quantity(self::str($c, 'inverseQuantity'), $product, true)),
                $o === null ? null : self::value('marks_cleared', self::count(self::int($o, 'marksCleared'))),
            ],
            'OPENING_STOCK_CONFIRM' => [self::value('line_count', self::count(self::int($c, 'rowCount'))), self::openingLines($c, $o, $names)],
            'OPENING_STOCK_REPLACE' => [
                self::value('quantity', self::quantity(self::str($c, 'quantity'), $product, false)),
                self::value('count_reference', self::text(self::str($c, 'countReference'))),
                $o === null ? null : self::value('cost', self::cost($o, self::str($o, 'zeroNote'))),
            ],
            'ONBOARDING_START', 'ONBOARDING_RELEASE' => [self::change('onboarding_state', self::side(self::code(self::str($c, 'from'), self::ONBOARDING_STATES)), self::side(self::code(self::str($c, 'to'), self::ONBOARDING_STATES)))],
            'ONBOARDING_RESTORE_HOLD' => [self::value('restored_state', self::code(self::str($c, 'restoredState'), self::ONBOARDING_STATES))],
            'RESTORE_RECONCILIATION' => [
                self::value('restore_subject', self::code(self::str($c, 'decision'), self::RESTORE_SUBJECTS)),
                self::value('restore_outcome', self::code(self::str($c, 'outcome'), self::RESTORE_OUTCOMES)),
                self::value('receipts_settled', self::count(self::int($c, 'receiptsSettled'))),
                self::value('restored_state', self::code(self::str($c, 'restoredState'), self::ONBOARDING_STATES)),
                self::value('onboarding_state', self::code(self::str($c, 'state'), self::ONBOARDING_STATES)),
            ],
            'COST_CORRECTION' => [
                $o === null ? null : self::change('cost', self::side(self::cost(self::map($o, 'old'), null)), self::side(self::cost(self::map($o, 'new'), self::str($o, 'zeroNote')))),
                self::value('marks_cleared', self::count(self::int($c, 'marksCleared'))),
            ],
            'COST_INCOMPLETE_MARK' => [
                self::value('mark_cause', self::code(self::str($c, 'rejection'), self::MARK_CAUSES)),
                self::value('mark_cleared', self::yesNo($c['cleared'] ?? null)),
                $o === null ? null : self::value('attempted_cost', self::cost(self::map($o, 'attempted'), self::str($o, 'zeroNote'))),
            ],
            'RECONCILIATION_BLOCK' => [self::value('mismatch', self::code(self::str($c, 'detailCode'), self::MISMATCHES))],
            'PRODUCT_CREATE' => [
                self::value('sku', self::text(self::str($c, 'sku'))),
                self::value('name', self::text(self::str($c, 'name'))),
                self::value('category', self::named($names['category'], self::int($c, 'categoryId'))),
                self::value('selling_price', self::money(self::str($c, 'sellingPrice'))),
                self::value('manufacturer_barcode', self::text(self::str($c, 'barcode'))),
                self::value('symbology', self::code(self::str($c, 'symbology'), self::SYMBOLOGIES)),
            ],
            'PRODUCT_EDIT' => self::productEdit($c, $product, $names),
            'PRICE_CHANGE' => [self::change('selling_price', self::side(self::money(self::str($c, 'from'))), self::side(self::money(self::str($c, 'to'))))],
            'CATEGORY_CREATE' => [self::value('category_name', self::text(self::str($c, 'name')))],
            'CATEGORY_RENAME' => [self::change('category_name', self::side(self::text(self::str($c, 'from'))), self::side(self::text(self::str($c, 'to'))))],
            'BARCODE_ADD' => [self::value('barcode', self::text(self::str($c, 'barcode'))), self::value('symbology', self::code(self::str($c, 'symbology'), self::SYMBOLOGIES))],
            'BARCODE_GENERATE' => [self::value('barcode', self::text(self::str($c, 'barcode')))],
            'BARCODE_RETIRE' => [self::value('barcode', self::text(self::str($c, 'barcode'))), self::value('retire_reason', self::code(self::str($c, 'reasonClass'), self::RETIRE_REASONS))],
            'BARCODE_CORRECTION' => [
                self::value('barcode', self::text(self::str($c, 'barcode'))),
                self::value('correction_kind', self::code(self::str($c, 'kind'), self::CORRECTION_KINDS)),
                self::change('product', self::side(self::productName($names, self::int($c, 'fromProductId'))), self::side(self::productName($names, self::int($c, 'toProductId')))),
            ],
            'CSV_IMPORT' => [
                self::value('data_rows', self::count(self::int($c, 'dataRowCount'))),
                self::value('products_created', self::count(self::int($c, 'productsCreated'))),
                self::value('barcodes_created', self::count(self::int($c, 'barcodesCreated'))),
                self::byCategory($c, $names),
            ],
            'PROFILE_CHANGE' => [self::value('changed', self::fieldLabels(self::map($c, 'changed'), self::PROFILE_FIELDS))],
            'REGISTER_CREATE' => [
                self::value('code', self::text(self::str($c, 'code'))),
                self::value('label', self::text(self::str($c, 'label'))),
                self::value('status', self::active($c['active'] ?? null)),
            ],
            'REGISTER_CHANGE' => self::registerChange($c),
            'USER_CREATE' => [self::value('roles', self::roles(self::map($c, 'roles')))],
            'ROLE_CHANGE' => [self::change('roles', self::side(self::roles(self::map($c, 'from'))), self::side(self::roles(self::map($c, 'to'))))],
            'DETAILS_CHANGE' => [self::value('changed', self::fieldLabels(self::map($c, 'changed'), self::ACCOUNT_DETAILS))],
            'LINK_ISSUE' => [self::value('link_kind', self::code(self::str($c, 'kind'), self::LINK_KINDS))],
            'LINK_CONSUME' => [self::value('link_kind', self::code(self::str($c, 'kind'), self::LINK_KINDS)), self::value('sessions_revoked', self::count(self::int($c, 'sessionsRevoked')))],
            'PASSWORD_CHANGE', 'OWNER_RECOVERY' => [self::value('sessions_revoked', self::count(self::int($c, 'sessionsRevoked')))],
            'OWNER_REPLACEMENT' => [
                self::change('owner', self::side(self::named($names['user'], self::int($c, 'outgoing'))), self::side(self::named($names['user'], self::int($c, 'incoming')))),
                self::value('sessions_revoked', self::count(self::int($c, 'sessionsRevoked'))),
            ],
            'PRINT' => self::printed($c),
            'EXPORT' => [self::value('format', self::text(self::str($c, 'format')))],
            'PROJECTION_REBUILT' => self::rebuilt($c, $o),
            default => [],
        };

        $found = [];

        foreach ($rows as $row) {
            if ($row !== null) {
                $found[] = $row;
            }
        }

        return $found;
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @param  array<array-key, mixed>|null  $o
     * @param  Names  $names
     * @return list<Change|null>
     */
    private static function sale(array $c, ?array $o, array $names): array
    {
        $costs = self::byNumber($o, 'lines', 'lineNo');
        $lines = [];

        foreach (self::items($c, 'lines') as $line) {
            $product = self::productOf($names, self::int($line, 'productId'));
            $cells = [self::productCell($product), self::quantity(self::str($line, 'quantity'), $product, false), self::money(self::str($line, 'unitPrice')), self::money(self::str($line, 'lineTotal'))];

            if ($o !== null) {
                $cells[] = self::lineCost($costs, self::int($line, 'lineNo'));
            }

            $lines[] = $cells;
        }

        return [
            self::value('session', self::text(self::str($c, 'sessionNumber'))),
            self::value('total', self::money(self::str($c, 'total'))),
            self::value('payment_method', self::code(self::str($c, 'method'), self::METHODS)),
            self::value('tendered', self::money(self::str($c, 'tendered'))),
            self::value('change', self::money(self::str($c, 'change'))),
            self::value('payment_reference', self::text(self::str($c, 'reference'))),
            self::value('line_count', self::count(self::int($c, 'lineCount'))),
            self::table('lines', ['product', 'quantity', 'unit_price', 'line_total', ...($o === null ? [] : ['cost'])], $lines),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @return list<Change|null>
     */
    private static function close(array $c): array
    {
        return [
            self::value('register', self::text(self::str($c, 'register'))),
            self::value('responsible', self::text(self::str($c, 'responsible'))),
            self::value('opening_cash', self::money(self::str($c, 'openingCash'))),
            self::value('sale_count', self::count(self::int($c, 'saleCount'))),
            self::value('cash_payments', self::money(self::str($c, 'cashApplied'))),
            self::value('transfer_payments', self::money(self::str($c, 'transferApplied'))),
            self::value('expected_cash', self::money(self::str($c, 'expectedCash'))),
            self::value('physical_cash', self::money(self::str($c, 'physicalCash'))),
            self::value('variance', self::number('variance', self::str($c, 'variance'))),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @param  array<array-key, mixed>|null  $o
     * @param  Names  $names
     * @return list<Change|null>
     */
    private static function correction(array $c, ?array $o, array $names): array
    {
        $refund = self::map($c, 'refund');
        $returns = self::byNumber($o, 'returns', 'saleLineId');
        $lines = [];

        foreach (self::items($c, 'lines') as $line) {
            $product = self::productOf($names, self::int($line, 'productId'));
            $cells = [
                self::productCell($product),
                self::quantity(self::str($line, 'returnedSaleable'), $product, false),
                self::quantity(self::str($line, 'returnedNotSaleable'), $product, false),
                self::money(self::str($line, 'refundAmount')),
            ];

            if ($o !== null) {
                $cells[] = self::lineCost($returns, self::int($line, 'saleLineId'));
            }

            $lines[] = $cells;
        }

        return [
            self::value('business_date', self::date(self::str($c, 'businessDate'))),
            self::value('refund_total', self::money(self::str($refund, 'amount'))),
            self::value('refund_method', self::code(self::str($refund, 'payoutMethod'), self::METHODS)),
            self::value('refund_source', self::code(self::str($refund, 'fundingSource'), self::SOURCES)),
            self::value('refund_reference', self::text(self::str($refund, 'payoutReference'))),
            self::table('lines', ['product', 'returned_saleable', 'returned_not_saleable', 'refund', ...($o === null ? [] : ['return_cost'])], $lines),
        ];
    }

    /**
     * The table of a receiving, a manual issue or an adjustment: product, quantity and, for the Owner, cost.
     *
     * @param  array<array-key, mixed>  $c
     * @param  array<array-key, mixed>|null  $o
     * @param  Names  $names
     * @return Change|null
     */
    private static function stockLines(array $c, ?array $o, string $list, string $number, array $names, bool $signed): ?array
    {
        $costs = self::byNumber($o, $list, $number);
        $lines = [];

        foreach (self::items($c, $list) as $line) {
            $product = self::productOf($names, self::int($line, 'productId'));
            $cells = [self::productCell($product), self::quantity(self::str($line, 'quantity'), $product, $signed)];

            if ($o !== null) {
                $cells[] = self::lineCost($costs, self::int($line, $number));
            }

            $lines[] = $cells;
        }

        return self::table('lines', ['product', 'quantity', ...($o === null ? [] : ['cost'])], $lines);
    }

    /**
     * The table of an opening stock entry: a zero count shows its word in place of the quantity.
     *
     * @param  array<array-key, mixed>  $c
     * @param  array<array-key, mixed>|null  $o
     * @param  Names  $names
     * @return Change|null
     */
    private static function openingLines(array $c, ?array $o, array $names): ?array
    {
        $costs = self::byNumber($o, 'rows', 'rowNo');
        $lines = [];

        foreach (self::items($c, 'rows') as $row) {
            $product = self::productOf($names, self::int($row, 'productId'));
            $zero = array_key_exists('zeroCountId', $row);
            $cells = [self::productCell($product), $zero ? self::labels(['inventory.onboarding.zero_count']) : self::quantity(self::str($row, 'quantity'), $product, false), self::text(self::str($row, 'countReference'))];

            if ($o !== null) {
                $cells[] = $zero ? null : self::lineCost($costs, self::int($row, 'rowNo'));
            }

            $lines[] = $cells;
        }

        return self::table('lines', ['product', 'quantity', 'count_reference', ...($o === null ? [] : ['cost'])], $lines);
    }

    /**
     * A change row for each field that a product edit names, in the order of section 11.15.3. Stok minimum
     * takes the unit and precision of the same side of the change, else the product's now (ACV-04).
     *
     * @param  array<array-key, mixed>  $c
     * @param  Product|null  $product
     * @param  Names  $names
     * @return list<Change|null>
     */
    private static function productEdit(array $c, ?array $product, array $names): array
    {
        $changed = self::strings(self::map($c, 'changed'));
        $from = self::map($c, 'from');
        $to = self::map($c, 'to');
        $unit = $product === null ? '' : $product['unit'];
        $precision = $product === null ? 0 : $product['precision'];
        $rows = [];

        foreach (self::PRODUCT_FIELDS as $field => $label) {
            if (! in_array($field, $changed, true)) {
                continue;
            }

            $before = self::productSide($field, $from, $unit, $precision, $names);
            $after = self::productSide($field, $to, $unit, $precision, $names);
            $rows[] = self::change($label, $before, $after);
        }

        return $rows;
    }

    /**
     * One side of a product edit's field.
     *
     * @param  array<array-key, mixed>  $side
     * @param  Names  $names
     * @return Value
     */
    private static function productSide(string $field, array $side, string $unit, int $precision, array $names): array
    {
        return self::side(match ($field) {
            'categoryId' => self::named($names['category'], self::int($side, $field)),
            'quantityPrecision' => self::count(self::int($side, $field)),
            'minimumStock' => self::quantity(self::str($side, $field), ['name' => '', 'unit' => self::str($side, 'baseUnit') ?? $unit, 'precision' => self::int($side, 'quantityPrecision') ?? $precision], false),
            default => self::text(self::str($side, $field)),
        });
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @param  Names  $names
     * @return Change|null
     */
    private static function byCategory(array $c, array $names): ?array
    {
        $lines = [];

        foreach (self::map($c, 'rowsByCategory') as $category => $count) {
            $lines[] = [self::named($names['category'], is_int($category) ? $category : null), self::count(is_int($count) ? $count : null)];
        }

        return self::table('products_by_category', ['category', 'product_count'], $lines);
    }

    /**
     * A change row for each field of a register whose two sides differ.
     *
     * @param  array<array-key, mixed>  $c
     * @return list<Change|null>
     */
    private static function registerChange(array $c): array
    {
        $from = self::map($c, 'from');
        $to = self::map($c, 'to');
        $rows = [];

        foreach (self::REGISTER_FIELDS as $field => $label) {
            $before = $from[$field] ?? null;
            $after = $to[$field] ?? null;

            if ($before === $after) {
                continue;
            }

            $rows[] = $field === 'active'
                ? self::change($label, self::side(self::active($before)), self::side(self::active($after)))
                : self::change($label, self::side(self::text(is_string($before) ? $before : null)), self::side(self::text(is_string($after) ? $after : null)));
        }

        return $rows;
    }

    /**
     * A label's print shows its barcode and count, a receipt's its size and whether it was a reprint; a
     * session report's print shows nothing.
     *
     * @param  array<array-key, mixed>  $c
     * @return list<Change|null>
     */
    private static function printed(array $c): array
    {
        if (array_key_exists('barcode', $c)) {
            return [self::value('barcode', self::text(self::str($c, 'barcode'))), self::value('label_count', self::count(self::int($c, 'count')))];
        }

        if (array_key_exists('size', $c)) {
            return [self::value('receipt_size', self::code(self::str($c, 'size'), self::RECEIPT_SIZES)), self::value('reprint', self::yesNo($c['copy'] ?? null))];
        }

        return [];
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @param  array<array-key, mixed>|null  $o
     * @return list<Change|null>
     */
    private static function rebuilt(array $c, ?array $o): array
    {
        $subject = self::str($c, 'subject');
        $rows = [self::value('rebuilt_subject', self::code($subject, self::REBUILT_SUBJECTS))];

        if ($subject === 'product') {
            $rows[] = self::value('balance_rebuilt', self::yesNo($c['balanceRebuilt'] ?? null));
            $rows[] = self::value('valuation_rebuilt', self::yesNo($c['valuationRebuilt'] ?? null));

            if ($o !== null) {
                $rows[] = self::change('inventory_value', self::side(self::cost(self::map($o, 'valueBefore'), null)), self::side(self::cost(self::map($o, 'valueAfter'), null)));
            }
        } elseif ($subject === 'sale') {
            $rows[] = self::value('totals_rebuilt', self::yesNo($c['totalsRebuilt'] ?? null));
        }

        return $rows;
    }

    /**
     * @param  Value|null  $value
     * @return Change|null
     */
    private static function value(string $field, ?array $value): ?array
    {
        return $value === null ? null : ['label' => self::FIELD.$field, 'kind' => 'value', 'value' => $value, 'from' => null, 'to' => null, 'columns' => [], 'lines' => []];
    }

    /**
     * A change row, "lama → baru" (ACV-03); a side that holds nothing is `audit.change.value.empty`.
     *
     * @param  Value  $from
     * @param  Value  $to
     * @return Change
     */
    private static function change(string $field, array $from, array $to): array
    {
        return ['label' => self::FIELD.$field, 'kind' => 'change', 'value' => null, 'from' => $from, 'to' => $to, 'columns' => [], 'lines' => []];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<Value|null>>  $lines
     * @return Change|null
     */
    private static function table(string $field, array $columns, array $lines): ?array
    {
        return $lines === [] ? null : [
            'label' => self::FIELD.$field,
            'kind' => 'table',
            'value' => null,
            'from' => null,
            'to' => null,
            'columns' => array_map(static fn (string $column): string => self::COLUMN.$column, $columns),
            'lines' => $lines,
        ];
    }

    /**
     * @param  Value|null  $value
     * @return Value
     */
    private static function side(?array $value): array
    {
        return $value ?? self::labels([self::VALUE.'empty']);
    }

    /**
     * @param  list<string>  $keys
     * @return Value
     */
    private static function labels(array $keys): array
    {
        return ['kind' => 'labels', 'text' => null, 'keys' => $keys, 'precision' => null, 'unit' => null];
    }

    /**
     * A canonical number of at most `$scale` decimals as the page formats its kind; null for anything else.
     *
     * @return Value|null
     */
    private static function number(string $kind, ?string $text, int $scale = 0): ?array
    {
        $pattern = $scale === 0 ? '/\A-?(0|[1-9][0-9]*)\z/' : '/\A-?(0|[1-9][0-9]*)(\.[0-9]{1,'.$scale.'})?\z/';

        return $text === null || preg_match($pattern, $text) !== 1 ? null : ['kind' => $kind, 'text' => $text, 'keys' => [], 'precision' => null, 'unit' => null];
    }

    /**
     * Whole rupiah (BR-NUM-01).
     *
     * @return Value|null
     */
    private static function money(?string $text): ?array
    {
        return self::number('money', $text);
    }

    /**
     * @return Value|null
     */
    private static function text(?string $text): ?array
    {
        return $text === null || trim($text) === '' ? null : ['kind' => 'text', 'text' => $text, 'keys' => [], 'precision' => null, 'unit' => null];
    }

    /**
     * @return Value|null
     */
    private static function date(?string $text): ?array
    {
        return $text === null || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $text) !== 1 ? null : ['kind' => 'date', 'text' => $text, 'keys' => [], 'precision' => null, 'unit' => null];
    }

    /**
     * @return Value|null
     */
    private static function count(?int $count): ?array
    {
        return $count === null || $count < 0 ? null : ['kind' => 'count', 'text' => (string) $count, 'keys' => [], 'precision' => null, 'unit' => null];
    }

    /**
     * A cost (ACV-04): UNKNOWN shows `costing.status.unknown`; a known zero with its evidence or reason
     * shows `audit.change.value.cost_zero`; otherwise the amount, which the page rounds once.
     *
     * @param  array<array-key, mixed>  $cost
     * @return Value|null
     */
    private static function cost(array $cost, ?string $zeroNote): ?array
    {
        $status = self::str($cost, 'status');

        if ($status === 'UNKNOWN') {
            return self::labels(['costing.status.unknown']);
        }

        // A cost holds at most six decimals (BR-NUM-03).
        $amount = $status === 'KNOWN' ? self::number('cost', self::str($cost, 'amount'), 6) : null;

        if ($amount !== null && $zeroNote !== null && trim($zeroNote) !== '' && preg_match('/\A0(\.0+)?\z/', (string) $amount['text']) === 1) {
            return ['kind' => 'cost_zero', 'text' => $zeroNote, 'keys' => [], 'precision' => null, 'unit' => null];
        }

        return $amount;
    }

    /**
     * The cost of a line from the Owner-only data, by its number; null for a line without one.
     *
     * @param  array<int, array<array-key, mixed>>  $costs
     * @return Value|null
     */
    private static function lineCost(array $costs, ?int $number): ?array
    {
        $cost = $number === null ? null : ($costs[$number] ?? null);

        return $cost === null ? null : self::cost($cost, self::str($cost, 'zeroNote'));
    }

    /**
     * A quantity in the product's base unit and precision; a value with more decimals than that precision
     * keeps its own, never rounded. A signed quantity shows + before an increase (ACV-04).
     *
     * @param  Product|null  $product
     * @return Value|null
     */
    private static function quantity(?string $text, ?array $product, bool $signed): ?array
    {
        if ($text === null || preg_match('/\A-?(0|[1-9][0-9]*)(?:\.([0-9]{1,3}))?\z/', $text, $match) !== 1) {
            return null;
        }

        $scale = strlen($match[2] ?? '');
        $precision = $product === null ? 0 : min(3, max(0, $product['precision']));
        $unit = $product === null || $product['unit'] === '' ? null : $product['unit'];

        return ['kind' => $signed ? 'signed' : 'quantity', 'text' => $text, 'keys' => [], 'precision' => max($scale, $precision), 'unit' => $unit];
    }

    /**
     * The label of a stored code; null for a code without one (ACV-01, ACV-04).
     *
     * @param  array<array-key, string>  $labels
     * @return Value|null
     */
    private static function code(?string $code, array $labels): ?array
    {
        return $code === null || ! array_key_exists($code, $labels) ? null : self::labels([$labels[$code]]);
    }

    /**
     * The labels of the fields that a list names, in the order of the table (ACV-03).
     *
     * @param  array<array-key, mixed>  $names
     * @param  array<array-key, string>  $labels
     * @return Value|null
     */
    private static function fieldLabels(array $names, array $labels): ?array
    {
        $named = self::strings($names);
        $keys = [];

        foreach ($labels as $name => $key) {
            if (in_array($name, $named, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? null : self::labels($keys);
    }

    /**
     * @param  array<array-key, mixed>  $roles
     * @return Value|null
     */
    private static function roles(array $roles): ?array
    {
        return self::fieldLabels($roles, self::ROLES);
    }

    /**
     * @return Value|null
     */
    private static function yesNo(mixed $value): ?array
    {
        return match (true) {
            is_bool($value) => self::labels([self::VALUE.($value ? 'yes' : 'no')]),
            is_int($value) && $value >= 0 => self::labels([self::VALUE.($value > 0 ? 'yes' : 'no')]),
            default => null,
        };
    }

    /**
     * @return Value|null
     */
    private static function active(mixed $value): ?array
    {
        return is_bool($value) ? self::labels([$value ? self::ACTIVE[0] : self::ACTIVE[1]]) : null;
    }

    /**
     * @param  array<int, string>  $names
     * @return Value|null
     */
    private static function named(array $names, ?int $key): ?array
    {
        return $key === null ? null : self::text($names[$key] ?? null);
    }

    /**
     * @param  Names  $names
     * @return Value|null
     */
    private static function productName(array $names, ?int $key): ?array
    {
        return self::productCell(self::productOf($names, $key));
    }

    /**
     * @param  Names  $names
     * @return Product|null
     */
    private static function productOf(array $names, ?int $key): ?array
    {
        return $key === null ? null : ($names['product'][$key] ?? null);
    }

    /**
     * @param  Product|null  $product
     * @return Value|null
     */
    private static function productCell(?array $product): ?array
    {
        return $product === null ? null : self::text($product['name']);
    }

    /**
     * The items of a list of the data, by their number field: the Owner-only costs of each line.
     *
     * @param  array<array-key, mixed>|null  $data
     * @return array<int, array<array-key, mixed>>
     */
    private static function byNumber(?array $data, string $list, string $number): array
    {
        $found = [];

        foreach ($data === null ? [] : self::items($data, $list) as $item) {
            $key = self::int($item, $number);

            if ($key !== null) {
                $found[$key] = $item;
            }
        }

        return $found;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    private static function items(array $data, string $key): array
    {
        $items = [];

        foreach (self::map($data, $key) as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private static function strings(array $values): array
    {
        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value)));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * The rows of a table with these keys; at most one page's worth.
     *
     * @param  list<string>  $columns
     * @param  list<int|null>  $keys
     * @return list<array<array-key, mixed>>
     */
    private static function read(string $table, array $columns, array $keys): array
    {
        $ids = array_values(array_unique(array_filter($keys, static fn (?int $key): bool => $key !== null)));

        if ($ids === []) {
            return [];
        }

        return array_values(array_map(static fn (object $row): array => (array) $row, DB::table($table)->whereIn('id', $ids)->get($columns)->all()));
    }

    /**
     * @param  list<array<array-key, mixed>>  $rows
     * @return array<int, string>
     */
    private static function texts(array $rows, string $column): array
    {
        $texts = [];

        foreach ($rows as $row) {
            $texts[Read::int($row, 'id')] = Read::text($row, $column);
        }

        return $texts;
    }
}
