<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\StockState;
use App\Support\Numbers\Quantity;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * Stock attention, RD-02 and the badge of RD-03 (AC-022-01, AC-022-02, AC-022-05; INVENTORY
 * section 10, BR-INV-40 to BR-INV-43; ARC-22; ROUTE_CONTRACTS RD-02, RD-03; INTERACTION_CONTRACTS
 * sections 3.2 and 3.3; EXECUTION_CONTEXT section 10, a read; suites STOCK01, INV01, AUTH02;
 * TASK-022 scope item 3), as L2 tests of the thresholds on real PostgreSQL and L4 tests of the
 * reads: with a minimum of 5, on hand 6 is Normal, 5 and 1 Menipis, 0 Habis; a minimum of 0 has
 * no Menipis; a new product with stock 0 is Habis; the badge and the list come from one
 * definition and agree, and with the Domain's StockState; the payload of each role; a cashier
 * refused and without a badge; the parameters as allowlists; keyset paging and the filters.
 */
final class StockAttentionTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $category = 0;

    private int $otherCategory = 0;

    /** @var array<string, int> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02T03:00:00Z');
        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category('Alat Tulis');
        $this->otherCategory = TokoContoh::category('Kertas');

        // Minimum 5: on hand 6, 5, 1 and 0; minimum 0 with stock 3; and a new product.
        foreach (['ATT-6' => ['6', '5'], 'ATT-5' => ['5', '5'], 'ATT-1' => ['1', '5'], 'ATT-0' => [null, '5'], 'ATT-M0' => ['3', '0'], 'ATT-NEW' => [null, '0']] as $sku => [$stock, $minimum]) {
            $this->products[$sku] = TokoContoh::product($sku, ['name' => 'Produk '.$sku, 'categoryId' => $sku === 'ATT-NEW' ? $this->otherCategory : $this->category, 'minimumStock' => $minimum]);

            if ($stock !== null) {
                TokoContoh::stock($this->products[$sku], $stock);
            }
        }

        // An inactive product with no stock is never counted (INVENTORY section 10).
        $inactive = TokoContoh::product('ATT-OFF', ['name' => 'Produk ATT-OFF', 'categoryId' => $this->category, 'minimumStock' => '5']);
        $this->as('Hendra');
        $this->postJson('/commands/product.deactivate', $this->envelope(['productId' => $inactive], ['operation' => 'product.deactivate', 'expected' => ['productVersion' => 1]]))->assertStatus(201);
    }

    public function test_the_thresholds_of_inventory_section_10_hold_and_the_badge_agrees_with_the_list(): void
    {
        $this->as('Agus');
        $list = $this->getJson('/data/stock?perPage=100')->assertOk();
        $states = [];

        foreach ((array) $list->json('rows') as $row) {
            $values = (array) $row;
            $sku = self::text($values['sku'] ?? null);
            $states[$sku] = $values['state'] ?? null;

            // The Domain's StockState says the same of each row.
            $given = $values['quantityPrecision'] ?? null;
            $precision = is_int($given) ? $given : 0;
            $this->assertSame(StockState::of(Quantity::of(self::text($values['onHand'] ?? null), $precision), Quantity::of(self::text($values['minimum'] ?? null), $precision))->value, $values['state'] ?? null, $sku);
        }

        $this->assertSame(['ATT-0' => 'OUT', 'ATT-1' => 'LOW', 'ATT-5' => 'LOW', 'ATT-6' => 'NORMAL', 'ATT-M0' => 'NORMAL', 'ATT-NEW' => 'OUT'], self::bySku($states));
        $this->assertSame([2, 2], [$list->json('outCount'), $list->json('lowCount')]);
        $this->assertSame('2026-11-02T03:00:00.000000Z', $list->json('asOf'));

        // The badge of RD-03 is the same number: Habis and Menipis.
        $this->getJson('/data/shell')->assertOk()->assertJsonPath('stockAttention', 4);

        // A receipt that brings 5 back to 6 makes it Normal, in both at once.
        TokoContoh::stock($this->products['ATT-5'], '1');
        $this->as('Agus');
        $this->assertSame([2, 1], [$this->getJson('/data/stock')->json('outCount'), $this->getJson('/data/stock')->json('lowCount')]);
        $this->getJson('/data/shell')->assertOk()->assertJsonPath('stockAttention', 3);
        $this->assertSame(['NORMAL'], array_column((array) $this->getJson('/data/stock?q=ATT-5')->json('rows'), 'state'));
    }

    public function test_each_role_gets_its_payload_and_a_cashier_neither_the_list_nor_the_badge(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $answer = $this->getJson('/data/stock')->assertOk();
            $this->assertJsonChannel($answer, 'data.stock', $actor, $secrets);
            $this->assertStringContainsString('no-store', (string) $answer->headers->get('Cache-Control'));
            $this->getJson('/data/shell')->assertOk()->assertJsonPath('stockAttention', 4);
        }

        $this->as('Rina');
        $this->getJson('/data/stock')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson('/data/shell')->assertOk()->assertJsonPath('stockAttention', null);

        $this->browse(null);
        $this->getJson('/data/stock')->assertStatus(401);
    }

    public function test_the_filters_and_the_keyset_pages(): void
    {
        $this->as('Agus');

        $this->assertSame(['ATT-0', 'ATT-NEW'], $this->skus($this->getJson('/data/stock?state=OUT')));
        $this->assertSame(['ATT-1', 'ATT-5'], $this->skus($this->getJson('/data/stock?state=LOW')));
        $this->assertSame(['ATT-NEW'], $this->skus($this->getJson('/data/stock?state=OUT&category='.$this->otherCategory)));
        $this->assertSame(['ATT-0', 'ATT-1', 'ATT-5', 'ATT-6', 'ATT-M0'], $this->skus($this->getJson('/data/stock?category='.$this->category)));
        $this->assertSame(['ATT-M0'], $this->skus($this->getJson('/data/stock?q=att-m')));
        $this->assertSame(['ATT-6'], $this->skus($this->getJson('/data/stock?q='.urlencode('Produk ATT-6'))));

        // A scanned code finds its product by its active barcode.
        $code = 'ATT'.str_repeat('7', 5);
        TokoContoh::barcode($this->products['ATT-1'], $code);
        $this->as('Agus');
        $this->assertSame(['ATT-1'], $this->skus($this->getJson('/data/stock?q='.$code)));

        // Name order, then identifier, two at a time; the counts stay those of every product.
        $seen = [];
        $after = null;

        do {
            $page = $this->getJson('/data/stock?perPage=2'.($after === null ? '' : '&after='.self::text($after)))->assertOk();
            $this->assertSame([2, 2], [$page->json('outCount'), $page->json('lowCount')]);
            $seen = [...$seen, ...$this->skus($page)];
            $after = $page->json('nextCursor');
        } while (is_string($after));

        $this->assertSame(['ATT-0', 'ATT-1', 'ATT-5', 'ATT-6', 'ATT-M0', 'ATT-NEW'], $seen);
    }

    public function test_the_parameters_are_allowlists(): void
    {
        $this->as('Agus');

        foreach (['state=HABIS', 'category=0', 'category=abc', 'perPage=0', 'perPage=101', 'after=***', 'page=2', 'sort=name', 'q='.str_repeat('a', 129)] as $query) {
            $this->getJson('/data/stock?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // A cursor that is not the list's is refused, never read as another position.
        foreach (['[7]', '[1,"Produk"]', '["Produk",0]', '"Produk"'] as $position) {
            $this->getJson('/data/stock?after='.rtrim(strtr(base64_encode($position), '+/', '-_'), '='))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->json('GET', '/data/stock', ['q' => 'ATT'])->assertStatus(422);

        // An empty answer is a list, never an error, and has no next page.
        $this->getJson('/data/stock?q=tidak-ada')->assertOk()->assertJsonPath('rows', [])->assertJsonPath('nextCursor', null)->assertJsonPath('outCount', 2);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<string>
     */
    private function skus(TestResponse $response): array
    {
        return array_values(array_map(static fn (mixed $row): string => self::text(((array) $row)['sku'] ?? null), (array) $response->assertOk()->json('rows')));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $states
     * @return array<string, mixed>
     */
    private static function bySku(array $states): array
    {
        ksort($states, SORT_STRING);

        return $states;
    }
}
