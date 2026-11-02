<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filesystems
    |--------------------------------------------------------------------------
    |
    | Private local storage outside the web root. The disk `media` holds the
    | stored files of the media pipeline (TASK-010; ARCHITECTURE ARC-40 to
    | ARC-42; SECURITY_MODEL SEC-UP-01, SEC-UP-06): never served by the web
    | server, delivered only through RD-07, each file under its random key
    | (App\Domain\Media\MediaStore). The backup store is no disk of this file:
    | config/backup.php and App\Domain\Operations\Backup\ObjectStore (TASK-013).
    | The disk `csv_imports` is the private temporary area of the CSV import
    | (TASK-019; ARCHITECTURE ARC-43): beside the file volume and never in it, so
    | never backed up and never served (App\Domain\Catalog\Import\CsvImportFiles).
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

        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/private/media'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        'csv_imports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/csv-imports'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [],

];
