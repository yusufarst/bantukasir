<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Authentication (ADR-004; SECURITY_MODEL sections 2 to 4; TASK-006)
|--------------------------------------------------------------------------
|
| One guard, the session guard over the database sessions of ADR-002, and one
| provider, the users table (SCHEMA section 5.1). The values below are fixed
| here, not per environment: a change follows change control.
|
| passwords.users  The broker of the one-time links (ADR-004 item 4,
|                  SECURITY_MODEL SEC-ACC-04): its table holds one hashed
|                  token per account; a link expires 60 minutes after it is
|                  created; a new link for the same account waits 60 seconds
|                  (the broker's throttle, SECURITY_MODEL section 11).
|                  App\Domain\Access\OneTimeLinks is the only code that uses it.
| password_timeout Recent authentication: a password confirmation counts for
|                  900 seconds, 15 minutes (SEC-SESS-05).
|
*/

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 900,

];
