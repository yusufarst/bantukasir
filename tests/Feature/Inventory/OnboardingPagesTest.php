<?php

namespace Tests\Feature\Inventory;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-412, RP-413, RD-01 in the context `opening` and the onboarding state of RD-03 (AC-035-05, AC-035-06;
 * ROUTE_CONTRACTS RP-412, RP-413, RD-01, RD-03; INTERACTION_CONTRACTS sections 3.1 to 3.3; NAVIGATION_CONTRACTS
 * UXP-412, UXP-413; BR-INV-26 to BR-INV-28, BR-INV-47, BR-INV-48; D-071, D-072, D-087; CAP-15; EXECUTION_CONTEXT
 * section 10, a route and a read; suites OPEN01, AUTH01, AUTH02; TASK-035 scope items 5 and 6), as L5 tests
 * of the pages' answers: their props against the allowlists, the Owner's alone, every other kind of account
 * refused; the state not started, running, released or held; the checklist with its rows, filter and pages,
 * the cost as its status only; the counts of the release review; the entry page only while onboarding is
 * open, with the product of a replacement as RD-01 reads it; each product's eligibility of D-087; the state
 * for every role in RD-03. No draft is read from the server and no lock is held between requests.
 */
final class OnboardingPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string HUB = '/stock/onboarding';

    private const string ENTRY = '/stock/onboarding/entry';

    private const string SEARCH = '/data/products/search?context=opening';

    private int $category = 0;

    private int $pen = 0;

    private int $rice = 0;

    private int $book = 0;

    private int $glue = 0;

    private int $ink = 0;

    private int $paper = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category('Alat Tulis');
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
        $this->rice = TokoContoh::product('BERAS-01', ['categoryId' => $this->category, 'name' => 'Beras', 'baseUnit' => 'kg', 'quantityPrecision' => 3]);
        $this->book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis']);
        $this->glue = TokoContoh::product('LEM-01', ['categoryId' => $this->category, 'name' => 'Lem']);
        $this->ink = TokoContoh::product('TINTA-01', ['categoryId' => $this->category, 'name' => 'Tinta']);
        $this->paper = TokoContoh::product('KERTAS-01', ['categoryId' => $this->category, 'name' => 'Kertas']);
        // Received before onboarding: another movement, so never on the checklist (BR-INV-26).
        TokoContoh::stock($this->paper, '5', '5000');
    }

    public function test_only_the_owner_opens_the_pages_and_the_read_with_only_their_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $this->as('Hendra');
        $this->assertPageChannel($this->get(self::HUB)->assertOk(), 'owner', $secrets);

        $this->entries();
        $this->assertPageChannel($this->get(self::HUB)->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get(self::HUB.'?filter=counted')->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get(self::ENTRY)->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get(self::ENTRY.'?product='.$this->ink)->assertOk(), 'owner', $secrets, 'product');
        $this->assertJsonChannel($this->getJson(self::SEARCH.'&q=PEN')->assertOk(), 'data.products.search.opening', 'owner', $secrets);

        // An empty page of the checklist: no product is still to be counted.
        TokoContoh::zeroCount($this->glue);
        TokoContoh::zeroCount($this->ink);
        $this->assertPageChannel($this->get(self::HUB.'?filter=not_counted')->assertOk(), 'owner', $secrets, 'empty');

        // AC-035-06: every other role and the combined account are refused, the pages and the read.
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach ([self::HUB, self::ENTRY] as $path) {
                $page = $this->get($path)->assertStatus(403)->viewData('page');
                $this->assertIsArray($page);
                $this->assertSame('shell/Error', $page['component'] ?? null, $person.' '.$path);
            }

            $this->getJson(self::SEARCH.'&q=PEN')->assertStatus(403);
        }

        $this->browse(null);
        $this->get(self::HUB)->assertRedirect('/login');
        $this->get(self::ENTRY)->assertRedirect('/login');
    }

    public function test_the_hub_gives_the_state_the_checklist_with_its_filter_and_the_counts_of_the_release(): void
    {
        $this->as('Hendra');

        // Not started: every active product without a movement is still to be counted (UXI-287).
        $props = $this->propsOf($this->get(self::HUB)->assertOk());
        $this->assertSame(['state' => 'NOT_STARTED', 'version' => 1, 'held' => false, 'startedAt' => null, 'releasedAt' => null], $props['onboarding'] ?? null);
        $this->assertSame(['filter' => null], $props['filters'] ?? null);
        $this->assertSame(['Beras', 'Buku Tulis', 'Lem', 'Pulpen Hitam', 'Tinta'], self::namesOf($props));
        $this->assertSame(['counted' => 0, 'notCounted' => 5, 'unknownCost' => 0], $props['counts'] ?? null);

        // Running: 5 pens at Rp5.000, 2 kg of rice at UNKNOWN, the book counted zero, the ink's OPENING reversed.
        [$penOpening, $riceOpening, $zeroCount] = $this->entries();
        $props = $this->propsOf($this->get(self::HUB)->assertOk());
        $onboarding = (array) ($props['onboarding'] ?? []);
        $this->assertSame(['ACTIVE', 2, false, null], [$onboarding['state'] ?? null, $onboarding['version'] ?? null, $onboarding['held'] ?? null, $onboarding['releasedAt'] ?? null]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', self::text($onboarding['startedAt'] ?? null));
        $checklist = (array) ($props['checklist'] ?? []);
        $this->assertSame([
            self::row($this->rice, 'Beras', 'BERAS-01', 'kg', 3, 'COUNTED', '2', 'UNKNOWN', $riceOpening, null),
            self::row($this->book, 'Buku Tulis', 'BUKU-01', 'pcs', 0, 'ZERO_COUNTED', null, null, null, $zeroCount),
            self::row($this->glue, 'Lem', 'LEM-01', 'pcs', 0, 'NOT_COUNTED', null, null, null, null),
            self::row($this->pen, 'Pulpen Hitam', 'PEN-01', 'pcs', 0, 'COUNTED', '5', 'KNOWN', $penOpening, null),
            self::row($this->ink, 'Tinta', 'TINTA-01', 'pcs', 0, 'REPLACEABLE', null, null, null, null),
        ], $checklist['rows'] ?? null);
        $this->assertSame([null, null], [$checklist['nextCursor'] ?? null, $checklist['previousCursor'] ?? null]);
        $this->assertSame(['counted' => 3, 'notCounted' => 2, 'unknownCost' => 1], $props['counts'] ?? null);

        // The filter "Sudah dihitung" and "Belum dihitung" (UXI-287).
        $this->assertSame(['Beras', 'Buku Tulis', 'Pulpen Hitam'], self::namesOf($this->propsOf($this->get(self::HUB.'?filter=counted')->assertOk())));
        $this->assertSame(['Lem', 'Tinta'], self::namesOf($this->propsOf($this->get(self::HUB.'?filter=not_counted')->assertOk())));

        // A correction of the cost makes it KNOWN; the status, never an amount (COPY_DECK UXI-287).
        TokoContoh::correctCost(TokoContoh::evidenceOf($riceOpening), '30000');
        $this->assertSame(['counted' => 3, 'notCounted' => 2, 'unknownCost' => 0], $this->propsOf($this->get(self::HUB)->assertOk())['counts'] ?? null);

        // Parameters are allowlists: a filter of the list, one cursor, nothing else.
        foreach (['filter=semua', 'after=x&before=y', 'after=%25%25', 'q=PEN', 'page=2'] as $query) {
            $this->getJson(self::HUB.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // Released, read only: its time; the counted products stay on the list (D-071).
        TokoContoh::releaseOnboarding();
        $onboarding = (array) ($this->propsOf($this->get(self::HUB)->assertOk())['onboarding'] ?? []);
        $this->assertSame(['RELEASED', 3, false, null], [$onboarding['state'] ?? null, $onboarding['version'] ?? null, $onboarding['held'] ?? null, $onboarding['startedAt'] ?? null]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', self::text($onboarding['releasedAt'] ?? null));
    }

    public function test_the_checklist_is_paged_both_ways_in_name_order(): void
    {
        // TECHNICAL_RULES section 11.4, TR-PAGE-01 to TR-PAGE-03: 50 rows a page.
        for ($product = 1; $product <= 50; $product++) {
            TokoContoh::product(sprintf('MASSAL-%02d', $product), ['categoryId' => $this->category, 'name' => sprintf('Massal %02d', $product)]);
        }

        $this->as('Hendra');
        $first = $this->propsOf($this->get(self::HUB)->assertOk());
        $names = self::namesOf($first);
        $this->assertSame(['Beras', 'Buku Tulis', 'Lem', 'Massal 01'], array_slice($names, 0, 4));
        $this->assertSame([50, 'Massal 47', null], [count($names), $names[49] ?? null, ((array) ($first['checklist'] ?? []))['previousCursor'] ?? null]);

        $second = $this->propsOf($this->get(self::HUB.'?after='.self::cursorOf($first))->assertOk());
        $this->assertSame(['Massal 48', 'Massal 49', 'Massal 50', 'Pulpen Hitam', 'Tinta'], self::namesOf($second));
        $this->assertNull(((array) ($second['checklist'] ?? []))['nextCursor'] ?? null);

        $back = $this->propsOf($this->get(self::HUB.'?before='.self::text(((array) ($second['checklist'] ?? []))['previousCursor'] ?? null))->assertOk());
        $this->assertSame($names, self::namesOf($back));
        $this->assertNull(((array) ($back['checklist'] ?? []))['previousCursor'] ?? null);
    }

    public function test_the_entry_page_is_open_only_while_onboarding_is_and_names_the_product_of_a_replacement(): void
    {
        $this->as('Hendra');

        // Not started: 302 to RP-412 (ROUTE_CONTRACTS RP-413).
        $this->get(self::ENTRY)->assertRedirect(self::HUB);

        [, , , $line] = $this->entries();
        $props = $this->propsOf($this->get(self::ENTRY)->assertOk());
        $this->assertSame([2, null], [$props['onboardingVersion'] ?? null, $props['product'] ?? null]);
        $this->assertSame([['id' => $this->category, 'name' => 'Alat Tulis', 'active' => true, 'version' => 1]], $props['categories'] ?? null);

        // The product of "Catat ulang stok awal", as RD-01 in the context `opening` reads it (UXI-291).
        $product = (array) ($this->propsOf($this->get(self::ENTRY.'?product='.$this->ink)->assertOk())['product'] ?? []);
        $this->assertSame([$this->ink, 1, ['eligibility' => 'REPLACEABLE', 'zeroCountId' => null, 'replacesLineId' => $line]], [$product['id'] ?? null, $product['version'] ?? null, $product['opening'] ?? null]);
        $this->assertSame((array) ($this->getJson(self::SEARCH.'&mode=exact&q=TINTA-01')->assertOk()->json('rows.0') ?? []), $product);

        // A product that does not exist is NOT_FOUND; parameters are allowlists; no draft is read.
        $this->getJson(self::ENTRY.'?product=999999')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach (['product=abc', 'product=0', 'product=1,2', 'from_page=stock', 'draft=1'] as $query) {
            $this->getJson(self::ENTRY.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // Held for a restore reconciliation, nothing is entered (D-072; UXS-50); reconciled, it is open again.
        $hold = $this->runtime()->table('onboarding_restore_holds')->insertGetId(['restored_state' => 'ACTIVE', 'recovery_epoch' => '\x00112233445566778899aabbccddeeff', 'set_at' => now()]);
        $this->get(self::ENTRY)->assertRedirect(self::HUB);
        $this->assertTrue(((array) ($this->propsOf($this->get(self::HUB)->assertOk())['onboarding'] ?? []))['held'] ?? null);
        $this->runtime()->table('onboarding_restore_reconciliations')->insert(['hold_id' => $hold, 'outcome' => 'NEVER_RELEASED', 'decided_by_user_id' => $this->people['Hendra']['id'], 'operator_note' => 'Belum pernah diakhiri', 'recorded_at' => now()]);
        $this->get(self::ENTRY)->assertOk();

        // Released: 302 again (D-071).
        TokoContoh::releaseOnboarding();
        $this->get(self::ENTRY)->assertRedirect(self::HUB);
    }

    public function test_the_search_in_the_context_opening_gives_each_products_eligibility_of_d_087(): void
    {
        // INTERACTION_CONTRACTS section 3.1; UXI-292: eligible; counted zero, with its zero count; counted,
        // its OPENING effective; its OPENING reversed, with the line its replacement would replace; another
        // movement first. Each with its version, as RC-11 and RC-48 expect it.
        [, , $zeroCount, $line] = $this->entries();
        $this->as('Hendra');

        foreach ([
            'LEM-01' => ['eligibility' => 'ELIGIBLE', 'zeroCountId' => null, 'replacesLineId' => null],
            'BUKU-01' => ['eligibility' => 'ZERO_COUNTED', 'zeroCountId' => $zeroCount, 'replacesLineId' => null],
            'PEN-01' => ['eligibility' => 'COUNTED', 'zeroCountId' => null, 'replacesLineId' => null],
            'TINTA-01' => ['eligibility' => 'REPLACEABLE', 'zeroCountId' => null, 'replacesLineId' => $line],
            'KERTAS-01' => ['eligibility' => 'HAS_MOVEMENT', 'zeroCountId' => null, 'replacesLineId' => null],
        ] as $sku => $opening) {
            $row = (array) ($this->getJson(self::SEARCH.'&mode=exact&q='.$sku)->assertOk()->json('rows.0') ?? []);
            $this->assertSame([$opening, 1], [$row['opening'] ?? null, $row['version'] ?? null], $sku);
        }

        // A withdrawn zero count makes its product eligible again (D-087).
        TokoContoh::withdrawZeroCount($zeroCount);
        $this->assertSame('ELIGIBLE', $this->getJson(self::SEARCH.'&mode=exact&q=BUKU-01')->assertOk()->json('rows.0.opening.eligibility'));
    }

    public function test_the_shell_gives_every_role_the_onboarding_state(): void
    {
        // RD-03, UXS-27: every role reads the state, which shows the banner of UXI-030 while it is ACTIVE.
        foreach (['NOT_STARTED' => null, 'ACTIVE' => 'start', 'RELEASED' => 'release'] as $state => $change) {
            if ($change === 'start') {
                TokoContoh::startOnboarding();
            } elseif ($change === 'release') {
                TokoContoh::releaseOnboarding();
            }

            foreach (['Hendra', 'Agus', 'Rina', 'Dewi'] as $person) {
                $this->as($person);
                $this->getJson('/data/shell')->assertOk()->assertJsonPath('onboarding', $state);
            }
        }
    }

    /**
     * Onboarding started, with 5 pens at Rp5.000, 2 kg of rice at UNKNOWN, the book counted zero and the
     * ink's OPENING reversed: the pens' and the rice's OPENINGs, the zero count and the ink's line.
     *
     * @return array{int, int, int, int}
     */
    private function entries(): array
    {
        TokoContoh::startOnboarding();
        $pen = TokoContoh::openingStock($this->pen, '5', '5000');
        $rice = TokoContoh::openingStock($this->rice, '2', null);
        $zeroCount = TokoContoh::zeroCount($this->book);
        $ink = TokoContoh::openingStock($this->ink, '3', '3000');
        TokoContoh::reverse($ink);

        return [$pen, $rice, $zeroCount, TokoContoh::openingLineOf($ink)];
    }

    /**
     * A row of the checklist as RP-412 gives it.
     *
     * @return array<string, mixed>
     */
    private static function row(int $id, string $name, string $sku, string $unit, int $precision, string $state, ?string $quantity, ?string $cost, ?int $movement, ?int $zeroCount): array
    {
        return [
            'product' => ['id' => $id, 'name' => $name, 'sku' => $sku, 'baseUnit' => $unit, 'quantityPrecision' => $precision, 'active' => true],
            'state' => $state,
            'quantity' => $quantity,
            'cost' => $cost,
            'movementId' => $movement,
            'zeroCountId' => $zeroCount,
        ];
    }

    /**
     * The product names of a page of the checklist, in its order.
     *
     * @param  array<array-key, mixed>  $props
     * @return list<string>
     */
    private static function namesOf(array $props): array
    {
        $rows = ((array) ($props['checklist'] ?? []))['rows'] ?? [];

        return array_values(array_map(static fn (mixed $row): string => self::text(((array) (((array) $row)['product'] ?? []))['name'] ?? null), is_array($rows) ? $rows : []));
    }

    /**
     * The cursor after the last row of a page of the checklist.
     *
     * @param  array<array-key, mixed>  $props
     */
    private static function cursorOf(array $props): string
    {
        return self::text(((array) ($props['checklist'] ?? []))['nextCursor'] ?? null);
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
