<?php

namespace App\Http\Auth;

use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * The last stage of the sign-in pipeline, after Fortify has regenerated the
 * session (SEC-AUTH-10): it records the two times the limits of the sign-in
 * session are counted from (SEC-SESS-02), and SIGN_IN_SUCCEEDED in the
 * security log.
 */
final class StartSignInSession
{
    public function __construct(
        private readonly SignInSession $signInSession,
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $this->signInSession->start($request->session());

        $user = $request->user();

        if ($user instanceof User) {
            $this->securityLog->record(SecurityEvent::SignInSucceeded, SecurityActor::user($user->id), SecurityOutcome::Succeeded, $request, $user->id);
        }

        return $next($request);
    }
}
