<?php

namespace Tests\Harness\Commands;

use App\Database\CommandPoints;
use Illuminate\Database\QueryException;

/**
 * Injects a deadlock (SQLSTATE 40P01) after write step 1 of the first
 * `deadlocks` attempts of a command (AC-008-05; TR-RETRY-01), and counts the
 * attempts by the identities they inserted.
 */
final class DeadlockPoints implements CommandPoints
{
    public int $attempts = 0;

    private int $injected = 0;

    public function __construct(private readonly int $deadlocks) {}

    public function afterIdentity(): void
    {
        $this->attempts++;
    }

    public function afterLock(string $step): void {}

    public function afterWrite(int $step): void
    {
        if ($step === 1 && $this->injected < $this->deadlocks) {
            $this->injected++;
            throw new QueryException('pgsql', 'select 1', [], new InjectedDeadlock);
        }
    }

    public function beforeCommit(): void {}

    public function afterOwnWrite(string $write): void {}
}
