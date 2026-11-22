<?php

namespace Tests\Unit\Audit;

use App\Domain\Audit\AuditActions;
use App\Queries\Audit\ChangeRows;
use App\Support\Localization\Dictionary;
use PHPUnit\Framework\TestCase;

/**
 * L1 of TASK-051 (AC-051-01, AC-051-02, AC-051-03; COPY_DECK section 11.15, ACV-01 to ACV-04; D-138): for each of
 * the 55 action codes, the rows that "Perubahan" shows are exactly those that section 11.15.3 names, in its order,
 * read from the approved document itself; the rows and columns marked "Pemilik" are built only for the Owner;
 * every copy key that a row may carry is in lang/id, and every key of `audit.change.*` in lang/id is one that a
 * row carries; no stored field name and no stored code leaves the rows; and each kind of value carries what the
 * page formats. The events are the change data and Owner-only data as the Actions write them (`AuditLog::record`).
 *
 * @phpstan-import-type Change from ChangeRows
 * @phpstan-import-type Names from ChangeRows
 */
final class ChangeRowsTest extends TestCase
{
    private const string PREFIX = 'audit.change.';

    /** The internal names of the change data and the Owner-only data, none of which may leave the read (ACV-01). */
    private const array FIELD_NAMES = [
        'evidenceId', 'movementId', 'correctionNo', 'publicationNo', 'projectionsRewritten', 'marksCleared', 'lineNo', 'rowNo', 'productId',
        'priceRevisionId', 'productSequence', 'allocationId', 'basedOnPublicationNo', 'sessionNumber', 'lineCount', 'rowCount', 'unitPrice',
        'lineTotal', 'zeroNote', 'fileSha256', 'productIds', 'mappingId', 'revisionId', 'previousRevisionId', 'cutoffVersion', 'holdId',
        'reportId', 'fenceId', 'version', 'blockId', 'noteId', 'zeroCountId', 'saleLineId', 'issueAllocationId', 'categoryId', 'fromProductId',
        'toProductId', 'correctedMappingId', 'sessionsRevoked', 'outgoing', 'incoming', 'quantityPrecision', 'minimumStock', 'baseUnit',
        'sellingPrice', 'documentDate', 'businessDate', 'refundAmount', 'returnedSaleable', 'returnedNotSaleable', 'payoutReference',
        'payoutMethod', 'fundingSource', 'cashApplied', 'transferApplied', 'expectedCash', 'physicalCash', 'openingCash', 'saleCount',
        'dataRowCount', 'productsCreated', 'barcodesCreated', 'rowsByCategory', 'balanceRebuilt', 'valuationRebuilt', 'totalsRebuilt',
        'valueBefore', 'valueAfter', 'issueProjections', 'returnProjections', 'receiptsSettled', 'restoredState', 'reasonCode',
        'originalType', 'originalQuantity', 'inverseQuantity', 'countReference', 'reasonClass', 'detailCode', 'rejection', 'correctionsSeen',
        'attempted', 'photoFileId', 'thumbnailFileId', 'previousFileId', 'mappingBefore', 'mappingAfter', 'replacesLineId', 'replacesMovementId',
        'originalMovementId', 'saleId', 'saleNumber', 'decision', 'operation', 'markId',
    ];

    /** The stored codes of the events, each of which shows only as its label (ACV-04). */
    private const array CODES = [
        'CASH', 'BANK_TRANSFER', 'DRAWER', 'OWNER_FUNDS', 'DAMAGED', 'SALE_ISSUE', 'NOT_STARTED', 'ACTIVE', 'RELEASED', 'intent',
        'onboarding_hold', 'NOT_COMMITTED', 'RELEASED_BEFORE_RESTORE', 'MOVEMENT_CAP', 'BALANCE_MISMATCH', 'EAN_13', 'EAN_8', 'MISASSIGNMENT',
        'MOVE', 'SUPER_ADMIN', 'CASHIER', 'OPERATIONS_ADMIN', 'FIRST_PASSWORD', 'RESET', 'A4', 'KNOWN', 'UNKNOWN', 'shopName', 'address',
        'logo', 'displayName', 'email', 'product', 'sale', 'token', 'sale.checkout',
    ];

    public function test_each_action_codes_rows_are_those_that_the_approved_amendment_names_and_the_owner_only_ones_reach_the_owner_alone(): void
    {
        $amendment = self::amendment();
        $events = self::events();

        $this->assertCount(55, $amendment);
        $this->assertSame(array_keys(AuditActions::LABELS), array_keys($amendment));
        $this->assertSame(array_keys($amendment), array_keys($events));

        foreach ($amendment as $code => [$keys, $owner]) {
            $this->assertSame($keys, self::labelsOf($events[$code], true), $code.' for the Owner');
            $this->assertSame(array_values(array_diff($keys, $owner)), self::labelsOf($events[$code], false), $code.' for another reader');
        }
    }

    public function test_every_key_of_a_row_is_in_lang_id_and_every_key_of_the_amendment_is_one_that_a_row_carries(): void
    {
        $dictionary = Dictionary::fromDirectory(dirname(__DIR__, 3).'/lang/id');
        $carried = ChangeRows::keys();

        foreach (self::events() as $events) {
            foreach ($events as $event) {
                foreach (ChangeRows::of($event, true, self::names()) as $change) {
                    $carried[] = $change['label'];
                    array_push($carried, ...$change['columns']);
                }
            }
        }

        $carried = array_values(array_unique($carried));

        foreach ($carried as $key) {
            $this->assertArrayHasKey($key, $dictionary, $key);
        }

        // The keys of section 11.15.2 that the page itself uses: a change row and a known zero cost (ACV-03, ACV-04).
        $approved = array_values(array_filter(array_keys($dictionary), static fn (string $key): bool => str_starts_with($key, self::PREFIX)));
        $used = [...array_values(array_filter($carried, static fn (string $key): bool => str_starts_with($key, self::PREFIX))), self::PREFIX.'from_to', self::PREFIX.'value.cost_zero'];
        sort($approved);
        sort($used);
        $this->assertSame($approved, $used);
    }

    public function test_no_stored_field_name_or_code_leaves_the_rows(): void
    {
        foreach ([true, false] as $owner) {
            $rows = [];

            foreach (self::events() as $events) {
                foreach ($events as $event) {
                    $rows[] = ChangeRows::of($event, $owner, self::names());
                }
            }

            $json = (string) json_encode($rows);

            foreach ([...self::FIELD_NAMES, ...self::CODES] as $name) {
                $this->assertStringNotContainsString('"'.$name.'"', $json, $name);
            }

            // The Owner-only data never reaches another reader (ACV-02, D-047).
            foreach (['"6000"', '"2600"', '"3500"', 'Hadiah pemasok', 'Barang bonus'] as $secret) {
                $owner ? $this->assertStringContainsString($secret, $json, $secret) : $this->assertStringNotContainsString($secret, $json, $secret);
            }
        }
    }

    public function test_each_kind_of_value_carries_what_the_page_formats(): void
    {
        $names = self::names();

        // A variance with its word; money; a count; a cash session's register as text.
        $close = self::short(self::rowsOf('CASH_SESSION_CLOSE'));
        $this->assertSame(['value', 'variance:-2000'], $close['audit.change.field.variance']);
        $this->assertSame(['value', 'money:128000'], $close['audit.change.field.physical_cash']);
        $this->assertSame(['value', 'count:2'], $close['audit.change.field.sale_count']);
        $this->assertSame(['value', 'text:LACI-1'], $close['audit.change.field.register']);

        // A Sale: its method by its label, its lines with the product's name now, the quantity in the product's
        // unit and precision, and the Owner's cost.
        $sale = self::short(self::rowsOf('SALE_CHECKOUT'));
        $this->assertSame(['value', 'labels:sales.payment.method_cash'], $sale['audit.change.field.payment_method']);
        $this->assertSame(['table', [self::PREFIX.'column.product', self::PREFIX.'column.quantity', self::PREFIX.'column.unit_price', self::PREFIX.'column.line_total', self::PREFIX.'column.cost'], [['text:Pulpen Hitam', 'quantity:2 pcs/0', 'money:7500', 'money:15000', 'cost:6000']]], $sale['audit.change.field.lines']);

        // A refund from a drawer; a return line's cost for the Owner.
        $refund = self::short(self::rowsOf('SALE_CORRECTION'));
        $this->assertSame(['value', 'labels:audit.change.value.source_drawer'], $refund['audit.change.field.refund_source']);
        $this->assertSame(['value', 'date:2026-11-15'], $refund['audit.change.field.business_date']);
        $this->assertSame([['text:Pulpen Hitam', 'quantity:1 pcs/0', 'quantity:0 pcs/0', 'money:7500', 'cost:3000']], $refund['audit.change.field.lines'][2] ?? null);

        // An UNKNOWN cost, a signed quantity, a known zero cost with its reason.
        $this->assertSame([['text:Kertas Kado', 'quantity:12.5 m/1', 'labels:costing.status.unknown']], self::short(self::rowsOf('RECEIVING_CONFIRM'))['audit.change.field.lines'][2] ?? null);
        $this->assertSame([['text:Pulpen Hitam', 'signed:-2 pcs/0', 'cost:6000'], ['text:Kertas Kado', 'signed:1.5 m/1', 'cost_zero:Barang bonus']], self::short(self::rowsOf('ADJUSTMENT_CONFIRM'))['audit.change.field.lines'][2] ?? null);
        $this->assertSame(['value', 'signed:-2 pcs/0'], self::short(self::rowsOf('REVERSAL_CONFIRM'))['audit.change.field.reversed_quantity']);
        $this->assertSame(['change', 'cost:3000', 'cost_zero:Hadiah pemasok'], self::short(self::rowsOf('COST_CORRECTION'))['audit.change.field.cost']);

        // A zero count shows its word, and has no cost.
        $this->assertSame([['text:Pulpen Hitam', 'quantity:5 pcs/0', 'text:HIT-2', 'cost:2500'], ['text:Kertas Kado', 'labels:inventory.onboarding.zero_count', null, null]], self::short(self::rowsOf('OPENING_STOCK_CONFIRM'))['audit.change.field.lines'][2] ?? null);

        // A product edit: each changed field in the order of the table; Stok minimum in the unit and precision of
        // its own side; an empty side is "Kosong".
        $edit = self::short(self::rowsOf('PRODUCT_EDIT'));
        $this->assertSame(['change', 'text:Pulpen', 'text:Pulpen Hitam'], $edit['audit.change.field.name']);
        $this->assertSame(['change', 'text:Alat Tulis', 'text:Kertas'], $edit['audit.change.field.category']);
        $this->assertSame(['change', 'labels:audit.change.value.empty', 'text:Faber'], $edit['audit.change.field.brand']);
        $this->assertSame(['change', 'count:0', 'count:1'], $edit['audit.change.field.precision']);
        $this->assertSame(['change', 'quantity:2 pcs/0', 'quantity:1.5 lusin/1'], $edit['audit.change.field.min_stock']);

        // Lists in the order of the table; roles joined; a user by the name now; yes or no.
        $this->assertSame(['value', 'labels:config.profile.shop_name,config.profile.address,config.profile.logo'], self::short(self::rowsOf('PROFILE_CHANGE'))['audit.change.field.changed']);
        $this->assertSame(['change', 'labels:access.role.cashier', 'labels:access.role.operations_admin,access.role.cashier'], self::short(self::rowsOf('ROLE_CHANGE'))['audit.change.field.roles']);
        $this->assertSame(['change', 'text:Hendra', 'text:Sinta'], self::short(self::rowsOf('OWNER_REPLACEMENT'))['audit.change.field.owner']);
        $this->assertSame(['value', 'labels:audit.change.value.no'], self::short(self::rowsOf('COST_INCOMPLETE_MARK'))['audit.change.field.mark_cleared']);
        $this->assertSame(['value', 'labels:audit.change.value.yes'], self::short(self::rowsOf('PROJECTION_REBUILT', 1))['audit.change.field.totals_rebuilt']);
        $this->assertSame(['change', 'cost:30000', 'labels:audit.change.value.empty'], self::short(self::rowsOf('PROJECTION_REBUILT'))['audit.change.field.inventory_value']);

        // A register's change shows only the fields whose two sides differ.
        $this->assertSame([self::PREFIX.'field.label', self::PREFIX.'field.status'], array_keys(self::short(ChangeRows::of(['action' => 'REGISTER_CHANGE', 'change' => ['from' => ['code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true], 'to' => ['code' => 'LACI-1', 'label' => 'Laci depan', 'active' => false]], 'owner' => null, 'product' => null], true, $names))));

        // A code without a label is not shown, nor a value the event does not hold (ACV-01, ACV-04).
        $this->assertSame([], ChangeRows::of(['action' => 'RECONCILIATION_BLOCK', 'change' => ['detailCode' => 'SOMETHING_NEW'], 'owner' => null, 'product' => null], true, $names));
        $this->assertSame([self::PREFIX.'field.line_count'], array_keys(self::short(ChangeRows::of(['action' => 'RECEIVING_CONFIRM', 'change' => ['lineCount' => 1, 'documentDate' => null, 'lines' => []], 'owner' => ['lines' => []], 'product' => null], true, $names))));
    }

    /**
     * The rows of each action code under "Perubahan" and its rows marked "Pemilik", as copy keys in the order of
     * COPY_DECK section 11.15.3, read from the approved document.
     *
     * @return array<string, array{list<string>, list<string>}>
     */
    private static function amendment(): array
    {
        $deck = (string) file_get_contents(dirname(__DIR__, 3).'/docs/07-ux-design/COPY_DECK.md');
        $start = strpos($deck, '#### 11.15.3 Rows per action code');
        $end = strpos($deck, '## 12. States');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $found = [];

        foreach (preg_split('/\R/', substr($deck, $start, $end - $start)) ?: [] as $line) {
            if (! str_starts_with($line, '| `')) {
                continue;
            }

            $cells = array_map(trim(...), explode('|', trim($line, ' |')));
            preg_match_all('/`([A-Z][A-Z0-9_]*)`/', $cells[0], $codes);
            $keys = self::keysIn($cells[1] ?? '');
            $owner = self::keysIn($cells[2] ?? '');

            // "As `CASH_SESSION_CLOSE`, with `field.responsible` (text) after `field.register`".
            if (preg_match('/^As `([A-Z_]+)`, with `([a-z._]+)` \([a-z ]+\) after `([a-z._]+)`/', $cells[1] ?? '', $as) === 1) {
                [$base] = $found[$as[1]] ?? [[]];
                $at = array_search(self::PREFIX.$as[3], $base, true);
                self::assertIsInt($at);
                $keys = [...array_slice($base, 0, $at + 1), self::PREFIX.$as[2], ...array_slice($base, $at + 1)];
            }

            foreach ($codes[1] as $code) {
                $found[$code] = [$keys, $owner];
            }
        }

        $ordered = [];

        foreach (array_keys(AuditActions::LABELS) as $code) {
            if (array_key_exists($code, $found)) {
                $ordered[$code] = $found[$code];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function keysIn(string $cell): array
    {
        preg_match_all('/`((?:field|column)\.[a-z_]+)`/', $cell, $matches);

        return array_values(array_unique(array_map(static fn (string $key): string => self::PREFIX.$key, $matches[1])));
    }

    /**
     * The labels of the rows of the events, and of a table's columns after its own, in order and once each.
     *
     * @param  list<array{action: string, change: array<array-key, mixed>, owner: array<array-key, mixed>|null, product: int|null}>  $events
     * @return list<string>
     */
    private static function labelsOf(array $events, bool $owner): array
    {
        $labels = [];

        foreach ($events as $event) {
            foreach (ChangeRows::of($event, $owner, self::names()) as $change) {
                foreach ([$change['label'], ...$change['columns']] as $label) {
                    if (! in_array($label, $labels, true)) {
                        $labels[] = $label;
                    }
                }
            }
        }

        return $labels;
    }

    /**
     * The rows by their label: a value as `kind:text`, the labels of codes as `labels:key,key`, a quantity with its
     * unit and precision; a change with both sides; a table with its columns and cells.
     *
     * @param  list<Change>  $changes
     * @return array<string, list<mixed>>
     */
    private static function short(array $changes): array
    {
        $short = [];

        foreach ($changes as $change) {
            $short[$change['label']] = match ($change['kind']) {
                'change' => ['change', self::valueText($change['from']), self::valueText($change['to'])],
                'table' => ['table', $change['columns'], array_map(static fn (array $line): array => array_map(self::valueText(...), $line), $change['lines'])],
                default => ['value', self::valueText($change['value'])],
            };
        }

        return $short;
    }

    /**
     * @param  array{kind: string, text: string|null, keys: list<string>, precision: int|null, unit: string|null}|null  $value
     */
    private static function valueText(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($value['kind']) {
            'labels' => 'labels:'.implode(',', $value['keys']),
            'quantity', 'signed' => $value['kind'].':'.($value['text'] ?? '').' '.($value['unit'] ?? '').'/'.(string) ($value['precision'] ?? ''),
            default => $value['kind'].':'.($value['text'] ?? ''),
        };
    }

    /**
     * The Owner's rows of the event of an action code, the first unless another is named.
     *
     * @return list<Change>
     */
    private static function rowsOf(string $code, int $at = 0): array
    {
        return ChangeRows::of(self::events()[$code][$at], true, self::names());
    }

    /**
     * The names now of the records that the events name.
     *
     * @return Names
     */
    private static function names(): array
    {
        return [
            'product' => [1 => ['name' => 'Pulpen Hitam', 'unit' => 'pcs', 'precision' => 0], 2 => ['name' => 'Kertas Kado', 'unit' => 'm', 'precision' => 1]],
            'category' => [5 => 'Alat Tulis', 6 => 'Kertas'],
            'user' => [7 => 'Hendra', 8 => 'Sinta'],
        ];
    }

    /**
     * For each action code, the events that its Actions write — two or three where one code is written for
     * different subjects — with every field that its rows read.
     *
     * @return array<string, list<array{action: string, change: array<array-key, mixed>, owner: array<array-key, mixed>|null, product: int|null}>>
     */
    private static function events(): array
    {
        $known = static fn (string $amount) => ['status' => 'KNOWN', 'amount' => $amount];
        $event = static fn (string $action, array $change, ?array $owner = null, ?int $product = null) => ['action' => $action, 'change' => $change, 'owner' => $owner, 'product' => $product];
        $close = ['number' => '20261115-000001', 'register' => 'LACI-1', 'cutoffVersion' => 3, 'openingCash' => '100000', 'saleCount' => 2, 'cashApplied' => '30000', 'transferApplied' => '15000', 'expectedCash' => '130000', 'physicalCash' => '128000', 'variance' => '-2000'];
        $events = [
            'SALE_CHECKOUT' => [$event('SALE_CHECKOUT', [
                'number' => '20261115-000007', 'sessionNumber' => '20261115-000001', 'total' => '15000', 'method' => 'CASH', 'tendered' => '20000', 'change' => '5000', 'reference' => 'REF-1', 'lineCount' => 1,
                'lines' => [['lineNo' => 1, 'productId' => 1, 'priceRevisionId' => 3, 'unitPrice' => '7500', 'quantity' => '2', 'lineTotal' => '15000', 'movementId' => 9, 'productSequence' => 4]],
            ], ['lines' => [['lineNo' => 1, 'allocationId' => 2, ...$known('6000'), 'basedOnPublicationNo' => 1]]], 1)],
            'CASH_SESSION_OPEN' => [$event('CASH_SESSION_OPEN', ['number' => '20261115-000001', 'register' => 'LACI-1', 'openingCash' => '100000'])],
            'CASH_SESSION_CLOSE' => [$event('CASH_SESSION_CLOSE', $close)],
            'CASH_SESSION_CLOSE_ASSISTED' => [$event('CASH_SESSION_CLOSE_ASSISTED', [...$close, 'responsible' => 'Rina'])],
            'SALE_CORRECTION' => [$event('SALE_CORRECTION', [
                'saleId' => 4, 'saleNumber' => '20261115-000007', 'businessDate' => '2026-11-15',
                'refund' => ['amount' => '7500', 'payoutMethod' => 'CASH', 'fundingSource' => 'DRAWER', 'payoutReference' => '20261115-000007/1'],
                'lines' => [['saleLineId' => 11, 'productId' => 1, 'refundAmount' => '7500', 'returnedSaleable' => '1', 'returnedNotSaleable' => '0', 'movementId' => 12, 'productSequence' => 5]],
            ], ['returns' => [['saleLineId' => 11, 'allocationId' => 3, 'issueAllocationId' => 2, ...$known('3000'), 'basedOnPublicationNo' => 1]]], 1)],
            'RECEIVING_CONFIRM' => [$event('RECEIVING_CONFIRM', ['lineCount' => 1, 'documentDate' => '2026-11-14', 'lines' => [['lineNo' => 1, 'productId' => 2, 'quantity' => '12.5', 'movementId' => 13, 'productSequence' => 1]]], ['lines' => [['lineNo' => 1, 'evidenceId' => 1, 'status' => 'UNKNOWN', 'amount' => null, 'zeroNote' => null]]], 2)],
            'MANUAL_ISSUE_CONFIRM' => [$event('MANUAL_ISSUE_CONFIRM', ['reasonCode' => 'DAMAGED', 'note' => 'Pecah di rak', 'lineCount' => 1, 'lines' => [['lineNo' => 1, 'productId' => 1, 'quantity' => '1', 'movementId' => 14, 'productSequence' => 6]]], ['lines' => [['lineNo' => 1, 'allocationId' => 4, ...$known('3000'), 'basedOnPublicationNo' => 1]]], 1)],
            'ADJUSTMENT_CONFIRM' => [$event('ADJUSTMENT_CONFIRM', [
                'reason' => 'Hitung ulang rak', 'countReference' => 'HIT-1', 'lineCount' => 2,
                'lines' => [['lineNo' => 1, 'productId' => 1, 'quantity' => '-2', 'movementId' => 15, 'productSequence' => 7], ['lineNo' => 2, 'productId' => 2, 'quantity' => '1.5', 'movementId' => 16, 'productSequence' => 2]],
            ], ['lines' => [['lineNo' => 1, 'allocationId' => 5, ...$known('6000'), 'basedOnPublicationNo' => 2], ['lineNo' => 2, 'evidenceId' => 6, ...$known('0'), 'zeroNote' => 'Barang bonus']]])],
            'REVERSAL_CONFIRM' => [$event('REVERSAL_CONFIRM', ['originalMovementId' => 9, 'originalType' => 'SALE_ISSUE', 'originalQuantity' => '-2', 'movementId' => 17, 'inverseQuantity' => '2', 'productSequence' => 8], ['publicationNo' => 3, 'projectionsRewritten' => 1, 'marksCleared' => 1], 1)],
            'OPENING_STOCK_CONFIRM' => [$event('OPENING_STOCK_CONFIRM', [
                'rowCount' => 2,
                'rows' => [['rowNo' => 1, 'productId' => 1, 'quantity' => '5', 'countReference' => 'HIT-2', 'movementId' => 18], ['rowNo' => 2, 'productId' => 2, 'quantity' => '0', 'countReference' => null, 'zeroCountId' => 4]],
            ], ['rows' => [['rowNo' => 1, 'evidenceId' => 7, ...$known('2500'), 'zeroNote' => null]]])],
            'OPENING_STOCK_REPLACE' => [$event('OPENING_STOCK_REPLACE', ['replacesLineId' => 1, 'replacesMovementId' => 18, 'lineId' => 2, 'movementId' => 19, 'quantity' => '7', 'countReference' => 'HIT-3', 'productSequence' => 9], ['evidenceId' => 8, ...$known('2600'), 'zeroNote' => null], 1)],
            'ZERO_COUNT_WITHDRAW' => [$event('ZERO_COUNT_WITHDRAW', ['zeroCountId' => 4, 'productId' => 2], null, 2)],
            'ONBOARDING_START' => [$event('ONBOARDING_START', ['from' => 'NOT_STARTED', 'to' => 'ACTIVE', 'version' => 2])],
            'ONBOARDING_RELEASE' => [$event('ONBOARDING_RELEASE', ['from' => 'ACTIVE', 'to' => 'RELEASED', 'version' => 3])],
            'COST_CORRECTION' => [$event('COST_CORRECTION', ['evidenceId' => 1, 'movementId' => 13, 'correctionNo' => 1, 'publicationNo' => 4, 'projectionsRewritten' => 2, 'marksCleared' => 0], ['old' => $known('3000'), 'new' => $known('0'), 'zeroNote' => 'Hadiah pemasok', 'reason' => 'Nota pemasok'], 2)],
            'COST_INCOMPLETE_MARK' => [$event('COST_INCOMPLETE_MARK', ['evidenceId' => 1, 'movementId' => 13, 'rejection' => 'MOVEMENT_CAP', 'correctionsSeen' => 0, 'cleared' => false], ['attempted' => $known('3500'), 'zeroNote' => null, 'reason' => 'Nota pemasok'], 2)],
            'RECONCILIATION_BLOCK' => [$event('RECONCILIATION_BLOCK', ['detailCode' => 'BALANCE_MISMATCH'], null, 1)],
            'POSTING_REOPEN' => [$event('POSTING_REOPEN', ['blockId' => 1], ['reason' => 'Sudah diperiksa'], 1)],
            'PRODUCT_CREATE' => [$event('PRODUCT_CREATE', ['sku' => 'PEN-01', 'name' => 'Pulpen Hitam', 'categoryId' => 5, 'sellingPrice' => '7500', 'revisionId' => 1, 'barcode' => '8991234567890', 'symbology' => 'EAN_13', 'mappingId' => 1], null, 1)],
            'PRODUCT_EDIT' => [$event('PRODUCT_EDIT', [
                'changed' => ['name', 'categoryId', 'brand', 'baseUnit', 'quantityPrecision', 'minimumStock', 'sellingPrice'],
                'from' => ['name' => 'Pulpen', 'categoryId' => 5, 'brand' => null, 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'minimumStock' => '2'],
                'to' => ['name' => 'Pulpen Hitam', 'categoryId' => 6, 'brand' => 'Faber', 'baseUnit' => 'lusin', 'quantityPrecision' => 1, 'minimumStock' => '1.5'],
            ], null, 1)],
            'PRICE_CHANGE' => [$event('PRICE_CHANGE', ['from' => '7000', 'to' => '7500', 'revisionId' => 2, 'previousRevisionId' => 1], null, 1)],
            'PRODUCT_DEACTIVATE' => [$event('PRODUCT_DEACTIVATE', [], null, 1)],
            'PRODUCT_REACTIVATE' => [$event('PRODUCT_REACTIVATE', [], null, 1)],
            'CATEGORY_CREATE' => [$event('CATEGORY_CREATE', ['name' => 'Kertas'])],
            'CATEGORY_RENAME' => [$event('CATEGORY_RENAME', ['from' => 'Kertas', 'to' => 'Kertas Kado'])],
            'CATEGORY_DEACTIVATE' => [$event('CATEGORY_DEACTIVATE', [])],
            'CATEGORY_REACTIVATE' => [$event('CATEGORY_REACTIVATE', [])],
            'PHOTO_CHANGE' => [$event('PHOTO_CHANGE', ['photoFileId' => 3, 'thumbnailFileId' => 4, 'previousFileId' => null], null, 1)],
            'PHOTO_REMOVE' => [$event('PHOTO_REMOVE', ['previousFileId' => 3], null, 1)],
            'BARCODE_ADD' => [$event('BARCODE_ADD', ['barcode' => '96385074', 'symbology' => 'EAN_8', 'mappingId' => 2], null, 1)],
            'BARCODE_GENERATE' => [$event('BARCODE_GENERATE', ['barcode' => '2000000000015', 'mappingId' => 3], null, 1)],
            'BARCODE_RETIRE' => [$event('BARCODE_RETIRE', ['barcode' => '96385074', 'reasonClass' => 'MISASSIGNMENT', 'mappingId' => 2], null, 1)],
            'BARCODE_CORRECTION' => [$event('BARCODE_CORRECTION', ['barcode' => '96385074', 'kind' => 'MOVE', 'fromProductId' => 1, 'toProductId' => 2, 'correctedMappingId' => 2, 'mappingId' => 4], null, 2)],
            'CSV_IMPORT' => [$event('CSV_IMPORT', ['fileSha256' => str_repeat('a', 64), 'dataRowCount' => 3, 'productsCreated' => 3, 'barcodesCreated' => 1, 'rowsByCategory' => [5 => 2, 6 => 1], 'productIds' => [1, 2, 3]])],
            'PROFILE_CHANGE' => [$event('PROFILE_CHANGE', ['revisionId' => 2, 'previousRevisionId' => 1, 'changed' => ['address', 'shopName', 'logo']])],
            'REGISTER_CREATE' => [$event('REGISTER_CREATE', ['code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true])],
            'REGISTER_CHANGE' => [$event('REGISTER_CHANGE', ['from' => ['code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true], 'to' => ['code' => 'LACI-2', 'label' => 'Laci depan', 'active' => false]])],
            'USER_CREATE' => [$event('USER_CREATE', ['roles' => ['CASHIER']])],
            'ROLE_CHANGE' => [$event('ROLE_CHANGE', ['from' => ['CASHIER'], 'to' => ['CASHIER', 'OPERATIONS_ADMIN']])],
            'DETAILS_CHANGE' => [$event('DETAILS_CHANGE', ['changed' => ['displayName', 'email']])],
            'USER_DEACTIVATE' => [$event('USER_DEACTIVATE', [])],
            'USER_REACTIVATE' => [$event('USER_REACTIVATE', [])],
            'USER_ANONYMIZE' => [$event('USER_ANONYMIZE', [])],
            'LINK_ISSUE' => [$event('LINK_ISSUE', ['kind' => 'FIRST_PASSWORD'])],
            'LINK_REVOKE' => [$event('LINK_REVOKE', [])],
            'LINK_CONSUME' => [$event('LINK_CONSUME', ['kind' => 'RESET', 'sessionsRevoked' => 2])],
            'PASSWORD_CHANGE' => [$event('PASSWORD_CHANGE', ['sessionsRevoked' => 1])],
            'OWNER_RECOVERY' => [$event('OWNER_RECOVERY', ['sessionsRevoked' => 0])],
            'OWNER_REPLACEMENT' => [$event('OWNER_REPLACEMENT', ['outgoing' => 7, 'incoming' => 8, 'sessionsRevoked' => 1])],
            'REVIEW_NOTE_ADD' => [$event('REVIEW_NOTE_ADD', ['number' => '20261115-000001', 'noteId' => 1])],
            'PRINT' => [
                $event('PRINT', ['barcode' => '96385074', 'mappingId' => 2, 'count' => 3], null, 1),
                $event('PRINT', ['size' => 'A4', 'copy' => true]),
                $event('PRINT', []),
            ],
            'EXPORT' => [$event('EXPORT', ['format' => 'CSV'])],
            'ONBOARDING_RESTORE_HOLD' => [$event('ONBOARDING_RESTORE_HOLD', ['holdId' => 1, 'restoredState' => 'ACTIVE', 'version' => 4])],
            'RESTORE_RECONCILIATION' => [
                $event('RESTORE_RECONCILIATION', ['decision' => 'intent', 'reportId' => 1, 'operation' => 'sale.checkout', 'outcome' => 'NOT_COMMITTED', 'fenceId' => 1, 'receiptsSettled' => 1]),
                $event('RESTORE_RECONCILIATION', ['decision' => 'onboarding_hold', 'holdId' => 1, 'restoredState' => 'ACTIVE', 'outcome' => 'RELEASED_BEFORE_RESTORE', 'state' => 'RELEASED', 'version' => 5]),
            ],
            'PROJECTION_REBUILT' => [
                $event('PROJECTION_REBUILT', ['subject' => 'product', 'balanceRebuilt' => true, 'valuationRebuilt' => false, 'publicationNo' => 5, 'issueProjections' => 0, 'returnProjections' => 0], ['valueBefore' => $known('30000'), 'valueAfter' => null], 1),
                $event('PROJECTION_REBUILT', ['subject' => 'sale', 'totalsRebuilt' => 1]),
                $event('PROJECTION_REBUILT', ['subject' => 'token', 'mappingBefore' => 2, 'mappingAfter' => 4]),
            ],
        ];

        $ordered = [];

        foreach (array_keys(AuditActions::LABELS) as $code) {
            $ordered[$code] = $events[$code];
        }

        return $ordered;
    }
}
