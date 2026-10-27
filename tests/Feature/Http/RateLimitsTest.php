<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * The rate buckets of SECURITY_MODEL section 11 that TASK-007 adds (AC-007-03;
 * ROUTE_CONTRACTS RT-09; SEC-HDR-05; ENVIRONMENTS section 10; ADR-003 item 10;
 * suites SEC01; THR-01, THR-16): before sign-in, 120 requests a minute per
 * address on the sign-in and link pages and the health route; after sign-in,
 * per user — 600 a minute for any request, and the buckets of search,
 * commands, photos, logos, CSV previews and prints — and one new link per
 * account per 60 seconds. Each throttled request runs nothing and answers
 * THROTTLED with Retry-After and a security log entry. The address is the
 * request's as only the trusted proxy hops give it: a forged X-Forwarded-For
 * or CF-Connecting-IP from an untrusted hop changes neither the bucket nor the
 * security log's address. The buckets of sign-in, links and confirmation are
 * TASK-006's (SignInTest, OneTimeLinkTest, RecentAuthenticationTest).
 */
final class RateLimitsTest extends DatabaseTestCase
{
    use Browsers;

    private int $ran = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-17T02:00:00Z');

        // A test-only route per bucket of a later Task, with the middleware of
        // RT-04 (TASK-015 to TASK-031 put them on their routes).
        foreach (['search', 'command', 'photo', 'logo', 'csv', 'print', 'link_issue'] as $bucket) {
            Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'throttle:'.$bucket, 'capability:CAP-01'])
                ->post('/harness/buckets/'.$bucket, function (): string {
                    $this->ran++;

                    return 'ran';
                });
        }
    }

    public function test_every_bucket_of_a_signed_in_user_throttles_after_its_limit(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->useDatabaseSessions();
        $this->browse($this->signInBrowser($rina['email'], $rina['password']));

        foreach (['search' => 300, 'command' => 60, 'print' => 60, 'photo' => 200, 'logo' => 20, 'csv' => 30] as $bucket => $limit) {
            $this->ran = 0;

            for ($request = 1; $request <= $limit; $request++) {
                $this->postJson('/harness/buckets/'.$bucket)->assertOk();
            }

            $this->assertThrottled($this->postJson('/harness/buckets/'.$bucket), null, $rina['id']);
            $this->assertSame($limit, $this->ran, $bucket.': the throttled request ran nothing');

            // The minute buckets count per minute; the hour buckets per hour,
            // after which the idle limit has ended the sign-in session.
            $this->travel(1)->minutes();

            if (in_array($bucket, ['photo', 'logo', 'csv'], true)) {
                $this->postJson('/harness/buckets/'.$bucket)->assertStatus(429);
                $this->travel(60)->minutes();
                $this->browse($this->signInBrowser($rina['email'], $rina['password']));
            }

            $this->postJson('/harness/buckets/'.$bucket)->assertOk();
            $this->travel(1)->minutes();
        }
    }

    public function test_the_bucket_of_every_signed_in_request_counts_per_user_not_per_address(): void
    {
        $rina = TokoContoh::person('Rina');
        $agus = TokoContoh::person('Agus');
        $this->useDatabaseSessions();
        $rinaSession = $this->signInBrowser($rina['email'], $rina['password']);
        $agusSession = $this->signInBrowser($agus['email'], $agus['password']);

        $this->browse($rinaSession);

        for ($request = 1; $request <= 600; $request++) {
            $this->getJson('/user/confirmed-password-status')->assertOk();
        }

        $this->assertThrottled($this->getJson('/user/confirmed-password-status'), 'password.confirmation', $rina['id']);

        // Another person at the same address of the shop is not throttled.
        $this->browse($agusSession);
        $this->getJson('/user/confirmed-password-status')->assertOk();
    }

    public function test_a_new_link_per_account_once_in_60_seconds(): void
    {
        $hendra = TokoContoh::person('Hendra');
        $this->post('/login', ['email' => $hendra['email'], 'password' => $hendra['password']])->assertRedirect('/');

        $this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => 7]])->assertOk();
        $this->assertThrottled($this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => 7]]), null, $hendra['id']);
        // The same account written as text is the same counter; a padded or
        // malformed identifier names no account, so it never opens a counter
        // of its own (the command refuses it as a value).
        $this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => '7']])->assertStatus(429);
        $this->assertSame(1, $this->ran);
        $this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => '07']])->assertOk();
        $this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => '07']])->assertOk();
        // Another account's link is not held back; a request that names no
        // account (a new account's first link) is not counted here.
        $this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => 8]])->assertOk();
        $this->postJson('/harness/buckets/link_issue')->assertOk();
        $this->postJson('/harness/buckets/link_issue')->assertOk();

        $this->travel(61)->seconds();
        $this->postJson('/harness/buckets/link_issue', ['payload' => ['userId' => 7]])->assertOk();
    }

    public function test_the_bucket_before_sign_in_counts_per_address_on_every_pre_authentication_route(): void
    {
        foreach (['/login', '/set-password', '/up'] as $path) {
            $this->flushLimits();

            for ($request = 1; $request <= 120; $request++) {
                $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get($path)->assertOk();
            }

            $throttled = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get($path);
            $throttled->assertStatus(429)->assertHeader('Retry-After');
            $this->assertSame('203.0.113.10', $this->lastThrottled()['client_address']);

            // Another address is not held back.
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->get($path)->assertOk();
        }

        // A throttled page loaded with GET is the page of UXS-12.
        $page = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/login')->assertStatus(429)->viewData('page');
        $this->assertIsArray($page);
        $this->assertSame('shell/Error', $page['component']);
        $this->assertSame('throttled', ((array) $page['props'])['state'] ?? null);
        $this->assertIsInt(((array) $page['props'])['retryAfter'] ?? null);

        // The submissions are in the same bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->post('/login', ['email' => 'x@contoh.test', 'password' => 'y'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->post('/set-password', ['user' => '1', 'token' => 'x', 'password' => 'y', 'password_confirmation' => 'y'])->assertStatus(429);
    }

    public function test_a_forged_forwarding_header_from_an_untrusted_hop_changes_neither_the_bucket_nor_the_log(): void
    {
        config(['security.trusted_proxies' => ['198.51.100.1']]);
        $this->flushLimits();

        // From an untrusted hop, every request names another client: all of
        // them count for the hop's own address.
        for ($request = 1; $request <= 120; $request++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
                ->withHeaders(['X-Forwarded-For' => '192.0.2.'.$request, 'CF-Connecting-IP' => '192.0.2.'.$request])
                ->get('/up')->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withHeaders(['X-Forwarded-For' => '192.0.2.200', 'CF-Connecting-IP' => '192.0.2.201'])
            ->get('/up')->assertStatus(429);
        $this->assertSame('203.0.113.20', $this->lastThrottled()['client_address']);

        // Through the trusted hop, the forwarded client is the address; a
        // CF-Connecting-IP is never read, and an address forged by the client
        // before the trusted hop is skipped only when it is the hop's own.
        $this->flushLimits();

        for ($request = 1; $request <= 120; $request++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
                ->withHeaders(['X-Forwarded-For' => '192.0.2.50', 'CF-Connecting-IP' => '192.0.2.'.$request])
                ->get('/up')->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->withHeaders(['X-Forwarded-For' => '192.0.2.50', 'CF-Connecting-IP' => '192.0.2.250'])
            ->get('/up')->assertStatus(429);
        $this->assertSame('192.0.2.50', $this->lastThrottled()['client_address']);

        // Another client behind the same proxy has a bucket of its own.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->withHeaders(['X-Forwarded-For' => '192.0.2.51'])->get('/up')->assertOk();
    }

    public function test_no_wildcard_proxy_is_ever_trusted(): void
    {
        config(['security.trusted_proxies' => ['*', '**', 'REMOTE_ADDR', ' 198.51.100.7 ', '']]);
        $this->assertSame(['198.51.100.7'], TrustProxies::proxyAddresses());

        // A range of prefix length 0 is every address (review finding R-34).
        config(['security.trusted_proxies' => ['0.0.0.0/0', '::/0', '203.0.113.0/00', '198.51.100.0/24', '2001:db8::/32']]);
        $this->assertSame(['198.51.100.0/24', '2001:db8::/32'], TrustProxies::proxyAddresses());

        config(['security.trusted_proxies' => 'not a list']);
        $this->assertSame([], TrustProxies::proxyAddresses());
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertThrottled(TestResponse $response, ?string $route, int $userId): void
    {
        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertExactJson(['outcome' => 'REJECTED', 'code' => 'THROTTLED', 'messageKey' => 'ux.state.throttled', 'params' => [], 'fields' => [], 'lines' => []]);
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        $last = $this->lastThrottled();
        $this->assertSame(['USER', $userId, 'REFUSED'], [$last['actor_kind'], $last['actor_user_id'], $last['outcome_code']]);

        if ($route !== null) {
            $this->assertSame($route, $last['route_name']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lastThrottled(): array
    {
        return (array) $this->observer()->table('security_log_entries')->where('event_type', 'THROTTLED')->orderByDesc('id')->first();
    }

    private function flushLimits(): void
    {
        app('cache')->store()->flush();
    }
}
