<?php

namespace App\Http\Middleware;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Http\Errors\ErrorAnswer;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recent authentication (SECURITY_MODEL SEC-SESS-05; ROUTE_CONTRACTS RT-04;
 * TECHNICAL_RULES section 4; TASK-007), the `password.confirm` of the routes
 * that section 6 of ROUTE_CONTRACTS marks: without a password confirmation in
 * the last 15 minutes (config/auth.php) the request runs nothing and is
 * answered 423, RECENT_AUTH_REQUIRED, on every channel — a JSON request, an
 * Inertia visit or a form — so that the page opens the recent-authentication
 * dialog of the shell (UXS-11, UXA-05) and sends the same request again after
 * it. The framework's middleware would send every request that does not ask
 * for JSON to a confirmation page, which BantuKasir does not have, and that
 * answered 500 (TASK-006 finding F-7). The 423 carries the error body of
 * RT-07 with RECENT_AUTH_REQUIRED and its message key (TASK-008; TASK-007
 * finding F-2, OPEN_ITEMS T-26).
 */
final class RequireRecentAuthentication extends RequirePassword
{
    public function __construct(ResponseFactory $responseFactory, UrlGenerator $urlGenerator)
    {
        parent::__construct($responseFactory, $urlGenerator, config()->integer('auth.password_timeout'));
    }

    /**
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     * @param  string|null  $redirectToRoute  not used: there is no confirmation page
     * @param  string|null  $passwordTimeoutSeconds
     */
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null)
    {
        if ($this->shouldConfirmPassword($request, $passwordTimeoutSeconds === null ? null : (int) $passwordTimeoutSeconds)) {
            return ErrorAnswer::refusal(CommandRefused::of(ErrorCode::RecentAuthRequired));
        }

        return $next($request);
    }
}
