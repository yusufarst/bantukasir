<?php

namespace Tests\Feature\Sales;

use Illuminate\Database\Connection;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The Sales pages of TASK-031 (AC-031-05; ROUTE_CONTRACTS RP-201, RP-202, RP-403; NAVIGATION_CONTRACTS
 * UXP-201, UXP-202; INTERACTION_INVENTORY UXI-224; SECURITY_MODEL SEC-AZ-03, SEC-CH-01; BR-DOC-06; D-067;
 * EXECUTION_CONTEXT section 10, a route, a read and a page; suites AUTH01, AUTH02, SESSION10; TASK-031
 * section 7, L4): the list of Sales in the viewer's scope with its filters and keyset pages; one Sale in
 * scope with its frozen lines, its payment and its session, NOT_FOUND outside it, also with guessed keys;
 * a SALE_ISSUE's Sale on RP-403, a link only within the viewer's scope; Operations refused.
 */
final class SalesPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $product = 0;

    private int $laci1 = 0;

    private int $laci2 = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '5000']);
        TokoContoh::stock($this->product, '50');
        $this->laci1 = TokoContoh::register('LACI-1', 'Laci 1');
        $this->laci2 = TokoContoh::register('LACI-2', 'Laci 2');
    }

    public function test_the_list_holds_the_viewers_scope_newest_first_with_its_filters_and_pages(): void
    {
        [$rina1, $rina2, $dewi] = $this->sales();

        // A cashier sees her own Sales alone, newest first (BR-DOC-06; REQ-SALE-12).
        $this->as('Rina');
        $page = $this->get('/sales')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('sales/Index', $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'cashier', $this->secrets());
        $props = $this->propsOf($page);
        $rows = $this->rowsOf($props);
        $this->assertSame([$rina2, $rina1], array_column($rows, 'id'));
        $this->assertSame(['10000', 'BANK_TRANSFER', 'Rina'], [$rows[0]['total'] ?? null, $rows[0]['method'] ?? null, $rows[0]['cashier'] ?? null]);
        $this->assertSame([[], []], [$props['registers'] ?? null, $props['cashiers'] ?? null]);

        // The number, exactly; a day of Asia/Jakarta; the page's size and its keyset pages both ways.
        $this->assertSame([$rina1], array_column($this->rowsOf($this->propsOf($this->get('/sales?number='.$this->numberOf($rina1))->assertOk())), 'id'));
        $this->assertSame([], $this->rowsOf($this->propsOf($this->get('/sales?number=20261111')->assertOk())));
        $this->assertSame([$rina1], array_column($this->rowsOf($this->propsOf($this->get('/sales?from=2026-11-11&to=2026-11-11')->assertOk())), 'id'));
        $first = (array) ($this->propsOf($this->get('/sales?perPage=1')->assertOk())['sales'] ?? []);
        $this->assertSame([$rina2], array_column($this->rowsOfPage($first), 'id'));
        $second = (array) ($this->propsOf($this->get('/sales?perPage=1&after='.self::text($first['nextCursor'] ?? null))->assertOk())['sales'] ?? []);
        $this->assertSame([[$rina1], null], [array_column($this->rowsOfPage($second), 'id'), $second['nextCursor'] ?? null]);
        $back = (array) ($this->propsOf($this->get('/sales?perPage=1&before='.self::text($second['previousCursor'] ?? null))->assertOk())['sales'] ?? []);
        $this->assertSame([$rina2], array_column($this->rowsOfPage($back), 'id'));

        // The Owner's filters are his alone; a cursor not of this list, a day after another and an
        // unknown parameter are refused.
        foreach (['cashier='.$this->people['Rina']['id'], 'register='.$this->laci1, 'from=2026-11-12&to=2026-11-11', 'after=abc', 'q=PEN', 'after=x&before=y'] as $query) {
            $this->getJson('/sales?'.$query)->assertStatus(422);
        }

        // The Owner sees every Sale, with the registers and the people of his filters.
        $this->as('Hendra');
        $owner = $this->get('/sales')->assertOk();
        $this->assertPageChannel($owner, 'owner', $this->secrets());
        $this->assertSame([$dewi, $rina2, $rina1], array_column($this->rowsOf($this->propsOf($owner)), 'id'));
        $this->assertSame([$dewi], array_column($this->rowsOf($this->propsOf($this->get('/sales?cashier='.$this->people['Dewi']['id'])->assertOk())), 'id'));
        $this->assertSame([$dewi], array_column($this->rowsOf($this->propsOf($this->get('/sales?register='.$this->laci2)->assertOk())), 'id'));

        $this->as('Dewi');
        $this->assertPageChannel($this->get('/sales')->assertOk(), 'combined', $this->secrets());
        $this->assertSame([$dewi], array_column($this->rowsOf($this->propsOf($this->get('/sales')->assertOk())), 'id'));

        // Operations has no Sales (SEC-AZ-03).
        $this->as('Agus');
        $this->get('/sales')->assertStatus(403);
    }

    public function test_one_sale_in_scope_with_its_lines_payment_and_session_and_not_found_outside_it(): void
    {
        [$rina1, $rina2, $dewi] = $this->sales();

        foreach (['Rina' => [$rina2, 'cashier'], 'Hendra' => [$rina2, 'owner'], 'Dewi' => [$dewi, 'combined']] as $person => [$sale, $actor]) {
            $this->as($person);
            $page = $this->get('/sales/'.$sale)->assertOk();
            $this->assertSame('sales/Show', $this->inertiaPage($page)['component'] ?? null);
            $this->assertPageChannel($page, $actor, $this->secrets());
        }

        $this->as('Rina');
        $props = $this->propsOf($this->get('/sales/'.$rina2)->assertOk());
        $sale = (array) ($props['sale'] ?? []);
        $this->assertSame([$this->numberOf($rina2), 'Rina', null, '10000'], [$sale['number'] ?? null, $sale['cashier'] ?? null, $sale['customerName'] ?? null, $sale['total'] ?? null]);
        $this->assertSame([['lineNo' => '1', 'sku' => 'PEN-01', 'name' => 'Pulpen Hitam', 'unit' => 'pcs', 'quantity' => '2', 'unitPrice' => '5000', 'lineTotal' => '10000']], $props['lines'] ?? null);
        $this->assertSame(['method' => 'BANK_TRANSFER', 'tendered' => null, 'change' => null, 'reference' => 'TRF-9'], $props['payment'] ?? null);
        $this->assertSame(self::number($this->watcher()->table('sales')->where('id', $rina2)->value('cash_session_id')), $sale['sessionId'] ?? null);

        // Another cashier's Sale, a guessed one and Operations (AC-031-05; TR-ERR-03).
        foreach (['/sales/'.$dewi, '/sales/999999', '/sales/abc'] as $path) {
            $this->getJson($path)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->getJson('/sales/'.$rina1.'?from_page=1')->assertStatus(422);
        $this->as('Agus');
        $this->getJson('/sales/'.$rina1)->assertStatus(403);
    }

    public function test_a_sale_issue_names_its_sale_as_a_link_within_the_viewers_scope_and_as_text_outside_it(): void
    {
        // UXI-224; SEC-AZ-03: the Owner and the Sale's own combined account link to RP-202, Operations
        // reads the number alone.
        [, , $dewi] = $this->sales();
        $movement = TokoContoh::saleIssueOf($dewi);
        $number = $this->numberOf($dewi);

        foreach (['Hendra' => $dewi, 'Dewi' => $dewi, 'Agus' => null] as $person => $id) {
            $this->as($person);
            $read = (array) ($this->propsOf($this->get('/stock/movements/'.$movement)->assertOk())['movement'] ?? []);
            $this->assertSame(['kind' => 'SALE', 'id' => $id, 'at' => '2026-11-12T03:00:00.000000Z', 'number' => $number], $read['source'] ?? null, $person);
        }
    }

    /**
     * Three Sales: Rina's on 11 November, Rina's by transfer and Dewi's on 12 November (Asia/Jakarta).
     *
     * @return array{int, int, int}
     */
    private function sales(): array
    {
        $this->travelTo('2026-11-11T02:00:00.000000Z');
        $rina = TokoContoh::openSession('Rina', $this->laci1);
        $rina1 = TokoContoh::sell('Rina', $rina, [['productId' => $this->product, 'quantity' => '1']]);
        $this->travelTo('2026-11-12T02:00:00.000000Z');
        $rina2 = TokoContoh::sell('Rina', $rina, [['productId' => $this->product, 'quantity' => '2']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-9']);
        $this->travelTo('2026-11-12T03:00:00.000000Z');
        $dewi = TokoContoh::sell('Dewi', TokoContoh::openSession('Dewi', $this->laci2), [['productId' => $this->product, 'quantity' => '1']]);
        $this->travelTo('2026-11-12T04:00:00.000000Z');

        return [$rina1, $rina2, $dewi];
    }

    /**
     * @param  array<array-key, mixed>  $props
     * @return list<array<array-key, mixed>>
     */
    private function rowsOf(array $props): array
    {
        return $this->rowsOfPage((array) ($props['sales'] ?? []));
    }

    /**
     * @param  array<array-key, mixed>  $page
     * @return list<array<array-key, mixed>>
     */
    private function rowsOfPage(array $page): array
    {
        $rows = [];

        foreach ((array) ($page['rows'] ?? []) as $row) {
            $rows[] = (array) $row;
        }

        return $rows;
    }

    private function numberOf(int $sale): string
    {
        return self::text($this->watcher()->table('sales')->where('id', $sale)->value('sale_number'));
    }

    /**
     * @return list<string>
     */
    private function secrets(): array
    {
        return array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
