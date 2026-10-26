<?php

namespace App\Http\Controllers\Access;

use Illuminate\Http\Response;

/**
 * RA-07, a user-activity request (ROUTE_CONTRACTS section 4; INTERACTION_CONTRACTS
 * IC-14): sent at most once a minute while the user types, and by "Tetap
 * masuk" (UXI-034, TASK-007). It counts as user activity, which
 * App\Http\Middleware\EnsureSignInSession records; it answers 204 and changes
 * nothing else.
 */
final class SessionActivityController
{
    public function __invoke(): Response
    {
        return new Response(null, 204);
    }
}
