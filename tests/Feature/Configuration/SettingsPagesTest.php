<?php

namespace Tests\Feature\Configuration;

use App\Queries\Configuration\Shop;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * UXP-601, UXP-607 and UXP-608 and the shop in every signed-in page
 * (AC-010-05, AC-010-06; ROUTE_CONTRACTS RP-601, RP-607, RP-608;
 * DESIGN_SYSTEM sections 3.3 and 7.2; SECURITY_MODEL SEC-CH-01, SEC-CH-02 as
 * TASK-010 amends it; suites AUTH02 and IDENTITY01; TASK-010 scope items 5
 * and 6), as L4 tests of the pages' answers: each page's props against the
 * allowlists, for the Owner; the index shows only the entries the role may
 * open; every other role is refused the three pages; the revision in force
 * and the earlier ones; the form with its expected version and the accent
 * set; and the shop's name, initials and accent in the shared props of every
 * signed-in page, of the revision in force, and none before sign-in.
 */
final class SettingsPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $first = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->first = TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_the_owner_opens_the_three_pages_and_each_carries_only_its_allowlisted_props(): void
    {
        $second = $this->changeProfile(['shopName' => 'Toko Contoh', 'address' => 'Jl. Contoh No. 1', 'accentToken' => 'blue']);
        $this->as('Hendra');
        $secrets = array_values(array_map(static fn (array $person): string => $person['password'], $this->people));

        $settings = $this->get('/settings')->assertOk();
        $this->assertSame('access/Settings', $this->inertiaPage($settings)['component'] ?? null);
        $this->assertSame([['id' => 'users', 'href' => '/settings/users'], ['id' => 'profile', 'href' => '/settings/business-profile']], $this->propsOf($settings)['entries'] ?? null);
        $this->assertPageChannel($settings, 'owner', $secrets);

        $profile = $this->get('/settings/business-profile')->assertOk();
        $props = $this->propsOf($profile);
        $current = $props['current'] ?? null;
        $this->assertIsArray($current);
        $this->assertSame([$second, 2, 'Toko Contoh', 'Jl. Contoh No. 1', 'blue', null, 'Hendra'], [$current['id'], $current['number'], $current['shopName'], $current['address'], $current['accentToken'], $current['logo'], $current['actorDisplayName']]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $this->readText($current['createdAt']));
        $earlier = $props['earlier'] ?? null;
        $this->assertIsArray($earlier);
        $this->assertSame([[$this->first, 1, 'green']], array_map(static fn (mixed $revision): array => is_array($revision) ? [$revision['id'], $revision['number'], $revision['accentToken']] : [], $earlier));
        $this->assertPageChannel($profile, 'owner', $secrets);

        $edit = $this->get('/settings/business-profile/edit')->assertOk();
        $props = $this->propsOf($edit);
        $this->assertSame(['green', 'teal', 'blue', 'purple', 'graphite'], $props['accents'] ?? null);
        $editing = $props['current'] ?? null;
        $this->assertIsArray($editing);
        $this->assertSame($second, $editing['id'] ?? null);
        $this->assertPageChannel($edit, 'owner', $secrets);
    }

    public function test_every_other_role_is_refused_the_settings_pages(): void
    {
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach (['/settings', '/settings/business-profile', '/settings/business-profile/edit'] as $path) {
                $page = $this->get($path)->assertStatus(403)->viewData('page');
                $this->assertIsArray($page);
                $this->assertSame(['shell/Error', 'forbidden'], [$page['component'] ?? null, ((array) ($page['props'] ?? []))['state'] ?? null], $person.' '.$path);
            }
        }
    }

    public function test_every_signed_in_page_carries_the_shop_of_the_revision_in_force_and_a_visitor_none(): void
    {
        foreach (['Hendra', 'Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->assertSame(['name' => 'Toko Contoh', 'initials' => 'TC', 'accent' => 'green'], $this->propsOf($this->get('/account')->assertOk())['shop'] ?? null, $person);
        }

        $this->changeProfile(['shopName' => 'Warung Maju Jaya', 'accentToken' => 'purple']);
        $this->as('Rina');
        $this->assertSame(['name' => 'Warung Maju Jaya', 'initials' => 'WM', 'accent' => 'purple'], $this->propsOf($this->get('/account')->assertOk())['shop'] ?? null);

        // Never before sign-in: no business data on those pages.
        $this->browse(null);
        $guest = $this->get('/login')->assertOk();
        $this->assertArrayHasKey('shop', $this->propsOf($guest));
        $this->assertNull($this->propsOf($guest)['shop']);
        $this->assertStringNotContainsString('Warung Maju Jaya', (string) $guest->getContent());
    }

    public function test_the_initials_are_the_first_letters_of_the_first_two_words(): void
    {
        $this->assertSame('TC', Shop::initials('Toko Contoh'));
        $this->assertSame('T', Shop::initials('  toko  '));
        $this->assertSame('ÉB', Shop::initials('élan  Baru Sekali'));
        $this->assertSame('', Shop::initials(''));
    }

    /**
     * A new revision through RC-32 as the Owner, and its internal key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function changeProfile(array $payload): int
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $current = $this->observer()->table('business_profile_current')->where('id', 1)->value('revision_id');

        $response = $this->postJson('/commands/business_profile.change', [
            'operation' => 'business_profile.change',
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => ['revisionId' => $current],
            'payload' => [
                'shopName' => TokoContoh::SHOP, 'address' => null, 'contact' => null, 'email' => null, 'documentHeader' => null,
                'documentFooter' => null, 'documentIdentity' => null, 'accentToken' => 'green', 'logoAction' => 'keep', ...$payload,
            ],
        ])->assertStatus(201);

        return self::number($response->json('result.id'));
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
