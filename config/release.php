<?php

/*
|--------------------------------------------------------------------------
| The release preflight (RELEASE_PROCESS REL-04, REL-23; TASK-014)
|--------------------------------------------------------------------------
|
| Fixed here, not per environment: a change follows change control. The
| environments, by their marker of ENV-04, in which release:preflight needs a
| successful backup less than one hour old.
|
*/

return [

    'backup_required_in' => ['production'],

];
