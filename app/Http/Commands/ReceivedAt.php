<?php

namespace App\Http\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The received time of TR-TIME-03: the moment the complete request, body
 * included, reached the web server of the application container, as that
 * server timestamped it. The web server passes it to PHP as the server
 * parameter REQUEST_RECEIVED_AT (nginx's `$msec`, set where it hands the
 * buffered request to PHP-FPM; docker/app/nginx.conf and the release image of
 * TASK-012), which a client cannot set: request headers reach PHP only as
 * HTTP_* parameters, and no time from a client or proxy header is ever read.
 * Without it — PHP's built-in server in a test — PHP's own start of the
 * request stands in.
 */
final class ReceivedAt
{
    public const string PARAMETER = 'REQUEST_RECEIVED_AT';

    public static function of(Request $request): CarbonImmutable
    {
        $received = $request->server(self::PARAMETER);

        if (is_string($received) && preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,6}))?$/', $received, $match) === 1) {
            return self::at($match[1], $match[2] ?? '0');
        }

        $start = $request->server('REQUEST_TIME_FLOAT');

        if (is_float($start) || is_int($start)) {
            [$seconds, $fraction] = explode('.', sprintf('%.6F', $start));

            return self::at($seconds, $fraction);
        }

        return CarbonImmutable::now('UTC');
    }

    private static function at(string $seconds, string $fraction): CarbonImmutable
    {
        $at = CarbonImmutable::createFromFormat('U.u', $seconds.'.'.str_pad($fraction, 6, '0'), 'UTC');

        return $at instanceof CarbonImmutable ? $at->setTimezone('UTC') : CarbonImmutable::now('UTC');
    }
}
