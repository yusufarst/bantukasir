<?php

namespace Tests\Feature\Sales;

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
 * The pages of TASK-034 (AC-034-01, AC-034-02, AC-034-07; ROUTE_CONTRACTS RP-202 to RP-207, RP-206P, RC-46;
 * INTERACTION_INVENTORY UXI-101, UXI-118, UXI-120, UXI-122, UXI-124, UXI-133, UXI-143, UXI-149; DOCUMENT_CONTRACTS
 * DC-02 row 8, DC-03; SECURITY_MODEL SEC-AZ-03, SEC-CH-01; BR-CASH-20, BR-RFD-03, BR-RFD-07, BR-RFD-12; D-068;
 * EXECUTION_CONTEXT section 10, a route and a page; suites AUTH01, AUTH02, RET02, SESSION04; TASK-034 section 7,
 * L4): RP-203 with the Sale's lines that something remains on and every OPEN session with its expected cash;
 * RP-204 with the committed correction and its drawer; RP-202 with the Owner's linked corrections and whether
 * anything remains, the cashier's as it was; and the drawer-funded refunds of a session in its close
 * previews, beside its report's variance, in its print and in its CSV — each with exactly its allowlisted
 * props, and NOT_FOUND outside the Sale.
 */
final class RefundPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** Counted whole: Rp30.000. */
    private int $pen = 0;

    /** Rina's session, opened with Rp50.000, whose drawer pays. */
    private int $drawer = 0;

    /** Dewi's session, where the Sale is made. */
    private int $other = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'name' => 'Pulpen Hitam', 'sellingPrice' => '30000']);
        TokoContoh::stock($this->pen, '20', '100000');
        $this->drawer = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'), '50000');
        $this->other = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
    }

    public function test_rp_203_shows_what_remains_on_each_line_and_every_open_session_with_its_expected_cash(): void
    {
        // UXI-122, UXI-124; BR-RFD-03, BR-RFD-07; G-05: after Rp40.000 of Rp60.000 refunded and both pens back,
        // the line keeps Rp20.000 and no goods; Rina's drawer paid Rp30.000 of its Rp50.000, and Dewi's holds
        // her Sale's Rp60.000. A Sale with nothing left shows no line; a missing one is NOT_FOUND.
        [$sale, $line] = $this->corrected();
        $this->as('Hendra');
        $page = $this->get('/sales/'.$sale.'/refund')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('sales/Refund', $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'owner', $this->secrets());
        $props = $this->propsOf($page);

        $this->assertSame(['id' => $sale, 'number' => $this->numberOf($sale)], $props['sale'] ?? null);
        $this->assertSame([['saleLineId' => $line, 'lineNo' => 1, 'name' => 'Pulpen Hitam', 'sku' => 'PEN-01', 'unit' => 'pcs', 'quantityPrecision' => 0, 'quantity' => '2', 'lineTotal' => '60000', 'remainingAmount' => '20000', 'remainingQuantity' => '0']], $props['lines'] ?? null);
        $this->assertSame([
            ['id' => $this->drawer, 'number' => $this->sessionNumber($this->drawer), 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1', 'responsible' => 'Rina', 'expectedCash' => '20000'],
            ['id' => $this->other, 'number' => $this->sessionNumber($this->other), 'registerCode' => 'LACI-2', 'registerLabel' => 'Laci 2', 'responsible' => 'Dewi', 'expectedCash' => '60000'],
        ], $props['sessions'] ?? null);

        $done = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->pen, 'quantity' => '1']]);
        TokoContoh::correct($done, [['saleLineId' => TokoContoh::saleLinesOf($done)[0] ?? 0, 'refundAmount' => '30000', 'returnedSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-2']);
        $this->assertSame([], $this->propsOf($this->get('/sales/'.$done.'/refund')->assertOk())['lines'] ?? null);

        foreach (['/sales/999999/refund', '/sales/abc/refund'] as $path) {
            $this->getJson($path)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->getJson('/sales/'.$sale.'/refund?sessionId=1')->assertStatus(422);
    }

    public function test_rp_204_shows_the_committed_correction_with_the_drawer_its_cash_is_taken_from(): void
    {
        // UXI-133; D-046, D-068: the drawer-funded refund with its session and register, its line's money and
        // goods, its reason and the Owner; a correction of another Sale, like a guessed one, is NOT_FOUND.
        [$sale, $line, $first] = $this->corrected();
        $this->as('Hendra');
        $page = $this->get('/sales/'.$sale.'/corrections/'.$first)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('sales/RefundRecorded', $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'owner', $this->secrets());
        $props = $this->propsOf($page);

        $this->assertSame(['id' => $sale, 'number' => $this->numberOf($sale)], $props['sale'] ?? null);
        $this->assertSame([
            'id' => $first,
            'recordedAt' => '2026-11-14T04:00:00.000000Z',
            'reason' => 'Uang dikembalikan dari laci',
            'actor' => 'Hendra',
            'refund' => ['amount' => '30000', 'method' => 'CASH', 'fundingSource' => 'DRAWER', 'reference' => $this->numberOf($sale).'/1', 'drawer' => ['sessionId' => $this->drawer, 'sessionNumber' => $this->sessionNumber($this->drawer), 'registerCode' => 'LACI-1', 'registerLabel' => 'Laci 1']],
            'lines' => [['saleLineId' => $line, 'lineNo' => 1, 'name' => 'Pulpen Hitam', 'unit' => 'pcs', 'quantityPrecision' => 0, 'refundAmount' => '30000', 'returnedSaleable' => '1', 'returnedNotSaleable' => '0']],
        ], $props['correction'] ?? null);

        $another = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->pen, 'quantity' => '1']]);
        $this->getJson('/sales/'.$another.'/corrections/'.$first)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/sales/'.$sale.'/corrections/999999')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/sales/'.$sale.'/corrections/'.$first.'?from_page=1')->assertStatus(422);
    }

    public function test_rp_202_gives_the_owner_the_linked_corrections_and_whether_anything_remains(): void
    {
        // UXI-118, UXI-120; BR-RFD-03, BR-RFD-12; SEC-AZ-03: the Owner's three corrections in their order — the
        // drawer-funded refund with its session, the bank transfer with its reference, the damaged return
        // without money — and the Sale still refundable; the combined account that made it sees none of them;
        // a Sale with nothing left is no longer refundable.
        [$sale, $line, $first, $second, $third] = $this->corrected();
        $this->as('Hendra');
        $page = $this->get('/sales/'.$sale)->assertOk();
        $this->assertPageChannel($page, 'owner', $this->secrets(), 'corrections');
        $props = $this->propsOf($page);
        $corrections = array_map(static fn (mixed $correction): array => (array) $correction, (array) ($props['corrections'] ?? []));

        $this->assertTrue($props['refundable'] ?? null);
        $this->assertSame([$first, $second, $third], array_column($corrections, 'id'));
        $this->assertSame(['amount' => '10000', 'method' => 'BANK_TRANSFER', 'fundingSource' => null, 'reference' => 'TRF-9', 'drawer' => null], $corrections[1]['refund'] ?? null);
        $return = $corrections[2] ?? [];
        $this->assertSame([true, null], [array_key_exists('refund', $return), $return['refund'] ?? null]);
        $this->assertSame([['saleLineId' => $line, 'lineNo' => 1, 'name' => 'Pulpen Hitam', 'unit' => 'pcs', 'quantityPrecision' => 0, 'refundAmount' => '0', 'returnedSaleable' => '0', 'returnedNotSaleable' => '1']], $return['lines'] ?? null);

        $this->as('Dewi');
        $hers = $this->get('/sales/'.$sale)->assertOk();
        $this->assertPageChannel($hers, 'combined', $this->secrets());
        $this->assertArrayNotHasKey('corrections', $this->propsOf($hers));

        $done = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->pen, 'quantity' => '1']]);
        TokoContoh::correct($done, [['saleLineId' => TokoContoh::saleLinesOf($done)[0] ?? 0, 'refundAmount' => '30000', 'returnedNotSaleable' => '1']], ['method' => 'CASH', 'reference' => 'Tunai dari Pemilik']);
        $this->as('Hendra');
        $this->assertFalse($this->propsOf($this->get('/sales/'.$done)->assertOk())['refundable'] ?? null);
    }

    public function test_a_sessions_drawer_funded_refunds_stand_in_its_previews_beside_its_variance_in_its_print_and_in_its_csv(): void
    {
        // UXI-101, UXI-149, UXI-143; DC-02 row 8, DC-03; BR-CASH-20; D-068: Rina's drawer paid Rp30.000 at 11:00
        // in Asia/Jakarta. Her close preview and the Owner's list it with its amount, Sale and time; she counts
        // Rp40.000 against Rp20.000 expected, the cash still in the drawer, a variance of +Rp20.000; her report
        // keeps the refund apart with its reference and the Owner, on the page, in the print and in the CSV.
        [$sale] = $this->corrected();
        $number = $this->numberOf($sale);
        $row = ['saleNumber' => $number, 'amount' => '30000', 'recordedAt' => '2026-11-14T04:00:00.000000Z'];

        $this->as('Rina');
        $close = $this->get('/till/close-session')->assertOk();
        $preview = $this->deferredOf('/till/close-session', 'cash/CloseSession', self::text($this->inertiaPage($close)['version'] ?? null));
        $this->assertDeferredChannel($preview, 'cashier', $this->secrets(), 'refunds');
        $read = (array) ($this->propsOf($preview)['preview'] ?? []);
        $this->assertSame([['openingCash' => '50000', 'saleCount' => 0, 'saleValue' => '0', 'cashPayments' => '0', 'transferPayments' => '0', 'drawerRefunds' => '30000', 'expectedCash' => '20000'], [$row]], [$read['figures'] ?? null, $read['drawerRefunds'] ?? null]);

        // The Owner's assisted close of her session lists it too, beside her unresolved checkout.
        app(RequestReceipts::class)->record($this->people['Rina']['id'], CheckoutSale::OPERATION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, '2026-11-14 05:00:00+00');
        $this->as('Hendra');
        $path = '/cash-sessions/'.$this->drawer.'/assisted-close';
        $assisted = $this->deferredOf($path, 'cash/AssistedClose', self::text($this->inertiaPage($this->get($path)->assertOk())['version'] ?? null));
        $this->assertDeferredChannel($assisted, 'owner', $this->secrets(), 'refunds');
        $this->assertSame([$row], ((array) ($this->propsOf($assisted)['preview'] ?? []))['drawerRefunds'] ?? null);

        // Settled by the Owner's status check, the checkout no longer holds her close; she counts Rp40.000.
        $this->postJson('/commands/resolve', ['operation' => CheckoutSale::OPERATION, 'sourceIntentId' => self::text($this->watcher()->table('command_request_receipts')->where('actor_user_id', $this->people['Rina']['id'])->value('source_intent_id')), 'recoveryEpoch' => $this->epoch])->assertOk();
        TokoContoh::closeSession($this->drawer, '40000');
        $frozen = ['saleNumber' => $number, 'reference' => $number.'/1', 'amount' => '30000', 'recordedAt' => '2026-11-14T04:00:00.000000Z', 'owner' => 'Hendra'];

        foreach (['Hendra' => 'owner', 'Rina' => 'cashier'] as $person => $actor) {
            $this->as($person);
            $detail = $this->get('/cash-sessions/'.$this->drawer)->assertOk();
            $this->assertPageChannel($detail, $actor, $this->secrets(), 'refunds');
            $this->assertSame([[$frozen], '20000'], [$this->propsOf($detail)['drawerRefunds'] ?? null, ((array) ($this->propsOf($detail)['count'] ?? []))['variance'] ?? null]);
        }

        // RP-206P and RC-46 from the snapshot (DC-02 row 8; DC-03).
        $this->as('Hendra');
        $print = self::number($this->postJson('/commands/document.print_session_report', $this->envelope(['sessionId' => $this->drawer], ['operation' => 'document.print_session_report']))->assertStatus(201)->json('commandId'));
        $view = $this->get('/cash-sessions/'.$this->drawer.'/report/print/'.$print)->assertOk();
        $this->assertPageChannel($view, 'owner', $this->secrets(), 'refunds');
        $this->assertSame([$frozen], ((array) ($this->propsOf($view)['report'] ?? []))['refunds'] ?? null);

        $file = (string) $this->postJson('/commands/document.export_session_report', $this->envelope(['sessionId' => $this->drawer], ['operation' => 'document.export_session_report']))->assertStatus(201)->getContent();
        $this->assertStringEndsWith("\"Penjualan\";\"Referensi\";\"Jumlah (Rp)\";\"Waktu\";\"Oleh\"\r\n\"{$number}\";\"{$number}/1\";30000;\"2026-11-14 11:00\";\"Hendra\"\r\n", $file);
        $this->assertStringContainsString("\r\n\"Pengembalian dari laci kas (Rp)\";30000\r\n", $file);
    }

    /**
     * Dewi's Sale of two pens, Rp60.000, corrected three times by the Owner: Rp30.000 from Rina's drawer with
     * one pen back saleable at 11:00 in Asia/Jakarta, Rp10.000 by bank transfer, and the other pen back
     * damaged without money.
     *
     * @return array{int, int, int, int, int}
     */
    private function corrected(): array
    {
        $this->travelTo('2026-11-14T03:00:00.000000Z');
        $sale = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->pen, 'quantity' => '2']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $this->travelTo('2026-11-14T04:00:00.000000Z');
        $first = TokoContoh::refundFromDrawer($sale, $this->drawer, [['saleLineId' => $line, 'refundAmount' => '30000', 'returnedSaleable' => '1']]);
        $this->travelTo('2026-11-14T04:10:00.000000Z');
        $second = TokoContoh::correct($sale, [['saleLineId' => $line, 'refundAmount' => '10000']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-9']);
        $this->travelTo('2026-11-14T04:20:00.000000Z');
        $third = TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedNotSaleable' => '1']]);

        return [$sale, $line, $first, $second, $third];
    }

    /**
     * A page's deferred props, read by name as the page reads them.
     *
     * @return TestResponse<Response>
     */
    private function deferredOf(string $path, string $component, string $version): TestResponse
    {
        return $this->get($path, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => $component, 'X-Inertia-Partial-Data' => 'preview'])->assertOk();
    }

    private function numberOf(int $sale): string
    {
        return self::text($this->watcher()->table('sales')->where('id', $sale)->value('sale_number'));
    }

    private function sessionNumber(int $session): string
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

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
