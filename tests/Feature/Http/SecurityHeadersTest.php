<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The headers of every answer (AC-007-04; SECURITY_MODEL SEC-HDR-02,
 * SEC-HDR-03, SEC-CH-06; ROUTE_CONTRACTS RT-06; TECHNICAL_RULES TR-HTTP-09;
 * suites SEC01 and AUTH02; THR-05, THR-07; TASK-007 scope item 5): a page,
 * JSON, a redirect, an answer without a session, every refusal and a server
 * error all carry nosniff, the referrer policy, the permissions policy,
 * X-Frame-Options DENY, the opener policy and the content security policy of
 * SEC-HDR-03 with the request's own nonce, which the page's generated tags
 * carry; the one-time link page keeps `no-referrer`; nothing of a signed-in
 * user is stored by a cache; HSTS only over HTTPS once the operator turned
 * it on. The browser tests prove that the policy blocks nothing a page needs.
 */
final class SecurityHeadersTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Route::middleware(['web'])->get('/harness/fails', static function (): never {
            throw new RuntimeException('An internal detail: /var/www/html, SELECT secret FROM somewhere');
        });
    }

    public function test_every_kind_of_answer_carries_the_headers(): void
    {
        $rina = TokoContoh::person('Rina');

        $answers = [
            'a page before sign-in' => $this->get('/login'),
            'the health route' => $this->get('/up'),
            'a redirect without a sign-in session' => $this->get('/account'),
            'an unknown path' => $this->get('/no-such-page'),
            'an unknown path, as JSON' => $this->getJson('/no-such-page'),
            'a server error' => $this->get('/harness/fails'),
            'a refused JSON read' => $this->getJson('/user/confirmed-password-status'),
        ];

        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');
        $answers['a signed-in page'] = $this->get('/account');
        $answers['a signed-in JSON read'] = $this->getJson('/user/confirmed-password-status');
        $answers['the home redirect'] = $this->get('/');
        $answers['an unknown path, signed in'] = $this->get('/no-such-page');

        foreach ($answers as $kind => $response) {
            $this->assertHeaders($response, $kind);
        }

        $answers['a signed-in page']->assertOk();
        $answers['a server error']->assertStatus(500);
    }

    public function test_markup_in_a_display_name_reaches_the_shell_as_text_only(): void
    {
        // THR-05: the account menu shows the display name, which an Owner
        // types (TASK-011); the page embeds it as JSON with every angle
        // bracket escaped, React renders it as text, and the policy admits
        // no inline script.
        $rina = TokoContoh::person('Rina');
        $name = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $this->runtime()->table('users')->where('id', $rina['id'])->update(['display_name' => $name]);

        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');
        $page = $this->get('/account')->assertOk();
        $html = (string) $page->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString(trim((string) json_encode('<script>alert(1)</script>', JSON_HEX_TAG), '"'), $html);
        $this->assertSame(1, substr_count($html, '<script data-page='), 'One script element for the page data, of type JSON.');

        $policy = (string) $page->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
    }

    public function test_the_policy_carries_the_requests_own_nonce_which_the_generated_tags_carry(): void
    {
        $first = $this->get('/login')->assertOk();
        $second = $this->get('/login')->assertOk();
        $nonce = $this->nonce($first);

        $this->assertNotSame($nonce, $this->nonce($second), 'A new nonce for every request.');
        $first->assertSee('<meta name="csp-nonce" nonce="'.$nonce.'">', false);
        $this->assertSame(
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
            $first->headers->get('Content-Security-Policy'),
        );
        $this->assertStringNotContainsString('unsafe-inline', (string) $first->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-eval', (string) $first->headers->get('Content-Security-Policy'));
    }

    public function test_the_link_page_keeps_no_referrer(): void
    {
        $this->get('/set-password')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/login')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_nothing_of_a_signed_in_user_or_any_json_is_stored_by_a_cache(): void
    {
        $rina = TokoContoh::person('Rina');
        $this->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])->assertRedirect('/');

        foreach (['/account', '/account/password', '/'] as $path) {
            $cache = (string) $this->get($path)->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cache, $path);
            $this->assertStringContainsString('private', $cache, $path);
        }

        $this->assertStringContainsString('no-store', (string) $this->getJson('/user/confirmed-password-status')->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $this->get('/no-such-page')->headers->get('Cache-Control'));
    }

    public function test_hsts_only_over_https_once_the_operator_turned_it_on(): void
    {
        config(['app.url' => 'https://toko.test', 'security.trusted_hosts' => ['toko.test']]);

        $this->get('https://toko.test/login')->assertOk()->assertHeaderMissing('Strict-Transport-Security');

        config(['security.hsts.enabled' => true]);
        $this->get('https://toko.test/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        config(['security.hsts.subdomains' => true]);
        $this->get('https://toko.test/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Never over plain HTTP, which is redirected first.
        config(['app.url' => 'http://toko.test']);
        $this->get('http://toko.test/login')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_a_server_error_shows_no_detail(): void
    {
        $page = $this->get('/harness/fails')->assertStatus(500);
        $page->assertDontSee('/var/www/html', false);
        $page->assertDontSee('SELECT secret', false);
        $page->assertDontSee('RuntimeException', false);
        $inertia = $page->viewData('page');
        $this->assertIsArray($inertia);
        $this->assertSame('load_failed', ((array) ($inertia['props'] ?? []))['state'] ?? null);

        $json = $this->getJson('/harness/fails')->assertStatus(500);
        $this->assertSame('{}', $json->getContent());
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertHeaders(TestResponse $response, string $kind): void
    {
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $kind);
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'), $kind);
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'), $kind);
        $this->assertSame(SecurityHeaders::PERMISSIONS_POLICY, $response->headers->get('Permissions-Policy'), $kind);
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'), $kind);
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'), $kind);
        $this->nonce($response);
        $this->assertFalse($response->headers->has('Strict-Transport-Security'), $kind.': no HSTS over plain HTTP');
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function nonce(TestResponse $response): string
    {
        $this->assertSame(1, preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/", (string) $response->headers->get('Content-Security-Policy'), $match));

        return $match[1] ?? '';
    }
}
