<?php

namespace App\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

/**
 * The PostgreSQL error classes that the command pipeline answers
 * (TECHNICAL_RULES sections 6 and 9; PostgreSQL 18, Appendix A "Error Codes";
 * TASK-008): a unique violation on a command's identity, a deadlock or
 * serialization failure that is retried, a lock wait beyond the lock timeout,
 * and a lost connection — the transaction budget's end (25P04, which
 * pdo_pgsql reports with the session's end, TASK-004) or a lost commit.
 */
final class SqlState
{
    public const string UNIQUE_VIOLATION = '23505';

    public const string SERIALIZATION_FAILURE = '40001';

    public const string DEADLOCK_DETECTED = '40P01';

    public const string LOCK_NOT_AVAILABLE = '55P03';

    public const string QUERY_CANCELED = '57014';

    /**
     * The SQLSTATE of a database failure, or null for any other failure.
     */
    public static function of(Throwable $failure): ?string
    {
        if (! $failure instanceof QueryException && ! $failure instanceof PDOException) {
            return null;
        }

        $code = $failure->getCode();

        return is_string($code) && preg_match('/^[0-9A-Z]{5}$/', $code) === 1 ? $code : null;
    }

    /**
     * Whether a retry of the whole transaction may succeed (TR-RETRY-01).
     */
    public static function retryable(Throwable $failure): bool
    {
        return in_array(self::of($failure), [self::DEADLOCK_DETECTED, self::SERIALIZATION_FAILURE], true);
    }

    /**
     * Whether a unique violation was raised by the named constraint or index.
     *
     * @param  list<string>  $constraints
     */
    public static function uniqueOn(Throwable $failure, array $constraints): bool
    {
        if (self::of($failure) !== self::UNIQUE_VIOLATION) {
            return false;
        }

        foreach ($constraints as $constraint) {
            if (str_contains($failure->getMessage(), '"'.$constraint.'"')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the connection was lost: the session ended, also by its
     * transaction budget, or the server could not be reached.
     */
    public static function connectionLost(Throwable $failure): bool
    {
        return ($failure instanceof QueryException || $failure instanceof PDOException)
            && (Str::contains($failure->getMessage(), ['terminating connection', 'server closed the connection', 'no connection to the server', 'connection to server', 'SSL connection has been closed'])
                || self::of($failure) === '08006' || self::of($failure) === '57P01');
    }
}
