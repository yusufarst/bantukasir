<?php

namespace Tests\Feature\Sales;

use Illuminate\Database\Connection;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The pages of TASK-029 (AC-029-03, AC-029-04, AC-029-06; ROUTE_CONTRACTS RN-01, RP-101 to RP-104;
 * NAVIGATION_CONTRACTS NC-12, UXP-101 to UXP-103; SECURITY_MODEL SEC-AZ-03, SEC-AZ-06, SEC-CH-01;
 * EXECUTION_CONTEXT section 10, a route and a page; suites AUTH01, AUTH02, POS03, POS04; TASK-029
 * section 7, L4): the till and its payment view for every holder of CAP-21 in their own OPEN session,
 * with exactly their allowlisted props, and Buka sesi kas without one; the receipt of a Sale from its
 * snapshot, for its own cashier and the Owner alone, never a cost, the same NOT_FOUND for a guessed Sale
 * and another cashier's; each page taking only its path; and RC-01's result leading to the receipt.
 */
final class TillPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_the_till_and_its_payment_view_open_in_the_users_own_session_with_their_allowlisted_props(): void
    {
        // RP-101, RP-102; AC-029-06: the session's header, the onboarding freeze off and the active
        // categories, for the Owner, a cashier and the combined account, each in its own session.
        $category = TokoContoh::category('Alat Tulis');
        $secrets = array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
        $registers = ['Hendra' => 'Laci 1', 'Rina' => 'Laci 2', 'Dewi' => 'Laci 3'];
        $sessions = [];

        foreach ($registers as $person => $label) {
            $sessions[$person] = TokoContoh::openSession($person, TokoContoh::register('LACI-'.$person, $label));
        }

        foreach (['Hendra' => 'owner', 'Rina' => 'cashier', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $number = self::text($this->watcher()->table('cash_sessions')->where('id', $sessions[$person])->value('session_number'));

            foreach (['/till' => 'till', '/till/payment' => 'payment'] as $path => $view) {
                $page = $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
                $this->assertSame('sales/Till', $this->inertiaPage($page)['component'] ?? null);
                $this->assertPageChannel($page, $actor, $secrets);
                $props = $this->propsOf($page);
                $this->assertSame(
                    [$view, ['id' => $sessions[$person], 'number' => $number, 'register' => $registers[$person]], false, [['id' => $category, 'name' => 'Alat Tulis', 'active' => true, 'version' => 1]]],
                    [$props['view'] ?? null, $props['session'] ?? null, $props['postingFrozen'] ?? null, $props['categories'] ?? null],
                    $person.' '.$path,
                );
            }

            // Each page takes only its path (TR-HTTP-02); nothing of a cart reaches the server.
            $this->get('/till?q=pulpen')->assertStatus(422);
            $this->get('/till/payment?total=1000')->assertStatus(422);
        }

        // Operations has no till (CAP-21).
        $this->as('Agus');
        $this->get('/till')->assertStatus(403);
        $this->get('/till/payment')->assertStatus(403);
    }

    public function test_without_an_open_session_the_till_leads_to_buka_sesi_kas_and_with_one_the_homes_lead_to_the_till(): void
    {
        // UXI-002, RP-101: without an OPEN session of the user's own, Buka sesi kas; another user's
        // session does not count. NC-12: with one, a cashier and the combined account go home to Kasir.
        TokoContoh::openSession('Hendra', TokoContoh::register('LACI-1', 'Laci 1'));

        foreach (['Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->get('/till')->assertRedirect('/till/open-session');
            $this->get('/till/payment')->assertRedirect('/till/open-session');
        }

        TokoContoh::openSession('Rina', TokoContoh::register('LACI-2', 'Laci 2'));
        TokoContoh::openSession('Dewi', TokoContoh::register('LACI-3', 'Laci 3'));

        foreach (['Rina' => '/till', 'Dewi' => '/till', 'Hendra' => '/account'] as $person => $home) {
            $this->as($person);
            $this->get('/')->assertRedirect($home);
            $this->get('/till/open-session')->assertRedirect('/till');
        }
    }

    public function test_the_receipt_shows_the_snapshot_to_its_cashier_and_the_owner_and_to_nobody_else(): void
    {
        // RP-103, DC-01, BR-SALE-14, BR-DOC-01: Rina's Sale in cash with a customer's name, and Dewi's by
        // transfer; each receipt from its snapshot with the shop identity in force at the Sale; its
        // cashier and the Owner see it, another cashier gets NOT_FOUND, as for a guessed Sale.
        $pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '4500']);
        $paper = TokoContoh::product('KADO-01', ['categoryId' => TokoContoh::category('Kertas'), 'name' => 'Kertas kado per meter', 'baseUnit' => 'm', 'quantityPrecision' => 1, 'sellingPrice' => '7000']);
        TokoContoh::stock($pen, '10', '20000');
        TokoContoh::stock($paper, '10', '20000');
        $rinaSession = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $dewiSession = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
        $cash = TokoContoh::sell('Rina', $rinaSession, [['productId' => $pen, 'quantity' => '2'], ['productId' => $paper, 'quantity' => '2.5']], ['method' => 'CASH', 'tendered' => '30000', 'customerName' => 'Bu Sari']);
        $transfer = TokoContoh::sell('Dewi', $dewiSession, [['productId' => $pen, 'quantity' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);
        $secrets = array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
        $row = (array) $this->watcher()->table('receipt_snapshots')->where('sale_id', $cash)->sole();
        $number = self::text($row['sale_number']);
        $shop = (array) $this->watcher()->table('business_profile_revisions')->where('id', $row['business_profile_revision_id'])->sole();

        foreach (['Rina' => 'cashier', 'Hendra' => 'owner'] as $person => $actor) {
            $this->as($person);
            $page = $this->get('/sales/'.$cash.'/receipt')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame('sales/Receipt', $this->inertiaPage($page)['component'] ?? null);
            $this->assertPageChannel($page, $actor, $secrets);
            $props = $this->propsOf($page);
            $receipt = (array) ($props['receipt'] ?? []);
            $this->assertSame(
                [$cash, $number, 'Rina', 'CASH', '26500', '30000', '3500', 'Bu Sari'],
                [$receipt['saleId'] ?? null, $receipt['number'] ?? null, $receipt['cashier'] ?? null, $receipt['method'] ?? null, $receipt['total'] ?? null, $receipt['tendered'] ?? null, $receipt['change'] ?? null, $receipt['customerName'] ?? null],
                $person,
            );
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', self::text($receipt['issuedAt'] ?? null));
            $this->assertSame([
                ['lineNo' => '1', 'sku' => 'PEN-01', 'name' => 'Pulpen Hitam', 'unit' => 'pcs', 'quantity' => '2', 'unitPrice' => '4500', 'lineTotal' => '9000'],
                ['lineNo' => '2', 'sku' => 'KADO-01', 'name' => 'Kertas kado per meter', 'unit' => 'm', 'quantity' => '2.5', 'unitPrice' => '7000', 'lineTotal' => '17500'],
            ], $receipt['lines'] ?? null);
            $this->assertSame($shop['shop_name'], ((array) ($props['issuer'] ?? []))['name'] ?? null);
            // The shell keeps its shared shop, with its initials and accent, beside the receipt's issuer.
            $this->assertSame(['name', 'initials', 'accent'], array_keys((array) ($props['shop'] ?? [])), $person);
        }

        // The transfer's receipt carries no cash amounts (DC-01 element 12), to its cashier.
        $this->as('Dewi');
        $receipt = (array) ($this->propsOf($this->get('/sales/'.$transfer.'/receipt')->assertOk())['receipt'] ?? []);
        $this->assertSame(['BANK_TRANSFER', '4500', null, null, null], [$receipt['method'] ?? null, $receipt['total'] ?? null, $receipt['tendered'] ?? null, $receipt['change'] ?? null, $receipt['customerName'] ?? null]);

        // SEC-AZ-03, TR-ERR-03: another cashier's Sale and a guessed one are the same NOT_FOUND.
        $this->getJson('/sales/'.$cash.'/receipt')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach (['999999', '0', 'abc', '-1'] as $guess) {
            $this->getJson('/sales/'.$guess.'/receipt')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        // The page takes only its path; Operations has no receipt (CAP-21, CAP-22).
        $this->get('/sales/'.$transfer.'/receipt?size=58')->assertStatus(422);
        $this->as('Agus');
        $this->get('/sales/'.$cash.'/receipt')->assertStatus(403);
    }

    public function test_a_checkout_leads_to_its_receipt_and_a_status_check_does_too(): void
    {
        // AC-029-03, AC-029-04; RC-01's result (INTERACTION_CONTRACTS RC-01; NC-08, NC-13 (e)): the answer
        // that committed the Sale and the status check of its intent both lead to RP-103, which opens.
        $pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'sellingPrice' => '4500']);
        TokoContoh::stock($pen, '10', '20000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $revision = self::number($this->watcher()->table('products')->where('id', $pen)->value('current_price_revision_id'));
        $this->as('Rina');
        $envelope = [
            ...$this->envelope(),
            'operation' => 'sale.checkout',
            'expected' => ['lines' => [['priceRevisionId' => $revision]]],
            'payload' => ['sessionId' => $session, 'lines' => [['productId' => $pen, 'quantity' => '1']], 'payment' => ['method' => 'CASH', 'tendered' => '5000', 'customerName' => null]],
        ];

        $committed = $this->postJson('/commands/sale.checkout', $envelope)->assertStatus(201);
        $location = self::text($committed->json('result.location'));
        $this->assertSame('/sales/'.self::number($committed->json('result.id')).'/receipt', $location);
        $this->check($envelope)->assertOk()->assertJsonPath('result.location', $location);
        $this->get($location)->assertOk();
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
