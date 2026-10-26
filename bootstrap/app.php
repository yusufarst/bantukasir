<?php

use App\Domain\Access\LinkInvalid;
use App\Http\Auth\LinkInvalidAnswer;
use App\Http\Auth\SignedOutAnswer;
use App\Http\Middleware\ConfirmationInput;
use App\Http\Middleware\EnsureSignInSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoReferrer;
use App\Http\Middleware\RecordSignOut;
use App\Http\Middleware\TrimStrings;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\TrimStrings as FrameworkTrimStrings;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The global trimming records the paths it changed, so that the
        // canonical-number rules refuse a padded number (TASK-003 finding A-01).
        // Passwords and one-time link tokens are never altered (TR-VAL-05;
        // TASK-006): the framework already leaves the password fields alone.
        $middleware->replace(FrameworkTrimStrings::class, TrimStrings::class);
        $middleware->trimStrings(except: ['token']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // The middleware of the authentication routes (ROUTE_CONTRACTS RT-04;
        // TASK-006).
        $middleware->alias([
            'signin.session' => EnsureSignInSession::class,
            'no-referrer' => NoReferrer::class,
            'signout.record' => RecordSignOut::class,
            'confirm.input' => ConfirmationInput::class,
        ]);

        // A visitor without a sign-in session is answered by the exception
        // renderer below; a signed-in visitor of a pre-authentication page is
        // sent to RN-01. From the one-time link page the address carries an
        // empty fragment, so that the browser never carries the link's token
        // over to the next page (SEC-ACC-05).
        $middleware->redirectUsersTo(static fn (Request $request): string => $request->routeIs('password.link', 'password.link.store') ? '/#' : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // UNAUTHENTICATED: JSON 401, or the sign-in page by a full page load
        // with the note that the sign-in session ended (UXS-09; TASK-006).
        $exceptions->render(static fn (AuthenticationException $exception, Request $request) => SignedOutAnswer::for($request));

        // A one-time link's token is never flashed as old input, like the
        // passwords that the framework already leaves out (SEC-CH-03,
        // SEC-ACC-05; TASK-006 review finding R-01).
        $exceptions->dontFlash(['token']);

        // LINK_INVALID, uniform, with its security log entry (UXS-41; TASK-006).
        $exceptions->dontReport(LinkInvalid::class);
        $exceptions->render(static fn (LinkInvalid $failure, Request $request) => LinkInvalidAnswer::for($request, $failure));
    })->create();
