<?php

namespace Tests\Feature\Inventory;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-410 and RD-01 in the context `adjustment` (AC-024-01, AC-024-04; ROUTE_CONTRACTS RP-410,
 * RD-01, RP-401, RP-402; INTERACTION_CONTRACTS sections 3.1 and 3.2, RC-09; NAVIGATION_CONTRACTS
 * UXP-410; BR-INV-31; CAP-16; EXECUTION_CONTEXT section 10, a route and a read; suites INV03,
 * AUTH01, AUTH02; TASK-024 scope item 3), as L4 tests of the page's answers: its props against the
 * allowlist, the Owner's alone, every other kind of account refused; the first line's product read
 * as RD-01 reads it, never a cost; its parameters as allowlists; no draft read from the server;
 * the freeze said on RP-401, RP-402 and RP-410 for "Sesuaikan stok" (UXI-217, UXI-221).
 */
final class AdjustmentPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $pen = 0;

    private int $old = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $category = TokoContoh::category('Alat Tulis');
        $this->pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'categoryId' => $category, 'minimumStock' => '5']);
        $this->old = TokoContoh::product('LAMA-01', ['name' => 'Pensil Lama', 'categoryId' => $category]);
        TokoContoh::stock($this->pen, '20', '200000');
        $this->as('Hendra');
        $this->command('product.deactivate', ['productId' => $this->old], ['productVersion' => 1])->assertStatus(201);
    }

    public function test_only_the_owner_opens_the_page_and_its_read_with_only_their_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $this->as('Hendra');

        $this->assertPageChannel($this->get('/stock/adjustments/new?product='.$this->pen.'&from_page=stock')->assertOk(), 'owner', $secrets);
        $this->assertJsonChannel($this->getJson('/data/products/search?context=adjustment&q=PEN')->assertOk(), 'data.products.search.adjustment', 'owner', $secrets);

        // AC-024-01: every other role and the combined account are refused, the page and its read.
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $page = $this->get('/stock/adjustments/new')->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $person);
            $this->getJson('/data/products/search?context=adjustment&q=PEN')->assertStatus(403);
        }

        $this->browse(null);
        $this->get('/stock/adjustments/new')->assertRedirect('/login');
    }

    public function test_the_page_is_a_draft_with_the_product_named_as_its_first_line_and_its_opener(): void
    {
        $this->as('Hendra');

        // No product: an empty draft; the freeze is off.
        $empty = $this->propsOf($this->get('/stock/adjustments/new')->assertOk());
        $this->assertSame([false, null, null], [$empty['postingFrozen'] ?? null, $empty['product'] ?? 'absent', $empty['opener'] ?? 'absent']);

        // The product as RD-01 in the context `adjustment` reads it: its version and its stock, never a cost.
        $props = $this->propsOf($this->get('/stock/adjustments/new?product='.$this->pen.'&from_page=history')->assertOk());
        $product = (array) ($props['product'] ?? []);
        $this->assertSame([$this->pen, 'Pulpen Hitam', 'PEN-01', 1, true, ['onHand' => '20', 'minimum' => '5', 'state' => 'NORMAL']], [$product['id'] ?? null, $product['name'] ?? null, $product['sku'] ?? null, $product['version'] ?? null, $product['active'] ?? null, $product['stock'] ?? null]);
        $this->assertSame('history', $props['opener'] ?? null);
        $search = (array) ($this->getJson('/data/products/search?context=adjustment&mode=exact&q=PEN-01')->assertOk()->json('rows.0') ?? []);
        $this->assertSame($search, $product);

        // An inactive product is given as it is, marked, for the page to refuse (UXS-32).
        $this->assertFalse(((array) ($this->propsOf($this->get('/stock/adjustments/new?product='.$this->old))['product'] ?? []))['active'] ?? null);

        // A product that does not exist is NOT_FOUND; parameters are allowlists; no draft is read.
        $this->getJson('/stock/adjustments/new?product=999999')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach (['product=abc', 'product=0', 'product=1,2', 'from_page=requests', 'from_page=', 'draft=1', 'q=PEN'] as $query) {
            $this->getJson('/stock/adjustments/new?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_stock_pages_and_the_page_say_whether_the_freeze_stops_an_adjustment(): void
    {
        // UXI-217, UXI-221, UXS-27; BR-INV-24.
        $this->as('Hendra');

        foreach (['/stock', '/stock/history?product='.$this->pen, '/stock/adjustments/new'] as $path) {
            $this->assertFalse($this->propsOf($this->get($path)->assertOk())['postingFrozen'] ?? null, $path);
        }

        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);

        foreach (['/stock', '/stock/history?product='.$this->pen, '/stock/adjustments/new'] as $path) {
            $this->assertTrue($this->propsOf($this->get($path)->assertOk())['postingFrozen'] ?? null, $path);
        }
    }

    /**
     * Another command with its envelope.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function command(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelope($payload, ['operation' => $operation, 'expected' => $expected]));
    }
}
