<?php

/*
|--------------------------------------------------------------------------
| The ping addresses of the heartbeat monitor (ADR-011 item 11)
|--------------------------------------------------------------------------
|
| Secret: a ping address lets anyone fake a ping, so none is ever printed or
| logged (ENVIRONMENTS sections 11 and 16). Each process role holds only its
| own: backup the backup check; web the web and reconciliation checks.
| App\Domain\Operations\Heartbeat is the client that SC-01, SC-03 and SC-04 use.
|
*/

return [

    'checks' => [
        'web' => env('HEARTBEAT_WEB_URL'),
        'reconciliation' => env('HEARTBEAT_RECONCILIATION_URL'),
        'backup' => env('HEARTBEAT_BACKUP_URL'),
    ],

];
