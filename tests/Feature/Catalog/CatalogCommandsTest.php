<?php

namespace Tests\Feature\Catalog;

use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-17 to RC-24 through their routes (AC-015-01, AC-015-02, AC-015-04;
 * INTERACTION_CONTRACTS section 5; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2; BR-CAT-01 to BR-CAT-15; SCH-14; EXECUTION_CONTEXT section 10, a
 * command; suites CMD01, AUTH03 and SEARCH02; OPEN_ITEMS T-27): each command's
 * commit, audit event, durable result and repeat; its refusals, which write
 * nothing; its envelope and its fields; the roles and the combined account, also
 * when a role changes before the command; the failure after every write step; and
 * no route that changes a price outside RC-17 and RC-18.
 */
final class CatalogCommandsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** The tables that a refused command leaves as they were. */
    private const array FACTS = ['categories', 'products', 'selling_price_revisions', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
    }

    public function test_a_product_is_created_with_its_first_revision_and_no_stock_audited(): void
    {
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $envelope = $this->productEnvelope(['sku' => 'Pen-'.$person, 'brand' => 'Merek Contoh', 'baseUnit' => 'kg', 'quantityPrecision' => 3, 'sellingPrice' => '12500', 'minimumStock' => '1.5']);

            $response = $this->post17($envelope)->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $id = self::number($response->json('result.id'));
            $commandId = self::number($response->json('commandId'));
            $this->assertSame(['product', '/products/'.$id], [$response->json('result.type'), $response->json('result.location')]);

            $observer = $this->watcher();
            $row = (array) $observer->table('products')->where('id', $id)->sole();
            $this->assertSame(['Pen-'.$person, 'Pulpen Hitam', $this->category, 'Merek Contoh', 'kg', 3, '1.500', true, 1, $commandId, null], [$row['sku'], $row['name'], $row['category_id'], $row['brand'], $row['base_unit'], $row['quantity_precision'], $row['minimum_stock'], $row['active'], $row['version'], $row['created_command_id'], $row['photo_file_id']]);

            // BR-CAT-13: the first revision, in force, with its actor's frozen name.
            $revision = (array) $observer->table('selling_price_revisions')->where('product_id', $id)->sole();
            $this->assertSame([12500, $this->people[$person]['id'], $person, $commandId, $revision['id']], [$revision['amount'], $revision['actor_user_id'], $revision['actor_display_name'], $revision['command_id'], $row['current_price_revision_id']]);

            $event = (array) $observer->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['BUSINESS', 'PRODUCT_CREATE', 'USER', $this->people[$person]['id'], $person, 'product', $id, $id], [$event['category'], $event['action'], $event['actor_kind'], $event['actor_user_id'], $event['actor_display_name'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
            $this->assertSame(['categoryId' => $this->category, 'name' => 'Pulpen Hitam', 'revisionId' => $revision['id'], 'sellingPrice' => '12500', 'sku' => 'Pen-'.$person], self::dataOf($event['change_data']));
            $this->assertSame('COMMITTED', $this->receiptsOf($envelope)[0]['outcome']);

            // A repeat returns the original, and writes nothing.
            $before = $this->facts();
            $this->post17($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.id', $id);
            $this->assertSame($before, $this->facts());
        }
    }

    public function test_a_sku_is_kept_as_typed_trimmed_of_its_outer_whitespace_and_refused_when_taken_in_any_letter_case(): void
    {
        $this->as('Agus');

        // OPEN_ITEMS T-27: the framework's trimming removes a superset of
        // [[:space:]], no-break spaces included (SCH-14); between letters a space
        // and a no-break space stay, and so does the letter case.
        foreach (["  Ab-01\u{00A0}" => 'Ab-01', "\u{2009}\u{FEFF}Cd-02\u{3000}" => 'Cd-02', 'Ef 03' => 'Ef 03', "Gh\u{00A0}04" => "Gh\u{00A0}04", '007' => '007', 'ÉCLAIR' => 'ÉCLAIR'] as $typed => $stored) {
            $id = self::number($this->post17($this->productEnvelope(['sku' => (string) $typed]))->assertStatus(201)->json('result.id'));
            $this->assertSame($stored, $this->watcher()->table('products')->where('id', $id)->value('sku'));
        }

        $before = $this->facts();

        foreach (['AB-01', 'ab-01', "\u{00A0}aB-01 ", 'éclair', 'Éclair'] as $taken) {
            $response = $this->post17($this->productEnvelope(['sku' => $taken]))->assertStatus(422);
            $response->assertJsonPath('code', 'DUPLICATE_SKU')->assertJsonPath('messageKey', 'ux.state.duplicate_code.sku');
            $this->assertSame('ux.state.duplicate_code.sku', self::firstKey($response, 'payload.sku'), $taken);
        }

        $this->assertSame($before, $this->facts());

        // These are other SKUs for the database (SCH-14).
        foreach (['7', 'ECLAIR', 'Ab 01'] as $other) {
            $this->post17($this->productEnvelope(['sku' => $other]))->assertStatus(201);
        }
    }

    public function test_the_fields_are_validated_with_their_keys_before_any_fact(): void
    {
        $this->as('Agus');
        $before = $this->facts();
        $cases = [
            'payload.sku' => [
                [['sku' => ''], 'ux.validation.required'],
                [['sku' => null], 'ux.validation.required'],
                [['sku' => str_repeat('s', 65)], 'ux.validation.sku'],
                [['sku' => "AB\u{0001}C"], 'ux.validation.sku'],
                [['sku' => "AB\nC"], 'ux.validation.sku'],
                [['sku' => "AB\u{0085}C"], 'ux.validation.sku'],
                [['sku' => 7], 'ux.validation.sku'],
            ],
            'payload.name' => [
                [['name' => ''], 'ux.validation.required'],
                [['name' => str_repeat('n', 201)], 'ux.validation.text_max'],
            ],
            'payload.brand' => [[['brand' => str_repeat('b', 101)], 'ux.validation.text_max']],
            'payload.baseUnit' => [
                [['baseUnit' => ''], 'ux.validation.required'],
                [['baseUnit' => str_repeat('u', 33)], 'ux.validation.text_max'],
            ],
            'payload.categoryId' => [
                [['categoryId' => null], 'ux.validation.required'],
                [['categoryId' => 0], 'ux.validation.choose_one'],
            ],
            'payload.quantityPrecision' => [
                [['quantityPrecision' => null], 'ux.validation.required'],
                [['quantityPrecision' => 4], 'ux.validation.choose_one'],
                [['quantityPrecision' => -1], 'ux.validation.choose_one'],
            ],
            'payload.sellingPrice' => [
                [['sellingPrice' => null], 'ux.validation.required'],
                [['sellingPrice' => 7500], 'ux.validation.money'],
                [['sellingPrice' => '7500.5'], 'ux.validation.money'],
                [['sellingPrice' => '7.500'], 'ux.validation.money'],
                [['sellingPrice' => ' 7500'], 'ux.validation.money'],
                [['sellingPrice' => '-1'], 'ux.validation.money_min'],
                [['sellingPrice' => '1000000000000'], 'ux.validation.money_max'],
            ],
            'payload.minimumStock' => [
                [['minimumStock' => null], 'ux.validation.required'],
                [['minimumStock' => '-1'], 'ux.validation.min_stock'],
                [['minimumStock' => 5], 'ux.validation.min_stock'],
                [['minimumStock' => '1.5'], 'ux.validation.quantity_whole'],
                [['minimumStock' => '1.25', 'quantityPrecision' => 1], 'ux.validation.quantity_decimals'],
                [['minimumStock' => '10000000000'], 'ux.validation.quantity_max'],
            ],
        ];

        foreach ($cases as $path => $values) {
            foreach ($values as [$payload, $key]) {
                $response = $this->post17($this->productEnvelope($payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
                $this->assertSame($key, self::firstKey($response, $path), $path.' '.(string) json_encode($payload));
            }
        }

        // A control character in a text, a text that looks like a number, and an
        // unknown field: refused, never trimmed (TR-VAL-01, TR-VAL-05, TR-VAL-06).
        foreach ([['name' => "Pulpen\u{0007}"], ['brand' => "Merek\nBaru"], ['baseUnit' => "pcs\u{001B}"], ['categoryId' => (string) $this->category], ['quantityPrecision' => '0'], ['warna' => 'merah'], ['barcode' => ['token' => '8991234567890']]] as $payload) {
            $this->post17($this->productEnvelope($payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_a_category_that_is_missing_or_inactive_is_refused_and_nothing_is_written(): void
    {
        $this->as('Agus');
        $inactive = TokoContoh::category('Lama');
        $this->postCommand('category.deactivate', ['categoryId' => $inactive], ['categoryVersion' => 1])->assertStatus(201);
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $before = $this->facts();

        $refused = $this->post17($this->productEnvelope(['categoryId' => $inactive]))->assertStatus(422)->assertJsonPath('code', 'CATEGORY_INACTIVE')->assertJsonPath('messageKey', 'ux.state.inactive.category');
        $this->assertSame('ux.state.inactive.category', self::firstKey($refused, 'payload.categoryId'));
        $this->post17($this->productEnvelope(['categoryId' => 999999]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        $this->postCommand('product.update', $this->editPayload($product, ['categoryId' => $inactive]), ['productVersion' => 1])->assertStatus(422)->assertJsonPath('code', 'CATEGORY_INACTIVE');
        $this->postCommand('product.update', $this->editPayload($product, ['categoryId' => 999999]), ['productVersion' => 1])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame($before, $this->facts());
    }

    public function test_a_product_in_a_category_deactivated_later_keeps_it_and_stays_editable(): void
    {
        // BR-CAT-09: a deactivation changes no product, and a category it already
        // has is not newly chosen.
        $this->as('Agus');
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $snapshot = $this->snapshot('products');

        $this->postCommand('category.deactivate', ['categoryId' => $this->category], ['categoryVersion' => 1])->assertStatus(201);
        $this->assertSame($snapshot, $this->snapshot('products'));

        $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru']), ['productVersion' => 1])->assertStatus(201);
        $this->assertSame([$this->category, 'Pulpen Biru'], array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['category_id', 'name'])));
    }

    public function test_an_edit_applies_its_fields_audited_and_a_price_change_appends_a_revision(): void
    {
        $this->as('Agus');
        $other = TokoContoh::category('Buku');
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $first = $this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id');

        // The fields without the price: no revision, PRODUCT_EDIT with their names.
        $response = $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru', 'categoryId' => $other, 'brand' => 'Merek', 'minimumStock' => '3']), ['productVersion' => 1])->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'ops');
        $this->assertSame('/products/'.$product, $response->json('result.location'));
        $row = (array) $this->watcher()->table('products')->where('id', $product)->sole();
        $this->assertSame(['PEN-01', 'Pulpen Biru', $other, 'Merek', '3.000', 2, $first], [$row['sku'], $row['name'], $row['category_id'], $row['brand'], $row['minimum_stock'], $row['version'], $row['current_price_revision_id']]);
        $this->assertSame(1, $this->watcher()->table('selling_price_revisions')->where('product_id', $product)->count());
        $this->assertSame([['PRODUCT_EDIT', ['changed' => ['name', 'categoryId', 'brand', 'minimumStock']]]], $this->eventsOf($response));

        // The price: a new revision in force, the old one kept, both events.
        $kept = $this->snapshot('selling_price_revisions');
        $response = $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru', 'categoryId' => $other, 'brand' => 'Merek', 'minimumStock' => '3', 'sellingPrice' => '8000']), ['productVersion' => 2])->assertStatus(201);
        $current = (array) $this->watcher()->table('selling_price_revisions')->where('product_id', $product)->orderByDesc('id')->first();
        $this->assertSame([8000, $this->people['Agus']['id'], 'Agus', $response->json('commandId')], [$current['amount'], $current['actor_user_id'], $current['actor_display_name'], $current['command_id']]);
        $this->assertSame($current['id'], $this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id'));
        $this->assertSame($kept, array_slice($this->snapshot('selling_price_revisions'), 0, count($kept)));
        $this->assertSame([
            ['PRODUCT_EDIT', ['changed' => ['sellingPrice']]],
            ['PRICE_CHANGE', ['from' => '7500', 'previousRevisionId' => $first, 'revisionId' => $current['id'], 'to' => '8000']],
        ], $this->eventsOf($response));

        // The unit and the precision change before any movement (RACE-05), under
        // the stronger lock, and the minimum follows the new precision.
        $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru', 'categoryId' => $other, 'brand' => 'Merek', 'baseUnit' => 'kg', 'quantityPrecision' => 2, 'minimumStock' => '3.25', 'sellingPrice' => '8000']), ['productVersion' => 3])->assertStatus(201);
        $this->assertSame(['kg', 2, '3.250', 4], array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['base_unit', 'quantity_precision', 'minimum_stock', 'version'])));

        // A precision the minimum does not fit is refused at the minimum.
        $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru', 'categoryId' => $other, 'brand' => 'Merek', 'baseUnit' => 'kg', 'quantityPrecision' => 0, 'minimumStock' => '3.25', 'sellingPrice' => '8000']), ['productVersion' => 4])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        // A brand removed is null; a request that changes nothing is refused.
        $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru', 'categoryId' => $other, 'brand' => null, 'baseUnit' => 'kg', 'quantityPrecision' => 2, 'minimumStock' => '3.25', 'sellingPrice' => '8000']), ['productVersion' => 4])->assertStatus(201);
        $this->assertNull($this->watcher()->table('products')->where('id', $product)->value('brand'));
        $before = $this->facts();
        $this->postCommand('product.update', $this->editPayload($product, ['name' => 'Pulpen Biru', 'categoryId' => $other, 'baseUnit' => 'kg', 'quantityPrecision' => 2, 'minimumStock' => '3.25', 'sellingPrice' => '8000']), ['productVersion' => 5])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame($before, $this->facts());
    }

    public function test_the_sku_cannot_be_edited_and_no_route_changes_a_price_outside_rc_17_and_rc_18(): void
    {
        // AC-015-02: RC-18 takes no SKU; the lifecycle and category commands take
        // no price; the routes that reach the revisions are these two.
        $this->as('Hendra');
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $before = $this->facts();

        $this->postCommand('product.update', [...$this->editPayload($product, ['name' => 'Baru']), 'sku' => 'LAIN'], ['productVersion' => 1])->assertStatus(422);
        $this->postCommand('product.deactivate', ['productId' => $product, 'sellingPrice' => '1'], ['productVersion' => 1])->assertStatus(422);
        $this->postCommand('product.reactivate', ['productId' => $product, 'sellingPrice' => '1'], ['productVersion' => 1])->assertStatus(422);
        $this->postCommand('category.rename', ['categoryId' => $this->category, 'name' => 'Baru', 'sellingPrice' => '1'], ['categoryVersion' => 1])->assertStatus(422);
        $this->assertSame($before, $this->facts());

        $writers = [];

        foreach (glob(base_path('app/Actions/*/*.php')) ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), 'products->insertRevision(')) {
                $writers[] = basename($file);
            }
        }

        sort($writers);
        $this->assertSame(['CreateProduct.php', 'UpdateProduct.php'], $writers);
    }

    public function test_deactivation_and_reactivation_of_a_product_keep_it_readable_and_delete_nothing(): void
    {
        $this->as('Agus');
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $products = $this->watcher()->table('products')->count();

        $response = $this->postCommand('product.deactivate', ['productId' => $product], ['productVersion' => 1])->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'ops');
        $this->assertSame('/products/'.$product, $response->json('result.location'));
        $this->assertSame([false, 2], array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['active', 'version'])));
        $this->assertSame([['PRODUCT_DEACTIVATE', []]], $this->eventsOf($response));

        // Refused while inactive, and readable (BR-CAT-08): stale for the old
        // version, and nothing to do for the current one.
        $this->postCommand('product.deactivate', ['productId' => $product], ['productVersion' => 1])->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->postCommand('product.deactivate', ['productId' => $product], ['productVersion' => 2])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->getJson('/data/products/search?context=catalog&q=PEN-01')->assertOk()->assertJsonPath('rows.0.id', $product)->assertJsonPath('rows.0.active', false);

        $response = $this->postCommand('product.reactivate', ['productId' => $product], ['productVersion' => 2])->assertStatus(201);
        $this->assertSame([true, 3], array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['active', 'version'])));
        $this->assertSame([['PRODUCT_REACTIVATE', []]], $this->eventsOf($response));
        $this->postCommand('product.reactivate', ['productId' => $product], ['productVersion' => 3])->assertStatus(422);

        // A product in an inactive category may come back: this assigns none.
        $this->postCommand('category.deactivate', ['categoryId' => $this->category], ['categoryVersion' => 1])->assertStatus(201);
        $this->postCommand('product.deactivate', ['productId' => $product], ['productVersion' => 3])->assertStatus(201);
        $this->postCommand('product.reactivate', ['productId' => $product], ['productVersion' => 4])->assertStatus(201);

        $this->assertSame($products, $this->watcher()->table('products')->count());
        $this->postCommand('product.deactivate', ['productId' => 999999], ['productVersion' => 1])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->postCommand('product.update', $this->editPayload(999999), ['productVersion' => 1])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_a_stale_version_is_refused_for_every_command_that_expects_one(): void
    {
        $this->as('Agus');
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $before = $this->facts();

        foreach ([
            ['product.update', $this->editPayload($product, ['name' => 'Baru']), ['productVersion' => 2]],
            ['product.deactivate', ['productId' => $product], ['productVersion' => 2]],
            ['category.rename', ['categoryId' => $this->category, 'name' => 'Baru'], ['categoryVersion' => 2]],
            ['category.deactivate', ['categoryId' => $this->category], ['categoryVersion' => 2]],
        ] as [$operation, $payload, $expected]) {
            $response = $this->postCommand($operation, $payload, $expected)->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION')->assertJsonPath('messageKey', 'ux.state.stale_version');
            $this->assertJsonChannel($response, 'commands.refused', 'ops');
        }

        // A version that is no JSON whole number, or missing, is refused as a shape.
        $this->postCommand('product.deactivate', ['productId' => $product], ['productVersion' => '1'])->assertStatus(422);
        $this->postCommand('product.deactivate', ['productId' => $product], [])->assertStatus(422);
        $this->postCommand('category.deactivate', ['categoryId' => (string) $this->category], ['categoryVersion' => 1])->assertStatus(422);
        $this->assertSame($before, $this->facts());
    }

    public function test_a_category_is_created_renamed_deactivated_and_reactivated_audited(): void
    {
        $this->as('Dewi');

        $response = $this->postCommand('category.create', ['name' => '  Kertas '], [])->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'combined');
        $id = self::number($response->json('result.id'));
        $this->assertSame(['category', '/categories'], [$response->json('result.type'), $response->json('result.location')]);
        $this->assertSame(['Kertas', true, 1], array_values((array) $this->watcher()->table('categories')->where('id', $id)->first(['name', 'active', 'version'])));
        $this->assertSame([['CATEGORY_CREATE', ['name' => 'Kertas']]], $this->eventsOf($response));

        // Names are not unique (SCHEMA section 5.6).
        $this->postCommand('category.create', ['name' => 'Kertas'], [])->assertStatus(201);

        $response = $this->postCommand('category.rename', ['categoryId' => $id, 'name' => 'Kertas HVS'], ['categoryVersion' => 1])->assertStatus(201);
        $this->assertSame(['Kertas HVS', 2], array_values((array) $this->watcher()->table('categories')->where('id', $id)->first(['name', 'version'])));
        $this->assertSame([['CATEGORY_RENAME', ['from' => 'Kertas', 'to' => 'Kertas HVS']]], $this->eventsOf($response));

        // The same name changes nothing, and is refused.
        $this->postCommand('category.rename', ['categoryId' => $id, 'name' => 'Kertas HVS'], ['categoryVersion' => 2])->assertStatus(422);

        $response = $this->postCommand('category.deactivate', ['categoryId' => $id], ['categoryVersion' => 2])->assertStatus(201);
        $this->assertSame([false, 3], array_values((array) $this->watcher()->table('categories')->where('id', $id)->first(['active', 'version'])));
        $this->assertSame([['CATEGORY_DEACTIVATE', []]], $this->eventsOf($response));
        $this->postCommand('category.deactivate', ['categoryId' => $id], ['categoryVersion' => 3])->assertStatus(422);

        // An inactive category may still be renamed.
        $this->postCommand('category.rename', ['categoryId' => $id, 'name' => 'Kertas A4'], ['categoryVersion' => 3])->assertStatus(201);

        $response = $this->postCommand('category.reactivate', ['categoryId' => $id], ['categoryVersion' => 4])->assertStatus(201);
        $this->assertSame([true, 5], array_values((array) $this->watcher()->table('categories')->where('id', $id)->first(['active', 'version'])));
        $this->assertSame([['CATEGORY_REACTIVATE', []]], $this->eventsOf($response));
        $this->postCommand('category.reactivate', ['categoryId' => $id], ['categoryVersion' => 5])->assertStatus(422);

        // The fields and a guessed identifier.
        $this->as('Agus');
        $this->assertSame('ux.validation.text_max', self::firstKey($this->postCommand('category.create', ['name' => str_repeat('k', 101)], [])->assertStatus(422), 'payload.name'));
        $this->assertSame('ux.validation.required', self::firstKey($this->postCommand('category.create', ['name' => '  '], [])->assertStatus(422), 'payload.name'));
        $this->postCommand('category.create', ['name' => "Kertas\nBaru"], [])->assertStatus(422);
        $this->postCommand('category.rename', ['categoryId' => 999999, 'name' => 'Lain'], ['categoryVersion' => 1])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->postCommand('category.reactivate', ['categoryId' => 999999], ['categoryVersion' => 1])->assertStatus(404);
    }

    public function test_a_cashier_is_refused_every_command_before_it_runs(): void
    {
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $this->as('Rina');
        $before = $this->facts();

        foreach ([
            ['product.create', ['sku' => 'X'], []],
            ['product.update', ['productId' => $product], ['productVersion' => 1]],
            ['product.deactivate', ['productId' => $product], ['productVersion' => 1]],
            ['product.reactivate', ['productId' => $product], ['productVersion' => 1]],
            ['category.create', ['name' => 'X'], []],
            ['category.rename', ['categoryId' => $this->category, 'name' => 'X'], ['categoryVersion' => 1]],
            ['category.deactivate', ['categoryId' => $this->category], ['categoryVersion' => 1]],
            ['category.reactivate', ['categoryId' => $this->category], ['categoryVersion' => 1]],
        ] as [$operation, $payload, $expected]) {
            $this->postCommand($operation, $payload, $expected)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', $operation)->where('actor_user_id', $this->people['Rina']['id'])->count(), $operation);
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_a_status_check_recovers_the_original_result(): void
    {
        // EXECUTION_CONTEXT section 10, recovery of the original result (RC-50;
        // ADR-007 section 3): rebuilt through the Action that wrote it.
        $this->as('Agus');
        $product = $this->productEnvelope();
        $productId = self::number($this->post17($product)->assertStatus(201)->json('result.id'));
        $category = $this->commandEnvelope('category.create', ['name' => 'Tinta'], []);
        $categoryId = self::number($this->postJson('/commands/category.create', $category)->assertStatus(201)->json('result.id'));

        $response = $this->check($product)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $productId)->assertJsonPath('result.location', '/products/'.$productId);
        $this->assertJsonChannel($response, 'commands.resolve', 'ops');
        $this->check($category)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $categoryId)->assertJsonPath('result.location', '/categories');
    }

    public function test_a_result_is_rebuilt_under_the_viewers_current_authorization(): void
    {
        // ADR-007 section 3: the combined account loses its Operations role; its
        // repeat of a committed command is refused FORBIDDEN by the gate first.
        $staff = TokoContoh::staff('Tono', ['cashier', 'operationsAdmin']);
        $this->people['Tono'] = ['id' => $staff['id'], 'email' => $staff['email'], 'password' => (string) $staff['password']];
        $this->as('Tono');
        $envelope = $this->commandEnvelope('category.create', ['name' => 'Tinta'], []);
        $this->postJson('/commands/category.create', $envelope)->assertStatus(201);

        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $this->postJson('/commands/user.update', $this->commandEnvelope('user.update', ['userId' => $staff['id'], 'displayName' => 'Tono', 'email' => $staff['email'], 'roles' => ['cashier']], ['userVersion' => $this->userVersion($staff['id'])]))->assertStatus(201);

        // The role change ended the sessions: sign in again.
        unset($this->browsers['Tono']);
        $this->as('Tono');
        $this->postJson('/commands/category.create', $envelope)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // TS-21: each command's write steps, as its Action lists them.
        $this->as('Agus');
        $product = TokoContoh::product('PEN-01', ['categoryId' => $this->category]);
        $name = fn (): array => $this->editPayload($product, ['name' => 'Gagal']);
        $inactive = TokoContoh::category('Nonaktif');
        $this->postCommand('category.deactivate', ['categoryId' => $inactive], ['categoryVersion' => 1])->assertStatus(201);
        $hidden = TokoContoh::product('PEN-02', ['categoryId' => $this->category]);
        $this->postCommand('product.deactivate', ['productId' => $hidden], ['productVersion' => 1])->assertStatus(201);

        $productVersion = fn (int $id): array => ['productVersion' => $this->watcher()->table('products')->where('id', $id)->value('version')];
        $categoryVersion = fn (int $id): array => ['categoryVersion' => $this->watcher()->table('categories')->where('id', $id)->value('version')];
        $cases = [
            'product.create' => [5, fn (): array => $this->productPayload(['sku' => 'BARU-01']), static fn (): array => []],
            'product.update' => [3, $name, fn (): array => $productVersion($product)],
            'product.update with a price' => [5, fn (): array => $this->editPayload($product, ['sellingPrice' => '9000']), fn (): array => $productVersion($product)],
            'product.deactivate' => [3, static fn (): array => ['productId' => $product], fn (): array => $productVersion($product)],
            'product.reactivate' => [3, static fn (): array => ['productId' => $hidden], fn (): array => $productVersion($hidden)],
            'category.create' => [3, static fn (): array => ['name' => 'Gagal'], static fn (): array => []],
            'category.rename' => [3, fn (): array => ['categoryId' => $this->category, 'name' => 'Gagal'], fn (): array => $categoryVersion($this->category)],
            'category.deactivate' => [3, fn (): array => ['categoryId' => $this->category], fn (): array => $categoryVersion($this->category)],
            'category.reactivate' => [3, static fn (): array => ['categoryId' => $inactive], fn (): array => $categoryVersion($inactive)],
        ];

        foreach ($cases as $case => [$steps, $payload, $expected]) {
            $operation = explode(' ', $case)[0];

            for ($step = 1; $step <= $steps; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $before = $this->factsAndRows();

                $this->postCommand($operation, $payload(), $expected())->assertStatus(500);

                $this->assertSame($before, $this->factsAndRows(), sprintf('%s after write step %d', $case, $step));
                $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', $operation)->orderByDesc('id')->value('outcome'), $case);
            }

            // The step after the last is no step of the command: it commits.
            $this->app->instance(CommandPoints::class, new FailurePoints($steps + 1));
            $committed = $this->postCommand($operation, $payload(), $expected())->assertStatus(201);
            $this->assertSame(1, $this->watcher()->table('command_results')->where('command_id', $committed->json('commandId'))->count(), $case);
            $this->app->instance(CommandPoints::class, new FailurePoints);
        }
    }

    public function test_an_envelope_is_refused_as_every_command_refuses_one(): void
    {
        // The envelope's refusals, and the receipt that records them (G-25).
        $this->as('Agus');
        $envelope = $this->commandEnvelope('category.create', ['name' => 'Kertas'], []);
        $before = $this->facts();

        foreach ([
            ['version' => 2],
            ['version' => '1'],
            ['expected' => ['categoryVersion' => 1.5]],
            ['payload' => ['name' => 'Kertas', 'warna' => 'biru']],
            ['tambahan' => 'x'],
        ] as $changes) {
            $this->postJson('/commands/category.create', [...$envelope, 'idempotencyKey' => (string) Str::uuid(), ...$changes])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // An operation other than the path's, no usable intent: no receipt at all.
        $this->postJson('/commands/category.create', [...$envelope, 'operation' => 'category.rename'])->assertStatus(422);
        $this->postJson('/commands/category.create', [...$envelope, 'sourceIntentId' => 'bukan'])->assertStatus(422);
        $this->assertSame($before, $this->facts());

        // Another payload under the same key is held, never run.
        $this->postJson('/commands/category.create', $envelope)->assertStatus(201);
        $this->postJson('/commands/category.create', [...$envelope, 'payload' => ['name' => 'Lain']])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        // An earlier epoch is refused before any fact.
        $stale = $this->commandEnvelope('category.create', ['name' => 'Lama'], []);
        $stale['recoveryEpoch'] = str_repeat('ab', 32);
        $this->postJson('/commands/category.create', $stale)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(0, $this->watcher()->table('categories')->where('name', 'Lama')->count());
    }

    /**
     * An envelope of RC-17.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function productEnvelope(array $payload = []): array
    {
        return $this->commandEnvelope('product.create', $this->productPayload($payload), []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function productPayload(array $payload = []): array
    {
        return [
            'sku' => 'PEN-'.Str::random(6),
            'name' => 'Pulpen Hitam',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            ...$payload,
        ];
    }

    /**
     * RC-18's payload for the fields of TokoContoh::product(), with $payload
     * replacing some.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function editPayload(int $product, array $payload = []): array
    {
        return [
            'productId' => $product,
            'name' => 'Pulpen Hitam',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            ...$payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function commandEnvelope(string $operation, array $payload, array $expected): array
    {
        return [
            'operation' => $operation,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => $expected === [] ? (object) [] : $expected,
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function post17(array $envelope): TestResponse
    {
        return $this->postJson('/commands/product.create', $envelope);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function postCommand(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->commandEnvelope($operation, $payload, $expected));
    }

    /**
     * The audit events of a committed answer, in order: the action and its data.
     *
     * @param  TestResponse<Response>  $response
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function eventsOf(TestResponse $response): array
    {
        $events = [];

        foreach ($this->watcher()->table('audit_events')->where('command_id', $response->json('commandId'))->orderBy('id')->get() as $event) {
            $events[] = [$this->readText($event->action), self::dataOf($event->change_data)];
        }

        return $events;
    }

    /**
     * An event's data with its keys sorted, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);
        ksort($data);

        return $data;
    }

    private function userVersion(int $id): int
    {
        return self::number($this->watcher()->table('users')->where('id', $id)->value('version'));
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * The facts, with the rows that a command changes.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $rows = [];

        foreach (['categories', 'products'] as $table) {
            $rows[$table] = $this->watcher()->table($table)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        }

        return ['facts' => $this->facts(), 'rows' => $rows];
    }

    /**
     * The first message key of a field of a refusal.
     *
     * @param  TestResponse<Response>  $response
     */
    private static function firstKey(TestResponse $response, string $path): ?string
    {
        $fields = $response->json('fields');
        $entries = is_array($fields) ? ($fields[$path] ?? null) : null;
        $first = is_array($entries) ? ($entries[0] ?? null) : null;
        $key = is_array($first) ? ($first['key'] ?? null) : null;

        return is_string($key) ? $key : null;
    }
}
