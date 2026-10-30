<?php

namespace Tests\Feature\Access;

use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * RP-003 and RP-004 (AC-006-09; ROUTE_CONTRACTS section 2; SECURITY_MODEL
 * SEC-CH-01, SEC-CH-04, SEC-SESS-01; STK-21; suites AUTH02 and SEC01 for what
 * this Task adds). Every role and the combined account reach their own
 * account only, by the sign-in session; the page receives the DTO's four
 * fields and nothing else of the account; the answers are not stored by a
 * cache and their history entries are encrypted.
 */
final class AccountPagesTest extends DatabaseTestCase
{
    use Browsers;
    use ChannelInspector;

    /** The kind of account of each person of Toko Contoh (Allowlists::ACTORS). */
    private const array ACTORS = ['Hendra' => 'owner', 'Agus' => 'ops', 'Rina' => 'cashier', 'Dewi' => 'combined'];

    protected function setUp(): void
    {
        parent::setUp();

        // The pages render the root view, whose assets the PHP tests never
        // read (Tests\TestCase).
        $this->withoutVite();
    }

    public function test_each_role_and_the_combined_account_see_their_own_account_and_nothing_else(): void
    {
        $expected = [
            'Hendra' => [['SUPER_ADMIN'], true],
            'Agus' => [['OPERATIONS_ADMIN'], false],
            'Rina' => [['CASHIER'], false],
            'Dewi' => [['CASHIER', 'OPERATIONS_ADMIN'], false],
        ];

        foreach ($expected as $person => [$roles, $owner]) {
            $account = TokoContoh::person($person);
            $this->post('/login', ['email' => $account['email'], 'password' => $account['password']])->assertRedirect('/');

            $response = $this->get('/account?user='.($account['id'] + 1));
            $response->assertOk();
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

            $page = $response->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('access/Account', $page['component']);
            $this->assertTrue($page['encryptHistory']);
            $props = (array) $page['props'];
            $this->assertSame(['account', 'auth', 'errors', 'passwordChanged', 'recoveryEpoch', 'shop'], self::propNames($props));
            $this->assertSame(['displayName' => $person, 'email' => $account['email'], 'roles' => $roles, 'owner' => $owner], $props['account']);
            $this->assertFalse($props['passwordChanged']);
            // Every field, against the allowlists of the role (TASK-007).
            $this->assertPageChannel($response, self::ACTORS[$person], [$account['password']]);

            $formResponse = $this->get('/account/password')->assertOk();
            $form = $formResponse->viewData('page');
            $this->assertIsArray($form);
            $this->assertSame('access/ChangePassword', $form['component']);
            $this->assertTrue($form['encryptHistory']);
            $this->assertSame(['auth', 'errors', 'recoveryEpoch', 'shop'], self::propNames((array) $form['props']));
            $this->assertPageChannel($formResponse, self::ACTORS[$person], [$account['password']]);

            $this->post('/logout')->assertRedirect(route('login'));
        }
    }

    public function test_the_session_cookie_is_http_only_lax_and_host_only(): void
    {
        $response = $this->get('/login')->assertOk();
        $cookie = null;

        foreach ($response->headers->getCookies() as $candidate) {
            if ($candidate->getName() === config('session.cookie')) {
                $cookie = $candidate;
            }
        }

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure(), 'Secure by default (review finding R-07).');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame(30, config('session.lifetime'));
    }
}
