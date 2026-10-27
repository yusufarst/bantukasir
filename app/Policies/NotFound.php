<?php

namespace App\Policies;

use RuntimeException;

/**
 * NOT_FOUND (TECHNICAL_RULES section 4, TR-ERR-03): the object does not exist
 * or is outside the actor's scope, one answer for both — 404 with the error
 * body of ROUTE_CONTRACTS RT-07, or the page of UXS-08 (bootstrap/app.php).
 */
final class NotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Not found.');
    }
}
