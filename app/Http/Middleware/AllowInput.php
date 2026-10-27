<?php

namespace App\Http\Middleware;

use App\Http\Errors\ErrorAnswer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The input allowlist of a route that has no FormRequest of its own
 * (TECHNICAL_RULES TR-VAL-01, TR-VAL-03; SECURITY_MODEL SEC-AZ-05, THR-03;
 * TASK-007), registered as `input.allow[:field,…]`: a request that sends any
 * other field — a role, a user, an actor or any value the server never takes
 * from the browser — is refused VALIDATION_FAILED (422, the error body of
 * ROUTE_CONTRACTS RT-07) and runs nothing. The framework's CSRF field is
 * always allowed. Without fields, the route takes none: RA-02 and RA-07.
 */
final class AllowInput
{
    private const array FRAMEWORK_FIELDS = ['_token'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$fields): Response
    {
        if (array_diff(array_map(strval(...), array_keys($request->all())), [...$fields, ...self::FRAMEWORK_FIELDS]) !== []) {
            return ErrorAnswer::body(422, 'VALIDATION_FAILED', 'ux.state.validation');
        }

        return $next($request);
    }
}
