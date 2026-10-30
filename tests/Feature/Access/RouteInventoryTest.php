<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Access\OwnPasswordController;
use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\Harness\Http\RouteInventory;
use Tests\TestCase;

/**
 * The route inventory (AC-007-01, AC-006-01; ROUTE_CONTRACTS RT-01, RT-04,
 * RT-05, section 8; SECURITY_MODEL SEC-AZ-01, SEC-AUTH-01, SEC-AUTH-07;
 * TECHNICAL_RULES TR-HTTP-02, TR-HTTP-04; suites AUTH01, SEC01, LINK01; THR-01,
 * THR-02, THR-03; TASK-006, TASK-007). Every route is listed here with its
 * route of ROUTE_CONTRACTS, its gate and the middleware it names; the rules of
 * Tests\Harness\Http\RouteInventory hold for every one of them — a route of V1
 * only, no DELETE, a gate on every signed-in route, RT-04's order — and the
 * check finds an ungated route. Fortify exposes exactly the features of
 * ADR-004 item 1, and no mail transport exists. Every later Task adds its
 * routes to ROUTES.
 */
final class RouteInventoryTest extends TestCase
{
    /** The middleware every signed-in route names (RT-04 to RT-06; TASK-007). */
    private const array SIGNED_IN = ['web', 'auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt'];

    /**
     * Every route: its name, its route of ROUTE_CONTRACTS, its gate — `guest`
     * before sign-in, `none` for the health route, otherwise the capabilities
     * of its `capability:` middleware — and the middleware it names.
     *
     * @var array<string, array{string, string, string, list<string>}>
     */
    private const array ROUTES = [
        'GET /' => ['home.redirect', 'RN-01', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'GET /account' => ['account.show', 'RP-003', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'GET /account/password' => ['account.password', 'RP-004', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'GET /login' => ['login', 'RP-001', 'guest', ['web', 'guest', 'throttle:presignin']],
        'GET /set-password' => ['password.link', 'RP-002', 'guest', ['web', 'no-referrer', 'guest', 'throttle:presignin']],
        'GET /up' => ['health', 'RH-01', 'none', ['throttle:presignin']],
        'GET /user/confirmed-password-status' => ['password.confirmation', 'RA-04', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'POST /login' => ['login.store', 'RA-01', 'guest', ['web', 'guest', 'throttle:presignin', 'throttle:signin']],
        'POST /logout' => ['logout', 'RA-02', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'input.allow', 'signout.record']],
        'POST /session/activity' => ['session.activity', 'RA-07', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'input.allow']],
        'POST /set-password' => ['password.link.store', 'RA-06', 'guest', ['web', 'no-referrer', 'guest', 'throttle:presignin', 'throttle:link']],
        'POST /user/confirm-password' => ['password.confirm.store', 'RA-03', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'throttle:confirm', 'confirm.input']],
        'PUT /user/password' => ['user-password.update', 'RA-05', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        // TASK-008: the status check and the unresolved request receipts.
        'POST /commands/resolve' => ['commands.resolve', 'RC-50', 'CAP-34', [...self::SIGNED_IN, 'capability:CAP-34', 'throttle:command']],
        'GET /data/command-requests' => ['data.requests', 'RD-04', 'CAP-34', [...self::SIGNED_IN, 'capability:CAP-34']],
        // TASK-009: the shell's indicators and the page of unresolved requests.
        'GET /data/shell' => ['data.shell', 'RD-03', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'input.allow']],
        'GET /requests' => ['requests.index', 'RP-006', 'CAP-34', [...self::SIGNED_IN, 'capability:CAP-34', 'input.allow']],
        // TASK-010: the settings index, the BusinessProfile pages and RC-32,
        // and RD-07, which chooses its own caching.
        'GET /settings' => ['settings.index', 'RP-601', 'CAP-25,CAP-30,CAP-31,CAP-32', [...self::SIGNED_IN, 'capability:CAP-25,CAP-30,CAP-31,CAP-32', 'input.allow']],
        'GET /settings/business-profile' => ['profile.show', 'RP-607', 'CAP-30', [...self::SIGNED_IN, 'capability:CAP-30', 'input.allow']],
        'GET /settings/business-profile/edit' => ['profile.edit', 'RP-608', 'CAP-30', [...self::SIGNED_IN, 'capability:CAP-30', 'input.allow']],
        'POST /commands/business_profile.change' => ['commands.business_profile.change', 'RC-32', 'CAP-30', [...self::SIGNED_IN, 'capability:CAP-30', 'throttle:command', 'throttle:logo', 'password.confirm']],
        'GET /media/{file}' => ['media.show', 'RD-07', 'CAP-01', ['web', 'auth', 'signin.session', 'throttle:authenticated', 'history.encrypt', 'capability:CAP-01', 'input.allow:variant']],
    ];

    public function test_the_routes_are_exactly_those_listed_each_with_its_gate(): void
    {
        $routes = RouteInventory::routes(app(Router::class));

        // The test-only routes of the local and testing environments: the
        // specimens (TASK-005, TASK-007) and the browser fixtures (TASK-006).
        foreach (RouteInventory::TESTING as $testing) {
            $this->assertArrayHasKey($testing, $routes);
            unset($routes[$testing]);
        }

        $actual = [];
        $contracts = RouteInventory::contracts();

        foreach ($routes as $key => $route) {
            $declared = array_map(strval(...), $route->gatherMiddleware());
            $gates = array_values(array_filter($declared, static fn (string $middleware): bool => str_starts_with($middleware, 'capability:')));
            $expected = self::ROUTES[$key] ?? null;
            $this->assertNotNull($expected, $key.' is not listed in the inventory.');
            $this->assertArrayHasKey($key, $contracts, $expected[1].' is in ROUTE_CONTRACTS.');
            $gate = $gates === [] ? (in_array('guest', $declared, true) ? 'guest' : 'none') : substr($gates[0], strlen('capability:'));

            $actual[$key] = [(string) $route->getName(), $expected[1], $gate, $declared];
        }

        ksort($actual);
        $expected = self::ROUTES;
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public function test_every_route_keeps_the_rules_of_the_inventory(): void
    {
        $this->assertSame([], RouteInventory::violations(app(Router::class)));
    }

    public function test_the_inventory_finds_an_ungated_route_and_one_outside_v1(): void
    {
        // AC-007-01: a route signed in but without a gate, a route that no
        // contract names, a DELETE route and a gate naming no capability are
        // each found.
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated'])->get('/account/harness-ungated', static fn (): string => '');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])->get('/harness/outside-v1', static fn (): string => '');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])->delete('/account', static fn (): string => '');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-99'])->post('/commands/sale.checkout', static fn (): string => '');
        app(Router::class)->getRoutes()->refreshNameLookups();

        $violations = implode("\n", RouteInventory::violations(app(Router::class)));

        $this->assertStringContainsString('GET /account/harness-ungated: 0 capability gates, not one (RT-05)', $violations);
        $this->assertStringContainsString('GET /account/harness-ungated: its history entries not encrypted (SEC-CH-04)', $violations);
        $this->assertStringContainsString('GET /account/harness-ungated: not a route of ROUTE_CONTRACTS', $violations);
        $this->assertStringContainsString('GET /harness/outside-v1: not a route of ROUTE_CONTRACTS', $violations);
        $this->assertStringContainsString('GET /harness/outside-v1: its history entries not encrypted (SEC-CH-04)', $violations);
        $this->assertStringContainsString('DELETE /account: a DELETE route (TR-HTTP-04)', $violations);
        $this->assertStringContainsString('POST /commands/sale.checkout: the gate names CAP-99, not a capability', $violations);
        $this->assertStringNotContainsString('GET /account/password', $violations);
    }

    public function test_the_routes_of_route_contracts_are_read_from_the_document(): void
    {
        $contracts = RouteInventory::contracts();

        $this->assertSame('home.redirect', $contracts['GET /'] ?? null);
        $this->assertSame('login.store', $contracts['POST /login'] ?? null);
        $this->assertSame('health', $contracts['GET /up'] ?? null);
        $this->assertSame('registers.edit', $contracts['GET /settings/cash-registers/{register}/edit'] ?? null);
        $this->assertSame('data.products.search', $contracts['GET /data/products/search'] ?? null);
        $this->assertArrayHasKey('POST /commands/sale.checkout', $contracts);
        $this->assertArrayHasKey('POST /commands/resolve', $contracts);
        // The 60 routable pages, RP-610 with two paths, three print views and
        // RN-01; 7 reads; 7 authentication endpoints; 3 requests; 48 commands
        // and the resolution (ROUTE_CONTRACTS section 8).
        $this->assertCount(60 + 1 + 3 + 1 + 7 + 7 + 3 + 49, $contracts);
    }

    public function test_no_route_of_a_disabled_feature_exists(): void
    {
        $paths = [];

        foreach (app(Router::class)->getRoutes()->getRoutes() as $route) {
            $paths[] = $route->uri();
            $this->assertNotContains('DELETE', $route->methods(), $route->uri());
        }

        foreach (['register', 'forgot-password', 'reset-password', 'email', 'verify', 'two-factor', 'passkey', 'profile-information', 'recovery-codes'] as $fragment) {
            foreach ($paths as $path) {
                $this->assertStringNotContainsString($fragment, $path);
            }
        }

        $this->assertSame([Features::updatePasswords()], config('fortify.features'));
        $this->assertFalse(config('fortify.views'));
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Features::enabled(Features::resetPasswords()));
        $this->assertFalse(Features::enabled(Features::emailVerification()));
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::enabled(Features::passkeys()));
        $this->assertFalse(Features::enabled(Features::updateProfileInformation()));
        $this->assertTrue(Features::enabled(Features::updatePasswords()));
    }

    public function test_no_mail_transport_is_configured_and_nothing_can_be_sent(): void
    {
        $this->assertSame('none', config('mail.default'));
        $mailers = config('mail.mailers');
        $this->assertIsArray($mailers);
        $this->assertSame([], array_filter($mailers), 'A mailer has a configuration: no mail transport is configured (D-049).');

        $this->expectExceptionMessage('Mailer [none] is not defined.');
        Mail::raw('-', fn () => null);
    }

    public function test_ra_05_runs_the_applications_controller_which_leaves_a_link_alone(): void
    {
        // Fortify's PasswordController deletes the user's one-time link after
        // the update (review finding R-02); RA-05 never runs it.
        $route = app(Router::class)->getRoutes()->getByName('user-password.update');

        $this->assertNotNull($route);
        $this->assertSame(OwnPasswordController::class.'@update', $route->getActionName());
    }

    public function test_the_password_broker_never_sends_a_link_by_email(): void
    {
        $user = new User;

        $this->expectExceptionMessage('The application never sends email');
        $user->sendPasswordResetNotification('token');
    }
}
