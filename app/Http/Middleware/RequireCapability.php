<?php

namespace App\Http\Middleware;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Http\Auth\SignedOutAnswer;
use App\Http\Errors\ErrorAnswer;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The capability gate of every authenticated route (ROUTE_CONTRACTS RT-04,
 * RT-05; SECURITY_MODEL SEC-AZ-01, SEC-AZ-02; TASK-007), registered as
 * `capability:CAP-nn[,CAP-nn…]`: the route opens when the signed-in account
 * holds one of the named capabilities so that it opens a gate — fully or on
 * its own objects, whose scope the Query or the FormRequest applies
 * (SEC-AZ-03). Deny by default: a gate that names no capability, or a name
 * that is not one of CAP-01 to CAP-34, opens nothing. A refused request is
 * answered FORBIDDEN — 403 with the error body of RT-07, or the page of
 * UXS-07 — and recorded in the security log as AUTHORIZATION_REFUSED
 * (SEC-LOG-03). It runs after the sign-in session's checks, CSRF and the rate
 * limit, and before recent authentication and the route's bindings, so that
 * no object is read for a request that the gate refuses (RT-04).
 */
final class RequireCapability
{
    public function __construct(private readonly SecurityLog $securityLog) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$gate): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return SignedOutAnswer::for($request);
        }

        if ($gate === []) {
            throw new InvalidArgumentException('A capability gate names at least one capability (RT-05).');
        }

        $capabilities = array_map(Capability::named(...), $gate);

        foreach ($capabilities as $capability) {
            if (Capabilities::allows($user, $capability)) {
                return $next($request);
            }
        }

        $this->securityLog->record(SecurityEvent::AuthorizationRefused, SecurityActor::user($user->id), SecurityOutcome::Refused, $request, $user->id);

        return ErrorAnswer::for($request, 403);
    }
}
