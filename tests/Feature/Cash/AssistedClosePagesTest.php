<?php

namespace Tests\Feature\Cash;

use App\Actions\Sales\CheckoutSale;
use App\Domain\Commands\RequestReceipts;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The pages of TASK-032 (AC-032-02, AC-032-05; ROUTE_CONTRACTS RP-207, RP-206; NAVIGATION_CONTRACTS UXP-207,
 * UXP-206; INTERACTION_INVENTORY UXI-140, UXI-144, UXI-149; INTERACTION_CONTRACTS section 2.5; SECURITY_MODEL
 * SEC-AZ-03, SEC-CH-01; BR-CASH-16, BR-CASH-19; G-27; EXECUTION_CONTEXT section 10, a route and a page;
 * suites AUTH01, AUTH02, SESSION07 and SESSION10; TASK-032 section 7, L4): RP-207 for the Owner on another
 * user's OPEN session, its header and its preview as a deferred prop with the responsible user's unresolved
 * checkouts by intent; the Owner's own session and a CLOSED one led to RP-206, a missing one NOT_FOUND;
 * the preview read again null once the session closed; RP-206's review notes, the Owner's deferred prop of a
 * CLOSED session; each with exactly its allowlisted props, and every other role refused.
 */
final class AssistedClosePagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** Rp50.000 a unit, counted whole. */
    private int $product = 0;

    private int $register = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->product = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '50000']);
        TokoContoh::stock($this->product, '20', '200000');
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
    }

    public function test_the_assisted_close_page_shows_another_users_open_session_and_its_preview_as_a_deferred_prop(): void
    {
        $session = TokoContoh::openSession('Rina', $this->register, '20000');
        TokoContoh::sell('Rina', $session, [['productId' => $this->product, 'quantity' => '1']]);
        $path = '/cash-sessions/'.$session.'/assisted-close';

        // RP-207: the header with the responsible user, and the preview deferred (BR-CASH-13 step 1).
        $this->as('Hendra');
        $page = $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertPageChannel($page, 'owner', $this->secrets());
        $object = $this->inertiaPage($page);
        $this->assertSame(['cash/AssistedClose', ['default' => ['preview']]], [$object['component'] ?? null, $object['deferredProps'] ?? null]);
        $this->assertSame(['id' => $session, 'number' => $this->numberOf($session), 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1', 'responsible' => 'Rina'], $this->propsOf($page)['session'] ?? null);

        $version = self::text($object['version'] ?? null);
        $this->assertSame([
            'sessionId' => $session,
            'activityVersion' => 1,
            'figures' => ['openingCash' => '20000', 'saleCount' => 1, 'saleValue' => '50000', 'cashPayments' => '50000', 'transferPayments' => '0', 'drawerRefunds' => '0', 'expectedCash' => '70000'],
            'unresolvedCheckouts' => [],
        ], $this->previewOf($path, $version));

        // G-27, ADR-009 section 4: her checkout receipts PENDING or HELD are listed by intent, one row each
        // however many receipts it has, oldest first, never with a payload; the preview's own channel.
        $receipts = app(RequestReceipts::class);
        $rina = $this->people['Rina']['id'];
        $intent = (string) Str::uuid();
        $receipts->record($rina, CheckoutSale::OPERATION, $intent, (string) Str::uuid(), $this->epoch, '2026-11-13 03:00:00+00');
        $receipts->record($rina, CheckoutSale::OPERATION, $intent, (string) Str::uuid(), $this->epoch, '2026-11-13 03:00:05+00');
        // Another operation of hers, and another user's checkout, are not hers to resolve here.
        $receipts->record($rina, 'cash_session.open', (string) Str::uuid(), (string) Str::uuid(), $this->epoch, '2026-11-13 03:00:00+00');
        $receipts->record($this->people['Dewi']['id'], CheckoutSale::OPERATION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, '2026-11-13 03:00:00+00');

        $this->assertSame([['sourceIntentId' => $intent, 'receivedAt' => '2026-11-13T03:00:00.000000Z']], $this->previewOf($path, $version)['unresolvedCheckouts'] ?? null);
        $this->assertDeferredChannel($this->previewReload($path, $version), 'owner', $this->secrets());

        // The page takes only its path.
        $this->get($path.'?reason=x')->assertStatus(422);
    }

    public function test_the_owners_own_session_and_a_closed_one_lead_to_the_report_and_a_missing_one_is_not_found(): void
    {
        // ROUTE_CONTRACTS RP-207: otherwise 302 to RP-206; SEC-AZ-03, TR-ERR-03 for a guessed session.
        $own = TokoContoh::openSession('Hendra', TokoContoh::register('LACI-H', 'Laci H'));
        $closed = TokoContoh::closeSession(TokoContoh::openSession('Rina', $this->register));
        $this->as('Hendra');

        $this->get('/cash-sessions/'.$own.'/assisted-close')->assertRedirect('/cash-sessions/'.$own);
        $this->get('/cash-sessions/'.$closed.'/assisted-close')->assertRedirect('/cash-sessions/'.$closed);

        foreach (['999999', '0', 'abc'] as $guess) {
            $this->getJson('/cash-sessions/'.$guess.'/assisted-close')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        // UXS-30: the page's own read of its preview after the session closed answers a null preview, where
        // the page loaded anew leads to the report.
        $session = TokoContoh::openSession('Rina', $this->register);
        $path = '/cash-sessions/'.$session.'/assisted-close';
        $version = self::text($this->inertiaPage($this->get($path)->assertOk())['version'] ?? null);
        TokoContoh::closeSession($session);
        $props = $this->propsOf($this->previewReload($path, $version));
        $this->assertArrayHasKey('preview', $props);
        $this->assertNull($props['preview']);
        $this->get($path)->assertRedirect('/cash-sessions/'.$session);

        // CAP-23 is the Owner's alone.
        $open = TokoContoh::openSession('Rina', $this->register);

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->get('/cash-sessions/'.$open.'/assisted-close')->assertStatus(403);
        }
    }

    public function test_the_owner_reads_the_review_notes_of_a_closed_session_as_a_deferred_prop_and_nobody_else_does(): void
    {
        // UXI-144; BR-CASH-19, D-069 (f): every note in order, with its text, its time and its author's
        // frozen name, read apart; none for an OPEN session; never for a cashier, even of her own session.
        $session = TokoContoh::closeSession(TokoContoh::openSession('Rina', $this->register));
        $this->travelTo('2026-11-13T04:00:00.000000Z');
        $first = TokoContoh::reviewNote($session, "Selisih dijelaskan.\nBaris kedua.");
        $this->travelTo('2026-11-13T04:30:00.000000Z');
        $second = TokoContoh::reviewNote($session, 'Koreksi catatan pertama.');

        $this->as('Hendra');
        $page = $this->get('/cash-sessions/'.$session)->assertOk();
        $this->assertPageChannel($page, 'owner', $this->secrets());
        $object = $this->inertiaPage($page);
        $this->assertSame(['default' => ['notes']], $object['deferredProps'] ?? null);

        $reload = $this->notesReload($session, self::text($object['version'] ?? null));
        $this->assertDeferredChannel($reload, 'owner', $this->secrets());
        $this->assertSame([
            ['id' => $first, 'text' => "Selisih dijelaskan.\nBaris kedua.", 'recordedAt' => '2026-11-13T04:00:00.000000Z', 'author' => 'Hendra'],
            ['id' => $second, 'text' => 'Koreksi catatan pertama.', 'recordedAt' => '2026-11-13T04:30:00.000000Z', 'author' => 'Hendra'],
        ], $this->propsOf($reload)['notes'] ?? null);

        // An OPEN session has no notes to read.
        $open = TokoContoh::openSession('Rina', $this->register);
        $this->assertArrayNotHasKey('deferredProps', $this->inertiaPage($this->get('/cash-sessions/'.$open)->assertOk()));

        // Her own CLOSED session, for her: no notes, and the page as TASK-030 gave it.
        $this->as('Rina');
        $hers = $this->get('/cash-sessions/'.$session)->assertOk();
        $this->assertPageChannel($hers, 'cashier', $this->secrets());
        $this->assertArrayNotHasKey('deferredProps', $this->inertiaPage($hers));
        $this->assertArrayNotHasKey('notes', $this->propsOf($this->notesReload($session, self::text($this->inertiaPage($hers)['version'] ?? null))));
    }

    /**
     * The page's own read of its close preview, by name, as the page reloads it.
     *
     * @return TestResponse<Response>
     */
    private function previewReload(string $path, string $version): TestResponse
    {
        $reload = $this->get($path, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'cash/AssistedClose',
            'X-Inertia-Partial-Data' => 'preview',
        ])->assertOk();
        $this->assertSame('cash/AssistedClose', $this->inertiaPage($reload)['component'] ?? null);
        $this->assertArrayNotHasKey('session', $this->propsOf($reload));

        return $reload;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function previewOf(string $path, string $version): array
    {
        return (array) ($this->propsOf($this->previewReload($path, $version))['preview'] ?? null);
    }

    /**
     * RP-206's own read of its review notes, by name, as the page reloads it after a save.
     *
     * @return TestResponse<Response>
     */
    private function notesReload(int $session, string $version): TestResponse
    {
        return $this->get('/cash-sessions/'.$session, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'cash/Session',
            'X-Inertia-Partial-Data' => 'notes',
        ])->assertOk();
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
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
