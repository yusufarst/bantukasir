<?php

namespace Tests\Feature\Http;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use ReflectionProperty;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\RouteInventory;

/**
 * CSRF in both of its layers on every state-changing request (AC-007-04;
 * SECURITY_MODEL SEC-HDR-01, SEC-LOG-03; ROUTE_CONTRACTS RT-04; suites SEC01
 * and AUTH01; THR-06; TASK-007 scope item 5): the browser's fetch-site header
 * admits a same-origin request; otherwise the CSRF token decides; a
 * cross-site or tokenless write is refused CSRF_FAILED (419) and recorded,
 * and runs nothing. Origin-only mode is off, another site of the same site is
 * not admitted, no route is excluded, and every non-GET route runs the
 * middleware. The framework skips the middleware while it runs its own unit
 * tests, so these tests run its unchanged logic with that skip removed.
 */
final class CsrfTest extends DatabaseTestCase
{
    private int $ran = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PreventRequestForgery::class, static fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])->post('/harness/write', function (): string {
            $this->ran++;

            return 'ran';
        });
    }

    public function test_a_tokenless_or_cross_site_write_is_refused_and_recorded(): void
    {
        $rina = TokoContoh::person('Rina');

        // The sign-in itself is protected: without a token or the header it
        // is refused before any credential is checked.
        $this->postJson('/login', ['email' => $rina['email'], 'password' => $rina['password']])
            ->assertStatus(419)
            ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'CSRF_FAILED', 'messageKey' => 'ux.state.reload', 'params' => [], 'fields' => [], 'lines' => []]);
        $this->assertGuest();
        $this->assertSame(['CSRF_FAILED', 'ANONYMOUS', 'REFUSED', 'login.store'], $this->lastEntry());

        $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');

        foreach (['cross-site', 'same-site', 'none', ''] as $site) {
            $this->withHeader('Sec-Fetch-Site', $site)->postJson('/harness/write')->assertStatus(419)->assertJsonPath('code', 'CSRF_FAILED');
        }

        $this->withHeader('Sec-Fetch-Site', 'cross-site')->postJson('/harness/write', ['_token' => 'wrong'])->assertStatus(419);
        $this->assertSame(0, $this->ran, 'A refused write runs nothing.');
        $this->assertSame(['CSRF_FAILED', 'USER', $rina['id'], 'REFUSED'], $this->lastEntry());
    }

    public function test_a_same_origin_write_or_one_with_the_sessions_token_runs(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');

        // The first layer: the browser says the request is of this origin.
        $this->withHeader('Sec-Fetch-Site', 'same-origin')->postJson('/harness/write')->assertOk();

        // The second layer: no fetch-site header (plain HTTP outside localhost),
        // the token of the session in the body or a header.
        $this->flushHeaders();
        $token = (string) session()->token();
        $this->postJson('/harness/write', ['_token' => $token])->assertOk();
        $this->withHeader('X-CSRF-TOKEN', $token)->postJson('/harness/write')->assertOk();

        $this->assertSame(3, $this->ran);
    }

    public function test_no_route_is_excluded_and_every_non_get_route_runs_the_middleware(): void
    {
        $except = new ReflectionProperty(PreventRequestForgery::class, 'neverVerify');
        $originOnly = new ReflectionProperty(PreventRequestForgery::class, 'originOnly');
        $sameSite = new ReflectionProperty(PreventRequestForgery::class, 'allowSameSite');

        $this->assertSame([], $except->getValue());
        $this->assertFalse($originOnly->getValue());
        $this->assertFalse($sameSite->getValue());

        // The HTTP kernel gives the router its groups and aliases.
        app(Kernel::class);
        $router = app(Router::class);

        foreach (RouteInventory::routes($router) as $key => $route) {
            if (str_starts_with($key, 'GET ') || $key === 'POST /harness/write') {
                continue;
            }

            $this->assertContains(PreventRequestForgery::class, $router->gatherRouteMiddleware($route), $key);
        }
    }

    /**
     * @return list<mixed>
     */
    private function lastEntry(): array
    {
        $last = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();

        return $last['actor_kind'] === 'USER'
            ? [$last['event_type'], $last['actor_kind'], $last['actor_user_id'], $last['outcome_code']]
            : [$last['event_type'], $last['actor_kind'], $last['outcome_code'], $last['route_name']];
    }
}
