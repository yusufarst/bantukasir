<?php

namespace Tests\Harness\Http;

use App\Domain\Access\Capability;
use App\Http\Middleware\EnsureSignInSession;
use App\Http\Middleware\RequireCapability;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use InvalidArgumentException;

/**
 * The rules of the route inventory (ROUTE_CONTRACTS RT-01, RT-04, RT-05,
 * section 8; SECURITY_MODEL SEC-AZ-01; TECHNICAL_RULES TR-HTTP-02, TR-HTTP-04;
 * suites AUTH01 and SEC01; TASK-007 scope item 2), which every later Task's
 * routes must keep:
 *
 * - every route is a route of ROUTE_CONTRACTS — its method and path, and its
 *   name where the contract names one — or a test-only route of the local and
 *   testing environments: nothing outside V1;
 * - no DELETE route;
 * - a pre-authentication route (SEC-AZ-01) is guest only and in the bucket
 *   before sign-in, and names no capability; the health route RH-01 runs no
 *   session middleware at all;
 * - every other route is signed in: authentication, the sign-in session's
 *   checks, the bucket of every signed-in request, and exactly one capability
 *   gate whose names are capabilities of SECURITY_MODEL section 6, in the
 *   order of RT-04 once the router has sorted them — the session, then
 *   authentication, the sign-in session, CSRF, the rate limits and the gate.
 *
 * violations() lists each broken rule; RouteInventoryTest proves that it finds
 * an ungated route (AC-007-01) and none on the tree.
 */
final class RouteInventory
{
    /** The test-only routes, which the local and testing environments alone hold (SpecimenController). */
    public const array TESTING = ['GET /testing/specimen', 'GET /testing/shell-specimen', 'POST /testing/browser/reset', 'POST /testing/browser/link', 'POST /commands/testing.probe'];

    /** The routes of SEC-AZ-01 that need no sign-in, and how each is restricted instead. */
    public const array PUBLIC = [
        'GET /login' => 'guest',
        'POST /login' => 'guest',
        'GET /set-password' => 'guest',
        'POST /set-password' => 'guest',
        'GET /up' => 'health',
    ];

    /**
     * Every route by "METHOD /path", without HEAD, which the framework adds to
     * every GET.
     *
     * @return array<string, Route>
     */
    public static function routes(Router $router): array
    {
        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $routes[$method.' /'.ltrim($route->uri(), '/')] = $route;
                }
            }
        }

        ksort($routes);

        return $routes;
    }

    /**
     * The routes of ROUTE_CONTRACTS, read from its tables: "METHOD /path" and
     * the route name where the contract names one.
     *
     * @return array<string, string|null>
     */
    public static function contracts(): array
    {
        $document = file_get_contents(base_path('docs/03-workflows/ROUTE_CONTRACTS.md'));

        if ($document === false) {
            throw new InvalidArgumentException('ROUTE_CONTRACTS is missing.');
        }

        $routes = [];

        foreach (explode("\n", $document) as $line) {
            if (preg_match('/^\| (R[NPDARHC]-[0-9]+P?) \| (.*)$/', $line, $match) !== 1) {
                continue;
            }

            $id = $match[1];
            $cells = array_map(trim(...), explode(' | ', $match[2]));
            $kind = substr($id, 0, 2);

            if ($kind === 'RN' || $kind === 'RP') {
                // ID | Page | Path | Name | …: GET pages, some rows with two paths and names.
                $paths = self::quoted($cells[1] ?? '');
                $names = self::quoted($cells[2] ?? '');

                foreach ($paths as $index => $path) {
                    $routes['GET '.$path] = $names[$index] ?? null;
                }
            } elseif ($kind === 'RC') {
                // ID | Operation | …: POST /commands/<operation>; the resolution names its path.
                if (preg_match('/`(POST [^`]+)`/', $cells[0], $resolution) === 1) {
                    $routes[$resolution[1]] = null;
                } else {
                    foreach (self::quoted($cells[0]) as $operation) {
                        $routes['POST /commands/'.$operation] = null;
                    }
                }
            } else {
                // RD: ID | Path | Name; RA, RR, RH: ID | Method and path | Name.
                $path = self::quoted($cells[0])[0] ?? '';
                $routes[str_contains($path, ' ') ? $path : 'GET '.$path] = self::quoted($cells[1] ?? '')[0] ?? null;
            }
        }

        return $routes;
    }

    /**
     * The broken rules, one line each.
     *
     * @return list<string>
     */
    public static function violations(Router $router, bool $testingRoutesAllowed = true): array
    {
        // The HTTP kernel gives the router its groups, aliases and priority
        // (bootstrap/app.php) when it is made.
        app(Kernel::class);

        $contracts = self::contracts();
        $violations = [];

        foreach (self::routes($router) as $key => $route) {
            [$method] = explode(' ', $key, 2);
            $declared = array_map(strval(...), $route->gatherMiddleware());
            $gates = array_values(array_filter($declared, static fn (string $middleware): bool => str_starts_with($middleware, 'capability:')));

            if ($method === 'DELETE') {
                $violations[] = $key.': a DELETE route (TR-HTTP-04)';
            }

            if (in_array($key, self::TESTING, true)) {
                if (! $testingRoutesAllowed) {
                    $violations[] = $key.': a test-only route outside the local and testing environments';
                }

                continue;
            }

            if (! array_key_exists($key, $contracts)) {
                $violations[] = $key.': not a route of ROUTE_CONTRACTS (REQ-SCOPE-02)';
            } elseif ($contracts[$key] !== null && $contracts[$key] !== $route->getName()) {
                $violations[] = $key.': named '.$route->getName().', not '.$contracts[$key];
            }

            $public = self::PUBLIC[$key] ?? null;

            if ($public !== null) {
                if ($gates !== []) {
                    $violations[] = $key.': a pre-authentication route with a capability gate';
                }

                if (! in_array('throttle:presignin', $declared, true)) {
                    $violations[] = $key.': not in the bucket before sign-in';
                }

                if ($public === 'guest' && ! in_array('guest', $declared, true)) {
                    $violations[] = $key.': a pre-authentication route that is not guest only';
                }

                if ($public === 'health' && $declared !== ['throttle:presignin']) {
                    $violations[] = $key.': the health route runs only its throttle (RH-01)';
                }

                continue;
            }

            foreach (['auth', 'signin.session', 'throttle:authenticated'] as $required) {
                if (! in_array($required, $declared, true)) {
                    $violations[] = $key.': without '.$required.' (RT-04)';
                }
            }

            // Every signed-in page's history entries are encrypted (SEC-CH-04;
            // review finding R-33). Its answers are never stored without a
            // route middleware: SecurityHeaders gives every answer that
            // chose no policy `no-store, private` (TR-HTTP-09).
            if (! in_array('history.encrypt', $declared, true)) {
                $violations[] = $key.': its history entries not encrypted (SEC-CH-04)';
            }

            if (count($gates) !== 1) {
                $violations[] = $key.': '.count($gates).' capability gates, not one (RT-05)';

                continue;
            }

            foreach (explode(',', substr($gates[0], strlen('capability:'))) as $name) {
                if (Capability::tryFrom($name) === null) {
                    $violations[] = $key.': the gate names '.$name.', not a capability';
                }
            }

            $order = self::order($router->gatherRouteMiddleware($route));

            if ($order !== [StartSession::class, Authenticate::class, EnsureSignInSession::class, PreventRequestForgery::class, ThrottleRequests::class, RequireCapability::class]) {
                $violations[] = $key.': the middleware runs as '.implode(', ', array_map(class_basename(...), $order)).', not in the order of RT-04';
            }
        }

        return $violations;
    }

    /**
     * The middleware of RT-04 among the router's sorted list, in their order,
     * each once.
     *
     * @param  array<array-key, mixed>  $resolved
     * @return list<string>
     */
    public static function order(array $resolved): array
    {
        $watched = [StartSession::class, Authenticate::class, EnsureSignInSession::class, PreventRequestForgery::class, ThrottleRequests::class, RequireCapability::class];
        $order = [];

        foreach ($resolved as $middleware) {
            $class = is_string($middleware) ? explode(':', $middleware, 2)[0] : null;

            if ($class !== null && in_array($class, $watched, true) && ! in_array($class, $order, true)) {
                $order[] = $class;
            }
        }

        return $order;
    }

    /**
     * The backquoted items of a cell.
     *
     * @return list<string>
     */
    private static function quoted(string $cell): array
    {
        preg_match_all('/`([^`]+)`/', $cell, $matches);

        return $matches[1];
    }
}
