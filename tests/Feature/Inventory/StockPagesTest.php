<?php

namespace Tests\Feature\Inventory;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-401 to RP-403 and the section "Stok" of RP-302 (AC-022-04, AC-022-05; ROUTE_CONTRACTS
 * RP-401 to RP-403; INTERACTION_CONTRACTS section 3.2; BR-INV-43, BR-INV-44; CAP-14, CAP-20;
 * EXECUTION_CONTEXT section 10, a route and a read; suites STOCK01, INV01, AUTH01, AUTH02;
 * TASK-022 scope items 2 and 4), as L4 tests of the pages' answers: each page's props against
 * the allowlists for each kind of account that may open it, a cashier refused; the history
 * newest first, in posting order within a product and otherwise by time, keyset-paged both
 * ways on the server, with its filters as allowlists; a movement with its source, and its cost
 * evidence for the Owner alone; a guessed identifier NOT_FOUND.
 */
final class StockPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $pen = 0;

    private int $paper = 0;

    /** @var list<int> */
    private array $documents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02T03:00:00Z');
        TokoContoh::shop();
        $this->setUpCommands();
        $category = TokoContoh::category('Alat Tulis');
        $this->pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'categoryId' => $category, 'minimumStock' => '5']);
        $this->paper = TokoContoh::product('KRT-01', ['name' => 'Kertas HVS', 'categoryId' => $category, 'baseUnit' => 'rim', 'minimumStock' => '2']);

        // Three documents a minute apart; the last holds a line of each product.
        $this->documents[] = TokoContoh::receive([['productId' => $this->pen, 'quantity' => '3', 'cost' => ['status' => 'KNOWN', 'amount' => '30000']]], 'NOTA-1');
        $this->travel(1)->minutes();
        $this->documents[] = TokoContoh::stock($this->paper, '2');
        $this->travel(1)->minutes();
        $this->documents[] = TokoContoh::receive([
            ['productId' => $this->pen, 'quantity' => '4', 'cost' => ['status' => 'KNOWN', 'amount' => '40000']],
            ['productId' => $this->paper, 'quantity' => '1', 'cost' => ['status' => 'UNKNOWN']],
        ]);
    }

    public function test_each_kind_of_account_of_cap_14_opens_each_page_with_only_its_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $movement = $this->movementOf($this->pen, 1);

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);

            $this->assertPageChannel($this->get('/stock')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/stock/history?product='.$this->pen.'&from_page=stock')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/stock/movements/'.$movement)->assertOk(), $actor, $secrets);
        }

        $this->as('Rina');

        foreach (['/stock', '/stock/history', '/stock/movements/'.$movement] as $path) {
            $page = $this->get($path)->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $path);
        }
    }

    public function test_the_stock_list_gives_its_first_page_and_its_filters_back(): void
    {
        $this->as('Agus');

        $props = $this->propsOf($this->get('/stock?q=pulpen&state=NORMAL&perPage=50')->assertOk());
        $this->assertSame(['q' => 'pulpen', 'state' => 'NORMAL', 'category' => null, 'perPage' => 50], $props['filters'] ?? null);
        $stock = (array) ($props['stock'] ?? []);
        $this->assertSame([['id' => $this->pen, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01', 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'onHand' => '7', 'minimum' => '5', 'state' => 'NORMAL']], $stock['rows'] ?? null);
        $this->assertSame([0, 0, null], [$stock['outCount'] ?? null, $stock['lowCount'] ?? null, $stock['nextCursor'] ?? null]);

        foreach (['state=lain', 'category=0', 'perPage=500', 'page=2', 'q='.str_repeat('a', 129)] as $query) {
            $this->getJson('/stock?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_history_is_newest_first_by_time_and_in_posting_order_within_a_product(): void
    {
        $this->as('Agus');

        $all = $this->history('/stock/history');
        $this->assertSame([[$this->paper, 2], [$this->pen, 2], [$this->paper, 1], [$this->pen, 1]], self::keys($all['rows']));
        $first = (array) ($all['rows'][3] ?? []);
        $this->assertSame(['RECEIPT', '3', 'pcs', 'Hendra'], [$first['type'], $first['quantity'], $first['baseUnit'], $first['actorDisplayName']]);
        $this->assertSame(['kind' => 'RECEIVING', 'id' => $this->documents[0], 'at' => '2026-11-02T03:00:00.000000Z'], $first['source']);
        $this->assertSame([null, null], [$all['nextCursor'], $all['previousCursor']]);

        $pen = $this->history('/stock/history?product='.$this->pen);
        $this->assertSame([[$this->pen, 2], [$this->pen, 1]], self::keys($pen['rows']));
        $this->assertSame([['id' => $this->pen, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01']], $this->propsOf($this->get('/stock/history?product='.$this->pen))['products'] ?? null);

        // The products of an adjustment, RC-09's location (TASK-024): each once, ascending, by
        // time; a repeated key counts once, and one that does not exist is NOT_FOUND.
        $both = $this->propsOf($this->get('/stock/history?product='.$this->paper.','.$this->pen.','.$this->paper));
        $this->assertSame(['products' => [min($this->pen, $this->paper), max($this->pen, $this->paper)], 'type' => null, 'from' => null, 'to' => null], $both['filters'] ?? null);
        $this->assertSame([[$this->paper, 2], [$this->pen, 2], [$this->paper, 1], [$this->pen, 1]], self::keys($this->history('/stock/history?product='.$this->pen.','.$this->paper)['rows']));
        $this->getJson('/stock/history?product='.$this->pen.',999999')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/stock/history?product='.implode(',', range(1, 201)))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->assertSame(4, count($this->history('/stock/history?type=RECEIPT')['rows']));
        $this->assertSame([], $this->history('/stock/history?type=MANUAL_ISSUE')['rows']);

        // Days of Asia/Jakarta: 03:00 UTC is 10:00 on 2 November there.
        $this->assertSame(4, count($this->history('/stock/history?from=2026-11-02&to=2026-11-02')['rows']));
        $this->assertSame([], $this->history('/stock/history?from=2026-11-03')['rows']);
        $this->assertSame([], $this->history('/stock/history?to=2026-11-01')['rows']);
    }

    public function test_the_history_pages_by_its_keyset_both_ways(): void
    {
        $this->as('Agus');

        foreach (['' => [[$this->paper, 2], [$this->pen, 2], [$this->paper, 1], [$this->pen, 1]], 'product='.$this->paper.'&' => [[$this->paper, 2], [$this->paper, 1]]] as $filter => $expected) {
            $forward = [];
            $pages = [];
            $cursor = null;

            do {
                $page = $this->history('/stock/history?'.$filter.'perPage=1'.($cursor === null ? '' : '&after='.self::text($cursor)));
                $pages[] = $page;
                $forward = [...$forward, ...self::keys($page['rows'])];
                $cursor = $page['nextCursor'];
            } while (is_string($cursor));

            $this->assertSame($expected, $forward, $filter);
            $this->assertNull($pages[0]['previousCursor']);

            // Back from the last page, newest first again, to the first.
            $backward = [];
            $cursor = $pages[count($pages) - 1]['previousCursor'];

            while (is_string($cursor)) {
                $page = $this->history('/stock/history?'.$filter.'perPage=1&before='.self::text($cursor));
                $backward = [...self::keys($page['rows']), ...$backward];
                $this->assertIsString($page['nextCursor']);
                $cursor = $page['previousCursor'];
            }

            $this->assertSame(array_slice($expected, 0, -1), $backward, $filter);
        }
    }

    public function test_the_history_takes_its_parameters_as_allowlists(): void
    {
        $this->as('Agus');

        $refused = $this->getJson('/stock/history?from=2026-11-03&to=2026-11-01')->assertStatus(422);
        $this->assertSame([['key' => 'ux.validation.date_order', 'params' => []]], $refused->json('fields.to'));

        foreach (['product=abc', 'product=0', 'product=1,', 'product=,1', 'product=1,0', 'product=1;2', 'type=SALE', 'from=2026-02-30', 'to=02-11-2026', 'perPage=0', 'after=x&before=y', 'from_page=lain', 'sort=time', 'after=***'] as $query) {
            $this->getJson('/stock/history?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // A cursor whose time is no instant of the calendar is refused, never a server error
        // (review finding R-02), both ways and with a product's own order.
        foreach (['["2026-13-45T99:99:99.000000Z",1]', '["0000-01-01T00:00:00.000000Z",1]', '["2026-02-29T00:00:00.000000Z",1]', '[0]'] as $position) {
            $cursor = rtrim(strtr(base64_encode($position), '+/', '-_'), '=');
            $this->getJson('/stock/history?after='.$cursor)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->getJson('/stock/history?before='.$cursor)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->getJson('/stock/history?product='.$this->pen.'&after='.rtrim(strtr(base64_encode('["2026-11-02T03:00:00.000000Z",1]'), '+/', '-_'), '='))->assertStatus(422);

        $this->getJson('/stock/history?product=999999')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_a_movement_shows_its_source_and_its_cost_evidence_to_the_owner_alone(): void
    {
        $movement = $this->movementOf($this->pen, 1);

        $this->as('Agus');
        $props = $this->propsOf($this->get('/stock/movements/'.$movement)->assertOk());
        $this->assertArrayNotHasKey('costEvidence', $props);
        $read = (array) ($props['movement'] ?? []);
        $this->assertSame(['RECEIPT', '3', 'pcs', 0, 'Hendra', 'NOTA-1', '2026-11-02T03:00:00.000000Z'], [$read['type'], $read['quantity'], $read['baseUnit'], $read['quantityPrecision'], $read['actorDisplayName'], $read['reference'], $read['recordedAt']]);
        $this->assertSame(['kind' => 'RECEIVING', 'id' => $this->documents[0], 'at' => '2026-11-02T03:00:00.000000Z'], $read['source']);
        $this->assertStringNotContainsString('30000', (string) json_encode($read), 'No cost in the movement of a staff account (CAP-14).');

        // The evidence as it is after the Owner's corrections, with how many there were (TASK-025).
        $this->as('Hendra');
        $unknown = $this->movementOf($this->paper, 2);
        $this->assertSame(['evidenceId' => TokoContoh::evidenceOf($movement), 'status' => 'KNOWN', 'total' => '30000', 'zeroNote' => null, 'corrections' => 0], $this->propsOf($this->get('/stock/movements/'.$movement)->assertOk())['costEvidence'] ?? null);
        $this->assertSame(['evidenceId' => TokoContoh::evidenceOf($unknown), 'status' => 'UNKNOWN', 'total' => null, 'zeroNote' => null, 'corrections' => 0], $this->propsOf($this->get('/stock/movements/'.$unknown)->assertOk())['costEvidence'] ?? null);

        foreach (['999999', 'abc', '0', '-1'] as $identifier) {
            $this->getJson('/stock/movements/'.$identifier)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->getJson('/stock/movements/'.$movement.'?other=1')->assertStatus(422);
    }

    public function test_the_product_page_shows_its_stock_to_each_kind_of_account_of_cap_14(): void
    {
        foreach (['Hendra', 'Agus', 'Dewi'] as $person) {
            $this->as($person);
            $this->assertSame(['onHand' => '7', 'minimum' => '5', 'state' => 'NORMAL'], $this->propsOf($this->get('/products/'.$this->pen)->assertOk())['stock'] ?? null, $person);
            $this->assertSame(['onHand' => '3', 'minimum' => '2', 'state' => 'NORMAL'], $this->propsOf($this->get('/products/'.$this->paper)->assertOk())['stock'] ?? null, $person);
        }
    }

    /**
     * The movement of a product at its posting sequence.
     */
    private function movementOf(int $product, int $sequence): int
    {
        $id = $this->observer()->table('stock_movements')->where('product_id', $product)->where('product_sequence', $sequence)->value('id');

        return is_int($id) ? $id : 0;
    }

    /**
     * The history of a page.
     *
     * @return array{rows: list<mixed>, nextCursor: mixed, previousCursor: mixed}
     */
    private function history(string $path): array
    {
        $history = (array) ($this->propsOf($this->get($path)->assertOk())['history'] ?? []);

        return ['rows' => array_values((array) ($history['rows'] ?? [])), 'nextCursor' => $history['nextCursor'] ?? null, 'previousCursor' => $history['previousCursor'] ?? null];
    }

    /**
     * Each row by its product and posting sequence.
     *
     * @param  list<mixed>  $rows
     * @return list<array{mixed, mixed}>
     */
    private static function keys(array $rows): array
    {
        return array_map(static fn (mixed $row): array => [((array) ((array) $row)['product'])['id'] ?? null, ((array) $row)['productSequence'] ?? null], $rows);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
