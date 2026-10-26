<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Testing\SpecimenController;
use Tests\Harness\DatabaseTestCase;

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

    public function test_the_fixtures_exist_only_where_the_specimen_does(): void
    {
        $this->assertSame(['local', 'testing'], SpecimenController::ENVIRONMENTS);
        $this->assertFalse(SpecimenController::enabledIn('staging'));
        $this->assertFalse(SpecimenController::enabledIn('production'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('testing.browser.reset'));
    }
}
