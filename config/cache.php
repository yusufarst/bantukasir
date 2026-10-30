<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The database store, used only for the rate limiter and scheduler locks
    | (ADR-002 item 3). Its tables belong to TASK-004; until then local runs
    | use the file store and tests the array store.
    |
    | In the process role backup, whose database role can only read and insert
    | backup_runs (SCHEMA SCH-42), the scheduler's lock is a file of that one
    | container, which holds the only scheduled command it guards (TASK-012).
    |
    */

    'default' => env('APP_PROCESS_ROLE') === 'backup' ? 'file' : env('CACHE_STORE', 'database'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/locks'),
        ],

    ],

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    'serializable_classes' => false,

];
