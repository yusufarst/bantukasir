<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    |
    | The settings are listed in ENVIRONMENTS section 11; .env.example holds
    | their placeholders. The clock is UTC and the scheduler's time zone is
    | Asia/Jakarta in every environment (ARC-50).
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    // The environment's name — local, test, drill, staging or production —
    // which equals the marker of its database (ENVIRONMENTS ENV-04; TASK-004).
    // A test-database reset and the fixture builder read both and refuse on a
    // difference, and so do the operator procedures and the release preflight.
    'environment_name' => env('APP_ENVIRONMENT_NAME'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => 'UTC',

    'schedule_timezone' => 'Asia/Jakarta',

    // V1 is Indonesian only, with no switcher and no locale detection; the
    // locale and the fallback locale are both "id" (ADR-005 item 2).
    'locale' => 'id',

    'fallback_locale' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Encryption key
    |--------------------------------------------------------------------------
    |
    | One key per environment; the previous keys are listed during a rotation
    | (ENVIRONMENTS section 16).
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
