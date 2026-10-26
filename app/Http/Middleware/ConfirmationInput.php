<?php

namespace App\Http\Middleware;

use App\Domain\Access\PasswordProfile;
use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordConfirmationResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The allowlist of RA-03, the password confirmation of recent authentication
 * (ROUTE_CONTRACTS section 4; TECHNICAL_RULES TR-VAL-01; TASK-006 review
 * finding R-04): exactly the field `password`, a text of at most 128
 * characters, beside the framework's CSRF field. Any other request is the
 * one uniform failure of a confirmation, recorded in the security log by the
 * failed response (App\Http\Auth\Responses), and never reaches Fortify's
 * controller, which would fail on a password that is not a text.
 */
final class ConfirmationInput
{
    private const array FIELDS = ['password', '_token'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $password = $request->input('password');
        $valid = array_diff(array_keys($request->all()), self::FIELDS) === []
            && is_string($password)
            && mb_strlen($password, 'UTF-8') <= PasswordProfile::MAX;

        if (! $valid) {
            return app(FailedPasswordConfirmationResponse::class)->toResponse($request);
        }

        return $next($request);
    }
}
