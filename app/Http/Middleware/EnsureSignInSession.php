<?php

namespace App\Http\Middleware;

use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Http\Auth\SignedOutAnswer;
use App\Http\Auth\SignInSession;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The active-account check and the limits of the sign-in session, after the
 * authentication of every signed-in route (ROUTE_CONTRACTS RT-04; SECURITY_MODEL
 * SEC-AUTH-09, SEC-SESS-02; ADR-004 item 8; TASK-006). Only an ACTIVE account
 * keeps its session. A sign-in session ends 30 minutes after its last
 * user-initiated request and 12 hours after its sign-in at most; a request
 * marked `X-Background-Request: 1` — the stock attention refresh, the
 * automatic repeats of a resolution — never counts as user activity, and the
 * header can only make a request not count (RT-10, TR-HTTP-10). An ended
 * sign-in session is signed out and invalidated, recorded in the security log
 * as SESSION_REVOKED, and answered UNAUTHENTICATED with a full page load to
 * the sign-in page (NC-06); it never changes a cash session (SEC-SESS-03).
 */
final class EnsureSignInSession
{
    public const string BACKGROUND_HEADER = 'X-Background-Request';

    public function __construct(
        private readonly SignInSession $signInSession,
        private readonly StatefulGuard $guard,
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return SignedOutAnswer::for($request);
        }

        $session = $request->session();

        if (! $user->isActive() || $this->signInSession->hasEnded($session)) {
            $this->guard->logout();
            $session->invalidate();
            $session->regenerateToken();

            $this->securityLog->record(SecurityEvent::SessionRevoked, SecurityActor::user($user->id), SecurityOutcome::Succeeded, $request, $user->id);

            return SignedOutAnswer::for($request);
        }

        if ($request->header(self::BACKGROUND_HEADER) !== '1') {
            $this->signInSession->touch($session);
        }

        return $next($request);
    }
}
