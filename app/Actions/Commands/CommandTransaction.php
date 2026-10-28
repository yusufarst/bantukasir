<?php

namespace App\Actions\Commands;

use App\Database\SqlState;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * One transaction of a command or a status check on the application's
 * connection, at READ COMMITTED (TR-TX-06), within its budget (TECHNICAL_RULES
 * section 9; config/database.php): the budget is set for the session
 * immediately before BEGIN and the role's default restored after the
 * transaction, never inside it (TASK-004). An ordinary command has the budget
 * of 10 seconds; a bounded heavy command the runtime role's default of 30.
 *
 * A failure rolls the transaction back and is rethrown. A lost connection —
 * the budget ended the session, or the commit could not be confirmed — is
 * never read as a failure of the command (TR-RETRY-03): it is answered
 * SERVICE_BUSY, so that the client stays UNCERTAIN, and the connection is
 * dropped, so that the next statement opens a new session.
 */
final class CommandTransaction
{
    /**
     * @template T
     *
     * @param  Closure(Connection): T  $work
     * @return T
     */
    public static function run(Closure $work, bool $heavy = false): mixed
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('A command\'s transaction is the only one on its connection (TR-TX-01).');
        }

        try {
            if (! $heavy) {
                $connection->statement('select pg_catalog.set_config(\'transaction_timeout\', ?, false)', [config()->string('database.limits.transaction_budget')]);
            }

            $connection->beginTransaction();

            try {
                $result = $work($connection);
                $connection->commit();

                return $result;
            } catch (Throwable $failure) {
                self::rollBack($connection);

                throw $failure;
            }
        } catch (Throwable $failure) {
            if (SqlState::connectionLost($failure)) {
                $connection->disconnect();
                report($failure);

                throw CommandRefused::of(ErrorCode::ServiceBusy);
            }

            throw $failure;
        } finally {
            if (! $heavy && $connection->getRawPdo() !== null) {
                try {
                    $connection->statement('reset transaction_timeout');
                } catch (Throwable) {
                    $connection->disconnect();
                }
            }
        }
    }

    private static function rollBack(Connection $connection): void
    {
        try {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        } catch (Throwable) {
            $connection->disconnect();
        }
    }
}
