<?php

namespace Tests\Harness\Commands;

use PDOException;

/**
 * A deadlock as PDO reports it, with SQLSTATE 40P01 as its code, injected by
 * the test (DeadlockPoints).
 */
final class InjectedDeadlock extends PDOException
{
    public function __construct()
    {
        parent::__construct('SQLSTATE[40P01]: Deadlock detected (injected by the test)');
        $this->code = '40P01';
        $this->errorInfo = ['40P01', 7, 'deadlock detected'];
    }
}
