<?php

namespace App\Domain\Catalog\Barcodes;

/**
 * The rules of a barcode token as text (BR-CAT-17, BR-CAT-18; TR-VAL-05). Leading
 * zeros, letter case and punctuation are kept: only the scanner terminator and
 * outer ASCII spaces are removed, so the global trimming never reaches a token
 * (bootstrap/app.php), which would remove a no-break space or a tab that these
 * rules must refuse. The terminator is one Enter at the end (BR-CAT-32), as a line
 * feed, a carriage return or both; any other control character or line break is
 * refused where it stands.
 */
final class BarcodeToken
{
    /** The namespace of the internal codes, reserved exactly as written (BR-CAT-18). */
    public const string RESERVED_NAMESPACE = 'BK-';

    /**
     * The token that a scanned or typed input carries.
     */
    public static function normalized(string $input): string
    {
        return trim((string) preg_replace('/(?:\r\n|\r|\n)\z/', '', rtrim($input, ' ')), ' ');
    }

    /**
     * Whether a normalized token holds 1 to 128 printable ASCII characters, without
     * an outer space; the format that barcode_tokens_token_format also checks.
     */
    public static function isWellFormed(string $token): bool
    {
        return preg_match('/\A[ -~]{1,128}\z/', $token) === 1 && trim($token, ' ') === $token;
    }

    public static function isReserved(string $token): bool
    {
        return str_starts_with($token, self::RESERVED_NAMESPACE);
    }
}
