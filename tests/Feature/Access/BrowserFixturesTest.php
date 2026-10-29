<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Testing\SpecimenController;
use Tests\Harness\Commands\Probe;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The fixtures of the browser tests (tests/Harness/Browser; TASK-006): they
 * exist only in the local and testing environments, where the specimen does,
 * and work only on a database whose marker and environment name are local or
 * test, so that no environment that staging or production can reach has them
 * (PRODUCTION_DATA_SAFETY section 1).
 */
final class BrowserFixturesTest extends DatabaseTestCase
{
    public function test_the_fixtures_give_people_new_passwords_and_links_on_the_test_database(): void
    {
        $reset = $this->postJson('/testing/browser/reset', ['people' => ['Rina', 'Dewi']])->assertOk();

        $this->assertSame(['Rina', 'Dewi'], array_keys((array) $reset->json('accounts')));
        $this->assertSame('rina@contoh.test', $reset->json('accounts.Rina.email'));
        $this->post('/login', ['email' => 'rina@contoh.test', 'password' => $reset->json('accounts.Rina.password')])->assertRedirect('/');

        $this->post('/logout');
        $link = $this->postJson('/testing/browser/link', ['person' => 'Dewi'])->assertOk();
        $this->assertSame($reset->json('accounts.Dewi.id'), $link->json('user'));
        $this->assertSame(64, strlen($this->readText($link->json('token'))));
    }

    public function test_the_fixtures_refuse_another_database_and_an_unknown_person(): void
    {
        config(['app.environment_name' => 'staging']);
        $this->postJson('/testing/browser/reset', ['people' => ['Rina']])->assertStatus(500);
        $this->postJson('/testing/browser/link', ['person' => 'Rina'])->assertStatus(500);
        config(['app.environment_name' => 'test']);

        $this->postJson('/testing/browser/reset', ['people' => ['Budi']])->assertStatus(500);
        $this->postJson('/testing/browser/reset', ['people' => []])->assertStatus(500);
        $this->postJson('/testing/browser/link', ['person' => 'Rina'])->assertStatus(500);
        $this->assertSame(0, $this->observer()->table('users')->count());
    }

    public function test_the_command_fixtures_prepare_the_probe_and_the_epoch_and_resolve_earlier_requests_through_the_owners_check(): void
    {
        foreach (Probe::TABLES as $table) {
            $this->harnessTable($table);
        }

        // A receipt of another device, written by the Commands module's service.
        $receipt = $this->postJson('/testing/browser/receipt', ['person' => 'Rina', 'operation' => 'sale.checkout'])->assertOk();
        $rina = $this->observer()->table('users')->where('email', 'rina@contoh.test')->value('id');
        $this->assertIsInt($rina);
        $this->assertSame(['sale.checkout', 'PENDING'], [
            $this->readText($this->observer()->table('command_request_receipts')->where('actor_user_id', $rina)->value('operation')),
            $this->readText($this->observer()->table('command_request_receipts')->where('actor_user_id', $rina)->value('outcome')),
        ]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->readText($receipt->json('sourceIntentId')));

        // The command fixture: the probe's tables, twice without harm, the
        // epoch, and Rina's earlier request fenced by the Owner's check.
        $first = $this->postJson('/testing/browser/commands', ['people' => ['Rina']])->assertOk();
        $this->postJson('/testing/browser/commands', ['people' => ['Rina']])->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->readText($first->json('epoch')));
        $this->assertSame(1, $this->observer()->table('harness_probe_counters')->count());
        $this->assertSame(['NOT_COMMITTED'], $this->observer()->table('command_request_receipts')->where('actor_user_id', $rina)->pluck('outcome')->all());
        $this->assertSame(1, $this->observer()->table('command_identities')->where('kind', 'FENCE')->count());

        $this->postJson('/testing/browser/receipt', ['person' => 'Rina', 'operation' => 'posting_block.block'])->assertStatus(500);
        $this->postJson('/testing/browser/receipt', ['person' => 'Budi', 'operation' => 'sale.checkout'])->assertStatus(500);
        config(['app.environment_name' => 'staging']);
        $this->postJson('/testing/browser/commands', ['people' => ['Rina']])->assertStatus(500);
        $this->postJson('/testing/browser/receipt', ['person' => 'Rina', 'operation' => 'sale.checkout'])->assertStatus(500);
    }

    public function test_the_command_specimen_opens_for_a_signed_in_user_through_the_gate_of_every_signed_in_page(): void
    {
        $this->withoutVite();
        $rina = TokoContoh::person('Rina');

        $this->get('/testing/command-specimen')->assertRedirect('/login');
        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');
        $page = $this->get('/testing/command-specimen')->assertOk()->viewData('page');
        $this->assertIsArray($page);
        $this->assertSame('Testing/CommandSpecimen', $page['component']);
        $this->assertTrue($page['encryptHistory']);
        $this->assertContains('capability:CAP-01', app('router')->getRoutes()->getByName('testing.command_specimen')?->gatherMiddleware() ?? []);
    }

    public function test_the_fixtures_exist_only_where_the_specimen_does(): void
    {
        $this->assertSame(['local', 'testing'], SpecimenController::ENVIRONMENTS);
        $this->assertFalse(SpecimenController::enabledIn('staging'));
        $this->assertFalse(SpecimenController::enabledIn('production'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('testing.browser.reset'));
    }
}
