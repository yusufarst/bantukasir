<?php

namespace App\Domain\Operations;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;

/**
 * The one HTTP client of the application's outbound requests: the backup store
 * and the heartbeat monitor (TASK-013 scope item 9; D-112, D-113).
 *
 * It uses Guzzle's StreamHandler, PHP's own HTTP stream wrapper over OpenSSL, set
 * explicitly, because Guzzle would otherwise choose its cURL handlers: no request
 * of this client reaches libcurl, so none of the image's curl findings is on its
 * path (TASK-013 evidence, the reassessment; OPEN_ITEMS T-38 (a)). It follows no
 * redirect, verifies the peer, and never turns a status into an exception, whose
 * message would hold the address.
 */
final class OutboundHttp
{
    public static function client(): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new StreamHandler),
            'http_errors' => false,
            'allow_redirects' => false,
            'decode_content' => false,
            'verify' => true,
            'expect' => false,
        ]);
    }
}
