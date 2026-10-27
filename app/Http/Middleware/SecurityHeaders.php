<?php

namespace App\Http\Middleware;

use App\Http\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers of every response of the application (SECURITY_MODEL
 * SEC-HDR-02, SEC-HDR-03, SEC-CH-06; ROUTE_CONTRACTS RT-06; TASK-007 scope
 * item 5), the outermost global middleware, so that refusals and error pages
 * carry them too:
 *
 * - the content security policy of SEC-HDR-03 with this request's nonce,
 *   made before anything renders, so that the generated script and style
 *   tags and the libraries' injected styles carry it (resources/views/
 *   app.blade.php, resources/js/lib/csp.ts);
 * - `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
 *   `Cross-Origin-Opener-Policy: same-origin`, a `Permissions-Policy` that
 *   disables camera, microphone, geolocation and payment, and
 *   `Referrer-Policy: strict-origin-when-cross-origin` — unless a route chose
 *   a stricter one, as the one-time link page does (`no-referrer`, NoReferrer);
 * - `Strict-Transport-Security` with a one-year max-age over HTTPS, only once
 *   the operator has verified HTTPS on the target and turned it on
 *   (config/security.php), with `includeSubDomains` only when every subdomain
 *   is verified, and never preload;
 * - `Cache-Control: no-store, private` on every answer that has not chosen a
 *   cache policy of its own (TR-HTTP-09): authenticated pages, JSON and
 *   refusals are never stored; only media will choose private caching
 *   (RD-07, SEC-CH-06).
 */
final class SecurityHeaders
{
    public const string PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=()';

    private const int HSTS_MAX_AGE = 31536000;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('Content-Security-Policy', ContentSecurityPolicy::header($nonce));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);

        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if ($request->isSecure() && config()->boolean('security.hsts.enabled')) {
            $headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE.(config()->boolean('security.hsts.subdomains') ? '; includeSubDomains' : ''));
        }

        if (! self::choseCachePolicy($response)) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Whether the answer chose its own caching: a public, max-age or no-store
     * directive. The framework's default, `no-cache, private`, is not a choice.
     */
    private static function choseCachePolicy(Response $response): bool
    {
        foreach (['public', 'max-age', 's-maxage', 'no-store', 'immutable'] as $directive) {
            if ($response->headers->hasCacheControlDirective($directive)) {
                return true;
            }
        }

        return false;
    }
}
