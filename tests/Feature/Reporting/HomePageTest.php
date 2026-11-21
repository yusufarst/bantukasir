<?php

namespace Tests\Feature\Reporting;

use App\Queries\Reporting\CostWorklist;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-007, UXP-007 "Beranda" (AC-041-03, AC-041-04; ROUTE_CONTRACTS RP-007, RN-01; KEY_SCREENS KS-02; NAVIGATION_CONTRACTS
 * UXP-007, NC-12; INTERACTION_INVENTORY UXI-059 to UXI-065; OPERATIONS_RUNBOOK section 5; SECURITY_MODEL CAP-22,
 * SEC-CH-01; UI_STATES UXS-07, UXS-22; EXECUTION_CONTEXT section 10, a route and a read; suites AUTH01, AUTH02, UX01,
 * STATUS01; TASK-041 section 7, L4 access): only the Owner reaches Beranda, which RN-01 sends him to; the page carries
 * nothing but its three deferred panels, each its own group, never stored; each panel read carries only its
 * allowlisted fields; each panel fails on its own as null, never zero, while the others stand; and the attention
 * items' evidence follows the records, each by the one definition of the page it leads to.
 */
final class HomePageTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PAGE = '/home';

    private const string COMPONENT = 'reporting/Home';

    private const string PANELS = 'today,openSessions,attention';

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_only_the_owner_opens_beranda_whose_panels_are_deferred_and_carry_only_their_fields(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Hendra');

        // RN-01 sends the Owner to Beranda (NC-12).
        $this->get('/')->assertRedirect(self::PAGE);

        $page = $this->get(self::PAGE)->assertOk();
        $inertia = $this->inertiaPage($page);
        $this->assertSame(self::COMPONENT, $inertia['component'] ?? null);
        $this->assertPageChannel($page, 'owner', $secrets);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        // Each panel is a deferred prop of its own group, read by a request of its own (UXS-22).
        $this->assertSame(['today' => ['today'], 'openSessions' => ['openSessions'], 'attention' => ['attention']], $inertia['deferredProps'] ?? null);

        $version = $inertia['version'] ?? null;
        $this->assertIsString($version);
        $panels = $this->panels($version);
        $this->assertDeferredChannel($panels, 'owner', $secrets);
        $props = $this->propsOf($panels);
        $sessions = $props['openSessions'] ?? null;
        $this->assertIsArray($sessions);
        $this->assertCount(1, $sessions);
        $row = (array) ($sessions[0] ?? []);
        $this->assertSame(['Laci 1', 'Rina'], [$row['registerLabel'] ?? null, $row['responsible'] ?? null]);

        // Every other role and the combined account: refused (UXS-07), the page and its panels alike.
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $refused = $this->get(self::PAGE)->assertStatus(403)->viewData('page');
            $this->assertIsArray($refused);
            $this->assertSame('shell/Error', $refused['component'] ?? null, $person);
            $this->getJson(self::PAGE)->assertStatus(403);
            $this->get(self::PAGE, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => self::COMPONENT, 'X-Inertia-Partial-Data' => self::PANELS])->assertStatus(403);
        }

        // The page takes no parameter.
        $this->as('Hendra');
        $this->getJson(self::PAGE.'?date=2026-11-01')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->browse(null);
        $this->get(self::PAGE)->assertRedirect('/login');
    }

    public function test_each_panel_fails_on_its_own_and_a_failed_panel_is_never_zero(): void
    {
        TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->as('Hendra');
        $version = $this->inertiaPage($this->get(self::PAGE)->assertOk())['version'] ?? null;
        $this->assertIsString($version);

        // The read of one panel fails: that panel alone is null (UXS-22), its cause reaches the page nowhere, and no
        // transaction stays open; the other panels stand.
        $failing = null;
        DB::listen(static function (QueryExecuted $query) use (&$failing): void {
            if (is_string($failing) && str_contains($query->sql, $failing)) {
                throw new RuntimeException('panel failed at /secret/path');
            }
        });

        foreach (['today' => 'join payments p', 'openSessions' => '"r"."label"', 'attention' => 'from backup_runs'] as $panel => $marker) {
            $failing = $marker;
            $read = $this->panels($version);
            $failing = null;
            $props = $this->propsOf($read);

            foreach (['today', 'openSessions', 'attention'] as $other) {
                $this->assertArrayHasKey($other, $props, $panel);

                if ($panel === $other) {
                    $this->assertNull($props[$other], $panel);
                } else {
                    $this->assertIsArray($props[$other], "{$other} stands while {$panel} fails");
                }
            }

            $this->assertStringNotContainsString('/secret/path', (string) $read->getContent());
            $this->assertSame(0, DB::connection()->transactionLevel(), $panel);

            // "Coba lagi": the panel alone, by name, read again.
            $again = $this->propsOf($this->panels($version, $panel));
            $this->assertIsArray($again[$panel] ?? null, $panel);

            foreach (array_diff(['today', 'openSessions', 'attention'], [$panel]) as $other) {
                $this->assertArrayNotHasKey($other, $again, "{$panel} is read alone");
            }
        }
    }

    public function test_the_attention_items_follow_their_evidence_by_the_definitions_of_their_pages(): void
    {
        $this->as('Hendra');
        $version = $this->inertiaPage($this->get(self::PAGE)->assertOk())['version'] ?? null;
        $this->assertIsString($version);

        // A new shop: nothing sold, no session, nothing to restock, no cost to complete, no incident, onboarding not
        // started; the system status has no evidence yet, so it needs checking (OPERATIONS_RUNBOOK section 5).
        $quiet = $this->propsOf($this->panels($version));
        $this->assertSame(['netSales' => '0', 'saleCount' => 0, 'paymentsCash' => '0', 'paymentsTransfer' => '0'], array_slice((array) $quiet['today'], 1));
        $this->assertSame([], $quiet['openSessions']);
        $this->assertSame(['restock' => ['out' => 0, 'low' => 0], 'costs' => 0, 'incidents' => 0, 'status' => true, 'onboarding' => false], $quiet['attention']);

        // A product never received is Habis; one below its minimum Menipis; one received at an UNKNOWN cost is a cost to
        // complete; a posting block of it an open incident; then onboarding starts, the freeze on.
        $category = TokoContoh::category();
        TokoContoh::product('HABIS-01', ['categoryId' => $category, 'name' => 'Map Habis']);
        TokoContoh::stock(TokoContoh::product('TIPIS-01', ['categoryId' => $category, 'name' => 'Lem Tipis', 'minimumStock' => '10']), '5', '5000');
        $unknown = TokoContoh::product('TANPA-01', ['categoryId' => $category, 'name' => 'Buku Tanpa Biaya']);
        TokoContoh::receive([['productId' => $unknown, 'quantity' => '5', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::postingBlock($unknown);
        TokoContoh::startOnboarding();

        $attention = $this->propsOf($this->panels($version))['attention'] ?? null;
        $this->assertSame(['restock' => ['out' => 1, 'low' => 1], 'costs' => 1, 'incidents' => 1, 'status' => true, 'onboarding' => true], $attention);

        // The restock count is the stock badge's of RD-03, and the cost count the worklist's (ARC-22).
        $this->assertSame(2, $this->getJson('/data/shell')->assertOk()->json('stockAttention'));
        $this->assertCount(1, app(CostWorklist::class)->list(['product' => null, 'after' => null, 'before' => null, 'perPage' => CostWorklist::MAX_PER_PAGE])['rows']);
    }

    /**
     * The page's own read of its panels, by name: all three, as the first visit's requests read them together, or one,
     * as "Coba lagi" reads it.
     *
     * @return TestResponse<Response>
     */
    private function panels(string $version, string $only = self::PANELS): TestResponse
    {
        $reload = $this->get(self::PAGE, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => self::COMPONENT,
            'X-Inertia-Partial-Data' => $only,
        ])->assertOk();
        $this->assertSame(self::COMPONENT, $this->inertiaPage($reload)['component'] ?? null);

        return $reload;
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
