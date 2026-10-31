<?php

namespace App\Domain\Operations;

use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * The heartbeat ping client (ADR-011 item 11; TASK-013 scope item 4): an outbound
 * request to the check's secret address, which is never printed or logged
 * (ENVIRONMENTS section 11, AC-013-08). SC-03 pings the backup check after a
 * successful run; SC-01 (TASK-036) and SC-04 (TASK-039) reuse it.
 *
 * A failed ping says nothing but false: the monitor alerts on the missing ping,
 * and an exception's message would hold the address.
 */
final class Heartbeat
{
    public const string BACKUP = 'backup';

    public const string WEB = 'web';

    public const string RECONCILIATION = 'reconciliation';

    private const int TIMEOUT_SECONDS = 10;

    public function __construct(private readonly ?ClientInterface $client = null) {}

    public function ping(string $check): bool
    {
        $address = config('heartbeat.checks.'.$check);

        if (! is_string($address) || $address === '') {
            return false;
        }

        try {
            $status = ($this->client ?? OutboundHttp::client())
                ->request('GET', $address, ['timeout' => self::TIMEOUT_SECONDS])
                ->getStatusCode();
        } catch (Throwable) {
            return false;
        }

        return $status >= 200 && $status < 300;
    }
}
