<?php

namespace App\Http\Auth;

use App\Domain\Access\Accounts;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The credential check of RA-01, a stage of Fortify's sign-in pipeline in
 * place of AttemptToAuthenticate (ROUTE_CONTRACTS RA-01; INTERACTION_CONTRACTS
 * IC-09; SECURITY_MODEL SEC-AUTH-01, SEC-AUTH-07 to SEC-AUTH-09). The email is
 * matched without regard to letter case by the database (SCH-14); only an
 * ACTIVE account with a usable password that matches signs in, and never with
 * "remember me". Every failure — an unknown email, a deactivated or anonymized
 * account, an account without a password, a wrong password — gives the one
 * uniform answer (UXS-42, TR-ERR-04), checked in the same time, and is
 * recorded in the security log as SIGN_IN_FAILED, with the account it
 * concerns when the email matched one; the typed email is never recorded.
 */
final class AuthenticateUser
{
    public function __construct(
        private readonly Accounts $accounts,
        private readonly StatefulGuard $guard,
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     *
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $candidate = is_string($email) ? $this->accounts->findByEmail($email) : null;
        $user = $this->accounts->verifyCredentials($candidate, is_string($password) ? $password : '');

        if ($user === null || ! is_string($password)) {
            $this->securityLog->record(SecurityEvent::SignInFailed, SecurityActor::anonymous(), SecurityOutcome::Refused, $request, $candidate?->id);

            throw self::failure();
        }

        $this->accounts->rehashIfNeeded($user, $password);
        $this->guard->login($user, false);

        return $next($request);
    }

    /** The one uniform answer of a failed sign-in (UXS-42). */
    public static function failure(): ValidationException
    {
        return ValidationException::withMessages(['form' => __('ux.state.signin_failed.sign_in.body')])->redirectTo(route('login'));
    }
}
