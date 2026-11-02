<?php

namespace Tests\Feature\Catalog;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-301 to RP-304 and RP-307 (AC-017-01, AC-017-03; ROUTE_CONTRACTS section 2;
 * INTERACTION_CONTRACTS section 3.2; SECURITY_MODEL SEC-CH-01; EXECUTION_CONTEXT section
 * 10, a route and a read; suites AUTH01, AUTH02; TASK-017), as L4 tests of the pages'
 * answers: each page's props against the allowlists, for each kind of account that may
 * open it; a cashier refused; the parameters as allowlists; a guessed identifier
 * NOT_FOUND; the price history and barcodes of a product, bounded; the categories that a
 * form offers; and no cost in any of them.
 */
final class CatalogPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $category = 0;

    private int $product = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category('Alat Tulis');
        $this->product = TokoContoh::product('PLP-001', ['categoryId' => $this->category, 'brand' => 'Merek A', 'minimumStock' => '5']);
        TokoContoh::barcode($this->product, 'PABRIK-AKTIF-1');
    }

    public function test_each_kind_of_account_of_cap_04_opens_each_page_with_only_its_allowlisted_props(): void
    {
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));

            $this->assertPageChannel($this->get('/products')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/products/new')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/products/'.$this->product)->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/products/'.$this->product.'/edit')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/categories')->assertOk(), $actor, $secrets);
        }
    }

    public function test_a_cashier_is_refused_each_page(): void
    {
        $this->as('Rina');

        foreach (['/products', '/products/new', '/products/'.$this->product, '/products/'.$this->product.'/edit', '/categories'] as $path) {
            $page = $this->get($path)->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $path);
        }
    }

    public function test_the_list_takes_its_filters_as_allowlists_and_gives_them_back(): void
    {
        $this->as('Agus');

        $filters = $this->propsOf($this->get('/products?q=pulpen&category='.$this->category.'&brand=Merek%20A&status=inactive&page=3')->assertOk())['filters'] ?? null;
        $this->assertSame(['q' => 'pulpen', 'category' => $this->category, 'brand' => 'Merek A', 'status' => 'inactive', 'page' => 3, 'import' => null], $filters);

        $none = $this->propsOf($this->get('/products')->assertOk())['filters'] ?? null;
        $this->assertSame(['q' => '', 'category' => null, 'brand' => '', 'status' => null, 'page' => 1, 'import' => null], $none);

        foreach (['status=lain', 'category=abc', 'category=0', 'page=0', 'page=abc', 'sort=name', 'q='.str_repeat('a', 129), 'brand='.str_repeat('b', 101)] as $query) {
            $this->getJson('/products?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->getJson('/categories?status=lain')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->getJson('/categories?sort=name')->assertStatus(422);
        $this->getJson('/products/new?other=1')->assertStatus(422);
        $this->getJson('/products/'.$this->product.'?other=1')->assertStatus(422);
    }

    public function test_a_guessed_identifier_is_not_found(): void
    {
        $this->as('Hendra');

        foreach (['999999', 'abc', '-1', '0'] as $identifier) {
            foreach (['', '/edit'] as $suffix) {
                $this->getJson('/products/'.$identifier.$suffix)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            }
        }
    }

    public function test_the_detail_shows_the_product_its_price_history_and_barcodes_active_first(): void
    {
        $this->as('Agus');

        $mapping = TokoContoh::barcode($this->product, 'PABRIK-PENSIUN-2');
        $this->as('Agus');
        TokoContoh::retireBarcode($mapping, 'MISASSIGNMENT');
        $this->as('Agus');

        $this->command('product.update', [
            'productId' => $this->product, 'name' => 'Pulpen Hitam', 'categoryId' => $this->category, 'brand' => 'Merek A', 'baseUnit' => 'pcs',
            'quantityPrecision' => 0, 'sellingPrice' => '8000', 'minimumStock' => '5',
        ], ['productVersion' => 1])->assertStatus(201);

        $props = $this->propsOf($this->get('/products/'.$this->product)->assertOk());
        $product = (array) ($props['product'] ?? []);

        $this->assertSame(['PLP-001', 'Alat Tulis', true, 'Merek A', 'pcs', 0, '5', true, 2, false], [
            $product['sku'] ?? null, $product['categoryName'] ?? null, $product['categoryActive'] ?? null, $product['brand'] ?? null, $product['baseUnit'] ?? null,
            $product['quantityPrecision'] ?? null, $product['minimumStock'] ?? null, $product['active'] ?? null, $product['version'] ?? null, $product['unitLocked'] ?? null,
        ]);
        $this->assertSame('8000', ((array) ($product['price'] ?? []))['amount'] ?? null);

        // The newest first, each with its time in UTC and the name frozen with it.
        $history = array_values((array) ($props['priceHistory'] ?? []));
        $this->assertCount(2, $history);
        $newest = (array) $history[0];
        $this->assertSame(['8000', 'Agus'], [$newest['amount'] ?? null, $newest['actorDisplayName'] ?? null]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', self::text($newest['startedAt'] ?? null));
        $this->assertSame('7500', ((array) $history[1])['amount'] ?? null);

        // The active one first; a retired one keeps its reason, and no action is offered by the server.
        $barcodes = array_values((array) ($props['barcodes'] ?? []));
        $this->assertSame([['PABRIK-AKTIF-1', 'MANUFACTURER', true, null], ['PABRIK-PENSIUN-2', 'MANUFACTURER', false, 'MISASSIGNMENT']], array_map(static function (mixed $row): array {
            $barcode = (array) $row;

            return [$barcode['token'] ?? null, $barcode['kind'] ?? null, $barcode['active'] ?? null, $barcode['reasonClass'] ?? null];
        }, $barcodes));
    }

    public function test_an_inactive_product_stays_visible_and_marked(): void
    {
        $this->as('Agus');
        $this->command('product.deactivate', ['productId' => $this->product], ['productVersion' => 1])->assertStatus(201);

        $product = (array) ($this->propsOf($this->get('/products/'.$this->product)->assertOk())['product'] ?? []);
        $this->assertFalse($product['active'] ?? null);
        $this->assertSame(2, $product['version'] ?? null);
    }

    public function test_the_detail_and_the_edit_show_the_unit_locked_once_the_product_has_a_movement(): void
    {
        // BR-CAT-07; TASK-017 finding F-2: Inventory says it, since TASK-021 (STK-13).
        $pages = ['/products/'.$this->product, '/products/'.$this->product.'/edit'];
        $this->as('Agus');

        foreach ($pages as $path) {
            $this->assertFalse(((array) ($this->propsOf($this->get($path)->assertOk())['product'] ?? []))['unitLocked'] ?? null, $path);
        }

        TokoContoh::stock($this->product, '3');
        $other = TokoContoh::product('PLP-002', ['categoryId' => $this->category]);
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);

            foreach ($pages as $path) {
                $response = $this->get($path)->assertOk();
                $this->assertPageChannel($response, $actor, $secrets);
                $product = (array) ($this->propsOf($response)['product'] ?? []);
                // A movement changes no field of its product, so its version stays.
                $this->assertSame([true, 1], [$product['unitLocked'] ?? null, $product['version'] ?? null], $path.' for '.$actor);
            }

            // Another product, with no movement, stays open.
            $this->assertFalse(((array) ($this->propsOf($this->get('/products/'.$other.'/edit')->assertOk())['product'] ?? []))['unitLocked'] ?? null, $actor);
        }
    }

    public function test_a_form_offers_the_active_categories_and_the_edit_keeps_the_one_the_product_has(): void
    {
        $this->as('Agus');
        TokoContoh::category('Kertas');
        $this->as('Agus');
        $this->command('category.deactivate', ['categoryId' => $this->category], ['categoryVersion' => 1])->assertStatus(201);

        $this->assertSame(['Kertas'], $this->categoryNames($this->get('/products/new')->assertOk()));
        $this->assertSame(['Alat Tulis', 'Kertas'], $this->categoryNames($this->get('/products/'.$this->product.'/edit')->assertOk()));
        $this->assertSame(['Alat Tulis', 'Kertas'], $this->categoryNames($this->get('/products')->assertOk()));
        $this->assertSame(['Alat Tulis'], $this->categoryNames($this->get('/categories?status=inactive')->assertOk()));
        $this->assertSame(['Kertas'], $this->categoryNames($this->get('/categories?status=active')->assertOk()));
        $this->assertSame(['Alat Tulis', 'Kertas'], $this->categoryNames($this->get('/categories')->assertOk()));

        $detail = (array) ($this->propsOf($this->get('/products/'.$this->product))['product'] ?? []);
        $this->assertFalse($detail['categoryActive'] ?? null);
    }

    public function test_the_code_a_working_page_hands_over_is_read_as_a_token(): void
    {
        $this->as('Agus');

        $this->assertSame('KODE-BARU-7', $this->propsOf($this->get('/products/new?code=KODE-BARU-7')->assertOk())['code'] ?? null);
        $this->assertNull($this->propsOf($this->get('/products/new')->assertOk())['code'] ?? null);
        $this->getJson('/products/new?code='.str_repeat('9', 129))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    /**
     * A command with its envelope.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function command(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelope($payload, ['operation' => $operation, 'expected' => $expected]));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<mixed>
     */
    private function categoryNames(TestResponse $response): array
    {
        return array_values(array_map(static fn (mixed $row): mixed => ((array) $row)['name'] ?? null, (array) ($this->propsOf($response)['categories'] ?? [])));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }
}
