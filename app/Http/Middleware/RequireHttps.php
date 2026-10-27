<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS only (SECURITY_MODEL SEC-HDR-04; ADR-003; TASK-007): where the
 * application's address is HTTPS — staging and production — a request that
 * reached it over plain HTTP, as the trusted proxy chain reports it
 * (TrustProxies), is sent to the same address over HTTPS with a permanent
 * redirect that keeps its method (308), and runs nothing. The local
 * environment, whose address is plain HTTP, is left as it is. Certificate
 * validation needs no path of the application: the origin presents a
 * Cloudflare Origin CA certificate, which no challenge renews (ADR-011
 * item 8).
 */
final class RequireHttps
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (self::required() && ! $request->isSecure()) {
            return new RedirectResponse('https://'.$request->getHost().$request->getRequestUri(), 308);
        }

        return $next($request);
    }

    /** Whether the application's address is HTTPS. */
    public static function required(): bool
    {
        return str_starts_with(strtolower(config()->string('app.url')), 'https://');
    }
}
