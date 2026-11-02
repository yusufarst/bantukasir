<?php

namespace App\Queries\Inventory;

use LogicException;

/**
 * The typed reading of a row of the stock reads (SCHEMA sections 5.6 to 5.8): a column that
 * the query selects holds the type of its definition, and anything else is a defect of the
 * query, never data to show.
 */
final class Column
{
    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function int(array $row, string $name): int
    {
        $value = $row[$name] ?? null;

        return is_int($value) ? $value : throw new LogicException(sprintf('The column %s holds a whole number (SCHEMA section 5).', $name));
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function nullableInt(array $row, string $name): ?int
    {
        $value = $row[$name] ?? null;

        return $value === null ? null : self::int($row, $name);
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function text(array $row, string $name): string
    {
        $value = $row[$name] ?? null;

        return is_string($value) ? $value : throw new LogicException(sprintf('The column %s holds a text (SCHEMA section 5).', $name));
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function nullableText(array $row, string $name): ?string
    {
        $value = $row[$name] ?? null;

        return $value === null ? null : self::text($row, $name);
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function bool(array $row, string $name): bool
    {
        $value = $row[$name] ?? null;

        return is_bool($value) ? $value : throw new LogicException(sprintf('The column %s holds a boolean (SCHEMA section 5).', $name));
    }
}
