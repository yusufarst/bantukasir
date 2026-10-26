<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Referrer-Policy: no-referrer` on the one-time link page and its submission
 * (ROUTE_CONTRACTS RP-002; SECURITY_MODEL SEC-ACC-05, SEC-HDR-02; TASK-006):
 * the page sends no referrer to any address, whatever the policy of every
 * other response (TASK-007) says.
 */
final class NoReferrer
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
