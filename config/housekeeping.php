<?php

/*
|--------------------------------------------------------------------------
| The bounds of the hourly housekeeping, SC-02 housekeeping:run
| (ROUTE_CONTRACTS SC-02; ENVIRONMENTS sections 13 and 14; TASK-038)
|--------------------------------------------------------------------------
|
| Fixed here, not per environment: a change follows change control. The
| command takes no option and no argument; these are its only inputs
| (TR-RUN-04, THR-10). The ages that the decisions set are not settings:
| 60 minutes for a link (config auth.passwords.users.expire), 30 days for a
| resolved receipt, 365 days for a security log entry and 24 hours for a
| temporary CSV file are constants of their owning services.
|
| batch_size          How many rows one transaction removes, or how many
|                     storage keys one read of stored_files checks: 1 to
|                     1,000.
| max_batches         How many batches one run takes of each cleanup, at
|                     most: 1 to 100. A run so removes at most
|                     batch_size x max_batches of each kind, and the next
|                     hour's run takes the rest.
| file_grace_seconds  How long an unreferenced stored file is kept after it
|                     was written: at least one hour, well above the
|                     longest request (TR-TX-05), and at most one day.
|
*/

return [

    'batch_size' => 500,
    'max_batches' => 20,
    'file_grace_seconds' => 3600,

];
