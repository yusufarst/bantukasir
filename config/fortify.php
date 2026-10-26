<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Laravel Fortify (ADR-004 item 1; ROUTE_CONTRACTS section 4; TASK-006)
|--------------------------------------------------------------------------
|
| Fortify is the authentication backend: sign-in and sign-out, password
| confirmation (recent authentication) and the change of one's own password.
| Of its optional features only updatePasswords is enabled. Registration, the
| public password reset by email, email verification, the profile update,
| two-factor authentication and passkeys are off.
|
| Fortify's own route file is not loaded (App\Providers\AccessServiceProvider
| calls Fortify::ignoreRoutes()): routes/web.php registers the five endpoints
| RA-01 to RA-05 with the middleware of ROUTE_CONTRACTS RT-04 — RA-01 to
| RA-04 on Fortify's own controllers, RA-05 on the application's, which runs
| Fortify's updater and, unlike Fortify's controller, leaves a one-time link
| alone (SEC-ACC-04) — and Fortify registers no view route, so no route of
| a disabled feature, no GET page of Fortify and no passkey route exists
| (tests/Feature/Access/RouteInventoryTest.php). The sign-in pipeline, the
| responses and the password update are the Access module's.
|
*/

return [

    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    // No GET page of Fortify: the pages are the application's (RP-001 to RP-004).
    'views' => false,

    // RN-01, the role's home (TASK-007).
    'home' => '/',

    'prefix' => '',

    'domain' => null,

    // The email is compared without regard to letter case by the database's
    // lower() (SCHEMA SCH-14), never folded by the application.
    'lowercase_usernames' => false,

    // The sign-in bucket is the throttle middleware of RA-01 (App\Http\Auth\Limits).
    'limiters' => [
        'login' => 'signin',
        'passkeys' => null,
    ],

    'redirects' => [
        'login' => '/',
        'logout' => '/login',
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => null,
    ],

    'features' => [
        Features::updatePasswords(),
    ],

];
