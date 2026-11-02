<?php

namespace App\Queries\Inventory;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;

/**
 * The opaque cursors of the keyset lists of the stock pages (INTERACTION_CONTRACTS section
 * 3.2; TECHNICAL_RULES TR-PAGE-01 to TR-PAGE-03): the sort key of a page's boundary row, as
 * base64url of its JSON. A cursor is read again under the viewer's scope and never grants
 * access; one that is not the shape of its list is VALIDATION_FAILED, as RD-04's is.
 */
final class Keyset
{
    /** An instant in UTC as the stock reads write one. */
    private const string INSTANT = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z$/D';

    /**
     * @param  list<int|string>  $position
     */
    public static function encode(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * The position of a cursor, each part of the kind its list sorts by: `int`, a key of at
     * least 1; `text`, a name of at most 200 characters; `instant`, a time in UTC.
     *
     * @param  list<'int'|'text'|'instant'>  $shape
     * @return list<int|string>
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    public static function decode(string $cursor, array $shape): array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $position = is_string($json) ? json_decode($json, true, 2) : null;

        if (! is_array($position) || ! array_is_list($position) || count($position) !== count($shape)) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        $parts = [];

        foreach ($shape as $index => $kind) {
            $part = $position[$index] ?? null;
            $valid = match ($kind) {
                'int' => is_int($part) && $part >= 1,
                'text' => is_string($part) && mb_strlen($part) <= 200,
                'instant' => is_string($part) && preg_match(self::INSTANT, $part) === 1,
            };

            if (! $valid || (! is_int($part) && ! is_string($part))) {
                throw CommandRefused::of(ErrorCode::ValidationFailed);
            }

            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * The whole number at this place of a position that decode() checked.
     *
     * @param  list<int|string>  $position
     */
    public static function int(array $position, int $index): int
    {
        $part = $position[$index] ?? null;

        return is_int($part) ? $part : throw CommandRefused::of(ErrorCode::ValidationFailed);
    }

    /**
     * The text at this place of a position that decode() checked.
     *
     * @param  list<int|string>  $position
     */
    public static function text(array $position, int $index): string
    {
        $part = $position[$index] ?? null;

        return is_string($part) ? $part : throw CommandRefused::of(ErrorCode::ValidationFailed);
    }
}
