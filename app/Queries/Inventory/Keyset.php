<?php

namespace App\Queries\Inventory;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The opaque cursors of the keyset lists of the stock pages (INTERACTION_CONTRACTS section
 * 3.2; TECHNICAL_RULES TR-PAGE-01 to TR-PAGE-03): the sort key of a page's boundary row, as
 * base64url of its JSON, its text unescaped so that a name of 200 characters fits the cursor's
 * limit, CURSOR (review finding R-01). A cursor is read again under the viewer's scope and never
 * grants access; one that is not the shape of its list, or whose time or text PostgreSQL could
 * not read — a day that does not exist, a control character — is VALIDATION_FAILED, as RD-04's
 * is, never a server error (review finding R-02).
 */
final class Keyset
{
    /**
     * The rule of a cursor's parameter: base64url of the JSON of a 200-character name of up to
     * four bytes per character and its key, about 1,100 characters, with room.
     */
    public const string CURSOR = 'regex:/^[A-Za-z0-9_-]{1,1400}$/D';

    /** An instant in UTC as the stock reads write one. */
    private const string INSTANT = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z$/D';

    private const string INSTANT_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    private const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /**
     * @param  list<int|string>  $position
     */
    public static function encode(array $position): string
    {
        // U+2028 and U+2029 too, which JSON_UNESCAPED_UNICODE alone still escapes in six bytes (review finding R-11).
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS)), '+/', '-_'), '=');
    }

    /**
     * Whether a text is an instant of the calendar from the year 1, written exactly as the
     * stock reads write one.
     */
    private static function isInstant(string $text): bool
    {
        if (preg_match(self::INSTANT, $text) !== 1) {
            return false;
        }

        $instant = DateTimeImmutable::createFromFormat('!'.self::INSTANT_FORMAT, $text, new DateTimeZone('UTC'));

        return $instant !== false && $instant->format(self::INSTANT_FORMAT) === $text && $instant->format('Y') !== '0000';
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
                'text' => is_string($part) && mb_strlen($part) <= 200 && preg_match(self::PLAIN_TEXT, $part) === 1,
                'instant' => is_string($part) && self::isInstant($part),
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
