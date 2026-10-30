<?php

namespace Tests\Feature\Commands;

use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * RP-006, UXP-006 "Permintaan belum pasti" (AC-009-05; ROUTE_CONTRACTS
 * section 2, RT-08; INTERACTION_CONTRACTS section 2.2; SECURITY_MODEL
 * SEC-CH-01, SEC-CH-04; EXECUTION_CONTEXT section 10, a route and a page;
 * suites AUTH01 and AUTH02; TASK-009 scope item 6): every role and the
 * combined account open the page, which carries no data of its own — the
 * browser builds the list from its references and RD-04, under RD-04's own
 * authorization — with the shared props only; it is not stored by a cache,
 * its history entry is encrypted, and it takes no parameter.
 */
final class RequestsPageTest extends DatabaseTestCase
{
    use Browsers;
    use ChannelInspector;

    private const array ACTORS = ['Hendra' => 'owner', 'Agus' => 'ops', 'Rina' => 'cashier', 'Dewi' => 'combined'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->useDatabaseSessions();
    }

    public function test_every_role_and_the_combined_account_open_the_page_with_no_data_of_its_own(): void
    {
        foreach (self::ACTORS as $person => $actor) {
            $account = TokoContoh::person($person);
            $this->browse($this->signInBrowser($account['email'], $account['password']));

            $response = $this->get('/requests')->assertOk();
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $page = $this->inertiaPage($response);
            $this->assertSame('commands/Requests', $page['component']);
            $this->assertTrue($page['encryptHistory']);
            $this->assertSame(['auth', 'errors', 'recoveryEpoch', 'shop'], self::propNames((array) $page['props']));
            $this->assertPageChannel($response, $actor, [$account['password']]);

            // RT-08: the page takes no parameter.
            $this->get('/requests?scope=all')->assertStatus(422);
        }

        $this->browse(null);
        $this->get('/requests')->assertRedirect('/login');
    }
}
