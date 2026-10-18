<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filesystems
    |--------------------------------------------------------------------------
    |
    | Private local storage outside the web root. Media storage is TASK-010
    | and the backup store TASK-013.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [],

];
