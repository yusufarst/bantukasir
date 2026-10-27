<?php

namespace Tests\Feature\Http;

use Tests\Harness\DatabaseTestCase;

/**
 * Trusted hosts and HTTPS only (AC-007-04; SECURITY_MODEL SEC-HDR-04,
 * SEC-HDR-05; ENVIRONMENTS sections 10 and 11; ADR-003; suites SEC01; THR-16;
 * TASK-007 scope item 5): a request for a host name that is not the
 * environment's is refused with 400 and no body, before a session or a
 * render, with the security headers; the names come from the configuration or
 * the application's address; where the address is HTTPS, a plain HTTP request
 * — also one that a trusted proxy reports as such — is sent to HTTPS keeping
 * its method, and a forged X-Forwarded-Proto from an untrusted hop changes
 * nothing.
 */
final class TrustedHostsAndHttpsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_unknown_host_is_refused_before_anything_runs(): void
    {
        config(['security.trusted_hosts' => ['toko.test']]);

        $this->get('http://toko.test/login')->assertOk();
        $this->get('http://TOKO.test:8443/login')->assertOk();

        foreach (['http://evil.test/login', 'http://toko.test.evil.test/login', 'http://sub.toko.test/login', 'http://localhost/login'] as $address) {
            $refused = $this->get($address);
            $refused->assertStatus(400);
            $this->assertSame('', $refused->getContent(), $address);
            $refused->assertHeader('X-Frame-Options', 'DENY');
            $refused->assertCookieMissing(config()->string('session.cookie'));
        }

        // A forwarded host is never read.
        $this->withHeaders(['X-Forwarded-Host' => 'evil.test'])->get('http://toko.test/login')->assertOk()->assertDontSee('evil.test');
    }

    public function test_plain_http_is_sent_to_https_where_the_address_is_https(): void
    {
        config(['app.url' => 'https://toko.test', 'security.trusted_hosts' => ['toko.test']]);

        $this->get('http://toko.test/login?next=1')->assertStatus(308)->assertHeader('Location', 'https://toko.test/login?next=1');
        $this->post('http://toko.test/login', ['email' => 'x@contoh.test'])->assertStatus(308);
        $this->get('https://toko.test/login')->assertOk();

        // A forged proto from an untrusted hop is not believed; from the
        // trusted proxy it is.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])->withHeaders(['X-Forwarded-Proto' => 'https'])->get('http://toko.test/login')->assertStatus(308);
        config(['security.trusted_proxies' => ['198.51.100.1']]);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->withHeaders(['X-Forwarded-Proto' => 'https'])->get('http://toko.test/login')->assertOk();
    }

    public function test_the_local_address_of_plain_http_is_left_as_it_is(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $this->get('/login')->assertOk();
    }
}
