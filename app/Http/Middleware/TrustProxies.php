<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;

/**
 * Only the proxy hops in front of the application are trusted for forwarded
 * headers (SECURITY_MODEL SEC-HDR-05, section 11; ENVIRONMENTS section 10;
 * ADR-003 item 10, ADR-011 item 10; TASK-007), so that the address that the
 * rate limits and the security log use is the visitor's, and a forged
 * forwarding header changes nothing. The hops are the addresses and ranges of
 * config/security.php (APP_TRUSTED_PROXIES): on the server, Coolify's proxy
 * and Cloudflare's published ranges, which the operator sets at gate TARGET;
 * none by default, and never every address. Of the forwarded headers, only
 * X-Forwarded-For, -Proto and -Port are read: the host is never taken from a
 * header (TrustHosts), and CF-Connecting-IP is never read at all.
 */
final class TrustProxies extends FrameworkTrustProxies
{
    /**
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;

    protected function setTrustedProxyIpAddresses(Request $request)
    {
        $request->setTrustedProxies(self::proxyAddresses(), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    }

    /**
     * The configured hops, without a wildcard: an address or a range in CIDR
     * notation each. A range of prefix length 0, such as `0.0.0.0/0` or
     * `::/0`, is every address and is refused like the wildcard (SEC-HDR-05,
     * THR-16; review finding R-34).
     *
     * @return list<string>
     */
    public static function proxyAddresses(): array
    {
        $proxies = config('security.trusted_proxies');

        if (! is_array($proxies)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $proxy): string => is_string($proxy) ? trim($proxy) : '', $proxies),
            static fn (string $proxy): bool => $proxy !== '' && ! str_contains($proxy, '*') && $proxy !== 'REMOTE_ADDR' && preg_match('#/\s*0+$#', $proxy) !== 1,
        ));
    }
}
