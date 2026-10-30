<?php

namespace Tests\Feature\Access;

use Illuminate\Support\Facades\Session;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-005 and RP-602 to RP-606 (AC-011-01, AC-011-05; ROUTE_CONTRACTS section 2;
 * SECURITY_MODEL SEC-CH-01, SEC-ACC-05; suites AUTH01, AUTH02; TASK-011 scope item
 * 4), as L4 tests of the pages' answers: each page's props against the allowlists,
 * for the Owner; every other role refused; the filters as allowlists; a guessed
 * identifier NOT_FOUND; and the channel inspection of the link: UXP-606 never holds
 * one, nor does any page or the session after it was issued.
 */
final class UserPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    public function test_the_owner_opens_each_page_and_it_carries_only_its_allowlisted_props(): void
    {
        $this->as('Hendra');
        $secrets = array_values(array_map(static fn (array $person): string => $person['password'], $this->people));
        $rina = $this->people['Rina']['id'];

        $this->assertPageChannel($this->get('/account/details')->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get('/settings/users')->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get('/settings/users/new')->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get('/settings/users/'.$rina)->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get('/settings/users/'.$rina.'/edit')->assertOk(), 'owner', $secrets);
        $this->assertPageChannel($this->get('/settings/users/'.$rina.'/link')->assertOk(), 'owner', $secrets);

        $detail = $this->propsOf($this->get('/settings/users/'.$rina))['user'] ?? null;
        $this->assertIsArray($detail);
        $this->assertSame([$rina, 'Rina', 'rina@contoh.test', ['CASHIER'], 'ACTIVE', true, null, false, false], [$detail['id'], $detail['displayName'], $detail['email'], $detail['roles'], $detail['status'], $detail['passwordSet'], $detail['linkExpiresAt'], $detail['superAdmin'], $detail['self']]);

        // The Owner's own account says so, and a link's expiry shows.
        $own = $this->propsOf($this->get('/settings/users/'.$this->people['Hendra']['id']))['user'] ?? null;
        $this->assertIsArray($own);
        $this->assertSame([true, true], [$own['superAdmin'], $own['self']]);
        $this->assertSame('Hendra', ((array) ($this->propsOf($this->get('/account/details'))['account'] ?? []))['displayName'] ?? null);
        TokoContoh::tokenFor($rina);
        $linked = $this->propsOf($this->get('/settings/users/'.$rina))['user'] ?? null;
        $this->assertIsArray($linked);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $this->readText($linked['linkExpiresAt']));
    }

    public function test_every_other_role_is_refused_each_page(): void
    {
        $rina = $this->people['Rina']['id'];

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach (['/account/details', '/settings/users', '/settings/users/new', '/settings/users/'.$rina, '/settings/users/'.$rina.'/edit', '/settings/users/'.$rina.'/link'] as $path) {
                $page = $this->get($path)->assertStatus(403)->viewData('page');
                $this->assertIsArray($page);
                $this->assertSame('shell/Error', $page['component'] ?? null, $person.' '.$path);
            }
        }
    }

    public function test_the_list_shows_every_account_by_its_filters_as_allowlists(): void
    {
        $this->as('Hendra');
        $budi = TokoContoh::staff('Budi');
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $this->deactivate($budi['id']);

        $this->assertSame(['Agus', 'Budi', 'Dewi', 'Hendra', 'Rina'], $this->namesOf($this->get('/settings/users')->assertOk()));
        $this->assertSame(['Budi'], $this->namesOf($this->get('/settings/users?status=deactivated')->assertOk()));
        $this->assertSame([], $this->namesOf($this->get('/settings/users?status=anonymized')->assertOk()));
        $this->assertSame(['Hendra'], $this->namesOf($this->get('/settings/users?role=owner')->assertOk()));
        $this->assertSame(['Budi', 'Dewi', 'Rina'], $this->namesOf($this->get('/settings/users?role=cashier')->assertOk()));
        $this->assertSame(['Agus', 'Dewi'], $this->namesOf($this->get('/settings/users?role=operations_admin&status=active')->assertOk()));

        $this->getJson('/settings/users?status=lain')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->getJson('/settings/users?role=SUPER_ADMIN')->assertStatus(422);
        $this->getJson('/settings/users?sort=email')->assertStatus(422);
    }

    public function test_a_guessed_identifier_is_not_found_and_only_a_staff_account_not_anonymized_can_be_edited(): void
    {
        $this->as('Hendra');

        foreach (['999999', 'abc', '-1', '0'] as $identifier) {
            foreach (['', '/edit', '/link'] as $suffix) {
                $this->getJson('/settings/users/'.$identifier.$suffix)->assertStatus(404);
            }
        }

        $this->getJson('/settings/users/'.$this->people['Hendra']['id'].'/edit')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        // An anonymized account: its detail shows no address, and it has no edit page.
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $budi = TokoContoh::staff('Budi');
        $this->as('Hendra');
        $version = $this->deactivate($budi['id']);
        $this->command('user.anonymize', ['userId' => $budi['id'], 'reason' => 'Permintaan.'], $version)->assertStatus(201);

        $anonymized = $this->propsOf($this->get('/settings/users/'.$budi['id'])->assertOk())['user'] ?? null;
        $this->assertIsArray($anonymized);
        $this->assertArrayHasKey('email', $anonymized);
        $this->assertNull($anonymized['email']);
        $rows = (array) ($this->propsOf($this->get('/settings/users?status=anonymized'))['users'] ?? []);
        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertNull($rows[0]['email']);
        $this->getJson('/settings/users/'.$budi['id'].'/edit')->assertStatus(404);
    }

    public function test_the_link_appears_only_in_the_issuing_json_body(): void
    {
        // AC-011-05: the channel inspection of the link. It is in the answer of
        // RC-35, and in no page, flash, session or log afterwards.
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $response = $this->command('user.create', ['displayName' => 'Budi', 'email' => 'budi@contoh.test', 'roles' => ['cashier']], null)->assertStatus(201);
        $url = $this->readText($response->json('link.url'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $token = rawurldecode(substr($url, (int) strrpos($url, '&t=') + 3));
        $id = self::number($response->json('result.id'));

        foreach (['/settings/users/'.$id.'/link', '/settings/users/'.$id, '/settings/users', '/settings/users/'.$id.'/edit', '/account'] as $path) {
            $page = $this->get($path)->assertOk();
            $this->assertNoSecret($page, [$token, $url]);
            $this->assertStringNotContainsString($token, (string) json_encode(Session::all()), $path);
        }

        // No row of the application holds it, in plain text.
        $this->assertSame(0, $this->observer()->table('audit_events')->whereRaw('change_data::text like ?', ['%'.$token.'%'])->count());
        $this->assertSame(0, $this->observer()->table('command_results')->whereRaw('result_detail::text like ?', ['%'.$token.'%'])->count());
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->where('token', $token)->count());
        $this->assertSame(0, $this->observer()->table('sessions')->where('payload', 'like', '%'.$token.'%')->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function command(string $operation, array $payload, ?int $version): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelope($payload, ['operation' => $operation, 'expected' => $version === null ? (object) [] : ['userVersion' => $version]]));
    }

    /**
     * Deactivates the account through RC-38 and returns its new version.
     */
    private function deactivate(int $id): int
    {
        $version = self::number($this->observer()->table('users')->where('id', $id)->value('version'));
        $this->command('user.deactivate', ['userId' => $id], $version)->assertStatus(201);

        return $version + 1;
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<mixed>
     */
    private function namesOf(TestResponse $response): array
    {
        $rows = (array) ($this->propsOf($response)['users'] ?? []);

        return array_values(array_map(static fn (mixed $row): mixed => is_array($row) ? $row['displayName'] : null, $rows));
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
