<?php

namespace App\Http\Controllers\Operations;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * RH-01 `GET /up`, the health route (ROUTE_CONTRACTS section 5; ADR-011 item
 * 11; ENVIRONMENTS section 13; TASK-007): it dispatches the framework's
 * DiagnosingHealth event, whose listener checks the database
 * (App\Domain\Operations\DatabaseHealth), and answers 200 when the
 * application has booted and its database answers, 503 otherwise. The body
 * is empty: no detail, version or name — unlike the framework's own health
 * page, which names the application, times the request and loads a
 * stylesheet from another origin. The route runs no session middleware and
 * sets no cookie; it is throttled with the bucket `presignin`
 * (bootstrap/app.php). A failure is reported to the log, never to the
 * answer: also a failure of the throttle, whose counter is in the database's
 * cache store, which the exception handler answers 503 with an empty body
 * (bootstrap/app.php; review finding R-29).
 */
final class HealthController
{
    public function __invoke(): Response
    {
        try {
            Event::dispatch(new DiagnosingHealth);
        } catch (Throwable $failure) {
            report($failure);

            return new Response('', 503);
        }

        return new Response('', 200);
    }
}
