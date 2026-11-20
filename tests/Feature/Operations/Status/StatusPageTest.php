<?php

namespace Tests\Feature\Operations\Status;

use App\Domain\Operations\VolumeSpace;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-611, UXP-611 "Status sistem", and its entry UXI-329 on UXP-601 (AC-039-01, AC-039-02; ROUTE_CONTRACTS
 * RP-611; INTERACTION_INVENTORY UXI-329, UXI-374, UXI-375; SECURITY_MODEL CAP-32, CAP-18, SEC-CH-01; UI_STATES
 * UXS-07, UXS-22; EXECUTION_CONTEXT section 10, a route and a read; suites AUTH01, AUTH02, SEC01; TASK-039
 * section 7, L4 RP-611 by role): only the Owner reaches the page and its entry, with only its allowlisted
 * props and never stored; every other role and the combined account refused; any parameter refused; the
 * status read again by name; a read that fails is a null status, never a passing look, and its cause
 * reaches the page nowhere; the incident link's flag follows the open incidents.
 */
final class StatusPageTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PAGE = '/settings/system-status';

    private const string COMPONENT = 'operations/Status';

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_only_the_owner_opens_the_page_and_its_entry_with_only_their_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $this->as('Hendra');

        $page = $this->get(self::PAGE)->assertOk();
        $this->assertSame(self::COMPONENT, $this->inertiaPage($page)['component'] ?? null);
        $this->assertPageChannel($page, 'owner', $secrets);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        // UXI-329 on UXP-601, after "Laci kas" (COPY_DECK section 11.1).
        $settings = (array) ($this->propsOf($this->get('/settings')->assertOk())['entries'] ?? []);
        $this->assertSame(['id' => 'status', 'href' => self::PAGE], $settings[3] ?? null);

        // Only its path: any parameter refused.
        $this->getJson(self::PAGE.'?x=1')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        // Every other role and the combined account: the page refused (UXS-07), and no entry to it.
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $refused = $this->get(self::PAGE)->assertStatus(403)->viewData('page');
            $this->assertIsArray($refused);
            $this->assertSame('shell/Error', $refused['component'] ?? null, $person);
        }

        $this->browse(null);
        $this->get(self::PAGE)->assertRedirect('/login');
    }

    public function test_the_status_is_read_again_by_name_and_a_failed_read_is_never_a_pass(): void
    {
        $this->as('Hendra');
        $version = $this->inertiaPage($this->get(self::PAGE)->assertOk())['version'] ?? null;
        $this->assertIsString($version);

        // UXI-374: the status alone, by name.
        $this->assertDeferredChannel($this->reload($version), 'owner');

        // A read that fails inside — here the measure of the volume — is a null status (UXS-22), on the
        // page and on its reload, and its message reaches neither.
        $this->app->instance(VolumeSpace::class, new class implements VolumeSpace
        {
            public function measure(string $path): ?array
            {
                throw new RuntimeException('volume unreadable at /secret/path');
            }
        });

        $failed = $this->get(self::PAGE)->assertOk();
        $this->assertArrayHasKey('status', $this->propsOf($failed));
        $this->assertNull($this->propsOf($failed)['status']);
        $this->assertPageChannel($failed, 'owner', ['/secret/path'], 'failed');

        $failedReload = $this->reload($version);
        $this->assertArrayHasKey('status', $this->propsOf($failedReload));
        $this->assertNull($this->propsOf($failedReload)['status']);
        $this->assertStringNotContainsString('/secret/path', (string) $failedReload->getContent());
    }

    public function test_the_incident_flag_follows_the_open_incidents(): void
    {
        $this->as('Hendra');
        $this->assertFalse($this->incidentsOpen());

        $product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'name' => 'Pulpen Hitam']);
        TokoContoh::stock($product, '5', '5000');
        $block = TokoContoh::postingBlock($product);
        $this->assertTrue($this->incidentsOpen());

        TokoContoh::reopenPostings($block);
        $this->assertFalse($this->incidentsOpen());
    }

    /** The page's flag of UXI-375, as the Owner's page carries it. */
    private function incidentsOpen(): mixed
    {
        $status = (array) ($this->propsOf($this->get(self::PAGE)->assertOk())['status'] ?? []);

        return array_key_exists('incidentsOpen', $status) ? $status['incidentsOpen'] : 'missing';
    }

    /**
     * The page's own read of its status, by name, as "Muat ulang" makes it.
     *
     * @return TestResponse<Response>
     */
    private function reload(string $version): TestResponse
    {
        $reload = $this->get(self::PAGE, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => self::COMPONENT,
            'X-Inertia-Partial-Data' => 'status',
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
