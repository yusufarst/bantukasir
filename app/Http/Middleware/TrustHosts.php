<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trusted hosts (SECURITY_MODEL SEC-HDR-05; ENVIRONMENTS section 10;
 * TASK-007): a request for any host name other than the environment's own is
 * refused — 400, with no body and no page, before routing, a session or a
 * render — so that no address, link or redirect is ever made from a host the
 * request chose. The names are those of config/security.php: the host of the
 * application's address unless the environment lists its names
 * (APP_TRUSTED_HOSTS). Unlike the framework's middleware, which it replaces,
 * it holds in every environment and in the tests too.
 */
final class TrustHosts
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->server->get('HTTP_HOST', $request->server->get('SERVER_NAME', ''));
        $host = preg_replace('/:\d+$/D', '', strtolower(is_string($header) ? $header : '')) ?? '';

        if (! in_array($host, self::hosts(), true)) {
            return new HttpResponse('', 400);
        }

        return $next($request);
    }

    /**
     * The trusted host names, in lower case.
     *
     * @return list<string>
     */
    public static function hosts(): array
    {
        $hosts = config('security.trusted_hosts');

        return is_array($hosts) ? array_values(array_map(static fn (mixed $host): string => strtolower(is_string($host) ? $host : ''), $hosts)) : [];
    }
}
