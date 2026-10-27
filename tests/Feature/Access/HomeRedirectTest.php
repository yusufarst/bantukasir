<?php

namespace Tests\Feature\Access;

use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * RN-01, the home redirect (AC-007-02; ROUTE_CONTRACTS RN-01;
 * NAVIGATION_CONTRACTS NC-12, UXP-001; INFORMATION_ARCHITECTURE section 4;
 * TASK-007 scope item 7): no role's home page exists yet, so every role and
 * the combined account are sent to Akun saya (RP-003), until TASK-022,
 * TASK-027, TASK-029 and TASK-041 switch them; a first visit is sent to the
 * sign-in page without the note that a sign-in session ended, and a browser
 * whose sign-in session a limit ended gets it — by the application's limit
 * or, a minute later, by the framework's own lifetime of the session.
 */
final class HomeRedirectTest extends DatabaseTestCase
{
    use Browsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-17T02:00:00Z');
        $this->useDatabaseSessions();
    }

    public function test_every_role_and_the_combined_account_go_to_their_home(): void
    {
        foreach (['Hendra', 'Agus', 'Rina', 'Dewi'] as $person) {
            $account = TokoContoh::person($person);
            $this->browse($this->signInBrowser($account['email'], $account['password']));

            $this->get('/')->assertRedirect('/account');
        }
    }

    public function test_no_redirect_leaves_the_application_whatever_the_request_names(): void
    {
        // THR-13: the sign-in, RN-01 and the answer to a signed-out request
        // lead only to the application's own paths; no parameter, field or
        // header names a target.
        $rina = TokoContoh::person('Rina');
        $this->browse(null);

        foreach (['redirect', 'next', 'url', 'return', 'intended'] as $parameter) {
            $this->get('/account?'.$parameter.'=https://evil.example/')->assertRedirect('/login');
            $this->get('/?'.$parameter.'=//evil.example/')->assertRedirect('/login');
        }

        // A target in the sign-in's request is an unknown field, refused to
        // the sign-in page; a sign-in goes home whatever the referrer says.
        $this->withHeader('Referer', 'https://evil.example/')
            ->post('/login?redirect=https://evil.example/', ['email' => $rina['email'], 'password' => $rina['password']])
            ->assertRedirect('/login');
        $this->browse(null);
        $signedIn = $this->withHeader('Referer', 'https://evil.example/')
            ->post('/login', ['email' => $rina['email'], 'password' => $rina['password']])
            ->assertRedirect('/');

        $this->browse($this->sessionOf($signedIn));
        $this->withHeader('Referer', 'https://evil.example/')->get('/?next=https://evil.example/')->assertRedirect('/account');
    }

    public function test_a_visitor_without_a_sign_in_session_goes_to_the_sign_in_page_without_the_note(): void
    {
        $this->browse(null);
        $home = $this->get('/')->assertRedirect('/login');

        // The browser follows with the session cookie the answer set.
        $this->browse($this->sessionOf($home));
        $page = $this->get('/login')->assertOk()->viewData('page');
        $this->assertIsArray($page);
        $this->assertNull(((array) $page['props'])['notice'] ?? null);
    }

    public function test_a_sign_in_session_that_a_limit_ended_gets_the_note_at_the_home_too(): void
    {
        $rina = TokoContoh::person('Rina');

        foreach ([30, 31] as $minutes) {
            $session = $this->signInBrowser($rina['email'], $rina['password']);

            $this->travel($minutes)->minutes();
            $this->browse($session);
            $home = $this->get('/')->assertRedirect('/login');

            $this->browse($this->sessionOf($home));
            $page = $this->get('/login')->assertOk()->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('signed_out', ((array) $page['props'])['notice'] ?? null, $minutes.' minutes');
        }
    }
}
