<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Capability;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Access\PermissionMatrix;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;
use Tests\Harness\Http\RouteInventory;

/**
 * The capability matrix, by direct request (AC-007-02; ROUTE_CONTRACTS RT-04,
 * RT-05, RT-06; SECURITY_MODEL SEC-AZ-01, SEC-AZ-02, SEC-AZ-05, SEC-AZ-07,
 * section 6; TECHNICAL_RULES TR-HTTP-02, TR-VAL-01, TR-VAL-03; EXECUTION_CONTEXT
 * section 10; suites AUTH01 and SEC01; THR-02, THR-03; TASK-007 scope items 1
 * and 2). Data-driven: every route present, for a visitor without a sign-in
 * session, each role and the combined account, answers what the matrix gives;
 * every gate of CAP-01 to CAP-34 opens exactly for the kinds of account that
 * SECURITY_MODEL section 6 allows, read from the document; a refusal is
 * FORBIDDEN and recorded; no GET writes; and a role, a user or an actor sent
 * in the body of any request is refused and changes nothing. Every later Task
 * adds its routes to ROUTES.
 */
final class CapabilityMatrixTest extends DatabaseTestCase
{
    use Browsers;

    /** The person of each kind of account (KEY_SCREENS section 5). */
    private const array PEOPLE = ['owner' => 'Hendra', 'ops' => 'Agus', 'cashier' => 'Rina', 'combined' => 'Dewi'];

    /**
     * Every route: its request, and the answer of each kind of account —
     * the status, and the location of a redirect.
     *
     * @var array<string, array{method: string, path: string, json: bool, answers: array<string, array{int, string|null}>}>
     */
    private const array ROUTES = [
        'RN-01' => ['method' => 'GET', 'path' => '/', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/account'], 'ops' => [302, '/account'], 'cashier' => [302, '/account'], 'combined' => [302, '/account'],
        ]],
        'RP-001' => ['method' => 'GET', 'path' => '/login', 'json' => false, 'answers' => [
            'guest' => [200, null], 'owner' => [302, '/'], 'ops' => [302, '/'], 'cashier' => [302, '/'], 'combined' => [302, '/'],
        ]],
        'RP-002' => ['method' => 'GET', 'path' => '/set-password', 'json' => false, 'answers' => [
            'guest' => [200, null], 'owner' => [302, '/#'], 'ops' => [302, '/#'], 'cashier' => [302, '/#'], 'combined' => [302, '/#'],
        ]],
        'RP-003' => ['method' => 'GET', 'path' => '/account', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RP-004' => ['method' => 'GET', 'path' => '/account/password', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RA-04' => ['method' => 'GET', 'path' => '/user/confirmed-password-status', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RA-07' => ['method' => 'POST', 'path' => '/session/activity', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [204, null], 'ops' => [204, null], 'cashier' => [204, null], 'combined' => [204, null],
        ]],
        'RA-02' => ['method' => 'POST', 'path' => '/logout', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/login'], 'ops' => [302, '/login'], 'cashier' => [302, '/login'], 'combined' => [302, '/login'],
        ]],
        'RH-01' => ['method' => 'GET', 'path' => '/up', 'json' => false, 'answers' => [
            'guest' => [200, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        // The routes that take a body, sent without one: a signed-in account
        // is sent away from the pre-authentication routes, and passes the
        // gate of the signed-in ones to the refusal of its missing fields.
        'RA-01' => ['method' => 'POST', 'path' => '/login', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/'], 'ops' => [302, '/'], 'cashier' => [302, '/'], 'combined' => [302, '/'],
        ]],
        'RA-06' => ['method' => 'POST', 'path' => '/set-password', 'json' => false, 'answers' => [
            'guest' => [302, '/set-password'], 'owner' => [302, '/#'], 'ops' => [302, '/#'], 'cashier' => [302, '/#'], 'combined' => [302, '/#'],
        ]],
        'RA-03' => ['method' => 'POST', 'path' => '/user/confirm-password', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        'RA-05' => ['method' => 'PUT', 'path' => '/user/password', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        // TASK-008: CAP-34 for every kind of account — the Owner any intent,
        // the others their own, which the Action checks.
        'RC-50' => ['method' => 'POST', 'path' => '/commands/resolve', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        'RD-04' => ['method' => 'GET', 'path' => '/data/command-requests', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        // TASK-009: RD-03 for every signed-in account (CAP-01), RP-006 for
        // every holder of CAP-34, which every role is.
        'RD-03' => ['method' => 'GET', 'path' => '/data/shell', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RP-006' => ['method' => 'GET', 'path' => '/requests', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
    ];

    /** @var array<string, array{id: int, email: string, password: string}> */
    private array $accounts = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The pages render the root view, whose assets the PHP tests never
        // read (Tests\TestCase).
        $this->withoutVite();
        $this->useDatabaseSessions();

        foreach (self::PEOPLE as $actor => $person) {
            $this->accounts[$actor] = TokoContoh::person($person);
        }
    }

    public function test_the_matrix_holds_every_route_of_the_router(): void
    {
        $listed = array_map(static fn (array $route): string => $route['method'].' '.$route['path'], array_values(self::ROUTES));
        $served = array_values(array_diff(array_keys(RouteInventory::routes(app('router'))), RouteInventory::TESTING));

        sort($listed);
        sort($served);

        $this->assertSame($served, $listed, 'Every route of the router has its row in ROUTES (AC-007-02; EXECUTION_CONTEXT section 10).');

        foreach (self::ROUTES as $id => $route) {
            $this->assertSame(['guest', 'owner', 'ops', 'cashier', 'combined'], array_keys($route['answers']), $id.' answers every kind of account.');
        }
    }

    public function test_every_route_answers_each_kind_of_account_as_the_matrix_gives(): void
    {
        foreach (self::ROUTES as $id => $route) {
            foreach ($route['answers'] as $actor => [$status, $location]) {
                $this->as($actor);
                $response = $this->send($route['method'], $route['path'], $route['json']);

                $this->assertSame($status, $response->getStatusCode(), sprintf('%s for %s', $id, $actor));

                if ($location !== null) {
                    $this->assertSame($location === '/#' ? url('/').'/#' : url($location), $response->headers->get('Location'), sprintf('%s for %s', $id, $actor));
                }
            }
        }
    }

    public function test_every_gate_opens_exactly_for_the_kinds_of_account_of_security_model_section_6(): void
    {
        $matrix = PermissionMatrix::cells();

        foreach (Capability::cases() as $capability) {
            Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:'.$capability->value])
                ->get('/harness/gate/'.$capability->value, static fn (): string => 'opened');
        }

        foreach (self::PEOPLE as $actor => $person) {
            $session = $this->signInBrowser($this->accounts[$actor]['email'], $this->accounts[$actor]['password']);

            foreach (Capability::cases() as $capability) {
                $opens = in_array(PermissionMatrix::grantFor($matrix[$capability->value], $actor), ['full', 'own'], true);
                $refusals = $this->refusals();

                $this->browse($session);
                $response = $this->getJson('/harness/gate/'.$capability->value);

                if ($opens) {
                    $response->assertOk()->assertSeeText('opened');
                    $this->assertSame($refusals, $this->refusals());
                } else {
                    $response->assertStatus(403)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'FORBIDDEN', 'messageKey' => 'ux.state.forbidden', 'params' => [], 'fields' => [], 'lines' => []]);
                    $this->assertSame($refusals + 1, $this->refusals(), sprintf('%s for %s is recorded (SEC-LOG-03).', $capability->value, $actor));
                }
            }
        }
    }

    public function test_a_refused_page_is_the_page_of_uxs_07_and_a_gate_naming_several_capabilities_opens_on_any(): void
    {
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-22'])->get('/harness/owner-page', static fn (): string => 'opened');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-21,CAP-22'])->get('/harness/sales', static fn (): string => 'opened');

        $this->as('cashier');
        $page = $this->get('/harness/owner-page')->assertStatus(403)->viewData('page');
        $this->assertIsArray($page);
        $this->assertSame(['shell/Error', 'forbidden'], [$page['component'], ((array) $page['props'])['state'] ?? null]);
        $this->assertTrue($page['encryptHistory'], 'The page of a signed-in user is encrypted in the history (SEC-CH-04).');

        $last = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['AUTHORIZATION_REFUSED', 'USER', $this->accounts['cashier']['id'], 'REFUSED'], [$last['event_type'], $last['actor_kind'], $last['actor_user_id'], $last['outcome_code']]);

        $this->get('/harness/sales')->assertOk();
        $this->as('ops');
        $this->get('/harness/sales')->assertStatus(403);
        $this->as('owner');
        $this->get('/harness/sales')->assertOk();
    }

    public function test_a_signed_in_route_without_a_declared_capability_is_refused_at_run_time(): void
    {
        // SEC-AZ-01: refused, not only found by the route inventory (review
        // finding R-32). The route would answer "served" if it ran.
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated'])->get('/harness/ungated', static fn (): string => 'served');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated'])->post('/harness/ungated', static fn (): string => 'served');

        foreach (['owner', 'ops', 'cashier', 'combined'] as $actor) {
            $this->as($actor);
            $refusals = $this->refusals();

            $this->getJson('/harness/ungated')->assertStatus(403)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'FORBIDDEN', 'messageKey' => 'ux.state.forbidden', 'params' => [], 'fields' => [], 'lines' => []]);
            $this->postJson('/harness/ungated')->assertStatus(403)->assertDontSeeText('served');
            $this->get('/harness/ungated')->assertStatus(403)->assertDontSeeText('served');
            $this->assertSame($refusals + 3, $this->refusals(), sprintf('Each refusal of %s is recorded (SEC-LOG-03).', $actor));
        }

        // Without a sign-in session, authentication refuses it first.
        $this->as('guest');
        $this->getJson('/harness/ungated')->assertStatus(401)->assertDontSeeText('served');
        $this->postJson('/harness/ungated')->assertStatus(401)->assertDontSeeText('served');
        $this->get('/harness/ungated')->assertRedirect('/login');
    }

    public function test_no_get_writes(): void
    {
        foreach (self::ROUTES as $id => $route) {
            if ($route['method'] !== 'GET') {
                continue;
            }

            foreach (array_keys($route['answers']) as $actor) {
                $this->as($actor);
                $before = $this->businessState();
                $this->send('GET', $route['path'], $route['json']);

                $this->assertSame($before, $this->businessState(), sprintf('%s for %s wrote (TR-HTTP-02).', $id, $actor));
            }
        }
    }

    public function test_a_role_a_user_or_an_actor_in_the_body_of_any_request_is_refused_and_changes_nothing(): void
    {
        $injections = [['role' => 'SUPER_ADMIN'], ['roles' => ['SUPER_ADMIN']], ['user_id' => '1'], ['actor_id' => '1'], ['responsible_user_id' => '1']];

        foreach (['owner', 'ops', 'cashier', 'combined'] as $actor) {
            foreach ($injections as $injection) {
                $this->as($actor);
                $before = $this->businessState();
                $new = TokoContoh::newPassword();
                $refused = [
                    'RA-02' => $this->postJson('/logout', $injection),
                    'RA-07' => $this->postJson('/session/activity', $injection),
                    'RA-03' => $this->postJson('/user/confirm-password', ['password' => $this->accounts[$actor]['password'], ...$injection]),
                    'RA-05' => $this->putJson('/user/password', ['current_password' => $this->accounts[$actor]['password'], 'password' => $new, 'password_confirmation' => $new, ...$injection]),
                    'RC-50' => $this->postJson('/commands/resolve', ['operation' => 'sale.checkout', 'sourceIntentId' => '7b0c1c53-5f0e-4c4e-9a43-2f1f6a7e9b10', 'recoveryEpoch' => str_repeat('ab', 32), ...$injection]),
                ];

                foreach ($refused as $id => $response) {
                    $this->assertSame(422, $response->getStatusCode(), sprintf('%s with %s for %s', $id, (string) json_encode($injection), $actor));
                }

                $this->assertSame($before, $this->businessState());
                // Still signed in: the refused sign-out ran nothing.
                $this->getJson('/user/confirmed-password-status')->assertOk()->assertExactJson(['confirmed' => false]);
            }
        }

        // Before sign-in: the sign-in and the link refuse a role in the body.
        $this->as('guest');
        $this->post('/login', ['email' => $this->accounts['owner']['email'], 'password' => $this->accounts['owner']['password'], 'role' => 'SUPER_ADMIN'])->assertRedirect();
        $this->assertGuest();
        $this->post('/set-password', ['user' => (string) $this->accounts['cashier']['id'], 'token' => 'x', 'password' => 'Aa1#aaaa', 'password_confirmation' => 'Aa1#aaaa', 'role' => 'SUPER_ADMIN'])->assertRedirect();
        $this->assertSame(['CASHIER'], $this->rolesOf($this->accounts['cashier']['id']));
    }

    /**
     * The next requests come from a new browser of this kind of account,
     * signed in, or without a sign-in session. The rate limiter's counters are
     * emptied first: this test signs the same people in far more often than
     * the sign-in buckets allow, which RateLimitsTest proves.
     */
    private function as(string $actor): void
    {
        Cache::store()->flush();

        if ($actor === 'guest') {
            $this->browse(null);

            return;
        }

        $this->browse($this->signInBrowser($this->accounts[$actor]['email'], $this->accounts[$actor]['password']));
    }

    /**
     * @return TestResponse<Response>
     */
    private function send(string $method, string $path, bool $json): TestResponse
    {
        if ($json) {
            return $this->json($method, $path);
        }

        return $method === 'GET' ? $this->get($path) : $this->post($path);
    }

    private function refusals(): int
    {
        return $this->watcher()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count();
    }

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * The business state that no GET may change: every account, link and
     * audit event, as committed.
     *
     * @return array<string, mixed>
     */
    private function businessState(): array
    {
        $observer = $this->watcher();

        return [
            'users' => $observer->table('users')->orderBy('id')->get(['id', 'status', 'password', 'role_super_admin', 'role_operations_admin', 'role_cashier', 'version', 'updated_at'])->map(static fn (object $row): array => (array) $row)->all(),
            'links' => $observer->table('password_reset_tokens')->orderBy('email')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'audit' => $observer->table('audit_events')->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function rolesOf(int $id): array
    {
        $row = (array) DB::table('users')->where('id', $id)->first();
        $roles = [];

        foreach (['role_super_admin' => 'SUPER_ADMIN', 'role_operations_admin' => 'OPERATIONS_ADMIN', 'role_cashier' => 'CASHIER'] as $column => $role) {
            if (($row[$column] ?? false) === true) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
