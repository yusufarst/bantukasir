<?php

namespace App\Domain\Operations;

use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * The public health check of SC-04 (ROUTE_CONTRACTS SC-04 and RH-01; ADR-011 item 11; ENVIRONMENTS section
 * 13; TASK-039 scope item 3): the environment's own `/up`, fetched from its public address, `APP_URL`,
 * whose name resolves to Cloudflare, so that the answer has crossed Cloudflare, the platform's proxy and
 * the application. In role web `settings:check` requires that address to be HTTPS (ENVIRONMENTS section
 * 11). The request goes through the one outbound client (OutboundHttp): no redirect is followed and no
 * status becomes an exception. The address is never printed or logged; a failure says only its status or
 * that no answer came, because an exception's message would hold the address.
 */
final class PublicHealth
{
    private const int TIMEOUT_SECONDS = 10;

    private const int CONNECT_SECONDS = 5;

    public function __construct(private readonly ?ClientInterface $client = null) {}

    /**
     * RH-01 at the configured public address, or null when no usable address is configured: an absolute
     * HTTP or HTTPS address with a host, and no user, password, query or fragment.
     */
    public static function address(): ?string
    {
        $url = config('app.url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ($parts['host'] ?? '') === ''
            || array_intersect_key($parts, array_flip(['user', 'pass', 'query', 'fragment'])) !== []) {
            return null;
        }

        return rtrim($url, '/').'/up';
    }

    /**
     * The status of the answer, or null when none came: a refused or failed connection, a timeout or a
     * certificate that does not verify.
     */
    public function status(string $address): ?int
    {
        try {
            return ($this->client ?? OutboundHttp::client())
                ->request('GET', $address, ['timeout' => self::TIMEOUT_SECONDS, 'connect_timeout' => self::CONNECT_SECONDS])
                ->getStatusCode();
        } catch (Throwable) {
            return null;
        }
    }
}
