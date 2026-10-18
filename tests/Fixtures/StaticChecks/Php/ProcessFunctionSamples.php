<?php

namespace Tests\Fixtures\StaticChecks;

// Negative samples of the process-function rule (THR-10). Never executed.

function processFunctions(string $command): void
{
    exec($command);
    shell_exec($command);
    system($command);
    passthru($command);
    popen($command, 'r');
    proc_open($command, [], $pipes);
    `ls`;
    $function = 'exec';
    $function($command);
    call_user_func('system', $command);
}

/**
 * @param  list<string>  $command
 */
function processWithArgumentList(array $command): void
{
    proc_open($command, [], $pipes);
}
