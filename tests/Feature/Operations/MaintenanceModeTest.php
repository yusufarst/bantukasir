<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\Commands\Probe;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\TestDatabase;

/**
 * Commands under maintenance mode (AC-014-04; RELEASE_PROCESS section 6, RB-20 step
 * 7; INTERACTION_CONTRACTS section 2; suites CMD01 and SEC01; TASK-014 scope item 5):
 * a command is answered before any route runs, with the empty body of a server error,
 * and leaves no receipt, no fact and no session; the operator's bypass comes from the
 * configuration, and authorizes nothing by itself. The browser's reading of the answer
 * is tests/Browser/operations/maintenance.spec.ts.
 */
final class MaintenanceModeTest extends DatabaseTestCase
{
    use CommandRequests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    protected function tearDown(): void
    {
        $this->app->maintenanceMode()->deactivate();

        parent::tearDown();
    }

    private function activate(): void
    {
        $this->app->maintenanceMode()->activate(['status' => 503]);
    }

    /**
     * What a request may have left: the facts, the receipts and the security log.
     *
     * @return array<string, int>
     */
    private function everything(): array
    {
        return [...$this->counts(), 'security_log_entries' => $this->observer()->table('security_log_entries')->count()];
    }

    public function test_a_command_is_answered_503_with_an_empty_body_and_leaves_nothing(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $before = $this->everything();
        $this->activate();

        $response = $this->probe($envelope)->assertStatus(503);

        $response->assertExactJson([]);
        $this->assertSame([], $response->headers->getCookies(), 'No session is touched.');
        $this->assertSame([], $this->receiptsOf($envelope));
        $this->assertSame($before, $this->everything());
    }

    public function test_a_status_check_is_answered_the_same_and_writes_no_fence(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $before = $this->everything();
        $this->activate();

        $this->check($envelope)->assertStatus(503)->assertExactJson([]);

        $this->assertSame($before, $this->everything());
    }

    public function test_it_answers_before_any_route_runs(): void
    {
        $before = $this->everything();
        $this->activate();

        // A visitor without a session, who would otherwise be refused 401 by the route.
        $this->postJson(Probe::PATH, $this->envelope())->assertStatus(503)->assertExactJson([]);

        // A path no route serves.
        $this->postJson('/commands/no-such-operation', [])->assertStatus(503)->assertExactJson([]);

        $this->assertSame($before, $this->everything());
    }

    public function test_it_answers_with_the_database_down_as_during_a_migration(): void
    {
        config(['database.connections.'.TestDatabase::RUNTIME.'.port' => 1]);
        DB::purge(TestDatabase::RUNTIME);
        $this->activate();

        $this->postJson(Probe::PATH, $this->envelope())->assertStatus(503)->assertExactJson([]);
    }

    public function test_after_the_window_the_command_runs_with_the_same_intent_and_key_and_the_check_finds_nothing_before_it(): void
    {
        $this->as('Agus');
        $envelope = $this->envelope();
        $this->activate();
        $this->probe($envelope)->assertStatus(503);
        $this->app->maintenanceMode()->deactivate();

        $this->check($envelope)->assertOk()->assertExactJson(['outcome' => 'NOT_COMMITTED', 'operation' => Probe::OPERATION]);

        $other = $this->envelope();
        $this->activate();
        $this->probe($other)->assertStatus(503);
        $this->app->maintenanceMode()->deactivate();
        $this->probe($other)->assertStatus(201);
    }

    public function test_the_operators_bypass_comes_from_the_configuration(): void
    {
        $secret = Str::random(40);
        config(['app.maintenance.bypass_secret' => $secret]);
        $this->activate();

        $this->get('/'.Str::random(40))->assertStatus(503);

        $response = $this->get('/'.$secret)->assertRedirect('/');
        $cookie = $response->getCookie('laravel_maintenance', false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue(MaintenanceModeBypassCookie::isValid((string) $cookie->getValue(), $secret));
    }

    public function test_the_cookie_passes_the_mode_only_and_every_check_of_the_route_still_applies(): void
    {
        $secret = Str::random(40);
        config(['app.maintenance.bypass_secret' => $secret]);
        $this->activate();
        $cookie = (string) MaintenanceModeBypassCookie::create($secret)->getValue();

        // Past the mode, still no sign-in session.
        $this->withUnencryptedCookie('laravel_maintenance', $cookie)->withCredentials()->postJson(Probe::PATH, $this->envelope())->assertStatus(401);
    }

    public function test_a_signed_in_operator_with_the_cookie_runs_a_command(): void
    {
        $secret = Str::random(40);
        config(['app.maintenance.bypass_secret' => $secret]);
        $this->as('Agus');
        $this->activate();
        $cookie = (string) MaintenanceModeBypassCookie::create($secret)->getValue();

        $this->withUnencryptedCookie('laravel_maintenance', $cookie)->withCredentials()->probe($this->envelope())->assertStatus(201);
    }

    public function test_without_the_setting_or_with_another_secrets_cookie_nobody_passes(): void
    {
        $secret = Str::random(40);
        $cookie = (string) MaintenanceModeBypassCookie::create($secret)->getValue();
        $this->activate();

        $this->get('/'.$secret)->assertStatus(503);
        $this->withUnencryptedCookie('laravel_maintenance', $cookie)->withCredentials()->postJson(Probe::PATH, $this->envelope())->assertStatus(503);

        config(['app.maintenance.bypass_secret' => '']);
        $this->withUnencryptedCookie('laravel_maintenance', $cookie)->withCredentials()->postJson(Probe::PATH, $this->envelope())->assertStatus(503);

        config(['app.maintenance.bypass_secret' => Str::random(40)]);
        $this->withUnencryptedCookie('laravel_maintenance', $cookie)->withCredentials()->postJson(Probe::PATH, $this->envelope())->assertStatus(503);
    }

    public function test_outside_the_mode_the_secret_is_an_ordinary_path(): void
    {
        $secret = Str::random(40);
        config(['app.maintenance.bypass_secret' => $secret]);

        $this->getJson('/'.$secret)->assertStatus(404);
    }

    public function test_the_health_route_still_answers_in_the_mode(): void
    {
        $this->activate();

        $this->get('/up')->assertOk()->assertContent('');
    }
}
