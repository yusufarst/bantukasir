<?php

namespace Tests\Feature\Sales;

use App\Queries\Catalog\ProductSearch;
use App\Queries\Catalog\SearchFilters;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\Configuration\ProfileChangeScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Media\TestImages;

/**
 * What a Sale records, how it is valued and how it is numbered, through RC-01's Action (AC-028-05 to
 * AC-028-07; SALES_CASH BR-SALE-05, BR-SALE-06, BR-SALE-09, BR-DOC-01; COSTING sections 4, 6 and 7,
 * BR-COST-03, BR-COST-10 to BR-COST-16; ADR-008 sections 2 and 4.1; SCHEMA section 6, SCH-21, SCH-22,
 * G-13, G-17, G-18; TECHNICAL_RULES RACE-18, RACE-23 and RACE-25 in sequence, their races being
 * CheckoutRaceTest's; D-036, D-038, D-051, D-052; suites FIN01, POS01, POS02, IDENTITY01 and BARCODE01;
 * TASK-028 section 7, L2): the L-15 example with its sales, cost of goods sold and gross profit; an
 * allocation UNKNOWN, never zero; the Sale that empties stock and the pool after it; the number by the
 * business date of Asia/Jakarta across midnight; the receipt's frozen identity by its revision, with a
 * logo; a cart line taken before a barcode move; a price change or a deactivation after a Sale.
 */
final class CheckoutRecordsTest extends DatabaseTestCase
{
    private int $owner = 0;

    private int $rina = 0;

    private int $category = 0;

    private ?Connection $watcher = null;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->rina = TokoContoh::person('Rina')['id'];
        $this->category = TokoContoh::category();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_the_example_of_l_15_gives_sales_cost_of_goods_sold_and_gross_profit(): void
    {
        // AC-028-05, FIN01; COSTING section 4, the L-15 example; BR-COST-10, BR-SALE-06; G-13. 10 units
        // worth Rp100.000, then 10 worth Rp140.000: 20 worth Rp240.000, average Rp12.000. Three sold at
        // Rp20.000: sales Rp60.000, cost of goods sold Rp36.000, gross profit Rp24.000, leaving 17 worth
        // Rp204.000. A manual issue of 2 then takes Rp24.000 and adds nothing to the cost of goods sold.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '20000']);
        TokoContoh::stock($book, '10', '100000');
        TokoContoh::stock($book, '10', '140000');
        $session = $this->rinasSession();

        $sale = TokoContoh::sell('Rina', $session, [['productId' => $book, 'quantity' => '3']]);
        $this->assertSame(['60000', '36000.000000', '24000.000000'], $this->figuresOf($sale));
        $this->assertSame(['17.000', 3], $this->balanceOf($book));
        $this->assertSame('KNOWN|204000.000000|3|3', $this->valuationOf($book));

        TokoContoh::issue([['productId' => $book, 'quantity' => '2']]);
        $this->assertSame([['SALE_ISSUE', '36000.000000'], ['MANUAL_ISSUE', '24000.000000']], $this->watcher()->table('issue_cost_allocations')->where('product_id', $book)->orderBy('id')->get(['issue_type', 'at_commit_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(['60000', '36000.000000', '24000.000000'], $this->figuresOf($sale));
        $this->assertSame('KNOWN|180000.000000|4|4', $this->valuationOf($book));

        // FIN01: 1,5 kg at Rp3.333 is Rp4.999,5, a line total of Rp5.000, half-up once; its cost the
        // average of the valuation at six decimals: 1,5 of 7 kg worth Rp10.000 is Rp2.142,857143.
        $sugar = TokoContoh::product('GULA-01', ['categoryId' => $this->category, 'name' => 'Gula', 'baseUnit' => 'kg', 'quantityPrecision' => 3, 'sellingPrice' => '3333']);
        TokoContoh::stock($sugar, '7', '10000');
        $weighed = TokoContoh::sell('Rina', $session, [['productId' => $sugar, 'quantity' => '1.5']]);
        $this->assertSame(['5000', '2142.857143', '2857.142857'], $this->figuresOf($weighed));
        $this->assertSame([[3333, '1.500', 5000]], $this->watcher()->table('sale_lines')->where('sale_id', $weighed)->get(['unit_price', 'quantity', 'line_total'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_an_unknown_valuation_gives_unknown_allocations_never_zero_and_the_sell_out_ends_its_pool(): void
    {
        // AC-028-05, AC-028-11 at commit; BR-COST-03, BR-COST-11, BR-COST-13, BR-COST-15; ADR-008
        // sections 2 and 4.1; G-13, G-14. 4 units worth Rp4.000 and 6 of UNKNOWN cost: a Sale of 7 is
        // UNKNOWN, with no amount, and so is its projection; the last 3 empty the stock, UNKNOWN still,
        // and the pool after zero is a known zero, from which a known receipt starts a known pool.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis', 'sellingPrice' => '5000']);
        TokoContoh::stock($book, '4', '4000');
        TokoContoh::receive([['productId' => $book, 'quantity' => '6', 'cost' => ['status' => 'UNKNOWN']]]);
        $session = $this->rinasSession();

        $partial = TokoContoh::sell('Rina', $session, [['productId' => $book, 'quantity' => '7']]);
        $this->assertSame([['UNKNOWN', null, 2, 'UNKNOWN', null]], $this->allocationsOf($partial));
        $this->assertSame('UNKNOWN||3|3', $this->valuationOf($book));
        $this->assertSame(['lines' => [['allocationId' => $this->allocationIdOf($partial), 'amount' => null, 'basedOnPublicationNo' => 2, 'lineNo' => 1, 'status' => 'UNKNOWN']]], $this->ownerDataOf($partial));

        $last = TokoContoh::sell('Rina', $session, [['productId' => $book, 'quantity' => '3']]);
        $this->assertSame([['UNKNOWN', null, 3, 'UNKNOWN', null]], $this->allocationsOf($last));
        $this->assertSame(['0.000', 4], $this->balanceOf($book));
        $this->assertSame('KNOWN|0.000000|4|4', $this->valuationOf($book));

        TokoContoh::stock($book, '2', '5000');
        $next = TokoContoh::sell('Rina', $session, [['productId' => $book, 'quantity' => '1']]);
        $this->assertSame([['KNOWN', '2500.000000', 5, 'KNOWN', '2500.000000']], $this->allocationsOf($next));

        // A known pool emptied by Sales: each takes its share, the last what is left (BR-COST-11).
        $pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '7500']);
        TokoContoh::stock($pen, '3', '100');

        foreach (['33.333333', '33.333334', '33.333333'] as $index => $amount) {
            $sale = TokoContoh::sell('Rina', $session, [['productId' => $pen, 'quantity' => '1']]);
            $this->assertSame([['KNOWN', $amount, $index + 1, 'KNOWN', $amount]], $this->allocationsOf($sale), (string) $index);
        }

        $this->assertSame('KNOWN|0.000000|4|4', $this->valuationOf($pen));
        $this->assertSame(0, $this->watcher()->table('issue_cost_allocation_projections')->where('current_status', 'UNKNOWN')->whereNotNull('current_amount')->count());
    }

    public function test_the_number_takes_its_date_in_asia_jakarta_across_midnight_and_its_counter_runs_on(): void
    {
        // AC-028-06, G-18; SCHEMA section 6, SCH-21, SCH-22; BR-DOC-01; TR-TX-10: 23:59:59,999999 on 6
        // November in Asia/Jakarta is 16:59:59,999999 UTC, and a microsecond later the business date is
        // 7 November. The counter is never reset by a date: it runs on in the order the numbers were
        // taken, and each receipt holds its Sale's number and instant.
        $this->travelTo('2026-11-06T16:00:00Z');
        $pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '7500']);
        TokoContoh::stock($pen, '10', '10000');
        $session = $this->rinasSession();
        $sales = [];

        foreach (['2026-11-06T16:59:59.999999Z', '2026-11-06T17:00:00.000000Z', '2026-11-07T16:59:59.000000Z', '2026-11-07T17:00:00.000001Z'] as $instant) {
            $this->travelTo($instant);
            $sales[] = TokoContoh::sell('Rina', $session, [['productId' => $pen, 'quantity' => '1']]);
        }

        $first = self::number($this->watcher()->table('sales')->where('id', $sales[0])->value('sale_number_seq'));
        $this->assertSame([
            [sprintf('20261106-%06d', $first), '2026-11-06', $first, '2026-11-06 23:59:59.999999'],
            [sprintf('20261107-%06d', $first + 1), '2026-11-07', $first + 1, '2026-11-07 00:00:00.000000'],
            [sprintf('20261107-%06d', $first + 2), '2026-11-07', $first + 2, '2026-11-07 23:59:59.000000'],
            [sprintf('20261108-%06d', $first + 3), '2026-11-08', $first + 3, '2026-11-08 00:00:00.000001'],
        ], array_map(fn (int $sale): array => array_values((array) $this->watcher()->selectOne(
            "select sale_number, business_date::text as business_date, sale_number_seq, to_char(committed_at at time zone 'Asia/Jakarta', 'YYYY-MM-DD HH24:MI:SS.US') as local from sales where id = ?",
            [$sale],
        )), $sales));
        $this->assertSame(0, self::number(((array) $this->watcher()->selectOne('select count(*) as wrong from receipt_snapshots r join sales s on s.id = r.sale_id where r.sale_number <> s.sale_number or r.issued_at <> s.committed_at'))['wrong'] ?? null));
    }

    public function test_each_receipt_freezes_the_identity_in_force_by_its_revision_with_its_logo(): void
    {
        // AC-028-07, RACE-25 in sequence; G-17; BR-DOC-01, BR-CFG-03; D-038; DOCUMENT_CONTRACTS DC-01. A
        // Sale's receipt references the BusinessProfile revision in force when its lock was taken, with
        // that revision's logo; a later change makes a new revision for later Sales and changes no
        // receipt already issued.
        $pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '7500']);
        TokoContoh::stock($pen, '10', '10000');
        $session = $this->rinasSession();
        $initial = $this->currentRevision();
        $first = TokoContoh::sell('Rina', $session, [['productId' => $pen, 'quantity' => '1']]);
        $this->assertSame([$initial, null, 'receipt.1'], $this->identityOf($first));

        $changed = (new ProfileChangeScenario)->run(['actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => TokoContoh::recoveryEpoch(), 'revision' => $initial, 'shop' => 'Toko Contoh Baru', 'logo' => $this->logoFile(TestImages::png(40, 20))]);
        $this->assertSame('COMMITTED', $changed['outcome'] ?? null);
        $revision = $this->currentRevision();
        $logo = self::number($this->watcher()->table('business_profile_revisions')->where('id', $revision)->value('logo_file_id'));
        $this->assertNotSame($initial, $revision);
        $this->assertGreaterThan(0, $logo);

        $second = TokoContoh::sell('Rina', $session, [['productId' => $pen, 'quantity' => '1']]);
        $this->assertSame([$revision, $logo, 'receipt.1'], $this->identityOf($second));
        $this->assertSame([$initial, null, 'receipt.1'], $this->identityOf($first));
        $this->assertSame('Toko Contoh Baru', $this->watcher()->table('business_profile_revisions')->where('id', $this->identityOf($second)[0])->value('shop_name'));
    }

    public function test_a_cart_line_taken_before_a_barcode_move_keeps_its_product(): void
    {
        // AC-028-07, RACE-18 the Sale side in sequence (the race is CheckoutRaceTest's); D-051, D-052,
        // BR-CAT-30: Sale lines reference products, never tokens. The cart takes the line of the product
        // that the code found; the code moves to its right product; the Sale commits the line's own
        // product, an earlier Sale keeps its own, and the code now finds the right product.
        $wrong = TokoContoh::product('SALAH-01', ['categoryId' => $this->category, 'name' => 'Produk Salah', 'sellingPrice' => '5000']);
        $right = TokoContoh::product('BENAR-01', ['categoryId' => $this->category, 'name' => 'Produk Benar', 'sellingPrice' => '6000']);
        TokoContoh::stock($wrong, '5', '5000');
        TokoContoh::stock($right, '5', '5000');
        $token = 'T-'.Str::random(8);
        TokoContoh::barcode($wrong, $token);
        $session = $this->rinasSession();
        $earlier = TokoContoh::sell('Rina', $session, [['productId' => $this->found($token), 'quantity' => '1']]);
        $cart = $this->found($token);
        $this->assertSame($wrong, $cart);

        $moved = $this->asOwner('barcode.correct_misassignment', ['token' => $token, 'targetProductId' => $right, 'reason' => 'Salah pasang'], ['mapping' => ['productId' => $wrong, 'state' => 'ACTIVE']]);
        $this->assertSame('COMMITTED', $moved['outcome'] ?? null);
        $this->assertSame($right, $this->found($token));

        $sale = TokoContoh::sell('Rina', $session, [['productId' => $cart, 'quantity' => '1']]);
        $later = TokoContoh::sell('Rina', $session, [['productId' => $this->found($token), 'quantity' => '1']]);
        $this->assertSame([[$wrong, 5000], [$wrong, 5000], [$right, 6000]], array_map(fn (int $id): array => array_values((array) $this->watcher()->table('sale_lines')->where('sale_id', $id)->sole(['product_id', 'unit_price'])), [$earlier, $sale, $later]));
        $this->assertSame([['3.000', 3], ['4.000', 2]], [$this->balanceOf($wrong), $this->balanceOf($right)]);
    }

    public function test_a_price_change_or_a_deactivation_after_a_sale_applies_to_later_sales_and_the_line_keeps_its_price(): void
    {
        // AC-028-07, RACE-23 in sequence, the Sale first (the change first is CheckoutSaleTest's, the
        // race CheckoutRaceTest's); D-036, BR-SALE-05, BR-CAT-08: the Sale line keeps the price it
        // applied, by its revision; a later Sale takes the new price, and a cart reviewed at the old one
        // is refused PRICE_CHANGED; after a deactivation, PRODUCT_INACTIVE.
        $pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'sellingPrice' => '7500']);
        TokoContoh::stock($pen, '10', '10000');
        $session = $this->rinasSession();
        $old = $this->revisionOf($pen);
        $sale = TokoContoh::sell('Rina', $session, [['productId' => $pen, 'quantity' => '2']]);

        $this->assertSame('COMMITTED', $this->asOwner('product.update', ['productId' => $pen, 'name' => 'Pulpen Hitam', 'categoryId' => $this->category, 'brand' => null, 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'sellingPrice' => '8000', 'minimumStock' => '0'], ['productVersion' => $this->versionOf($pen)])['outcome'] ?? null);
        $new = $this->revisionOf($pen);
        $this->assertSame(['refused' => 'PRICE_CHANGED'], $this->checkout($session, $pen, $old, '7500'));
        $later = TokoContoh::sell('Rina', $session, [['productId' => $pen, 'quantity' => '1']]);
        $this->assertSame([[$old, 7500, 15000], [$new, 8000, 8000]], array_map(fn (int $id): array => array_values((array) $this->watcher()->table('sale_lines')->where('sale_id', $id)->sole(['selling_price_revision_id', 'unit_price', 'line_total'])), [$sale, $later]));

        $this->assertSame('COMMITTED', $this->asOwner('product.deactivate', ['productId' => $pen], ['productVersion' => $this->versionOf($pen)])['outcome'] ?? null);
        $this->assertSame(['refused' => 'PRODUCT_INACTIVE'], $this->checkout($session, $pen, $new, '8000'));
        $this->assertSame([2, 15000, 8000], [$this->watcher()->table('sales')->count(), $this->watcher()->table('sales')->where('id', $sale)->value('total_amount'), $this->watcher()->table('sales')->where('id', $later)->value('total_amount')]);
    }

    /**
     * RC-01 by Rina in this session through its Action, one line of the product reviewed at this
     * revision, paid in CASH: its outcome, or the code of its refusal.
     *
     * @return array<string, mixed>
     */
    private function checkout(int $session, int $product, int $revision, string $tendered): array
    {
        return (new UserCommandScenario)->run([
            'operation' => 'sale.checkout',
            'actor' => $this->rina,
            'intent' => (string) Str::uuid(),
            'key' => (string) Str::uuid(),
            'epoch' => TokoContoh::recoveryEpoch(),
            'expected' => ['lines' => [['priceRevisionId' => $revision]]],
            'payload' => ['sessionId' => $session, 'lines' => [['productId' => $product, 'quantity' => '1']], 'payment' => ['method' => 'CASH', 'tendered' => $tendered]],
        ]);
    }

    /**
     * A command of the Owner through its Action: its outcome, or the code of its refusal.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function asOwner(string $operation, array $payload, array $expected): array
    {
        return (new UserCommandScenario)->run(['operation' => $operation, 'actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => TokoContoh::recoveryEpoch(), 'expected' => $expected, 'payload' => $payload]);
    }

    /**
     * The product that a scanned code finds in RD-01's exact mode, as the till's lookup does.
     */
    private function found(string $code): int
    {
        $rows = app(ProductSearch::class)(new SearchFilters($code, exact: true))['rows'];

        return self::number($rows[0]['id'] ?? null);
    }

    /**
     * A receipt's identity: its BusinessProfile revision, its logo and its template.
     *
     * @return list<mixed>
     */
    private function identityOf(int $sale): array
    {
        return array_values((array) $this->watcher()->table('receipt_snapshots')->where('sale_id', $sale)->sole(['business_profile_revision_id', 'logo_file_id', 'template_version']));
    }

    private function currentRevision(): int
    {
        return self::number($this->watcher()->table('business_profile_current')->where('id', 1)->value('revision_id'));
    }

    private function revisionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id'));
    }

    private function versionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('version'));
    }

    /**
     * A logo in a file of its own, which RC-32's Action reads as an upload.
     */
    private function logoFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bk-logo-');

        if ($path === false || file_put_contents($path, $bytes) === false) {
            $this->fail('The test logo is written to a temporary file.');
        }

        $this->files[] = $path;

        return $path;
    }

    /**
     * Rina's OPEN session, opened on first use at the time the test stands at.
     */
    private function rinasSession(): int
    {
        $open = $this->watcher()->table('cash_sessions')->where('responsible_user_id', $this->rina)->where('status', 'OPEN')->value('id');

        return is_int($open) ? $open : TokoContoh::openSession('Rina', TokoContoh::register('LACI-'.Str::upper(Str::random(4)), 'Laci Rina'));
    }

    /**
     * A Sale's sales, cost of goods sold and gross profit, as the database computes them from its
     * total and its lines' allocations.
     *
     * @return list<mixed>
     */
    private function figuresOf(int $sale): array
    {
        return array_values((array) $this->watcher()->selectOne(
            'select s.total_amount::text as sales, sum(a.at_commit_amount)::text as cost, (s.total_amount - sum(a.at_commit_amount))::text as profit from sales s join sale_lines l on l.sale_id = s.id join issue_cost_allocations a on a.sale_line_id = l.id where s.id = ? group by s.id, s.total_amount',
            [$sale],
        ));
    }

    /**
     * A Sale's allocations by line: the fact's status, amount and publication, the projection's
     * status and amount.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $sale): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')->join('sale_lines as l', 'l.id', '=', 'a.sale_line_id')
            ->where('l.sale_id', $sale)->orderBy('l.line_no')
            ->get(['a.at_commit_status', 'a.at_commit_amount', 'a.based_on_publication_no', 'p.current_status', 'p.current_amount'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    private function allocationIdOf(int $sale): int
    {
        return self::number($this->watcher()->table('issue_cost_allocations as a')->join('sale_lines as l', 'l.id', '=', 'a.sale_line_id')->where('l.sale_id', $sale)->orderBy('l.line_no')->value('a.id'));
    }

    /**
     * The Owner's part of a Sale's audit event.
     *
     * @return array<array-key, mixed>
     */
    private function ownerDataOf(int $sale): array
    {
        $data = json_decode(self::text($this->watcher()->table('audit_events')->where('subject_type', 'sale')->where('subject_id', $sale)->value('owner_only_data')), true);
        $this->assertIsArray($data);
        $lines = [];

        foreach ((array) ($data['lines'] ?? []) as $line) {
            $values = (array) $line;
            ksort($values);
            $lines[] = $values;
        }

        return ['lines' => $lines];
    }

    /**
     * A product's balance: on hand and the last sequence.
     *
     * @return list<mixed>
     */
    private function balanceOf(int $product): array
    {
        return array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']));
    }

    /**
     * A product's valuation as "status|value|publication|last sequence".
     */
    private function valuationOf(int $product): string
    {
        $row = (array) $this->watcher()->table('inventory_valuations')->where('product_id', $product)->sole();

        return implode('|', [self::text($row['value_status']), self::text($row['value']), (string) self::number($row['publication_no']), (string) self::number($row['last_sequence'])]);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
