<?php

/*
|--------------------------------------------------------------------------
| The bounds of the daily reconciliation, SC-01 reconciliation:run, and of
| the cost recovery, OP-06 costing:recover (ROUTE_CONTRACTS SC-01, OP-06;
| ENVIRONMENTS section 13; SECURITY_MODEL SEC-OP-06; TASK-036)
|--------------------------------------------------------------------------
|
| Fixed here, not per environment: a change follows change control.
|
| run_budget       The seconds a run may take, from its start to its last
|                  comparison: below the hour of the scheduler's no-overlap
|                  lock (app/Console/ProcessSchedule.php), so that the next
|                  day's run never meets a lock left by this one. A run that
|                  passes it ends FAILED, and the heartbeat is not pinged.
| batch            How many products, Sales or tokens one read of the run
|                  takes, each product in a snapshot of its own.
| recovery_factor  OP-06's movement cap as a multiple of the replay's
|                  (config costing.replay.movement_cap): at most ten.
| recovery_budget  OP-06's seconds for its whole transaction: at most ten
|                  minutes. The replay keeps the publication's reserve of
|                  config costing.replay.publication_reserve within it.
|
*/

return [

    'run_budget' => 2700,
    'batch' => 500,
    'recovery_factor' => 10,
    'recovery_budget' => 600,

];
