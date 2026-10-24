<?php

namespace App\Database;

use InvalidArgumentException;

/**
 * A PostgreSQL identifier — a role, table, column or sequence name — written
 * into SQL, where PostgreSQL takes no binding for it (TASK-004). Only the
 * names that this project's setup script and schema use are accepted:
 * lowercase letters, digits and underscores, starting with a letter or an
 * underscore, at most 63 bytes (SCHEMA section 1, setup.sql). The name is
 * then double-quoted, so it is never read as a keyword.
 *
 * The raw-SQL rule (TR-VAL-07, tests/PHPStan/RawSqlRule.php) accepts SQL
 * built from constant strings and the result of quote(), and nothing else.
 */
final class Identifier
{
    private const string PATTERN = '/^[a-z_][a-z0-9_]{0,62}$/D';

    public static function quote(string $name): string
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Not an identifier of this schema: %s', json_encode($name)));
        }

        return '"'.$name.'"';
    }
}
