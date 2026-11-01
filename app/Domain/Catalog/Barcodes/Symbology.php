<?php

namespace App\Domain\Catalog\Barcodes;

/**
 * The symbologies a manufacturer barcode may be declared as (INTERACTION_CONTRACTS
 * RC-17; SCHEMA section 5.6), and the check digit of a declared token (BR-CAT-17,
 * G-09). UPC-E carries a number system of 0 or 1, six digits and the check digit of
 * the UPC-A code that it abbreviates, to which it is expanded first; any other
 * number system has no such code, so its check digit cannot hold.
 */
enum Symbology: string
{
    case Ean13 = 'EAN_13';
    case Ean8 = 'EAN_8';
    case UpcA = 'UPC_A';
    case UpcE = 'UPC_E';

    /**
     * Whether a token has this symbology's digits and a valid check digit.
     */
    public function accepts(string $token): bool
    {
        if (preg_match('/\A[0-9]+\z/', $token) !== 1 || strlen($token) !== $this->length()) {
            return false;
        }

        $digits = $this === self::UpcE ? self::upcAOf($token) : $token;

        return $digits !== null && self::checkDigitHolds($digits);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $symbology): string => $symbology->value, self::cases());
    }

    private function length(): int
    {
        return match ($this) {
            self::Ean13 => 13,
            self::Ean8, self::UpcE => 8,
            self::UpcA => 12,
        };
    }

    /**
     * The UPC-A code of an UPC-E token of 8 digits, by its sixth digit, or null for
     * a number system that UPC-E does not have.
     */
    private static function upcAOf(string $token): ?string
    {
        if ($token[0] !== '0' && $token[0] !== '1') {
            return null;
        }

        $body = substr($token, 1, 6);
        $last = $body[5];

        $expanded = match ($last) {
            '0', '1', '2' => substr($body, 0, 2).$last.'0000'.substr($body, 2, 3),
            '3' => substr($body, 0, 3).'00000'.substr($body, 3, 2),
            '4' => substr($body, 0, 4).'00000'.$body[4],
            default => substr($body, 0, 5).'0000'.$last,
        };

        return $token[0].$expanded.$token[7];
    }

    /**
     * The sum of modulo 10, from the check digit leftwards, by 1 and 3 in turn: the
     * same rule for each of these codes, whatever its length.
     */
    private static function checkDigitHolds(string $digits): bool
    {
        $sum = 0;
        $weight = 1;

        for ($position = strlen($digits) - 1; $position >= 0; $position--) {
            $sum += (int) $digits[$position] * $weight;
            $weight = $weight === 1 ? 3 : 1;
        }

        return $sum % 10 === 0;
    }
}
