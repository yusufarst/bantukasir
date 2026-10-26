<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Access\OwnPasswordController;
use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Fortify exposes exactly the features of ADR-004 item 1 (AC-006-01; ROUTE_CONTRACTS
 * sections 2 and 4; SECURITY_MODEL SEC-AUTH-01, SEC-AUTH-07; suite LINK01;
 * THR-01): the routes of TASK-006 are exactly RP-001 to RP-004 and RA-01 to
 * RA-07, each with the middleware of RT-04; no registration, email
 * verification, two-factor, passkey, public reset or Fortify view route
 * exists; no DELETE route; and no mail transport is configured. TASK-007's
 * route-inventory test then covers every route with its gate.
 */
final class RouteInventoryTest extends TestCase
{
    /**
     * Method, path, name and the middleware of each route of TASK-006.
     */
    private const array ROUTES = [
        'GET /login' => ['login', ['web', 'guest']],
        'POST /login' => ['login.store', ['web', 'guest', 'throttle:signin']],
        'GET /set-password' => ['password.link', ['web', 'no-referrer', 'guest']],
        'POST /set-password' => ['password.link.store', ['web', 'no-referrer', 'guest', 'throttle:link']],
        'POST /logout' => ['logout', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private', 'signout.record']],
        'POST /user/confirm-password' => ['password.confirm.store', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private', 'throttle:confirm', 'confirm.input']],
        'GET /user/confirmed-password-status' => ['password.confirmation', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private']],
        'PUT /user/password' => ['user-password.update', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private']],
        'POST /session/activity' => ['session.activity', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private']],
        'GET /account' => ['account.show', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private']],
        'GET /account/password' => ['account.password', ['web', 'auth', 'signin.session', 'cache.headers:no_store;private']],
    ];

    public function test_the_routes_are_exactly_those_of_task_006_and_the_specimen(): void
    {
        $routes = [];

        foreach (app(Router::class)->getRoutes()->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $routes[$method.' /'.ltrim($route->uri(), '/')] = [$route->getName(), $route->gatherMiddleware()];
            }
        }

        // The test-only routes of the local and testing environments: the
        // specimen (TASK-005) and the browser fixtures (TASK-006).
        foreach (['GET /testing/specimen', 'POST /testing/browser/reset', 'POST /testing/browser/link'] as $testing) {
            $this->assertArrayHasKey($testing, $routes);
            unset($routes[$testing]);
        }

        $this->assertSame(self::ROUTES, $routes);
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
