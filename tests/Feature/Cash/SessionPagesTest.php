<?php

namespace Tests\Feature\Cash;

use App\Actions\Sales\CheckoutSale;
use App\Domain\Commands\RequestReceipts;
use App\Support\Time\Clock;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The pages of TASK-030 (AC-030-01, AC-030-07; ROUTE_CONTRACTS RP-105, RP-205, RP-206; NAVIGATION_CONTRACTS
 * UXP-105, UXP-205, UXP-206, UXP-604; KEY_SCREENS KS-07; SECURITY_MODEL SEC-AZ-03, SEC-CH-01; BR-CASH-13,
 * BR-CASH-19 to BR-CASH-21; G-05, G-27; EXECUTION_CONTEXT section 10, a route and a page; suites AUTH01,
 * AUTH02, SESSION01 and SESSION04; TASK-030 section 7, L4): the close page for the user's own OPEN session
 * with its preview as a deferred prop, the till without one; the list of sessions in the viewer's scope
 * with its filters and pages; the detail of one session in scope, OPEN with its running figures and
 * CLOSED with its report, NOT_FOUND outside it; the session link of the user page; each with exactly its
 * allowlisted props, and Operations refused.
 */
final class SessionPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** Rp50.000 a unit, counted whole. */
    private int $product = 0;

    private int $laci1 = 0;

    private int $laci2 = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '50000']);
        TokoContoh::stock($this->product, '20', '200000');
        $this->laci1 = TokoContoh::register('LACI-1', 'Laci 1');
        $this->laci2 = TokoContoh::register('LACI-2', 'Laci 2');
    }

    public function test_the_close_page_shows_the_users_own_open_session_and_its_preview_as_a_deferred_prop(): void
    {
        // RP-105, KS-07: without an OPEN session of the user's own, the till, which leads on to Buka sesi
        // kas; another user's session does not count.
        $session = TokoContoh::openSession('Rina', $this->laci1, '20000');

        foreach (['Hendra', 'Dewi'] as $person) {
            $this->as($person);
            $this->get('/till/close-session')->assertRedirect('/till');
        }

        // With one: the session's header, and the preview deferred (BR-CASH-13 step 1).
        TokoContoh::sell('Rina', $session, [self::line($this->product, '2')]);
        TokoContoh::sell('Rina', $session, [self::line($this->product, '1')], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
        $this->as('Rina');
        $page = $this->get('/till/close-session')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertPageChannel($page, 'cashier', $this->secrets());
        $object = $this->inertiaPage($page);
        $this->assertSame(['cash/CloseSession', ['default' => ['preview']]], [$object['component'] ?? null, $object['deferredProps'] ?? null]);
        $this->assertSame(['id' => $session, 'number' => $this->numberOf($session), 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1'], $this->propsOf($page)['session'] ?? null);

        // The preview, read when the page asks for it: the activity version the close compares, the
        // running figures with expected cash (G-05), BANK_TRANSFER apart, and the unresolved checkouts.
        $version = self::text($object['version'] ?? null);
        $preview = $this->previewOf($version);
        $this->assertSame([
            'sessionId' => $session,
            'activityVersion' => 2,
            'figures' => ['openingCash' => '20000', 'saleCount' => 2, 'saleValue' => '150000', 'cashPayments' => '100000', 'transferPayments' => '50000', 'drawerRefunds' => '0', 'expectedCash' => '120000'],
            'unresolvedCheckouts' => 0,
        ], $preview);

        // G-27: a checkout receipt of the user PENDING is counted, so that the page disables "Lanjut
        // hitung" (UXI-103), as the server refuses the close.
        app(RequestReceipts::class)->record($this->people['Rina']['id'], CheckoutSale::OPERATION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, app(Clock::class)->now()->format('Y-m-d H:i:s.uP'));
        $this->assertSame(1, $this->previewOf($version)['unresolvedCheckouts'] ?? null);

        // The preview's own channel, exactly its allowlist, for the cashier, the combined account and the
        // Owner, each in their own OPEN session (SEC-CH-01; review R-07).
        $this->assertDeferredChannel($this->previewReload($version), 'cashier', $this->secrets());
        TokoContoh::openSession('Dewi', $this->laci2);
        TokoContoh::openSession('Hendra', TokoContoh::register('LACI-3', 'Laci 3'));

        foreach (['Dewi' => 'combined', 'Hendra' => 'owner'] as $person => $actor) {
            $this->as($person);
            $this->assertPageChannel($this->get('/till/close-session')->assertOk(), $actor, $this->secrets());
            $this->assertDeferredChannel($this->previewReload($version), $actor, $this->secrets());
        }

        // The page takes only its path; Operations has no close (CAP-21).
        $this->as('Rina');
        $this->get('/till/close-session?sessionId='.$session)->assertStatus(422);
        $this->as('Agus');
        $this->get('/till/close-session')->assertStatus(403);
    }

    public function test_the_preview_read_again_is_null_once_the_session_closed_and_names_the_next_one_once_it_opened(): void
    {
        // UXP-105, F-6 (review R-05): the page's own read of its preview after the session closed — by the
        // user elsewhere or by the Owner — answers a null preview, which the page shows as UXS-30, where the
        // page loaded anew leads to the till; once a new session opened, the preview names that one, which
        // the page never counts under the header of the one it shows.
        $session = TokoContoh::openSession('Rina', $this->laci1);
        $this->as('Rina');
        $version = self::text($this->inertiaPage($this->get('/till/close-session')->assertOk())['version'] ?? null);
        $this->assertSame($session, $this->previewOf($version)['sessionId'] ?? null);

        TokoContoh::closeSession($session);
        $props = $this->propsOf($this->previewReload($version));
        $this->assertArrayHasKey('preview', $props);
        $this->assertNull($props['preview']);
        $this->get('/till/close-session')->assertRedirect('/till');

        $next = TokoContoh::openSession('Rina', $this->laci2);
        $this->assertSame($next, $this->previewOf($version)['sessionId'] ?? null);
    }

    public function test_the_sessions_list_holds_the_viewers_scope_newest_first_with_its_filters_and_pages(): void
    {
        [$closed, $dewi, $rina] = $this->sessions();

        // RP-205, BR-CASH-21: a cashier sees her own sessions alone, newest first by opening; a CLOSED
        // one with its close time and variance, its register as its report froze it (G-17).
        $this->as('Rina');
        $page = $this->get('/cash-sessions')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('cash/Sessions', $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'cashier', $this->secrets());
        $props = $this->propsOf($page);
        $this->assertSame([['status' => null, 'from' => null, 'to' => null, 'register' => null, 'responsible' => null, 'perPage' => 25], [], []], [$props['filters'] ?? null, $props['registers'] ?? null, $props['responsibles'] ?? null]);
        $this->assertSame([
            'rows' => [
                ['id' => $rina, 'number' => $this->numberOf($rina), 'status' => 'OPEN', 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1', 'responsible' => 'Rina', 'openedAt' => '2026-11-11T02:00:00.000000Z', 'closedAt' => null, 'variance' => null],
                ['id' => $closed, 'number' => $this->numberOf($closed), 'status' => 'CLOSED', 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1', 'responsible' => 'Rina', 'openedAt' => '2026-11-10T01:00:00.000000Z', 'closedAt' => '2026-11-10T09:00:00.000000Z', 'variance' => '-1000'],
            ],
            'nextCursor' => null,
            'previousCursor' => null,
        ], $props['sessions'] ?? null);

        // Her filters: the status and the days of Asia/Jakarta; the Owner's filters are not hers.
        $this->assertSame([$closed], $this->idsOf('status=CLOSED'));
        $this->assertSame([$rina], $this->idsOf('status=OPEN'));
        $this->assertSame([$rina], $this->idsOf('from=2026-11-11'));
        $this->assertSame([$closed], $this->idsOf('from=2026-11-10&to=2026-11-10'));
        $this->getJson('/cash-sessions?from=2026-11-11&to=2026-11-10')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('fields', ['to' => [['key' => 'ux.validation.date_order', 'params' => []]]]);

        foreach (['register='.$this->laci1, 'responsible='.$this->people['Rina']['id'], 'status=PAUSED', 'status=open', 'from=2026-13-01', 'to=11-11-2026', 'perPage=0', 'perPage=101', 'after=x', 'after=abc&before=abc', 'q=Rina', 'from_page=till'] as $query) {
            $this->getJson('/cash-sessions?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The combined account sees its own; the Owner every session, with the registers and the people
        // of the filters.
        $this->as('Dewi');
        $combined = $this->get('/cash-sessions')->assertOk();
        $this->assertPageChannel($combined, 'combined', $this->secrets());
        $this->assertSame([$dewi], $this->idsOf(''));

        $this->as('Hendra');
        $owner = $this->get('/cash-sessions')->assertOk();
        $this->assertPageChannel($owner, 'owner', $this->secrets());
        $props = $this->propsOf($owner);
        $this->assertSame([
            [['id' => $this->laci1, 'code' => 'LACI-1', 'label' => 'Laci 1'], ['id' => $this->laci2, 'code' => 'LACI-2', 'label' => 'Laci 2']],
            [['id' => $this->people['Dewi']['id'], 'displayName' => 'Dewi'], ['id' => $this->people['Rina']['id'], 'displayName' => 'Rina']],
        ], [$props['registers'] ?? null, $props['responsibles'] ?? null]);
        $this->assertSame([$rina, $dewi, $closed], $this->idsOf(''));
        $this->assertSame([$dewi], $this->idsOf('register='.$this->laci2));
        $this->assertSame([$rina, $closed], $this->idsOf('responsible='.$this->people['Rina']['id']));
        $this->assertSame([], $this->idsOf('responsible='.$this->people['Agus']['id']));

        // Pages of one, and back (TR-PAGE-01); a cursor read again under the scope (TR-PAGE-03). The page's
        // size stands among the filters, so that the page keeps it when it pages (NC-10).
        $this->assertSame(1, ((array) ($this->propsOf($this->get('/cash-sessions?perPage=1')->assertOk())['filters'] ?? []))['perPage'] ?? null);
        $first = $this->listOf('perPage=1');
        $this->assertSame([[$rina], null], [array_column($first['rows'], 'id'), $first['previousCursor']]);
        $second = $this->listOf('perPage=1&after='.self::text($first['nextCursor']));
        $this->assertSame([$dewi], array_column($second['rows'], 'id'));
        $third = $this->listOf('perPage=1&after='.self::text($second['nextCursor']));
        $this->assertSame([[$closed], null], [array_column($third['rows'], 'id'), $third['nextCursor']]);
        $back = $this->listOf('perPage=1&before='.self::text($third['previousCursor']));
        $this->assertSame([$dewi], array_column($back['rows'], 'id'));
        $this->getJson('/cash-sessions?after='.rtrim(strtr(base64_encode('["2026-02-30T00:00:00.000000Z",1]'), '+/', '-_'), '='))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        // Operations has no sessions (CAP-21, CAP-22).
        $this->as('Agus');
        $this->get('/cash-sessions')->assertStatus(403);
    }

    public function test_the_session_detail_shows_an_open_session_running_and_a_closed_one_as_its_report_in_scope_only(): void
    {
        [$closed, $dewi, $rina] = $this->sessions();
        TokoContoh::sell('Rina', $rina, [self::line($this->product, '1')], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-2']);

        // RP-206, UXI-139: an OPEN session of her own, its running figures — BANK_TRANSFER never in
        // expected cash — and the close for her alone; no count yet.
        $this->as('Rina');
        $page = $this->get('/cash-sessions/'.$rina)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('cash/Session', $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'cashier', $this->secrets());
        $props = $this->propsOf($page);
        $this->assertSame([
            ['id' => $rina, 'number' => $this->numberOf($rina), 'status' => 'OPEN', 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1', 'responsible' => 'Rina', 'openedAt' => '2026-11-11T02:00:00.000000Z', 'closedAt' => null, 'canClose' => true],
            ['openingCash' => '0', 'saleCount' => 1, 'saleValue' => '50000', 'cashPayments' => '0', 'transferPayments' => '50000', 'drawerRefunds' => '0', 'expectedCash' => '0'],
            ['physicalCash' => null, 'variance' => null, 'closedBy' => null, 'closeReason' => null, 'notes' => null],
        ], [$props['session'] ?? null, $props['figures'] ?? null, $props['count'] ?? null]);

        // A CLOSED one shows its immutable report (BR-CASH-19, BR-CASH-20): the figures and the count as
        // they were frozen, never a cost.
        $report = $this->propsOf($this->get('/cash-sessions/'.$closed)->assertOk());
        $this->assertSame([
            ['CLOSED', '2026-11-10T09:00:00.000000Z', false],
            ['openingCash' => '20000', 'saleCount' => 1, 'saleValue' => '50000', 'cashPayments' => '50000', 'transferPayments' => '0', 'drawerRefunds' => '0', 'expectedCash' => '70000'],
            ['physicalCash' => '69000', 'variance' => '-1000', 'closedBy' => 'Rina', 'closeReason' => null, 'notes' => null],
        ], [[((array) ($report['session'] ?? []))['status'] ?? null, ((array) ($report['session'] ?? []))['closedAt'] ?? null, ((array) ($report['session'] ?? []))['canClose'] ?? null], $report['figures'] ?? null, $report['count'] ?? null]);

        // SEC-AZ-03, TR-ERR-03: another user's session and a guessed one are the same NOT_FOUND; the page
        // takes only its path.
        $this->getJson('/cash-sessions/'.$dewi)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach (['999999', '0', 'abc', '-1'] as $guess) {
            $this->getJson('/cash-sessions/'.$guess)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->get('/cash-sessions/'.$rina.'?from_page=till')->assertStatus(422);

        // The combined account, a holder of CAP-21 as she is, sees its own sessions alone (review R-15).
        $this->as('Dewi');
        $this->getJson('/cash-sessions/'.$rina)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        // The Owner sees every session, and closes none here: an own close is the responsible user's
        // (RC-03); the Owner's sections are TASK-032's.
        $this->as('Hendra');
        $owner = $this->get('/cash-sessions/'.$rina)->assertOk();
        $this->assertPageChannel($owner, 'owner', $this->secrets());
        $this->assertFalse(((array) ($this->propsOf($owner)['session'] ?? []))['canClose'] ?? null);
        $this->assertPageChannel($this->get('/cash-sessions/'.$dewi)->assertOk(), 'owner', $this->secrets());

        $this->as('Agus');
        $this->get('/cash-sessions/'.$rina)->assertStatus(403);
    }

    public function test_the_user_page_names_the_accounts_open_session(): void
    {
        // UXI-340, UXP-604: the Owner's page of an account names its OPEN cash session, which RP-206 opens;
        // none without one.
        [, , $rina] = $this->sessions();
        $this->as('Hendra');

        $page = $this->get('/settings/users/'.$this->people['Rina']['id'])->assertOk();
        $this->assertPageChannel($page, 'owner', $this->secrets());
        $this->assertSame(['id' => $rina], $this->propsOf($page)['openSession'] ?? null);
        $this->assertSame(['id' => null], $this->propsOf($this->get('/settings/users/'.$this->people['Agus']['id'])->assertOk())['openSession'] ?? null);
        $this->get('/cash-sessions/'.$rina)->assertOk();
    }

    /**
     * Rina's session on LACI-1 of 10 November, Rp20.000 opening, one Sale in CASH and Rp69.000 counted;
     * Dewi's OPEN one on LACI-2 and Rina's OPEN one on LACI-1, both of 11 November. Through the
     * Actions, before anyone signs in, at times of their own.
     *
     * @return array{int, int, int}
     */
    private function sessions(): array
    {
        $this->travelTo('2026-11-10T01:00:00.000000Z');
        $closed = TokoContoh::openSession('Rina', $this->laci1, '20000');
        TokoContoh::sell('Rina', $closed, [self::line($this->product, '1')]);
        $this->travelTo('2026-11-10T09:00:00.000000Z');
        TokoContoh::closeSession($closed, '69000');
        $this->travelTo('2026-11-11T01:00:00.000000Z');
        $dewi = TokoContoh::openSession('Dewi', $this->laci2);
        $this->travelTo('2026-11-11T02:00:00.000000Z');
        $rina = TokoContoh::openSession('Rina', $this->laci1);
        $this->travelTo('2026-11-11T03:00:00.000000Z');

        return [$closed, $dewi, $rina];
    }

    /**
     * The page's own read of its close preview, by name, as the page reloads it: the page's component,
     * and never its header.
     *
     * @return TestResponse<Response>
     */
    private function previewReload(string $version): TestResponse
    {
        $reload = $this->get('/till/close-session', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'cash/CloseSession',
            'X-Inertia-Partial-Data' => 'preview',
        ])->assertOk();
        $this->assertSame('cash/CloseSession', $this->inertiaPage($reload)['component'] ?? null);
        $this->assertArrayNotHasKey('session', $this->propsOf($reload));

        return $reload;
    }

    /**
     * The close preview, as the page reloads it by name.
     *
     * @return array<array-key, mixed>
     */
    private function previewOf(string $version): array
    {
        return (array) ($this->propsOf($this->previewReload($version))['preview'] ?? null);
    }

    /**
     * One page of RP-205 for this query string, as the viewer reads it.
     *
     * @return array{rows: list<array<array-key, mixed>>, nextCursor: mixed, previousCursor: mixed}
     */
    private function listOf(string $query): array
    {
        $sessions = (array) ($this->propsOf($this->get('/cash-sessions'.($query === '' ? '' : '?'.$query))->assertOk())['sessions'] ?? []);
        $rows = [];

        foreach ((array) ($sessions['rows'] ?? []) as $row) {
            $rows[] = (array) $row;
        }

        return ['rows' => $rows, 'nextCursor' => $sessions['nextCursor'] ?? null, 'previousCursor' => $sessions['previousCursor'] ?? null];
    }

    /**
     * The sessions of one page of RP-205, by internal key.
     *
     * @return list<mixed>
     */
    private function idsOf(string $query): array
    {
        return array_column($this->listOf($query)['rows'], 'id');
    }

    private function numberOf(int $session): string
    {
        return self::text($this->watcher()->table('cash_sessions')->where('id', $session)->value('session_number'));
    }

    /**
     * Every person's password, which no page may carry.
     *
     * @return list<string>
     */
    private function secrets(): array
    {
        return array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
    }

    /**
     * A line of RC-01.
     *
     * @return array{productId: int, quantity: string}
     */
    private static function line(int $product, string $quantity): array
    {
        return ['productId' => $product, 'quantity' => $quantity];
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
