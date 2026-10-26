<?php

use App\Http\Controllers\Access\AccountController;
use App\Http\Controllers\Access\OwnPasswordController;
use App\Http\Controllers\Access\PasswordLinkController;
use App\Http\Controllers\Access\SessionActivityController;
use App\Http\Controllers\Access\SignInController;
use App\Http\Controllers\Testing\SpecimenController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;

// Web routes. Every route of ROUTE_CONTRACTS is built by the Task that
// TRACEABILITY section 7 names; RN-01 and RH-01 by TASK-007, which also adds
// the capability gates, the remaining rate buckets and the security headers.

// TASK-006: sign-in, the one-time link, the own account and the sign-in
// session (ROUTE_CONTRACTS sections 2 and 4). RA-01 to RA-04 are Fortify's
// controllers and RA-05 the application's on Fortify's updater (ADR-004,
// config/fortify.php), with the middleware of RT-04.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SignInController::class, 'show'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:signin')->name('login.store');
});

// The link page sends no referrer, also when a signed-in visitor is sent on
// (review finding R-10): `no-referrer` wraps `guest`.
Route::middleware(['no-referrer', 'guest'])->group(function (): void {
    Route::get('/set-password', [PasswordLinkController::class, 'show'])->name('password.link');
    Route::post('/set-password', [PasswordLinkController::class, 'store'])->middleware('throttle:link')->name('password.link.store');
});

// RA-05 is the application's controller on Fortify's updater, which leaves a
// one-time link alone (OwnPasswordController; review finding R-02); RA-03
// takes only its allowlisted field (ConfirmationInput; review finding R-04).
Route::middleware(['auth', 'signin.session', 'cache.headers:no_store;private'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('signout.record')->name('logout');
    Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware(['throttle:confirm', 'confirm.input'])->name('password.confirm.store');
    Route::get('/user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])->name('password.confirmation');
    Route::put('/user/password', [OwnPasswordController::class, 'update'])->name('user-password.update');
    Route::post('/session/activity', SessionActivityController::class)->name('session.activity');

    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::get('/account/password', [AccountController::class, 'password'])->name('account.password');
});

// The design system's component specimen, for the browser tests (TASK-005):
// only in the local and testing environments, never in staging or production,
// and not a route of ROUTE_CONTRACTS.
if (SpecimenController::enabledIn((string) app()->environment())) {
    Route::get('/testing/specimen', SpecimenController::class)->name('testing.specimen');

    // The fixtures of the browser tests, from the test harness (TASK-006): only
    // where the harness exists, which the release build does not contain.
    if (is_file(base_path('tests/Harness/Browser/routes.php'))) {
        require base_path('tests/Harness/Browser/routes.php');
    }
}
