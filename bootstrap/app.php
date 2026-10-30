<?php

use App\Console\ProcessSchedule;
use App\Domain\Access\LinkInvalid;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Domain\Commands\CommandRefused;
use App\Http\Auth\LinkInvalidAnswer;
use App\Http\Auth\SignedOutAnswer;
use App\Http\Controllers\Operations\HealthController;
use App\Http\Errors\ErrorAnswer;
use App\Http\Middleware\AllowInput;
use App\Http\Middleware\ConfirmationInput;
use App\Http\Middleware\EnsureSignInSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoReferrer;
use App\Http\Middleware\RecordSignOut;
use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\RequireDeclaredCapability;
use App\Http\Middleware\RequireHttps;
use App\Http\Middleware\RequireRecentAuthentication;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\TrustProxies;
use App\Models\User;
use App\Policies\NotFound;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Middleware\EncryptHistory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // RH-01, the health route (ROUTE_CONTRACTS section 5; TASK-007): outside
        // the web group, so that it runs no session middleware and sets no
        // cookie, throttled with the bucket before sign-in.
        then: static function (): void {
            Route::get('/up', HealthController::class)->middleware('throttle:presignin')->name('health');
        },
    )
    // The scheduled commands of the process role (ENVIRONMENTS section 13;
    // TASK-012).
    ->withSchedule(static function (Schedule $schedule): void {
        ProcessSchedule::register($schedule, config('app.process_role'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // RH-01 says whether the application and its database answer, also in
        // maintenance mode: the health check and the heartbeat read it during
        // a release's window, and the page of that mode is for people
        // (ROUTE_CONTRACTS RH-01; TASK-007 finding F-11).
        $middleware->preventRequestsDuringMaintenance(except: ['up']);

        // The global middleware, in order (TASK-007): the security headers
        // outermost, so that every answer carries them, refusals included;
        // trusted hosts and proxies before anything reads the host or the
        // client address; HTTPS before the request runs (SECURITY_MODEL
        // section 12; ROUTE_CONTRACTS RT-04). The global trimming records the
        // paths it changed, so that the canonical-number rules refuse a padded
        // number (TASK-003 finding A-01). Passwords and one-time link tokens
        // are never altered (TR-VAL-05; TASK-006): the framework already
        // leaves the password fields alone.
        $middleware->use([
            SecurityHeaders::class,
            ValidatePathEncoding::class,
            InvokeDeferredCallbacks::class,
            TrustHosts::class,
            TrustProxies::class,
            RequireHttps::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);
        $middleware->trimStrings(except: ['token']);

        // A signed-in route that names no capability gate is refused at run
        // time too (SEC-AZ-01; review finding R-32).
        $middleware->web(append: [
            HandleInertiaRequests::class,
            RequireDeclaredCapability::class,
        ]);

        // CSRF in both of its layers on every state-changing request: the
        // browser's fetch-site header, then the token; never origin only, no
        // other site, no excluded route (SEC-HDR-01; TASK-007).
        $middleware->preventRequestForgery(except: [], originOnly: false, allowSameSite: false);

        // The middleware of the routes (ROUTE_CONTRACTS RT-04; TASK-006, TASK-007).
        $middleware->alias([
            'signin.session' => EnsureSignInSession::class,
            'no-referrer' => NoReferrer::class,
            'signout.record' => RecordSignOut::class,
            'confirm.input' => ConfirmationInput::class,
            'capability' => RequireCapability::class,
            'input.allow' => AllowInput::class,
            'password.confirm' => RequireRecentAuthentication::class,
            'history.encrypt' => EncryptHistory::class,
        ]);

        // The order of RT-04 for every authenticated route, whatever the order
        // they are named in: the session, authentication, the active account
        // and the sign-in session's limits, CSRF, the rate limit, the
        // capability gate, recent authentication, and only then the route's
        // bindings, so that no object is read for a refused request.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            EnsureSignInSession::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            RequireCapability::class,
            RequireRecentAuthentication::class,
            SubstituteBindings::class,
            Authorize::class,
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
        // with the note that the sign-in session ended (UXS-09; TASK-006),
        // which a first visit does not get (TASK-007).
        $exceptions->render(static fn (AuthenticationException $exception, Request $request) => SignedOutAnswer::for($request));

        // A one-time link's token is never flashed as old input, like the
        // passwords that the framework already leaves out (SEC-CH-03,
        // SEC-ACC-05; TASK-006 review finding R-01).
        $exceptions->dontFlash(['token']);

        // LINK_INVALID, uniform, with its security log entry (UXS-41; TASK-006).
        $exceptions->dontReport(LinkInvalid::class);
        $exceptions->render(static fn (LinkInvalid $failure, Request $request) => LinkInvalidAnswer::for($request, $failure));

        // NOT_FOUND from the object-scope pattern: one answer for a missing
        // object and one outside the actor's scope (TR-ERR-03; TASK-007).
        $exceptions->dontReport(NotFound::class);
        $exceptions->render(static fn (NotFound $exception, Request $request) => ErrorAnswer::for($request, 404));

        // A refusal of the command platform — a status check, a read or a
        // FormRequest of RT-07 — with its code, status and message key only
        // (TECHNICAL_RULES section 4; TASK-008). The command routes answer
        // their own refusals in the pipeline (CommandPipeline).
        $exceptions->dontReport(CommandRefused::class);
        $exceptions->render(static fn (CommandRefused $refusal) => ErrorAnswer::refusal($refusal));

        // Every other refusal of the framework and every server error: the
        // error body of RT-07 or the generic, translated page of its state,
        // never a trace or detail outside local development (SEC-CH-10,
        // TR-ERR-02; TASK-007 scope items 5 and 9). FORBIDDEN and CSRF_FAILED
        // are recorded in the security log, after any transaction has ended
        // (SEC-LOG-03): a refused policy and a refused actor guard (SEC-AZ-04)
        // arrive here as 403; the capability gate records and answers its own
        // refusal (RequireCapability). Of the exception's headers only the
        // retry time is kept (UXS-12).
        $exceptions->render(static function (Throwable $exception, Request $request): ?Response {
            if ($exception instanceof ValidationException || $exception instanceof HttpResponseException) {
                return null;
            }

            if (! $exception instanceof HttpExceptionInterface) {
                // RH-01 says nothing, whatever failed: its check, or its
                // throttle, whose counter is in the database's cache store
                // (ROUTE_CONTRACTS RH-01; review finding R-29).
                if ($request->routeIs('health')) {
                    return new Response('', 503);
                }

                return config()->boolean('app.debug') ? null : ErrorAnswer::for($request, 500);
            }

            $status = $exception->getStatusCode();
            $user = $request->user();
            $actor = $user instanceof User ? SecurityActor::user($user->id) : SecurityActor::anonymous();

            if ($status === 403) {
                app(SecurityLog::class)->record(SecurityEvent::AuthorizationRefused, $actor, SecurityOutcome::Refused, $request, $user instanceof User ? $user->id : null);
            }

            if ($status === 419 && $exception->getPrevious() instanceof TokenMismatchException) {
                app(SecurityLog::class)->record(SecurityEvent::CsrfFailed, $actor, SecurityOutcome::Refused, $request, $user instanceof User ? $user->id : null);
            }

            if ($status >= 500 && config()->boolean('app.debug')) {
                return null;
            }

            $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
            $headers = is_int($retryAfter) || is_string($retryAfter) ? ['Retry-After' => $retryAfter] : [];

            // A path or method that does not exist is not found, like an object.
            return ErrorAnswer::for($request, $status === 405 ? 404 : $status, $headers);
        });
    })->create();
