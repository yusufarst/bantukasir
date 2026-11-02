<?php

namespace Tests\Feature\Inventory;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-404 to RP-406 and RD-01 in the context `receiving` (AC-022-03, AC-022-04, AC-022-05;
 * ROUTE_CONTRACTS RP-404 to RP-406, RD-01; INTERACTION_CONTRACTS sections 3.1 and 3.2, RC-07;
 * BR-INV-14, BR-INV-15, BR-INV-17; D-033, D-047, D-069 (a); CAP-11, CAP-12; EXECUTION_CONTEXT
 * section 10, a route and a read; suites INV01, AUTH01, AUTH02; TASK-022 scope item 1), as L4
 * tests of the pages' answers: each page's props against the allowlists per kind of account,
 * a cashier refused; the documents newest first, keyset-paged both ways, with their filters as
 * allowlists; no draft read from the server; OPERATIONS_ADMIN sees the purchase cost of every
 * document, never a zero's reason, an average, a value or a margin; RC-07 leads to RP-406.
 */
final class ReceivingPagesTest extends DatabaseTestCase
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
        $this->paper = TokoContoh::product('KRT-01', ['name' => 'Kertas HVS', 'categoryId' => $category, 'minimumStock' => '2']);

        // The Owner's document; the Operations admin's, with an unknown cost; the Owner's zero.
        $this->documents[] = TokoContoh::receive([['productId' => $this->pen, 'quantity' => '3', 'cost' => ['status' => 'KNOWN', 'amount' => '30000']]], 'NOTA-1');
        $this->travel(1)->minutes();
        $this->as('Agus');
        $this->documents[] = $this->receiveAs([['productId' => $this->paper, 'quantity' => '2', 'cost' => ['status' => 'UNKNOWN']]], 'NOTA-2', '2026-10-30');
        $this->travel(1)->minutes();
        $this->documents[] = TokoContoh::receive([['productId' => $this->pen, 'quantity' => '1', 'cost' => ['status' => 'KNOWN', 'amount' => '0', 'zeroEvidence' => 'Sampel gratis']]]);
    }

    public function test_each_kind_of_account_of_cap_11_opens_each_page_with_only_its_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);

            $this->assertPageChannel($this->get('/stock/receivings')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/stock/receivings/new')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/stock/receivings/'.$this->documents[0])->assertOk(), $actor, $secrets);
            $this->assertJsonChannel($this->getJson('/data/products/search?context=receiving&q=PEN')->assertOk(), 'data.products.search.receiving', $actor, $secrets);
            $this->assertJsonChannel($this->getJson('/data/products/search?context=stock&q=PEN')->assertOk(), 'data.products.search', $actor, $secrets);
        }

        $this->as('Rina');

        foreach (['/stock/receivings', '/stock/receivings/new', '/stock/receivings/'.$this->documents[0]] as $path) {
            $page = $this->get($path)->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $path);
        }

        $this->getJson('/data/products/search?context=receiving&q=PEN')->assertStatus(403);
    }

    public function test_the_new_receiving_is_an_empty_draft_and_only_the_owner_may_enter_a_zero(): void
    {
        foreach (['Hendra' => true, 'Agus' => false, 'Dewi' => false] as $person => $zero) {
            $this->as($person);
            $props = $this->propsOf($this->get('/stock/receivings/new')->assertOk());

            $this->assertSame([false, $zero], [$props['postingFrozen'] ?? null, $props['zeroAllowed'] ?? null], $person);
            $this->assertSame(['Alat Tulis'], array_column((array) ($props['categories'] ?? []), 'name'));
        }

        // No draft is read from the server (D-069 (a)): the page takes no parameter.
        $this->as('Agus');
        $this->getJson('/stock/receivings/new?draft=1')->assertStatus(422);
    }

    public function test_the_list_is_newest_first_with_its_filters_and_keyset_pages(): void
    {
        $this->as('Agus');

        $this->assertSame([$this->documents[2], $this->documents[1], $this->documents[0]], $this->ids('/stock/receivings'));
        $first = (array) (((array) ($this->propsOf($this->get('/stock/receivings'))['receivings'] ?? []))['rows'] ?? [])[2];
        $this->assertSame([$this->documents[0], '2026-11-02T03:00:00.000000Z', 'NOTA-1', 1, 'Hendra'], [$first['id'], $first['confirmedAt'], $first['reference'], $first['lineCount'], $first['actorDisplayName']]);

        $this->assertSame([$this->documents[1]], $this->ids('/stock/receivings?q=nota-2'));
        $this->assertSame([$this->documents[1]], $this->ids('/stock/receivings?q='.urlencode('kertas')));
        $this->assertSame([$this->documents[2], $this->documents[0]], $this->ids('/stock/receivings?q=PEN-01'));
        $this->assertSame([], $this->ids('/stock/receivings?q='.urlencode('100%')));
        $this->assertSame(3, count($this->ids('/stock/receivings?from=2026-11-02&to=2026-11-02')));
        $this->assertSame([], $this->ids('/stock/receivings?from=2026-11-03'));

        // Two pages of one, and back.
        $page = (array) ($this->propsOf($this->get('/stock/receivings?perPage=2'))['receivings'] ?? []);
        $this->assertSame([$this->documents[2], $this->documents[1]], array_column((array) $page['rows'], 'id'));
        $this->assertNull($page['previousCursor']);
        $next = (array) ($this->propsOf($this->get('/stock/receivings?perPage=2&after='.self::text($page['nextCursor'] ?? null)))['receivings'] ?? []);
        $this->assertSame([$this->documents[0]], array_column((array) $next['rows'], 'id'));
        $this->assertNull($next['nextCursor']);
        $back = (array) ($this->propsOf($this->get('/stock/receivings?perPage=2&before='.self::text($next['previousCursor'] ?? null)))['receivings'] ?? []);
        $this->assertSame([$this->documents[2], $this->documents[1]], array_column((array) $back['rows'], 'id'));

        $refused = $this->getJson('/stock/receivings?from=2026-11-03&to=2026-11-01')->assertStatus(422);
        $this->assertSame([['key' => 'ux.validation.date_order', 'params' => []]], $refused->json('fields.to'));

        foreach (['from=2026-13-01', 'q='.str_repeat('a', 129), 'perPage=0', 'after=x&before=y', 'status=draft', 'before=***'] as $query) {
            $this->getJson('/stock/receivings?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_detail_shows_the_purchase_cost_to_operations_and_a_zeros_reason_to_the_owner_alone(): void
    {
        $this->as('Agus');

        $props = $this->propsOf($this->get('/stock/receivings/'.$this->documents[1])->assertOk());
        $this->assertSame(['id' => $this->documents[1], 'confirmedAt' => '2026-11-02T03:01:00.000000Z', 'reference' => 'NOTA-2', 'documentDate' => '2026-10-30', 'lineCount' => 1, 'actorDisplayName' => 'Agus'], $props['document'] ?? null);
        $line = (array) ((array) ($props['lines'] ?? []))[0];
        $this->assertSame([1, $this->paper, '2', ['status' => 'UNKNOWN', 'amount' => null]], [$line['lineNo'], ((array) $line['product'])['id'] ?? null, $line['quantity'], $line['cost']]);
        $this->assertIsInt($line['movementId']);

        // The Owner's document: Operations sees its purchase cost (BR-INV-14), and of a zero
        // the amount, never its reason.
        $this->assertSame(['status' => 'KNOWN', 'amount' => '30000'], ((array) ((array) ($this->propsOf($this->get('/stock/receivings/'.$this->documents[0]))['lines'] ?? []))[0])['cost'] ?? null);
        $zero = $this->get('/stock/receivings/'.$this->documents[2])->assertOk();
        $this->assertSame(['status' => 'KNOWN', 'amount' => '0'], ((array) ((array) ($this->propsOf($zero)['lines'] ?? []))[0])['cost'] ?? null);
        $this->assertStringNotContainsString('Sampel gratis', (string) $zero->getContent());

        $this->as('Hendra');
        $owner = (array) ((array) ($this->propsOf($this->get('/stock/receivings/'.$this->documents[2]))['lines'] ?? []))[0];
        $this->assertSame('Sampel gratis', $owner['zeroNote'] ?? null);

        foreach (['999999', 'abc', '0'] as $identifier) {
            $this->getJson('/stock/receivings/'.$identifier)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }
    }

    public function test_rc_07_leads_to_the_detail_of_its_document_and_the_receiving_context_gives_each_version(): void
    {
        $this->as('Agus');

        $row = (array) ((array) $this->getJson('/data/products/search?context=receiving&mode=exact&q=PEN-01')->assertOk()->json('rows'))[0];
        $this->assertSame([$this->pen, 1, true], [$row['id'], $row['version'], $row['active']]);
        $this->assertArrayNotHasKey('version', (array) ((array) $this->getJson('/data/products/search?context=stock&q=PEN-01')->json('rows'))[0]);

        $answer = $this->postJson('/commands/receiving.confirm', $this->envelope(
            ['reference' => null, 'documentDate' => null, 'lines' => [['productId' => $this->pen, 'quantity' => '2', 'cost' => ['status' => 'KNOWN', 'amount' => '20000']]]],
            ['operation' => 'receiving.confirm', 'expected' => ['products' => [['productId' => $this->pen, 'version' => 1]]]],
        ))->assertStatus(201);

        $id = $answer->json('result.id');
        $location = self::text($answer->json('result.location'));
        $this->assertSame('/stock/receivings/'.(is_int($id) ? (string) $id : ''), $location);
        $this->assertSame(['status' => 'KNOWN', 'amount' => '20000'], ((array) ((array) ($this->propsOf($this->get($location)->assertOk())['lines'] ?? []))[0])['cost'] ?? null);
    }

    /**
     * A receiving confirmed through RC-07 by the account signed in now: its document's key.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function receiveAs(array $lines, ?string $reference, ?string $documentDate): int
    {
        $products = [];

        foreach ($lines as $line) {
            $id = is_int($line['productId'] ?? null) ? $line['productId'] : 0;
            $products[$id] = ['productId' => $id, 'version' => 1];
        }

        $answer = $this->postJson('/commands/receiving.confirm', $this->envelope(
            ['reference' => $reference, 'documentDate' => $documentDate, 'lines' => $lines],
            ['operation' => 'receiving.confirm', 'expected' => ['products' => array_values($products)]],
        ))->assertStatus(201);

        $id = $answer->json('result.id');

        return is_int($id) ? $id : 0;
    }

    /**
     * The documents of a page of RP-404, in order.
     *
     * @return list<mixed>
     */
    private function ids(string $path): array
    {
        $receivings = (array) ($this->propsOf($this->get($path)->assertOk())['receivings'] ?? []);

        return array_values(array_column((array) ($receivings['rows'] ?? []), 'id'));
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
