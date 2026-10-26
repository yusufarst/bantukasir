<?php

namespace App\Http\Auth;

use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordConfirmationResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Contracts\PasswordUpdateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answers of the Fortify endpoints, as ROUTE_CONTRACTS section 4 sets
 * them (TASK-006). A sign-in and a sign-out end with a full page load
 * (SEC-AUTH-10): for an Inertia visit, Inertia's location answer, which the
 * client follows with window.location.
 */
final class Responses implements FailedPasswordConfirmationResponse, LoginResponse, LogoutResponse, PasswordConfirmedResponse, PasswordUpdateResponse
{
    /** The flash of the account page's success message (COPY_DECK section 6.5). */
    public const string STATUS = 'access.status';

    /** The value of that flash after a password change, a state code, never text. */
    public const string PASSWORD_CHANGED = 'password_changed';

    private function __construct(private readonly string $answer) {}

    /** RA-01: a full page load to RN-01, the role's home (TASK-007). */
    public static function signedIn(): self
    {
        return new self('signed_in');
    }

    /** RA-02: a full page load to RP-001. */
    public static function signedOut(): self
    {
        return new self('signed_out');
    }

    /** RA-05: back to RP-003 with the success message. */
    public static function passwordChanged(): self
    {
        return new self('password_changed');
    }

    /**
     * RA-03 succeeded: 15 minutes of recent authentication have started
     * (SEC-SESS-05), and the client sends its frozen request again (TASK-009).
     */
    public static function confirmed(): self
    {
        return new self('confirmed');
    }

    /** RA-03: the uniform answer of a failed confirmation (UXS-42), recorded in the security log. */
    public static function confirmationFailed(): self
    {
        return new self('confirmation_failed');
    }

    /**
     * @param  Request  $request
     *
     * @throws ValidationException
     */
    public function toResponse($request): Response
    {
        return match ($this->answer) {
            'signed_in' => self::fullPageLoad($request, '/'),
            'signed_out' => self::fullPageLoad($request, route('login')),
            'password_changed' => $request->wantsJson() && ! $request->header('X-Inertia')
                ? new JsonResponse(null, 200)
                : redirect()->route('account.show')->with(self::STATUS, self::PASSWORD_CHANGED),
            'confirmed' => $request->wantsJson() && ! $request->header('X-Inertia')
                ? new JsonResponse(null, 201)
                : redirect()->route('account.show'),
            default => $this->refuseConfirmation($request),
        };
    }

    private static function fullPageLoad(Request $request, string $url): Response
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(null, 204);
        }

        return $request->header('X-Inertia') ? Inertia::location($url) : redirect()->to($url);
    }

    /**
     * @throws ValidationException
     */
    private function refuseConfirmation(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            app(SecurityLog::class)->record(SecurityEvent::PasswordConfirmationFailed, SecurityActor::user($user->id), SecurityOutcome::Refused, $request, $user->id);
        }

        throw ValidationException::withMessages(['password' => __('ux.state.signin_failed.confirmation.body')]);
    }
}
