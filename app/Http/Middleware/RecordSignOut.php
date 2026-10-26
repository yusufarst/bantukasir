<?php

namespace App\Http\Middleware;

use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a sign-out (RA-02), the revocation of that sign-in session (SEC-SESS-04),
 * as SESSION_REVOKED in the security log, after Fortify has signed out and
 * invalidated the session (SEC-LOG-03).
 */
final class RecordSignOut
{
    public function __construct(private readonly SecurityLog $securityLog) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $response = $next($request);

        if ($user instanceof User && $request->user() === null) {
            $this->securityLog->record(SecurityEvent::SessionRevoked, SecurityActor::user($user->id), SecurityOutcome::Succeeded, $request, $user->id);
        }

        return $response;
    }
}
