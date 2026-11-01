<?php

namespace Tests\Feature\Documents;

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
 * RC-47, RP-306 and RP-306P through their routes (AC-018-05; INTERACTION_CONTRACTS,
 * "RC-44 to RC-47"; DOCUMENT_CONTRACTS DC-05; BR-CAT-35, BR-CAT-36, BR-AUD-06; TR-HTTP-02;
 * suites SESSION08, AUTH01, CMD01; TASK-018 scope item 3): 1 to 100 labels of a mapping
 * active on the product; each request audited, creating no fact, repeated as the same
 * print request; the print view reads what the request recorded, a code, a name, a SKU and
 * a count and never a cost or a price, and writes nothing.
 */
final class LabelPrintTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PATH = '/commands/document.print_labels';

    /** The tables that a print request leaves as they were, but its identity, result and audit event. */
    private const array UNTOUCHED = ['products', 'selling_price_revisions', 'barcode_tokens', 'barcode_mappings', 'barcode_mapping_retirements', 'barcode_active_mappings', 'stored_files'];

    /** The tables that a refused print request leaves as they were. */
    private const array FACTS = ['command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private int $product = 0;

    private int $mapping = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'sellingPrice' => '7777']);
        $this->mapping = TokoContoh::barcode($this->product, 'KODE-AKTIF-1');
    }

    public function test_each_role_of_cap_08_records_a_print_request_audited_that_creates_no_fact(): void
    {
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $untouched = $this->counts2(self::UNTOUCHED);
            $envelope = $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 3]);

            $response = $this->postJson(self::PATH, $envelope)->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $commandId = self::number($response->json('commandId'));
            $location = '/products/'.$this->product.'/labels/print/'.$commandId;
            $this->assertSame(['label_print', $commandId, $location], [$response->json('result.type'), $response->json('result.id'), $response->json('result.location')]);

            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['BUSINESS', 'PRINT', $this->people[$person]['id'], 'barcode_mapping', $this->mapping, $this->product], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
            $this->assertSame(['barcode' => 'KODE-AKTIF-1', 'count' => 3, 'mappingId' => $this->mapping], self::dataOf($event['change_data']));

            // No business fact (BR-AUD-06), and the result holds references only.
            $this->assertSame($untouched, $this->counts2(self::UNTOUCHED));
            $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
            $this->assertSame(['label_print', $commandId, ['productId' => $this->product]], [$stored['result_type'], $stored['result_ref'], self::dataOf($stored['result_detail'])]);

            // A repeat is the same print request; a reprint is a new one, audited again.
            $before = $this->counts2(self::FACTS);
            $this->postJson(self::PATH, $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.location', $location);
            $this->assertSame($before, $this->counts2(self::FACTS));

            $again = $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 3]))->assertStatus(201);
            $this->assertNotSame($commandId, $again->json('commandId'));
            $this->assertSame(1, $this->watcher()->table('audit_events')->where('command_id', $again->json('commandId'))->where('action', 'PRINT')->count());
        }
    }

    public function test_one_to_one_hundred_labels_and_nothing_else_are_accepted(): void
    {
        $this->as('Agus');

        foreach ([1, 100] as $count) {
            $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => $count]))->assertStatus(201);
        }

        $before = $this->counts2(self::FACTS);

        foreach ([
            [0, 'ux.validation.quantity_positive'],
            [-1, 'ux.validation.quantity_positive'],
            [101, 'ux.validation.quantity_max'],
            [null, 'ux.validation.required'],
        ] as [$count, $key]) {
            $response = $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => $count]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame($key, self::firstKey($response, 'payload.count'), (string) json_encode($count));
        }

        // A text that looks like a number, a decimal, an unknown field and a missing one.
        foreach ([
            ['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => '5'],
            ['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1.5],
            ['productId' => (string) $this->product, 'mappingId' => $this->mapping, 'count' => 5],
            ['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 5, 'price' => '1'],
            ['productId' => $this->product, 'count' => 5],
        ] as $payload) {
            $this->postJson(self::PATH, $this->commandEnvelope($payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->counts2(self::FACTS));
    }

    public function test_a_mapping_no_longer_active_on_the_product_is_stale_and_a_guessed_one_is_not_found(): void
    {
        $this->as('Agus');
        $other = TokoContoh::product('PEN-02', ['categoryId' => $this->category]);
        $elsewhere = TokoContoh::barcode($other, 'KODE-LAIN-1');
        $retired = TokoContoh::barcode($this->product, 'KODE-LAMA-1');
        TokoContoh::retireBarcode($retired, 'OTHER');
        $before = $this->counts2(self::FACTS);

        foreach ([$retired, $elsewhere] as $mapping) {
            $response = $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $mapping, 'count' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
            $this->assertJsonChannel($response, 'commands.refused', 'ops');
        }

        $this->postJson(self::PATH, $this->commandEnvelope(['productId' => 999999, 'mappingId' => $this->mapping, 'count' => 1]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => 999999, 'count' => 1]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame($before, $this->counts2(self::FACTS));
    }

    public function test_the_print_view_shows_what_the_request_recorded_and_never_a_cost_and_writes_nothing(): void
    {
        // AC-018-05; TR-HTTP-02.
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $location = self::text($this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 7]))->assertStatus(201)->json('result.location'));
            $before = $this->counts2([...self::FACTS, ...self::UNTOUCHED]);

            $page = $this->get($location)->assertOk();
            $this->assertPageChannel($page, $actor, array_values(array_map(static fn (array $one): string => $one['password'], $this->people)));
            $this->assertSame('no-store, private', $page->headers->get('Cache-Control'));
            $props = $this->inertiaPage($page)['props'] ?? null;
            $this->assertIsArray($props);
            $this->assertSame(['name' => 'Pulpen Hitam', 'sku' => 'PEN-01', 'code' => 'KODE-AKTIF-1', 'count' => 7], $props['label'] ?? null);

            $this->assertSame($before, $this->counts2([...self::FACTS, ...self::UNTOUCHED]));
        }

        // It shows the code as it was recorded, also after the code was retired.
        $this->as('Agus');
        $location = self::text($this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 2]))->assertStatus(201)->json('result.location'));
        TokoContoh::retireBarcode($this->mapping, 'OTHER');
        $props = $this->inertiaPage($this->get($location)->assertOk())['props'] ?? null;
        $this->assertIsArray($props);
        $this->assertSame(['name' => 'Pulpen Hitam', 'sku' => 'PEN-01', 'code' => 'KODE-AKTIF-1', 'count' => 2], $props['label'] ?? null);
    }

    public function test_a_guessed_print_request_or_another_product_or_command_answers_not_found(): void
    {
        $this->as('Agus');
        $other = TokoContoh::product('PEN-02', ['categoryId' => $this->category]);
        $print = self::number($this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1]))->assertStatus(201)->json('commandId'));
        $retirement = $this->postJson('/commands/barcode.generate', [...$this->commandEnvelope(['productId' => $this->product]), 'operation' => 'barcode.generate'])->assertStatus(201);
        $another = self::number($retirement->json('commandId'));

        foreach ([
            '/products/'.$this->product.'/labels/print/999999',
            '/products/'.$this->product.'/labels/print/abc',
            '/products/'.$this->product.'/labels/print/'.$another,
            '/products/'.$other.'/labels/print/'.$print,
            '/products/999999/labels/print/'.$print,
            '/products/999999/labels',
            '/products/abc/labels',
        ] as $path) {
            $this->getJson($path)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        // Their query strings are refused, as every route's that takes none.
        $this->getJson('/products/'.$this->product.'/labels?count=5')->assertStatus(422);
        $this->getJson('/products/'.$this->product.'/labels/print/'.$print.'?count=100')->assertStatus(422);
    }

    public function test_the_label_page_gives_the_active_tokens_only_and_a_cashier_is_refused_everything(): void
    {
        $second = TokoContoh::barcode($this->product, 'KODE-AKTIF-2');
        TokoContoh::retireBarcode(TokoContoh::barcode($this->product, 'KODE-LAMA-1'), 'OTHER');

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $page = $this->get('/products/'.$this->product.'/labels')->assertOk();
            $this->assertPageChannel($page, $actor, array_values(array_map(static fn (array $one): string => $one['password'], $this->people)));
            $props = $this->inertiaPage($page)['props'] ?? null;
            $this->assertIsArray($props);
            $this->assertSame(['id' => $this->product, 'name' => 'Pulpen Hitam'], $props['product'] ?? null);
            $this->assertSame([['mappingId' => $this->mapping, 'token' => 'KODE-AKTIF-1'], ['mappingId' => $second, 'token' => 'KODE-AKTIF-2']], $props['barcodes'] ?? null);
        }

        $this->as('Agus');
        $location = self::text($this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1]))->assertStatus(201)->json('result.location'));

        $this->as('Rina');
        $before = $this->counts2(self::FACTS);
        $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1]))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson('/products/'.$this->product.'/labels')->assertStatus(403);
        $this->getJson($location)->assertStatus(403);
        $this->assertSame($before, $this->counts2(self::FACTS));
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', 'document.print_labels')->where('actor_user_id', $this->people['Rina']['id'])->count());
    }

    public function test_the_pages_offer_the_labels_only_for_a_product_with_an_active_barcode(): void
    {
        // UXI-163 and UXI-175.
        $without = TokoContoh::product('PEN-02', ['categoryId' => $this->category]);
        $this->as('Agus');

        $list = $this->inertiaPage($this->get('/products')->assertOk())['props'] ?? null;
        $this->assertIsArray($list);
        $this->assertTrue($list['canPrintLabels'] ?? null);

        $with = $this->inertiaPage($this->get('/products/'.$this->product)->assertOk())['props'] ?? null;
        $this->assertIsArray($with);
        $this->assertTrue($with['canPrintLabels'] ?? null);

        $none = $this->inertiaPage($this->get('/products/'.$without)->assertOk())['props'] ?? null;
        $this->assertIsArray($none);
        $this->assertFalse($none['canPrintLabels'] ?? null);

        $this->getJson('/data/products/search?context=catalog&q=PEN-01')->assertOk()->assertJsonPath('rows.0.activeBarcode', true);
        $this->getJson('/data/products/search?context=catalog&q=PEN-02')->assertOk()->assertJsonPath('rows.0.activeBarcode', false);

        // A retirement ends it.
        TokoContoh::retireBarcode($this->mapping, 'OTHER');
        $this->getJson('/data/products/search?context=catalog&q=PEN-01')->assertOk()->assertJsonPath('rows.0.activeBarcode', false);
        $after = $this->inertiaPage($this->get('/products/'.$this->product)->assertOk())['props'] ?? null;
        $this->assertIsArray($after);
        $this->assertFalse($after['canPrintLabels'] ?? null);
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        // TS-21: the audit event and the result.
        $this->as('Agus');

        foreach ([1, 2] as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->counts2(self::FACTS);

            $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1]))->assertStatus(500);

            $this->assertSame($before, $this->counts2(self::FACTS), 'after write step '.$step);
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', 'document.print_labels')->orderByDesc('id')->value('outcome'));
        }

        $this->app->instance(CommandPoints::class, new FailurePoints(3));
        $this->postJson(self::PATH, $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1]))->assertStatus(201);
        $this->app->instance(CommandPoints::class, new FailurePoints);
    }

    public function test_a_status_check_recovers_the_print_request(): void
    {
        // EXECUTION_CONTEXT section 10; RC-50.
        $this->as('Agus');
        $envelope = $this->commandEnvelope(['productId' => $this->product, 'mappingId' => $this->mapping, 'count' => 1]);
        $commandId = self::number($this->postJson(self::PATH, $envelope)->assertStatus(201)->json('commandId'));

        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/products/'.$this->product.'/labels/print/'.$commandId);
    }

    /**
     * An envelope of RC-47.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function commandEnvelope(array $payload): array
    {
        return [
            'operation' => 'document.print_labels',
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => (object) [],
            'payload' => $payload,
        ];
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function counts2(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * An event's data with its keys sorted.
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
