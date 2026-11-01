<?php

namespace App\Domain\Catalog\Barcodes;

/**
 * The source of the internal codes for Code 128 labels (BR-CAT-18): `BK-P-` and 16
 * uppercase Crockford Base32 characters, each from a secure random source, 80 bits
 * in all. A test replaces this class in the container to control what it draws
 * (EXECUTION_CONTEXT section 10), so that a clash can be proven; Barcodes retries on
 * one.
 */
class InternalCodes
{
    public const string PREFIX = 'BK-P-';

    /** Crockford's Base32, without I, L, O and U. */
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int LENGTH = 16;

    public function next(): string
    {
        $code = self::PREFIX;

        for ($position = 0; $position < self::LENGTH; $position++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
