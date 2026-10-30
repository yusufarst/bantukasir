<?php

namespace Tests\Feature\Operations;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\TestDatabase;

/**
 * RH-01, the health route (AC-007-05; ROUTE_CONTRACTS section 5, RT-04;
 * ADR-011 item 11; ENVIRONMENTS section 13; suites SEC01 and STATUS01;
 * TASK-007 scope item 6): 200 when the application has booted and its
 * database answers, 503 when the database does not; an empty body either
 * way — no detail, version or name; no session and no cookie; and the bucket
 * before sign-in, 120 a minute per address.
 */
final class HealthRouteTest extends DatabaseTestCase
{
    public function test_it_answers_200_with_the_database_up_and_says_nothing(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        $this->assertSame([], $response->headers->getCookies(), 'No session and no cookie.');
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertSame(0, $this->observer()->table('sessions')->count());

        $json = $this->getJson('/up')->assertOk();
        $this->assertSame('', $json->getContent());
    }

    public function test_it_answers_an_error_status_with_the_database_down_and_says_nothing(): void
    {
        // The runtime connection points at a port where no database listens,
        // which refuses the connection at once.
        config(['database.connections.'.TestDatabase::RUNTIME.'.port' => 1]);
        DB::purge(TestDatabase::RUNTIME);

        $response = $this->get('/up');

        $response->assertStatus(503);
        $this->assertSame('', $response->getContent());
        $response->assertDontSee('SQLSTATE', false);
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_it_answers_503_and_says_nothing_when_its_throttle_cannot_count_in_the_database(): void
    {
        // As configured outside the tests (compose.yaml, ADR-002): the rate
        // limiter counts in the database's cache store, which fails first
        // when the database is down (review finding R-29).
        config(['app.debug' => false]);
        (new ReflectionProperty(RateLimiter::class, 'cache'))->setValue(app(RateLimiter::class), Cache::store('database'));
        config(['database.connections.'.TestDatabase::RUNTIME.'.port' => 1]);
        DB::purge(TestDatabase::RUNTIME);

        $response = $this->get('/up');

        $response->assertStatus(503);
        $this->assertSame('', $response->getContent());
        $this->assertSame([], $response->headers->getCookies());

        config(['app.debug' => true]);
        $this->get('/up')->assertStatus(503)->assertContent('');
    }

    public function test_it_answers_in_maintenance_mode_while_the_pages_do_not(): void
    {
        // TASK-007 finding F-11; TASK-012.
        $this->app->maintenanceMode()->activate(['status' => 503]);

        try {
            $this->get('/up')->assertOk()->assertContent('');
            $this->getJson('/login')->assertStatus(503);
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    public function test_it_is_throttled_with_the_bucket_before_sign_in(): void
    {
        for ($request = 1; $request <= 120; $request++) {
            $this->get('/up')->assertOk();
        }

        $this->get('/up')->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(['THROTTLED', 'ANONYMOUS', 'REFUSED', 'health'], array_values((array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first(['event_type', 'actor_kind', 'outcome_code', 'route_name'])));
    }
}
