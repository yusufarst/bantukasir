<?php

namespace Tests\Feature\Catalog;

use App\Queries\Catalog\ProductSearch;
use App\Queries\Catalog\SearchFilters;
use Illuminate\Testing\TestResponse;
use PDO;
use PDOStatement;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RD-01 and RD-06 (AC-015-03, AC-015-05, AC-016-04; INTERACTION_CONTRACTS sections
 * 3.1 and 3.5; ROUTE_CONTRACTS RD-01, RD-06, RT-08; BR-CAT-04, BR-CAT-23 to BR-CAT-27;
 * THR-04; TECHNICAL_RULES section 12; EXECUTION_CONTEXT section 10, a read; suites
 * AUTH01, AUTH02, SEC01, SEARCH01 and SEARCH02): the order and paging, the barcode's
 * rank and the exact mode; the filters as allowlists, the fields per role, never a
 * cost; every context not yet provided refused; injection payloads treated as text;
 * the indexes that serve a search; and the two documents agreeing on the contexts.
 */
final class ProductSearchTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string SEARCH = '/data/products/search';

    private const string CHECK = '/data/products/check';

    private int $category = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
    }

    public function test_results_come_in_the_order_exact_sku_sku_prefix_then_name_each_by_name_then_identifier(): void
    {
        $exact = $this->product('tulis', 'Zebra');
        $prefixLast = $this->product('TULIS-2', 'Spidol');
        $prefixFirst = $this->product('Tulis-1', 'Kertas');
        $nameFirst = $this->product('A-01', 'Buku Tulis');
        $sameOne = $this->product('A-02', 'Pensil TULIS');
        $sameTwo = $this->product('A-03', 'Pensil TULIS');
        $this->product('B-01', 'Penghapus');

        $this->as('Agus');

        // Without regard to letter case, the case of the text as typed.
        foreach (['tulis', 'TULIS', 'TuLiS'] as $text) {
            $response = $this->search(['q' => $text])->assertOk();
            $this->assertSame([$exact, $prefixFirst, $prefixLast, $nameFirst, $sameOne, $sameTwo], self::ids($response), $text);
        }

        $this->assertJsonChannel($this->search(['q' => 'tulis']), 'data.products.search', 'ops');

        // Anywhere in the name, and a prefix of the SKU only at its start.
        $this->assertSame([$this->idOf('Penghapus')], self::ids($this->search(['q' => 'NGHAP'])));
        $this->assertSame([], self::ids($this->search(['q' => 'ULIS-1'])));

        // With no text, every product by name, then identifier.
        $response = $this->search()->assertOk();
        $this->assertSame([$nameFirst, $prefixFirst, $this->idOf('Penghapus'), $sameOne, $sameTwo, $prefixLast, $exact], self::ids($response));
    }

    public function test_a_page_holds_25_by_default_and_at_most_100_and_says_whether_more_exist(): void
    {
        $ids = [];

        foreach (range(1, 27) as $number) {
            $ids[] = $this->product(sprintf('SKU-%02d', $number), sprintf('Barang %02d', $number));
        }

        $this->as('Agus');

        $first = $this->search()->assertOk();
        $this->assertSame([array_slice($ids, 0, 25), 1, 25, true], [self::ids($first), $first->json('page'), $first->json('perPage'), $first->json('hasMore')]);

        $second = $this->search(['page' => '2'])->assertOk();
        $this->assertSame([array_slice($ids, 25), 2, 25, false], [self::ids($second), $second->json('page'), $second->json('perPage'), $second->json('hasMore')]);

        $small = $this->search(['perPage' => '10', 'page' => '3'])->assertOk();
        $this->assertSame([array_slice($ids, 20, 7), false], [self::ids($small), $small->json('hasMore')]);

        $this->assertCount(27, self::ids($this->search(['perPage' => '100'])));
        $this->assertSame([], self::ids($this->search(['page' => '9'])->assertOk()));

        foreach ([['perPage' => '101'], ['perPage' => '0'], ['perPage' => '-1'], ['perPage' => 'abc'], ['page' => '0'], ['page' => '1.5'], ['page' => '1000000']] as $invalid) {
            $this->search($invalid)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_the_filters_are_allowlists_and_keep_the_order(): void
    {
        $other = TokoContoh::category('Buku');
        $merek = $this->product('A-01', 'Pulpen A', ['brand' => 'Merek']);
        $lain = $this->product('A-02', 'Pulpen B', ['brand' => 'Lain', 'categoryId' => $other]);
        $inactive = $this->product('A-03', 'Pulpen C', ['brand' => 'MEREK']);

        $this->as('Agus');
        $this->postJson('/commands/product.deactivate', $this->envelopeFor('product.deactivate', ['productId' => $inactive], ['productVersion' => 1]))->assertStatus(201);

        $this->assertSame([$merek, $lain, $inactive], self::ids($this->search(['q' => 'pulpen'])), 'Inactive results are in the results, marked (BR-CAT-27).');
        $this->assertSame([$lain], self::ids($this->search(['category' => (string) $other])));
        $this->assertSame([], self::ids($this->search(['category' => '999999'])));
        $this->assertSame([$merek, $inactive], self::ids($this->search(['brand' => 'merek'])), 'A brand without regard to letter case.');
        $this->assertSame([$merek, $lain], self::ids($this->search(['status' => 'active'])));
        $this->assertSame([$inactive], self::ids($this->search(['status' => 'inactive'])));
        $this->assertSame([false], array_column((array) $this->search(['status' => 'inactive'])->json('rows'), 'active'));
        $this->assertSame([$merek], self::ids($this->search(['q' => 'pulpen', 'brand' => 'merek', 'status' => 'active', 'category' => (string) $this->category])));

        foreach ([['status' => 'semua'], ['status' => ''], ['category' => 'abc'], ['category' => '0'], ['category' => '9223372036854775808'], ['mode' => 'lain'], ['sort' => 'name'], ['cost' => '1'], ['category' => ['1']], ['q' => ['a']], ['context' => ['catalog']]] as $invalid) {
            $this->search($invalid)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // A body is no source of parameters.
        $this->json('GET', self::SEARCH.'?context=catalog', ['q' => 'pulpen'])->assertStatus(422);
    }

    public function test_the_fields_are_those_of_section_3_1_for_each_role_and_never_a_cost(): void
    {
        $id = $this->product('PEN-01', 'Pulpen Hitam', ['baseUnit' => 'kg', 'quantityPrecision' => 2, 'sellingPrice' => '12500']);

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $response = $this->search()->assertOk();
            $this->assertJsonChannel($response, 'data.products.search', $actor);
            $this->assertSame('no-store, private', $this->cacheControl($response));
            $revision = $this->observer()->table('products')->where('id', $id)->value('current_price_revision_id');
            $response->assertExactJson([
                'rows' => [[
                    'id' => $id,
                    'name' => 'Pulpen Hitam',
                    'sku' => 'PEN-01',
                    'baseUnit' => 'kg',
                    'quantityPrecision' => 2,
                    'price' => ['amount' => '12500', 'revisionId' => $revision],
                    'active' => true,
                    'thumbnail' => null,
                    'activeBarcode' => false,
                ]],
                'page' => 1,
                'perPage' => 25,
                'hasMore' => false,
            ]);

            foreach (['cost', 'valuation', 'margin', 'acquisition'] as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, (string) $response->getContent());
            }
        }
    }

    public function test_the_context_needs_its_capability_and_every_other_context_is_forbidden_until_its_task(): void
    {
        $this->product('PEN-01', 'Pulpen Hitam');
        $observer = $this->observer();
        $refusals = static fn (): int => $observer->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count();

        foreach (['Hendra', 'Agus', 'Dewi'] as $person) {
            $this->as($person);

            // `correction` opened with TASK-020, for CAP-07, the Owner's alone.
            foreach ($person === 'Hendra' ? ['till', 'receiving', 'issue', 'adjustment', 'opening', 'stock'] : ['till', 'receiving', 'issue', 'adjustment', 'opening', 'stock', 'correction'] as $context) {
                $before = $refusals();
                $this->getJson(self::SEARCH.'?context='.$context)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
                $this->assertSame($before + 1, $refusals(), $context.' is recorded (SEC-LOG-03).');
            }

            // A context no contract names, or none, is a refused parameter.
            foreach (['?context=labels', '?context=semua', '', '?q=PEN'] as $query) {
                $this->getJson(self::SEARCH.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            }
        }

        // A cashier is refused at the gate of CAP-02 in every context today: the
        // narrow form of the till is TASK-028's.
        $this->as('Rina');

        foreach (['catalog', 'till', 'stock'] as $context) {
            $this->getJson(self::SEARCH.'?context='.$context)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->getJson(self::CHECK.'?sku=PEN-01')->assertStatus(403);

        // Without a sign-in session nothing is answered.
        $this->browse(null);
        $this->getJson(self::SEARCH.'?context=catalog')->assertStatus(401);
        $this->getJson(self::CHECK.'?sku=PEN-01')->assertStatus(401);
    }

    public function test_an_injection_payload_in_q_is_only_text(): void
    {
        $id = $this->product('PEN-01', 'Pulpen Hitam');
        $percent = $this->product('DISKON-1', '100% Katun');
        $underscore = $this->product('SNAKE_CASE', 'Tinta_Biru');
        $quote = $this->product("O'Neil", "Buku O'Neil");
        $bang = $this->product('BANG!1', 'Pensil!Merah');
        $products = $this->observer()->table('products')->count();

        $this->as('Agus');

        foreach (["' OR '1'='1", "'; DROP TABLE products; --", '%\' OR 1=1 --', '" OR ""="', '\\', '%00', '$1', 'pulpen\' UNION SELECT cost FROM products --'] as $payload) {
            $response = $this->search(['q' => $payload])->assertOk();
            $this->assertNotContains($id, self::ids($response), $payload);
        }

        $this->assertSame($products, $this->observer()->table('products')->count());

        // A wildcard of LIKE matches itself alone, and so does the escape character.
        $this->assertSame([], self::ids($this->search(['q' => '%%%'])));
        $this->assertSame([$percent], self::ids($this->search(['q' => '100%'])));
        $this->assertSame([], self::ids($this->search(['q' => '1_0'])));
        $this->assertSame([$underscore], self::ids($this->search(['q' => 'SNAKE_'])));
        $this->assertSame([], self::ids($this->search(['q' => 'tinta-biru'])));
        $this->assertSame([$underscore], self::ids($this->search(['q' => 'tinta_biru'])));
        $this->assertSame([$quote], self::ids($this->search(['q' => "o'neil"])));
        $this->assertSame([$bang], self::ids($this->search(['q' => 'bang!'])));
        $this->assertSame([$bang], self::ids($this->search(['q' => 'pensil!m'])));
        $this->assertSame([], self::ids($this->search(['q' => '!%'])));

        // A NUL, a control character, invalid UTF-8 and a text beyond the limit
        // never reach the database as a failure of its own.
        foreach (['q=a%00b', 'q=a%07b', 'q=%FF%FE', 'q='.str_repeat('a', 129)] as $query) {
            $this->getJson(self::SEARCH.'?context=catalog&'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // An outer NUL is white space to the framework's trimming: an empty search.
        $this->getJson(self::SEARCH.'?context=catalog&q=%00')->assertOk();

        $this->search(['q' => str_repeat('a', 128)])->assertOk();

        // The check treats its text the same way.
        $this->getJson(self::CHECK.'?sku='.rawurlencode("' OR '1'='1"))->assertOk()->assertJsonPath('sku', null);
        $this->getJson(self::CHECK.'?name='.rawurlencode("'; DROP TABLE products; --"))->assertOk();
        $this->assertSame($products, $this->observer()->table('products')->count());
    }

    public function test_the_trigram_index_serves_a_name_search_and_the_prefix_index_a_sku_search(): void
    {
        $this->product('PEN-01', 'Pulpen Hitam');
        $this->product('BUKU-01', 'Buku Tulis');

        $plan = $this->planOf(new SearchFilters(q: 'tulis'));

        // Each arm of the match of BR-CAT-24 has its index of SCHEMA section 11.
        $this->assertStringContainsString('products_name_trgm_index', $plan);
        $this->assertStringContainsString('products_sku_prefix_index', $plan);

        $this->assertStringContainsString('products_brand_ci_index', $this->planOf(new SearchFilters(brand: 'merek')));
        $this->assertStringContainsString('products_category_id_index', $this->planOf(new SearchFilters(category: $this->category)));
    }

    public function test_the_header_of_the_client_sequence_is_repeated_and_a_page_of_it_is_validated(): void
    {
        $this->product('PEN-01', 'Pulpen Hitam');
        $this->as('Agus');

        $this->getJson(self::SEARCH.'?context=catalog', ['X-Search-Sequence' => '41'])->assertOk()->assertHeader('X-Search-Sequence', '41');
        $this->getJson(self::SEARCH.'?context=catalog')->assertOk()->assertHeaderMissing('X-Search-Sequence');

        foreach (['abc', '-1', '1.5', str_repeat('9', 17)] as $sequence) {
            $this->getJson(self::SEARCH.'?context=catalog', ['X-Search-Sequence' => $sequence])->assertStatus(422);
        }
    }

    public function test_rd_06_answers_the_exact_barcode_the_exact_sku_and_up_to_10_similar_names_and_refuses_nothing_as_a_duplicate(): void
    {
        $exact = $this->product('Pen-01', 'Pulpen Hitam');
        $coded = $this->product('LAIN-01', 'Tinta Biru');
        TokoContoh::barcode($coded, '0012-Ab');

        foreach (range(1, 12) as $number) {
            $this->product(sprintf('SIMILAR-%02d', $number), sprintf('Pulpen Hitam %02d', $number));
        }

        $holds = $this->product('X-01', 'Pak Isi Pulpen Hitam Merek Khusus Untuk Kantor');
        $this->product('Y-01', 'Kertas');

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $response = $this->getJson(self::CHECK.'?barcode=0012-Ab&sku=PEN-01&name='.rawurlencode('pulpen hitam'))->assertOk();
            $this->assertJsonChannel($response, 'data.products.check', $actor);
            $this->assertSame('no-store, private', $this->cacheControl($response));
            $this->assertSame([$coded, 'LAIN-01'], [$response->json('barcode.id'), $response->json('barcode.sku')], 'The product of the exact barcode.');
            $this->assertSame([$exact, 'Pen-01'], [$response->json('sku.id'), $response->json('sku.sku')], 'Without regard to letter case, as it is stored.');
            $similar = (array) $response->json('similarNames');
            $this->assertCount(10, $similar);
            $this->assertSame($exact, is_array($similar[0]) ? ($similar[0]['id'] ?? null) : null, 'The most similar first.');
        }

        $this->as('Agus');

        // A similar name in another order of words, and a long name that holds it.
        $this->assertContains($exact, self::similarIds($this->getJson(self::CHECK.'?name='.rawurlencode('hitam pulpen'))->assertOk()));
        $this->assertContains($holds, self::similarIds($this->getJson(self::CHECK.'?name='.rawurlencode('Merek Khusus'))->assertOk()));

        // Nothing found is no refusal; one check alone answers the other as empty.
        $this->getJson(self::CHECK.'?sku=TIDAK-ADA')->assertOk()->assertExactJson(['barcode' => null, 'sku' => null, 'similarNames' => []]);
        $this->assertSame([], self::similarIds($this->getJson(self::CHECK.'?name=zzzzzzzz')->assertOk()));
        $this->assertNull($this->getJson(self::CHECK.'?name=Kertas')->assertOk()->json('sku'));

        // The parameters are allowlists.
        foreach (['', '?lain=x', '?sku[]=x', '?barcode[]=x', '?sku='.str_repeat('s', 65), '?name='.str_repeat('n', 201), '?sku=a%00b', '?name=a%07b'] as $query) {
            $this->getJson(self::CHECK.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->json('GET', self::CHECK.'?sku=PEN-01', ['name' => 'x'])->assertStatus(422);
    }

    public function test_rd_06_reports_an_exact_barcode_as_br_cat_17_reads_a_token(): void
    {
        // AC-016-04.
        $product = $this->product('PEN-01', 'Pulpen Hitam');
        $mapping = TokoContoh::barcode($product, '0012-Ab');
        $this->as('Agus');

        // Compared exactly: leading zeros, case and punctuation; the terminator and
        // outer spaces removed.
        foreach (['0012-Ab', rawurlencode(" 0012-Ab \r\n")] as $query) {
            $this->getJson(self::CHECK.'?barcode='.$query)->assertOk()->assertJsonPath('barcode.id', $product)->assertJsonPath('sku', null);
        }

        foreach (['012-Ab', '0012-ab', '0012Ab', '0012-Ab-'] as $other) {
            $this->getJson(self::CHECK.'?barcode='.$other)->assertOk()->assertJsonPath('barcode', null);
        }

        // A token that holds what no token may is refused at its field.
        foreach (['%C3%A9', 'a%07b', 'a%0Ab', '%0A12', '%C2%A012', str_repeat('a', 129)] as $query) {
            $response = $this->getJson(self::CHECK.'?barcode='.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $response->json('fields');
            $this->assertArrayHasKey('barcode', $fields, $query);
        }

        // Injection is only text (THR-04).
        $this->getJson(self::CHECK.'?barcode='.rawurlencode("' OR '1'='1"))->assertOk()->assertJsonPath('barcode', null);

        // A retired token has no product to show; RC-17 refuses it.
        $this->postJson('/commands/barcode.retire', $this->envelopeFor('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER'], []))->assertStatus(201);
        $this->getJson(self::CHECK.'?barcode=0012-Ab')->assertOk()->assertJsonPath('barcode', null);
    }

    public function test_an_exact_barcode_leads_the_search_and_a_retired_one_leads_nowhere(): void
    {
        // BR-CAT-24: the barcode's rank, before the exact SKU, the prefix and the name.
        $sku = $this->product('899', 'Zebra');
        $prefix = $this->product('8991', 'Kertas');
        $name = $this->product('A-01', 'Buku 899');
        $coded = $this->product('B-01', 'Pena');
        $mapping = TokoContoh::barcode($coded, '899');
        $this->as('Agus');

        $response = $this->search(['q' => '899'])->assertOk();
        $this->assertSame([$coded, $sku, $prefix, $name], self::ids($response));
        $this->assertFalse($response->json('hasMore'));

        // A token is compared exactly, unlike a SKU: `abc` is no match of `ABC`.
        $other = $this->product('C-01', 'Spidol');
        TokoContoh::barcode($other, 'ABC-7');
        $this->assertSame([$other], self::ids($this->search(['q' => 'ABC-7'])));
        $this->assertSame([], self::ids($this->search(['q' => 'abc-7'])));

        // The filters apply to the barcode's rank too.
        $this->assertSame([], self::ids($this->search(['q' => 'ABC-7', 'status' => 'inactive'])));

        // A token's inactive product is in the results, marked (BR-CAT-27).
        $this->postJson('/commands/product.deactivate', $this->envelopeFor('product.deactivate', ['productId' => $other], ['productVersion' => 1]))->assertStatus(201);
        $this->search(['q' => 'ABC-7'])->assertOk()->assertJsonPath('rows.0.id', $other)->assertJsonPath('rows.0.active', false);

        // A retired token leads to no product; the other matches stay.
        $this->postJson('/commands/barcode.retire', $this->envelopeFor('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'MISASSIGNMENT'], []))->assertStatus(201);
        $this->assertSame([$sku, $prefix, $name], self::ids($this->search(['q' => '899'])));
    }

    public function test_the_exact_mode_answers_at_most_one_product_the_barcode_before_the_sku(): void
    {
        // AC-016-04; INTERACTION_CONTRACTS section 3.1.
        $bySku = $this->product('899', 'Zebra');
        $this->product('8991', 'Kertas');
        $this->product('A-01', 'Buku 899');
        $coded = $this->product('B-01', 'Pena');
        $mapping = TokoContoh::barcode($coded, '899');
        $inactive = $this->product('C-01', 'Spidol');
        TokoContoh::barcode($inactive, '0012-Ab');

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $response = $this->search(['mode' => 'exact', 'q' => '899'])->assertOk();
            $this->assertJsonChannel($response, 'data.products.search', $actor);
            $this->assertSame([[$coded], 1, 1, false], [self::ids($response), $response->json('page'), $response->json('perPage'), $response->json('hasMore')]);
        }

        $this->as('Agus');

        // The sequence of a scan is repeated, so that scans apply in order (BR-CAT-30).
        $this->getJson(self::SEARCH.'?context=catalog&mode=exact&q=899', ['X-Search-Sequence' => '7'])->assertOk()->assertHeader('X-Search-Sequence', '7');

        // The exact SKU, without regard to letter case, when no token is active.
        $this->assertSame([$coded], self::ids($this->search(['mode' => 'exact', 'q' => 'b-01'])));
        $this->postJson('/commands/barcode.retire', $this->envelopeFor('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER'], []))->assertStatus(201);
        $this->assertSame([$bySku], self::ids($this->search(['mode' => 'exact', 'q' => '899'])));

        // An inactive product is answered, marked: the page decides (BR-CAT-27).
        $this->postJson('/commands/product.deactivate', $this->envelopeFor('product.deactivate', ['productId' => $inactive], ['productVersion' => 1]))->assertStatus(201);
        $this->search(['mode' => 'exact', 'q' => '0012-Ab'])->assertOk()->assertJsonPath('rows.0.id', $inactive)->assertJsonPath('rows.0.active', false);

        // What a scan delivers: the terminator and outer spaces are removed. `q` follows
        // the global trimming of section 3.1, so an outer tab or no-break space is
        // removed too, unlike a token of RC-27 or RD-06; an inner one is refused
        // (review findings R-02, R-03).
        foreach (['0012-Ab%0D%0A', '0012-Ab%0A', '%200012-Ab%20', '%090012-Ab', '%C2%A00012-Ab'] as $scanned) {
            $this->getJson(self::SEARCH.'?context=catalog&mode=exact&q='.$scanned)->assertOk()->assertJsonPath('rows.0.id', $inactive);
        }

        $this->assertSame([], self::ids($this->getJson(self::SEARCH.'?context=catalog&mode=exact&q=0012-ab%0D%0A')->assertOk()));
        $this->getJson(self::SEARCH.'?context=catalog&mode=exact&q=0012%0A-Ab')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        // No prefix, no name, no other case of a token, and no code at all: none.
        foreach (['89', 'Zebra', 'Buku 899', '0012-ab', '012-Ab', '%', "' OR '1'='1"] as $code) {
            $this->assertSame([], self::ids($this->search(['mode' => 'exact', 'q' => $code])->assertOk()), $code);
        }

        $this->search(['mode' => 'exact'])->assertOk()->assertExactJson(['rows' => [], 'page' => 1, 'perPage' => 1, 'hasMore' => false]);

        // A filter or a page has no place in a lookup of one code.
        foreach (['category' => (string) $this->category, 'brand' => 'Merek', 'status' => 'active', 'page' => '2', 'perPage' => '10'] as $name => $value) {
            $this->search(['mode' => 'exact', 'q' => '899', $name => $value])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The other contexts stay closed in this mode too, and so does a cashier.
        $this->getJson(self::SEARCH.'?context=till&mode=exact&q=899')->assertStatus(403);
        $this->as('Rina');
        $this->getJson(self::SEARCH.'?context=catalog&mode=exact&q=899')->assertStatus(403);
    }

    public function test_the_indexes_of_section_11_serve_the_exact_lookup(): void
    {
        $product = $this->product('PEN-01', 'Pulpen Hitam');
        TokoContoh::barcode($product, '899');

        $plan = $this->planOf(new SearchFilters(q: '899', exact: true));

        $this->assertStringContainsString('barcode_tokens_token_unique', $plan);

        // Either index of lower(sku) serves an equality.
        $this->assertMatchesRegularExpression('/products_sku_(?:ci_uniq|prefix_index)/', $plan);
    }

    public function test_a_read_that_answers_writes_nothing(): void
    {
        // EXECUTION_CONTEXT section 10, no GET that writes; TR-HTTP-02.
        $this->product('PEN-01', 'Pulpen Hitam');
        $this->as('Agus');
        $observer = $this->observer();
        $state = static function () use ($observer): array {
            $state = [];

            foreach (['categories', 'products', 'selling_price_revisions', 'command_identities', 'command_results', 'command_request_receipts', 'audit_events', 'security_log_entries'] as $table) {
                $state[$table] = $observer->table($table)->count();
            }

            $state['rows'] = $observer->table('products')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();

            return $state;
        };
        $before = $state();

        $this->search(['q' => 'pulpen'])->assertOk()->assertJsonPath('rows.0.sku', 'PEN-01');
        $this->getJson(self::CHECK.'?sku=PEN-01&name=pulpen')->assertOk()->assertJsonPath('sku.sku', 'PEN-01');
        $this->search(['mode' => 'exact', 'q' => 'PEN-01'])->assertOk()->assertJsonPath('rows.0.sku', 'PEN-01');
        $this->getJson(self::CHECK.'?barcode=899')->assertOk()->assertJsonPath('barcode', null);

        $this->assertSame($before, $state());
    }

    public function test_the_two_documents_agree_on_the_contexts(): void
    {
        // AC-015-05: INTERACTION_CONTRACTS section 3.1, ROUTE_CONTRACTS RD-01 and
        // the code name one list.
        $interaction = (string) file_get_contents(base_path('docs/03-workflows/INTERACTION_CONTRACTS.md'));
        $route = (string) file_get_contents(base_path('docs/03-workflows/ROUTE_CONTRACTS.md'));

        $this->assertSame(1, preg_match('/^\| `context` \| ((?:`[a-z]+`(?:, | or )?)+);/m', $interaction, $inInteraction));
        $this->assertSame(1, preg_match('/`context` \(((?:`[a-z]+`(?:, )?)+)\)/', $route, $inRoute));

        $names = static function (string $list): array {
            preg_match_all('/`([a-z]+)`/', $list, $matches);
            sort($matches[1]);

            return $matches[1];
        };

        $expected = ['adjustment', 'catalog', 'correction', 'issue', 'opening', 'receiving', 'stock', 'till'];
        $this->assertSame($expected, $names($inInteraction[1] ?? ''));
        $this->assertSame($expected, $names($inRoute[1] ?? ''));
        $this->assertStringNotContainsString('`labels`', substr($interaction, (int) strpos($interaction, '### 3.1'), (int) strpos($interaction, '### 3.2') - (int) strpos($interaction, '### 3.1')));
    }

    /**
     * The plan of a page of RD-01 as the runtime role, the query as the search
     * builds it, its values as literals because EXPLAIN takes no binding. A bitmap
     * scan is the only one left, and it needs an index that a condition can use, so
     * that the plan on these few rows shows what serves each condition.
     */
    private function planOf(SearchFilters $filters): string
    {
        $sql = app(ProductSearch::class)->builder($filters)->toRawSql();
        $runtime = $this->runtime();
        $runtime->beginTransaction();

        try {
            $runtime->statement('set local enable_seqscan = off');
            $runtime->statement('set local enable_indexscan = off');
            $runtime->statement('set local enable_indexonlyscan = off');

            // The query as text, which only a test runs; these values are its own.
            $pdo = $runtime->getPdo();
            $this->assertInstanceOf(PDO::class, $pdo);
            $plan = $pdo->query('explain '.$sql);
            $this->assertInstanceOf(PDOStatement::class, $plan);

            return (string) json_encode($plan->fetchAll(PDO::FETCH_NUM));
        } finally {
            $runtime->rollBack();
        }
    }

    /**
     * A product of the shop, through RC-17's Action.
     *
     * @param  array<string, mixed>  $fields
     */
    private function product(string $sku, string $name, array $fields = []): int
    {
        return TokoContoh::product($sku, ['name' => $name, 'categoryId' => $this->category, ...$fields]);
    }

    private function idOf(string $name): int
    {
        return self::number($this->observer()->table('products')->where('name', $name)->value('id'));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return TestResponse<Response>
     */
    private function search(array $parameters = []): TestResponse
    {
        return $this->getJson(self::SEARCH.'?'.http_build_query(['context' => 'catalog', ...$parameters]));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function envelopeFor(string $operation, array $payload, array $expected): array
    {
        return $this->envelope($payload, ['operation' => $operation, 'expected' => $expected]);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function cacheControl(TestResponse $response): string
    {
        $directives = array_map(trim(...), explode(',', (string) $response->headers->get('Cache-Control')));
        sort($directives);

        return implode(', ', $directives);
    }

    /**
     * The identifiers of a page of results.
     *
     * @param  TestResponse<Response>  $response
     * @return list<mixed>
     */
    private static function ids(TestResponse $response): array
    {
        return array_column((array) $response->json('rows'), 'id');
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<mixed>
     */
    private static function similarIds(TestResponse $response): array
    {
        return array_column((array) $response->json('similarNames'), 'id');
    }
}
