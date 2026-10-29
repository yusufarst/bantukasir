<?php

use App\Domain\Commands\Operations;
use App\Http\Controllers\Access\AccountController;
use App\Http\Controllers\Access\HomeRedirectController;
use App\Http\Controllers\Access\OwnPasswordController;
use App\Http\Controllers\Access\PasswordLinkController;
use App\Http\Controllers\Access\SessionActivityController;
use App\Http\Controllers\Access\ShellDataController;
use App\Http\Controllers\Access\SignInController;
use App\Http\Controllers\Commands\RequestsController;
use App\Http\Controllers\Commands\RequestsDataController;
use App\Http\Controllers\Commands\ResolveIntentController;
use App\Http\Controllers\Testing\SpecimenController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;

// Web routes. Every route of ROUTE_CONTRACTS is built by the Task that
// TRACEABILITY section 7 names, with its gate (RT-05) and the middleware of
// RT-04; tests/Feature/Access/RouteInventoryTest.php lists them all, and every
// Task adds its own there. RH-01, the health route, is registered outside the
// web group (bootstrap/app.php).

// The pre-authentication routes (RP-001, RA-01, RP-002, RA-06; TASK-006): no
// gate, guest only, the bucket of every request before sign-in (TASK-007) and
// their own buckets. RA-01 to RA-04 are Fortify's controllers and RA-05 the
// application's on Fortify's updater (ADR-004, config/fortify.php).
Route::middleware(['guest', 'throttle:presignin'])->group(function (): void {
    Route::get('/login', [SignInController::class, 'show'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:signin')->name('login.store');
});

// The link page sends no referrer, also when a signed-in visitor is sent on
// (review finding R-10): `no-referrer` wraps `guest`.
Route::middleware(['no-referrer', 'guest', 'throttle:presignin'])->group(function (): void {
    Route::get('/set-password', [PasswordLinkController::class, 'show'])->name('password.link');
    Route::post('/set-password', [PasswordLinkController::class, 'store'])->middleware('throttle:link')->name('password.link.store');
});

// Every signed-in route: authentication, the active account and the limits of
// the sign-in session, the bucket of every signed-in request, its capability
// gate, answers never stored, and its history entries encrypted (RT-04 to
// RT-06; SECURITY_MODEL SEC-AZ-01, SEC-CH-04; TASK-007). CSRF is the web
// group's, ordered after the sign-in session (bootstrap/app.php). RA-05 is the
// application's controller on Fortify's updater, which leaves a one-time link
// alone (OwnPasswordController; review finding R-02); RA-03 takes only its
// allowlisted field (ConfirmationInput; review finding R-04); RA-02 and RA-07
// take no field at all (input.allow).
Route::middleware(['auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt'])->group(function (): void {
    // CAP-01: sign in and out, view one's own account, change one's own
    // password — every role (SECURITY_MODEL section 6). RN-01 and the sign-in
    // session's own endpoints are "Signed in" in ROUTE_CONTRACTS, which every
    // role is through CAP-01.
    Route::middleware('capability:CAP-01')->group(function (): void {
        Route::get('/', HomeRedirectController::class)->name('home.redirect');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware(['input.allow', 'signout.record'])->name('logout');
        Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware(['throttle:confirm', 'confirm.input'])->name('password.confirm.store');
        Route::get('/user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])->name('password.confirmation');
        Route::put('/user/password', [OwnPasswordController::class, 'update'])->name('user-password.update');
        Route::post('/session/activity', SessionActivityController::class)->middleware('input.allow')->name('session.activity');

        Route::get('/account', [AccountController::class, 'show'])->name('account.show');
        Route::get('/account/password', [AccountController::class, 'password'])->name('account.password');

        // RD-03, the shell's indicators (TASK-009): a background read that
        // takes no parameter (RT-08, RT-10).
        Route::get('/data/shell', ShellDataController::class)->middleware('input.allow')->name('data.shell');
    });

    // CAP-34: the status check of an uncertain intent, RC-50, in the bucket
    // of every command (RT-09), and the unresolved request receipts, RD-04
    // (TASK-008); the page of unresolved requests, RP-006, built in the
    // browser from the stored references and RD-04 (TASK-009). Who may check
    // which intent, and scope=all for the Owner only, are checked by the
    // Action and the FormRequest.
    Route::middleware('capability:CAP-34')->group(function (): void {
        Route::post('/commands/resolve', ResolveIntentController::class)->middleware('throttle:command')->name('commands.resolve');
        Route::get('/data/command-requests', RequestsDataController::class)->name('data.requests');
        Route::get('/requests', [RequestsController::class, 'index'])->middleware('input.allow')->name('requests.index');
    });
});

// The probe operation of the command platform (TASK-008 scope item 9): only
// in the testing environment and, for the browser suite, the local one
// (TASK-009), and only where the test harness exists, which the release
// build does not contain.
if (app()->environment(Operations::PROBE_ENVIRONMENTS) && is_file(base_path('tests/Harness/Commands/routes.php'))) {
    require base_path('tests/Harness/Commands/routes.php');
}

// The design system's component specimen and the shell's, for the browser
// tests (TASK-005, TASK-007): only in the local and testing environments,
// never in staging or production, and not routes of ROUTE_CONTRACTS.
if (SpecimenController::enabledIn((string) app()->environment())) {
    Route::get('/testing/specimen', SpecimenController::class)->name('testing.specimen');
    Route::get('/testing/shell-specimen', [SpecimenController::class, 'shell'])->name('testing.shell_specimen');
    Route::get('/testing/command-specimen', [SpecimenController::class, 'command'])->middleware(['auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt', 'capability:CAP-01'])->name('testing.command_specimen');

    // The fixtures of the browser tests, from the test harness (TASK-006): only
    // where the harness exists, which the release build does not contain.
    if (is_file(base_path('tests/Harness/Browser/routes.php'))) {
        require base_path('tests/Harness/Browser/routes.php');
    }
}
