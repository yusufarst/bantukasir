<?php

/*
|--------------------------------------------------------------------------
| The bounds of the cost-correction replay (TECHNICAL_RULES section 9, TR-TIME-01;
| COSTING BR-COST-27, BR-COST-28; ADR-008 section 4.3; TASK-025)
|--------------------------------------------------------------------------
|
| Fixed here, not per environment: a change follows change control.
|
| movement_cap        The most logical movements a replay recomputes, from its
|                     start position to the end of the product's ledger: the
|                     reference of 10,000 per product, a starting point that
|                     PERF01 measures on the intended server (BR-COST-28).
| publication_reserve The seconds of the heavy transaction budget (config
|                     database.limits.heavy_transaction_budget, 30 seconds) kept
|                     for the publication, the clearances, the audit, the result
|                     and the commit: the recomputation stops and the correction
|                     is refused TIME_BUDGET once less than this remains, so that
|                     PostgreSQL's own end of the session at the budget is only
|                     the backstop.
|
*/

return [

    'replay' => [
        'movement_cap' => 10000,
        'publication_reserve' => 5,
    ],

];
