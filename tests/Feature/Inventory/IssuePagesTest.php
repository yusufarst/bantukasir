<?php

namespace Tests\Feature\Inventory;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-407 to RP-409 and RD-01 in the context `issue` (AC-023-06; ROUTE_CONTRACTS RP-407 to RP-409,
 * RD-01; INTERACTION_CONTRACTS sections 3.1 and 3.2, RC-08; BR-INV-18 to BR-INV-22; D-018;
 * CAP-13; EXECUTION_CONTEXT section 10, a route and a read; suites INV02, AUTH01, AUTH02;
 * TASK-023 scope item 4), as L4 tests of the pages' answers: each page's props against the
 * allowlists per kind of account, a cashier refused; the issues newest first, keyset-paged both
 * ways, with their filters as allowlists; no draft read from the server; no cost on any page; the
 * movement of an issue with its source and reason (UXI-224); RC-08 leads to RP-409.
 */
final class IssuePagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $pen = 0;

    private int $paper = 0;

    /** @var list<int> */
    private array $issues = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A time before the runner's own: a command's hard end counts from the time it was received.
        $this->travelTo('2026-11-01T03:00:00Z');
        TokoContoh::shop();
        $this->setUpCommands();
        $category = TokoContoh::category('Alat Tulis');
        $this->pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'categoryId' => $category]);
        $this->paper = TokoContoh::product('KRT-01', ['name' => 'Kertas HVS', 'categoryId' => $category]);
        TokoContoh::stock($this->pen, '20', '200000');
        TokoContoh::stock($this->paper, '10', '50000');

        // The Owner's issue; the Operations admin's, with a note; the Owner's of two products.
        $this->issues[] = TokoContoh::issue([['productId' => $this->pen, 'quantity' => '2']], 'DAMAGED');
        $this->travel(1)->minutes();
        $this->as('Agus');
        $this->issues[] = $this->issueAs([['productId' => $this->paper, 'quantity' => '1']], 'SAMPLE', 'Untuk pameran');
        $this->travel(1)->minutes();
        $this->issues[] = TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1'], ['productId' => $this->paper, 'quantity' => '3']], 'INTERNAL_USE');
    }

    public function test_each_kind_of_account_of_cap_13_opens_each_page_with_only_its_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);

            $this->assertPageChannel($this->get('/stock/issues')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/stock/issues/new')->assertOk(), $actor, $secrets);
            $this->assertPageChannel($this->get('/stock/issues/'.$this->issues[2])->assertOk(), $actor, $secrets);
            $this->assertJsonChannel($this->getJson('/data/products/search?context=issue&q=PEN')->assertOk(), 'data.products.search.issue', $actor, $secrets);
        }

        $this->as('Rina');

        foreach (['/stock/issues', '/stock/issues/new', '/stock/issues/'.$this->issues[0]] as $path) {
            $page = $this->get($path)->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $path);
        }

        $this->getJson('/data/products/search?context=issue&q=PEN')->assertStatus(403);
    }

    public function test_the_new_issue_is_an_empty_draft_that_says_whether_the_freeze_is_on(): void
    {
        $this->as('Agus');
        $this->assertSame(['postingFrozen' => false], array_intersect_key($this->propsOf($this->get('/stock/issues/new')->assertOk()), ['postingFrozen' => true]));

        $this->runtime()->table('onboarding_state')->where('id', 1)->update(['state' => 'ACTIVE', 'version' => 2]);
        $this->assertTrue($this->propsOf($this->get('/stock/issues/new')->assertOk())['postingFrozen'] ?? null);
        $this->assertTrue($this->propsOf($this->get('/stock/issues')->assertOk())['postingFrozen'] ?? null);

        // No draft is read from the server: the page takes no parameter.
        $this->getJson('/stock/issues/new?draft=1')->assertStatus(422);
    }

    public function test_the_list_is_newest_first_with_its_filters_and_keyset_pages_and_no_cost(): void
    {
        $this->as('Agus');

        $this->assertSame([$this->issues[2], $this->issues[1], $this->issues[0]], $this->ids('/stock/issues'));
        $listed = (array) (((array) ($this->propsOf($this->get('/stock/issues'))['issues'] ?? []))['rows'] ?? []);
        $this->assertSame(['id' => $this->issues[0], 'confirmedAt' => '2026-11-01T03:00:00.000000Z', 'reasonCode' => 'DAMAGED', 'lineCount' => 1, 'actorDisplayName' => 'Hendra'], $listed[2] ?? null);
        $this->assertSame(['id' => $this->issues[1], 'confirmedAt' => '2026-11-01T03:01:00.000000Z', 'reasonCode' => 'SAMPLE', 'lineCount' => 1, 'actorDisplayName' => 'Agus'], $listed[1] ?? null);

        $this->assertSame([$this->issues[1]], $this->ids('/stock/issues?reason=SAMPLE'));
        $this->assertSame([], $this->ids('/stock/issues?reason=LOST'));
        $this->assertSame(3, count($this->ids('/stock/issues?from=2026-11-01&to=2026-11-01')));
        $this->assertSame([], $this->ids('/stock/issues?from=2026-11-02'));
        $this->assertSame([$this->issues[0]], $this->ids('/stock/issues?reason=DAMAGED&from=2026-11-01&to=2026-11-01'));

        // Two pages of two and one, and back.
        $page = (array) ($this->propsOf($this->get('/stock/issues?perPage=2'))['issues'] ?? []);
        $this->assertSame([$this->issues[2], $this->issues[1]], array_column((array) $page['rows'], 'id'));
        $this->assertNull($page['previousCursor']);
        $next = (array) ($this->propsOf($this->get('/stock/issues?perPage=2&after='.self::text($page['nextCursor'] ?? null)))['issues'] ?? []);
        $this->assertSame([$this->issues[0]], array_column((array) $next['rows'], 'id'));
        $this->assertNull($next['nextCursor']);
        $back = (array) ($this->propsOf($this->get('/stock/issues?perPage=2&before='.self::text($next['previousCursor'] ?? null)))['issues'] ?? []);
        $this->assertSame([$this->issues[2], $this->issues[1]], array_column((array) $back['rows'], 'id'));

        $refused = $this->getJson('/stock/issues?from=2026-11-04&to=2026-11-01')->assertStatus(422);
        $this->assertSame([['key' => 'ux.validation.date_order', 'params' => []]], $refused->json('fields.to'));

        foreach (['from=2026-13-01', 'reason=damaged', 'reason=SALE', 'perPage=0', 'perPage=101', 'after=x&before=y', 'q=pen', 'before=***'] as $query) {
            $this->getJson('/stock/issues?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // No cost reaches the list (ADR-008 section 6).
        $this->assertStringNotContainsString('20000', (string) $this->get('/stock/issues')->getContent());
    }

    public function test_the_detail_shows_the_lines_with_their_movements_and_no_cost(): void
    {
        $this->as('Agus');

        $props = $this->propsOf($this->get('/stock/issues/'.$this->issues[1])->assertOk());
        $this->assertSame(['id' => $this->issues[1], 'confirmedAt' => '2026-11-01T03:01:00.000000Z', 'reasonCode' => 'SAMPLE', 'note' => 'Untuk pameran', 'lineCount' => 1, 'actorDisplayName' => 'Agus'], $props['issue'] ?? null);

        $two = $this->propsOf($this->get('/stock/issues/'.$this->issues[2])->assertOk());
        $lines = (array) ($two['lines'] ?? []);
        $this->assertSame([[1, $this->pen, '1', 'pcs', 0], [2, $this->paper, '3', 'pcs', 0]], array_map(static fn (mixed $line): array => [((array) $line)['lineNo'] ?? null, ((array) ((array) $line)['product'])['id'] ?? null, ((array) $line)['quantity'] ?? null, ((array) $line)['baseUnit'] ?? null, ((array) $line)['quantityPrecision'] ?? null], $lines));

        // Each line's movement: its source is the issue, with the issue's reason (UXI-224).
        $movement = ((array) $lines[1])['movementId'] ?? null;
        $this->assertIsInt($movement);
        $read = (array) ($this->propsOf($this->get('/stock/movements/'.$movement)->assertOk())['movement'] ?? []);
        $this->assertSame(['MANUAL_ISSUE', '-3', 'INTERNAL_USE', null, ['kind' => 'MANUAL_ISSUE', 'id' => $this->issues[2], 'at' => '2026-11-01T03:02:00.000000Z', 'number' => null]], [$read['type'], $read['quantity'], $read['reasonCode'], $read['reference'], $read['source']]);

        // The history names it as its source too.
        $history = (array) (((array) ($this->propsOf($this->get('/stock/history?product='.$this->paper))['history'] ?? []))['rows'] ?? []);
        $this->assertSame(['kind' => 'MANUAL_ISSUE', 'id' => $this->issues[2], 'at' => '2026-11-01T03:02:00.000000Z'], ((array) $history[0])['source'] ?? null);

        // No cost, for the Owner neither: the allocation is the reports' (ADR-008 section 6).
        $this->as('Hendra');
        $content = (string) $this->get('/stock/issues/'.$this->issues[0])->assertOk()->getContent();

        foreach (['20000', 'allocation', 'KNOWN'] as $word) {
            $this->assertStringNotContainsString($word, $content, $word);
        }

        foreach (['999999', 'abc', '0'] as $identifier) {
            $this->getJson('/stock/issues/'.$identifier)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->getJson('/stock/issues/'.$this->issues[0].'?other=1')->assertStatus(422);
    }

    public function test_rc_08_leads_to_the_detail_of_its_issue_and_the_issue_context_gives_each_version_and_stock(): void
    {
        $this->as('Agus');

        $row = (array) ((array) $this->getJson('/data/products/search?context=issue&mode=exact&q=PEN-01')->assertOk()->json('rows'))[0];
        $this->assertSame([$this->pen, 1, true, '17'], [$row['id'], $row['version'], $row['active'], ((array) ($row['stock'] ?? []))['onHand'] ?? null]);
        $this->getJson('/data/products/search?context=issue&status=active')->assertStatus(422);

        $answer = $this->postJson('/commands/manual_issue.confirm', $this->envelope(
            ['reasonCode' => 'LOST', 'note' => null, 'lines' => [['productId' => $this->pen, 'quantity' => '2']]],
            ['operation' => 'manual_issue.confirm', 'expected' => ['products' => [['productId' => $this->pen, 'version' => 1]]]],
        ))->assertStatus(201);

        $id = $answer->json('result.id');
        $location = self::text($answer->json('result.location'));
        $this->assertSame('/stock/issues/'.(is_int($id) ? (string) $id : ''), $location);
        $this->assertSame('LOST', ((array) ($this->propsOf($this->get($location)->assertOk())['issue'] ?? []))['reasonCode'] ?? null);
    }

    /**
     * An issue confirmed through RC-08 by the account signed in now: its key.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function issueAs(array $lines, string $reason, ?string $note): int
    {
        $products = [];

        foreach ($lines as $line) {
            $given = $line['productId'] ?? null;
            $id = is_int($given) ? $given : 0;
            $products[$id] = ['productId' => $id, 'version' => 1];
        }

        $answer = $this->postJson('/commands/manual_issue.confirm', $this->envelope(
            ['reasonCode' => $reason, 'note' => $note, 'lines' => $lines],
            ['operation' => 'manual_issue.confirm', 'expected' => ['products' => array_values($products)]],
        ))->assertStatus(201);

        $id = $answer->json('result.id');

        return is_int($id) ? $id : 0;
    }

    /**
     * The issues of a page of RP-407, in order.
     *
     * @return list<mixed>
     */
    private function ids(string $path): array
    {
        $issues = (array) ($this->propsOf($this->get($path)->assertOk())['issues'] ?? []);

        return array_column((array) ($issues['rows'] ?? []), 'id');
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
