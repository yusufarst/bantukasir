<?php

/*
|--------------------------------------------------------------------------
| Password hashing (ADR-004 item 7; SECURITY_MODEL SEC-AUTH-06; TASK-006)
|--------------------------------------------------------------------------
|
| Argon2id through the framework's hashing driver, never bcrypt. The
| parameters are PHP's defaults until TASK-048 benchmarks them on the target
| server; a stored hash whose parameters are lower than these is hashed again
| at the next sign-in (App\Domain\Access\Accounts::rehashIfNeeded). The hasher
| verifies that a stored hash is Argon2id before it checks it. The decoy hash
| that a refused sign-in checks, Accounts::DECOY_HASH, has these parameters;
| when they change, tests/Unit/Access/DecoyHashTest.php fails and gives its
| replacement.
|
*/

return [

    'driver' => 'argon2id',

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => true,
    ],

    // Fortify's sign-in pipeline is replaced (App\Http\Auth\AuthenticateUser), so the
    // session guard's own rehash never runs; the Access module rehashes.
    'rehash_on_login' => false,

];
