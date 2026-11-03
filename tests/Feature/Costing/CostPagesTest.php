<?php

namespace Tests\Feature\Costing;

use App\Actions\Costing\CorrectCost;
use App\Http\Requests\Costing\CorrectionPageRequest;
use App\Http\Requests\Costing\CostHistoryPageRequest;
use Illuminate\Database\Connection;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-503 to RP-505, and what RP-403 and RP-406 show of a corrected cost (AC-025-01, AC-025-02;
 * ROUTE_CONTRACTS RP-503 to RP-505, RP-403, RP-406; NAVIGATION_CONTRACTS UXP-503 to UXP-505; D-047;
 * BR-COST-04, BR-COST-06, BR-COST-20 to BR-COST-22, BR-COST-27, BR-COST-28, BR-COST-38; BR-INV-15;
 * CAP-12, CAP-19, CAP-20; EXECUTION_CONTEXT section 10, a route and a read; suites FIN02, AUTH01,
 * AUTH02; TASK-025 scope item 6), as L4 tests of the pages' answers: each page's props against its
 * allowlist, the Owner's alone, every other kind of account refused; the worklist of the cost sources
 * that are UNKNOWN or marked, newest first, keyset-paged both ways, with its filter; a cost source as
 * it is now and as it was posted, with its history and the page that opened it; a product's
 * valuation, cost sources and corrections; the parameters as allowlists; a receiving that shows its
 * corrected cost and the cost at confirmation to every holder of CAP-12 and the reason and history
 * to the Owner alone, and a movement that shows its corrections to the Owner.
 */
final class CostPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string WORKLIST = '/reports/costs-to-complete';

    private int $category = 0;

    private int $pen = 0;

    private int $book = 0;

    /** The pen's first receipt, at Rp100.000, which a refused correction marked. */
    private int $marked = 0;

    /** The pen's second receipt, at Rp140.000, which the Owner corrected to Rp150.000. */
    private int $corrected = 0;

    /** The book's receipt, at an UNKNOWN cost. */
    private int $unknown = 0;

    /** The receiving documents of the pen's second receipt and of the book's. */
    private int $penDocument = 0;

    private int $bookDocument = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-03T03:00:00Z');
        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category('Alat Tulis');
        $this->pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'categoryId' => $this->category]);
        $this->book = TokoContoh::product('BUKU-01', ['name' => 'Buku Tulis', 'categoryId' => $this->category]);

        // The pen: 10 at Rp100.000, 10 at Rp140.000, then 3 out; the book: 4 at an UNKNOWN cost.
        TokoContoh::stock($this->pen, '10', '100000');
        $this->travel(1)->minutes();
        $this->penDocument = TokoContoh::stock($this->pen, '10', '140000');
        $this->travel(1)->minutes();
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '3']]);
        $this->travel(1)->minutes();
        $this->bookDocument = TokoContoh::receive([['productId' => $this->book, 'quantity' => '4', 'cost' => ['status' => 'UNKNOWN']]]);
        $this->marked = TokoContoh::evidenceOf($this->movementOf($this->pen, 1));
        $this->corrected = TokoContoh::evidenceOf($this->movementOf($this->pen, 2));
        $this->unknown = TokoContoh::evidenceOf($this->movementOf($this->book, 1));

        // The Owner corrects the second receipt; a correction of the first, past a movement cap of
        // one, is refused and marks it (BR-COST-27).
        $this->travel(1)->minutes();
        TokoContoh::correctCost($this->corrected, '150000', 'Nota susulan');
        $this->travel(1)->minutes();
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        config(['costing.replay.movement_cap' => 1]);
        $this->postJson('/commands/'.CorrectCost::OPERATION, $this->envelope(['evidenceId' => $this->marked, 'newCost' => ['amount' => '110000'], 'reason' => 'Nota pemasok'], ['operation' => CorrectCost::OPERATION, 'expected' => ['correctionNo' => 0]]))
            ->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        config(['costing.replay.movement_cap' => 10000]);
    }

    public function test_only_the_owner_opens_the_cost_pages_with_only_their_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $pages = [self::WORKLIST.'?product='.$this->pen, '/costs/'.$this->corrected.'/correct?from_page=history', '/products/'.$this->pen.'/cost-history?from_page=product'];
        $this->as('Hendra');

        foreach ($pages as $path) {
            $this->assertPageChannel($this->get($path)->assertOk(), 'owner', $secrets);
        }

        // AC-025-02, THR-19: every other role and the combined account are refused.
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach ($pages as $path) {
                $page = $this->get($path)->assertStatus(403)->viewData('page');
                $this->assertIsArray($page);
                $this->assertSame('shell/Error', $page['component'] ?? null, $person.' '.$path);
            }
        }

        $this->browse(null);

        foreach ($pages as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_the_worklist_holds_the_unknown_and_the_marked_sources_newest_first_with_its_filter_and_pages(): void
    {
        $this->as('Hendra');

        // BR-COST-27, BR-COST-38: the source never completed and the marked one; the corrected one is not.
        $this->assertSame([
            'rows' => [
                ['evidenceId' => $this->unknown, 'product' => ['id' => $this->book, 'name' => 'Buku Tulis', 'sku' => 'BUKU-01'], 'sourceType' => 'RECEIPT', 'recordedAt' => '2026-11-03T03:03:00.000000Z', 'unknown' => true, 'marked' => false],
                ['evidenceId' => $this->marked, 'product' => ['id' => $this->pen, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01'], 'sourceType' => 'RECEIPT', 'recordedAt' => '2026-11-03T03:00:00.000000Z', 'unknown' => false, 'marked' => true],
            ],
            'nextCursor' => null,
            'previousCursor' => null,
        ], $this->worklist(''));
        $whole = $this->propsOf($this->get(self::WORKLIST)->assertOk());
        $this->assertSame([['product' => null, 'perPage' => 25], null], [self::field($whole, 'filters'), self::field($whole, 'product')]);

        // The filter of one product, named; one that does not exist is NOT_FOUND.
        $filtered = $this->propsOf($this->get(self::WORKLIST.'?product='.$this->pen.'&perPage=10')->assertOk());
        $this->assertSame([['product' => $this->pen, 'perPage' => 10], ['id' => $this->pen, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01']], [$filtered['filters'] ?? [], $filtered['product'] ?? null]);
        $this->assertSame([$this->marked], array_column($this->worklist('product='.$this->pen)['rows'], 'evidenceId'));
        $this->assertSame([$this->unknown], array_column($this->worklist('product='.$this->book)['rows'], 'evidenceId'));
        $this->getJson(self::WORKLIST.'?product=999999')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        // Pages of one, and back (TR-PAGE-01).
        $first = $this->worklist('perPage=1');
        $this->assertSame([[$this->unknown], null], [array_column($first['rows'], 'evidenceId'), $first['previousCursor']]);
        $this->assertIsString($first['nextCursor']);
        $second = $this->worklist('perPage=1&after='.self::text($first['nextCursor']));
        $this->assertSame([[$this->marked], null], [array_column($second['rows'], 'evidenceId'), $second['nextCursor']]);
        $back = $this->worklist('perPage=1&before='.self::text($second['previousCursor']));
        $this->assertSame([[$this->unknown], null], [array_column($back['rows'], 'evidenceId'), $back['previousCursor']]);
        $this->assertIsString($back['nextCursor']);

        // A completed cost leaves the list (BR-COST-22); a later correction of the marked source
        // clears its mark (BR-COST-28), and the list is empty.
        TokoContoh::correctCost($this->unknown, '8000', 'Nota ditemukan');
        $this->assertSame([$this->marked], array_column($this->worklist('')['rows'], 'evidenceId'));
        TokoContoh::correctCost($this->marked, '110000');
        $this->assertSame(['rows' => [], 'nextCursor' => null, 'previousCursor' => null], $this->worklist(''));

        // The parameters are allowlists; a cursor that is not the list's is refused.
        foreach (['product=abc', 'product=0', 'product=1,2', 'perPage=0', 'perPage=101', 'after=x', 'after=x&before=y', 'before=***', 'q=PEN', 'from_page=worklist'] as $query) {
            $this->getJson(self::WORKLIST.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_correction_page_gives_the_source_as_it_is_now_and_as_it_was_posted_with_its_history(): void
    {
        // BR-COST-20 to BR-COST-22, G-15: the number of the latest correction, which RC-15 expects.
        $this->as('Hendra');

        $props = $this->propsOf($this->get('/costs/'.$this->corrected.'/correct?from_page=receiving')->assertOk());
        $this->assertSame([
            'evidenceId' => $this->corrected, 'product' => ['id' => $this->pen, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01'], 'movementId' => $this->movementOf($this->pen, 2),
            'sourceType' => 'RECEIPT', 'recordedAt' => '2026-11-03T03:01:00.000000Z', 'quantity' => '10', 'baseUnit' => 'pcs', 'quantityPrecision' => 0,
            'cost' => ['status' => 'KNOWN', 'amount' => '150000'], 'zeroNote' => null, 'confirmedCost' => ['status' => 'KNOWN', 'amount' => '140000'], 'correctionNo' => 1, 'marked' => false,
        ], $props['source'] ?? null);
        $this->assertSame([self::corrected()], $props['corrections'] ?? null);
        $this->assertSame('receiving', $props['opener'] ?? null);

        // The marked source, as it was posted; the unknown one, UNKNOWN and never zero (BR-COST-03).
        $marked = (array) ($this->propsOf($this->get('/costs/'.$this->marked.'/correct')->assertOk())['source'] ?? []);
        $this->assertSame([['status' => 'KNOWN', 'amount' => '100000'], 0, true], [$marked['cost'] ?? null, $marked['correctionNo'] ?? null, $marked['marked'] ?? null]);
        $unknown = $this->propsOf($this->get('/costs/'.$this->unknown.'/correct')->assertOk());
        $source = (array) ($unknown['source'] ?? []);
        $this->assertSame([['status' => 'UNKNOWN', 'amount' => null], ['status' => 'UNKNOWN', 'amount' => null], 0, false, [], null], [$source['cost'] ?? null, $source['confirmedCost'] ?? null, $source['correctionNo'] ?? null, $source['marked'] ?? null, $unknown['corrections'] ?? null, self::field($unknown, 'opener')]);

        foreach (CorrectionPageRequest::OPENERS as $opener) {
            $this->assertSame($opener, $this->propsOf($this->get('/costs/'.$this->unknown.'/correct?from_page='.$opener)->assertOk())['opener'] ?? null);
        }

        // A source that does not exist is NOT_FOUND; the parameters are allowlists, and no draft is read.
        foreach (['999999', 'abc', '0'] as $identifier) {
            $this->getJson('/costs/'.$identifier.'/correct')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        foreach (['from_page=requests', 'from_page=product', 'from_page=', 'draft=1', 'amount=1'] as $query) {
            $this->getJson('/costs/'.$this->corrected.'/correct?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_cost_history_gives_the_valuation_the_sources_and_the_corrections_of_one_product(): void
    {
        $this->as('Hendra');

        // The pen replayed with Rp150.000 (ADR-008 section 5): 10 at Rp100.000 and 10 at Rp150.000,
        // 3 out at Rp37.500, Rp212.500 for 17, an average of Rp12.500 rounded once.
        $props = $this->propsOf($this->get('/products/'.$this->pen.'/cost-history?from_page=worklist')->assertOk());
        $this->assertSame(['id' => $this->pen, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01', 'baseUnit' => 'pcs', 'quantityPrecision' => 0], $props['product'] ?? null);
        $this->assertSame(['status' => 'KNOWN', 'value' => '212500', 'averageCost' => '12500', 'onHand' => '17'], $props['valuation'] ?? null);
        $this->assertTrue($props['marked'] ?? null);
        $this->assertSame([
            ['evidenceId' => $this->corrected, 'movementId' => $this->movementOf($this->pen, 2), 'sourceType' => 'RECEIPT', 'recordedAt' => '2026-11-03T03:01:00.000000Z', 'quantity' => '10', 'cost' => ['status' => 'KNOWN', 'amount' => '150000'], 'zeroNote' => null, 'correctionNo' => 1, 'marked' => false],
            ['evidenceId' => $this->marked, 'movementId' => $this->movementOf($this->pen, 1), 'sourceType' => 'RECEIPT', 'recordedAt' => '2026-11-03T03:00:00.000000Z', 'quantity' => '10', 'cost' => ['status' => 'KNOWN', 'amount' => '100000'], 'zeroNote' => null, 'correctionNo' => 0, 'marked' => true],
        ], $props['evidence'] ?? null);
        $this->assertSame([['evidenceId' => $this->corrected, 'correctionNo' => 1, 'sourceType' => 'RECEIPT', 'sourceRecordedAt' => '2026-11-03T03:01:00.000000Z', ...array_diff_key(self::corrected(), ['correctionNo' => true])]], $props['corrections'] ?? null);
        $this->assertSame('worklist', $props['opener'] ?? null);

        // The book at an UNKNOWN cost has no value and no average (BR-COST-03); completed, it has both,
        // and its correction keeps the old value as UNKNOWN.
        $book = $this->propsOf($this->get('/products/'.$this->book.'/cost-history')->assertOk());
        $this->assertSame([['status' => 'UNKNOWN', 'value' => null, 'averageCost' => null, 'onHand' => '4'], false, []], [$book['valuation'] ?? null, $book['marked'] ?? null, $book['corrections'] ?? null]);
        $this->travel(1)->minutes();
        TokoContoh::correctCost($this->unknown, '8000', 'Nota ditemukan');
        $book = $this->propsOf($this->get('/products/'.$this->book.'/cost-history')->assertOk());
        $this->assertSame(['status' => 'KNOWN', 'value' => '8000', 'averageCost' => '2000', 'onHand' => '4'], $book['valuation'] ?? null);
        $evidence = (array) ((array) ($book['evidence'] ?? []))[0];
        $this->assertSame([['status' => 'KNOWN', 'amount' => '8000'], 1], [$evidence['cost'] ?? null, $evidence['correctionNo'] ?? null]);
        $correction = (array) ((array) ($book['corrections'] ?? []))[0];
        $this->assertSame([null, '8000', 'Nota ditemukan', '2026-11-03T03:06:00.000000Z'], [self::field($correction, 'oldAmount'), $correction['newAmount'] ?? null, $correction['reason'] ?? null, $correction['recordedAt'] ?? null]);

        // A product with no movement has no valuation yet.
        $blank = $this->propsOf($this->get('/products/'.TokoContoh::product('BARU-01', ['name' => 'Produk Baru', 'categoryId' => $this->category]).'/cost-history')->assertOk());
        $this->assertSame([null, false, [], []], [self::field($blank, 'valuation'), $blank['marked'] ?? null, $blank['evidence'] ?? null, $blank['corrections'] ?? null]);

        foreach (CostHistoryPageRequest::OPENERS as $opener) {
            $this->assertSame($opener, $this->propsOf($this->get('/products/'.$this->pen.'/cost-history?from_page='.$opener)->assertOk())['opener'] ?? null);
        }

        foreach (['999999', 'abc', '0'] as $identifier) {
            $this->getJson('/products/'.$identifier.'/cost-history')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        foreach (['from_page=movement', 'from_page=', 'product=1', 'after=x'] as $query) {
            $this->getJson('/products/'.$this->pen.'/cost-history?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_a_receiving_shows_the_corrected_cost_to_every_holder_of_cap_12_and_the_history_to_the_owner_alone(): void
    {
        // D-047, BR-INV-15, UXI-247: the cost now, the mark of a correction and the cost at
        // confirmation to OPERATIONS_ADMIN, never the reason, a zero's evidence or the history.
        $this->travel(1)->minutes();
        TokoContoh::correctCost($this->unknown, '0', 'Barang hadiah', 'Hadiah pemasok');
        $this->as('Agus');

        $page = $this->get('/stock/receivings/'.$this->penDocument)->assertOk();
        $line = (array) ((array) ($this->propsOf($page)['lines'] ?? []))[0];
        $this->assertSame([['status' => 'KNOWN', 'amount' => '150000'], true, ['status' => 'KNOWN', 'amount' => '140000']], [$line['cost'] ?? null, $line['corrected'] ?? null, $line['confirmedCost'] ?? null]);
        $this->assertSame([], array_values(array_intersect(array_keys($line), ['zeroNote', 'evidenceId', 'correctionNo', 'corrections'])));
        $this->assertStringNotContainsString('Nota susulan', (string) $page->getContent());

        $zero = $this->get('/stock/receivings/'.$this->bookDocument)->assertOk();
        $line = (array) ((array) ($this->propsOf($zero)['lines'] ?? []))[0];
        $this->assertSame([['status' => 'KNOWN', 'amount' => '0'], true, ['status' => 'UNKNOWN', 'amount' => null]], [$line['cost'] ?? null, $line['corrected'] ?? null, $line['confirmedCost'] ?? null]);
        $this->assertStringNotContainsString('Hadiah pemasok', (string) $zero->getContent());
        $this->assertStringNotContainsString('Barang hadiah', (string) $zero->getContent());

        // A movement's cost source is the Owner's alone (CAP-20).
        $this->assertArrayNotHasKey('costEvidence', $this->propsOf($this->get('/stock/movements/'.$this->movementOf($this->pen, 2))->assertOk()));

        // The Owner: the source for "Koreksi biaya", the number of its latest correction and the history.
        $this->as('Hendra');
        $owner = (array) ((array) ($this->propsOf($this->get('/stock/receivings/'.$this->penDocument)->assertOk())['lines'] ?? []))[0];
        $this->assertSame([$this->corrected, 1, null, [self::corrected()]], [$owner['evidenceId'] ?? null, $owner['correctionNo'] ?? null, self::field($owner, 'zeroNote'), $owner['corrections'] ?? null]);
        $zeroLine = (array) ((array) ($this->propsOf($this->get('/stock/receivings/'.$this->bookDocument)->assertOk())['lines'] ?? []))[0];
        $this->assertSame(['Hadiah pemasok', 'Barang hadiah'], [$zeroLine['zeroNote'] ?? null, ((array) ((array) ($zeroLine['corrections'] ?? []))[0])['reason'] ?? null]);
        $this->assertSame(['evidenceId' => $this->corrected, 'status' => 'KNOWN', 'total' => '150000', 'zeroNote' => null, 'corrections' => 1], $this->propsOf($this->get('/stock/movements/'.$this->movementOf($this->pen, 2))->assertOk())['costEvidence'] ?? null);
    }

    /**
     * The Owner's correction of the pen's second receipt as its history gives it.
     *
     * @return array{correctionNo: int, oldAmount: string, newAmount: string, zeroNote: null, reason: string, recordedAt: string, actorDisplayName: string}
     */
    private static function corrected(): array
    {
        return ['correctionNo' => 1, 'oldAmount' => '140000', 'newAmount' => '150000', 'zeroNote' => null, 'reason' => 'Nota susulan', 'recordedAt' => '2026-11-03T03:04:00.000000Z', 'actorDisplayName' => 'Hendra'];
    }

    /**
     * A field that must be present, whatever its value, null included.
     *
     * @param  array<array-key, mixed>  $values
     */
    private static function field(array $values, string $key): mixed
    {
        self::assertArrayHasKey($key, $values);

        return $values[$key];
    }

    /**
     * The worklist of RP-503 for this query string, as the Owner reads it.
     *
     * @return array{rows: list<array<array-key, mixed>>, nextCursor: mixed, previousCursor: mixed}
     */
    private function worklist(string $query): array
    {
        $worklist = (array) ($this->propsOf($this->get(self::WORKLIST.($query === '' ? '' : '?'.$query))->assertOk())['worklist'] ?? []);
        $rows = [];

        foreach ((array) ($worklist['rows'] ?? []) as $row) {
            $rows[] = (array) $row;
        }

        return ['rows' => $rows, 'nextCursor' => $worklist['nextCursor'] ?? null, 'previousCursor' => $worklist['previousCursor'] ?? null];
    }

    /**
     * The movement of a product at this place of its sequence.
     */
    private function movementOf(int $product, int $sequence): int
    {
        return self::number($this->watcher()->table('stock_movements')->where('product_id', $product)->where('product_sequence', $sequence)->value('id'));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
