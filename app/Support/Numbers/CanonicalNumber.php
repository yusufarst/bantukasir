<?php

namespace App\Support\Numbers;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;

/**
 * The canonical number strings of ADR-006 item 2: digits with `.` as the
 * decimal point; no thousands separator, exponent, leading `+`, leading zero
 * other than a single `0` before the point, whitespace or negative zero; a
 * point is followed by at least one digit; a leading `-` only where the kind is
 * signed. A string is checked for length and form before any decimal operation
 * (ADR-006 item 3), and one that is not canonical is refused, never repaired.
 */
final class CanonicalNumber
{
    /**
     * The longest canonical string that is parsed: far above every range of
     * TECHNICAL_RULES section 11.1, so that a longer one is outside every
     * range and is refused before Brick Math sees it.
     */
    public const int MAX_LENGTH = 40;

    private const string PATTERN = '/^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D';

    /**
     * The parts of a canonical string with any number of fractional digits,
     * or null when the string is not canonical in form.
     *
     * @return array{negative: bool, integer: string, fraction: string}|null
     */
    public static function split(string $value): ?array
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            return null;
        }

        $negative = $matches[1] === '-';
        $fraction = $matches[3] ?? '';

        if ($negative && $matches[2] === '0' && trim($fraction, '0') === '') {
            return null;
        }

        return ['negative' => $negative, 'integer' => $matches[2], 'fraction' => $fraction];
    }

    /**
     * Parses a canonical string with at most $maxScale fractional digits, as
     * written, and a sign only when $signed. A value that is not a string — a
     * PHP float above all, which coercive typing would otherwise turn into a
     * string first — is refused at run time (TASK-003 finding A-11).
     *
     * @param  string  $value  any other type is refused at run time
     *
     * @throws NonCanonicalNumber
     * @throws NumberOutOfRange when the string is longer than MAX_LENGTH
     */
    public static function parse(mixed $value, int $maxScale, bool $signed, string $kind): BigDecimal
    {
        $value = self::string($value, $kind);
        $parts = self::split($value);

        if ($parts === null || strlen($parts['fraction']) > $maxScale || ($parts['negative'] && ! $signed)) {
            throw NonCanonicalNumber::for($kind);
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw NumberOutOfRange::for($kind);
        }

        return BigDecimal::of($value);
    }

    /**
     * The value itself when it is a PHP string; any other type is refused,
     * never converted (ADR-006 items 2 and 5).
     *
     * @throws NonCanonicalNumber
     */
    public static function string(mixed $value, string $kind): string
    {
        return is_string($value) ? $value : throw NonCanonicalNumber::for($kind);
    }

    /**
     * Whether a canonical string is above a canonical maximum, checking its
     * length before any decimal comparison (ADR-006 item 3).
     */
    public static function exceeds(string $value, string $maximum): bool
    {
        return strlen($value) > self::MAX_LENGTH || BigDecimal::of($value)->isGreaterThan($maximum);
    }

    /**
     * The canonical string of an exact value in its shortest form: no
     * trailing zero in the fraction and no point without a fraction.
     */
    public static function format(BigNumber $value): string
    {
        return $value->toBigDecimal()->strippedOfTrailingZeros()->toString();
    }
}
