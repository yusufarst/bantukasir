<?php

namespace App\Domain\Operations\Reconciliation;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * One consistent snapshot of the database for a read of the reconciliation (SCHEMA guard G-03;
 * BR-INV-45): a REPEATABLE READ, READ ONLY transaction of its own on the application's connection,
 * within the runtime role's own budgets, always rolled back, since it writes nothing. Every
 * statement of the read sees the same committed state, so the facts and the projections it compares
 * agree in time.
 */
final class Snapshot
{
    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public static function read(Closure $read): mixed
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('A snapshot of the reconciliation is a transaction of its own.');
        }

        $connection->beginTransaction();

        try {
            $connection->statement('set transaction isolation level repeatable read, read only');

            return $read();
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }
}
