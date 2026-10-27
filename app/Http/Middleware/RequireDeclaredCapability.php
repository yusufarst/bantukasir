<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Deny by default at run time (SECURITY_MODEL SEC-AZ-01: "a route without a
 * declared capability is refused"; AGENTS rule 8; TASK-007 review finding
 * R-32): a route of the web group that requires authentication but names no
 * capability gate is refused FORBIDDEN, whoever asks, before anything of the
 * route runs; the exception handler answers with the error body of RT-07 or
 * the page of UXS-07 and records AUTHORIZATION_REFUSED (SEC-LOG-03). The route
 * inventory proves at test time that no such route exists
 * (tests/Harness/Http/RouteInventory.php); this refusal holds if one is ever
 * added.
 */
final class RequireDeclaredCapability
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $declared = array_map(strval(...), $route->gatherMiddleware());
            $signedIn = array_filter($declared, static fn (string $middleware): bool => $middleware === 'auth' || str_starts_with($middleware, 'auth:'));
            $gated = array_filter($declared, static fn (string $middleware): bool => str_starts_with($middleware, 'capability:'));

            if ($signedIn !== [] && $gated === []) {
                throw new AccessDeniedHttpException;
            }
        }

        return $next($request);
    }
}
