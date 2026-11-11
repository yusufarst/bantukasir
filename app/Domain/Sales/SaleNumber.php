<?php

namespace App\Domain\Sales;

use App\Support\Time\BusinessDate;
use InvalidArgumentException;

/**
 * The number of a Sale (SCHEMA section 6, SCH-22; D-062; BR-DOC-01): `YYYYMMDD-n`, the Sale's
 * business date in Asia/Jakarta and the counter value in decimal, zero-padded to at least six digits
 * and never truncated. The database checks the same form against the row's own date and counter
 * (`sales_number_matches`).
 */
final class SaleNumber
{
    public const int MIN_DIGITS = 6;

    public static function of(BusinessDate $date, int $counter): string
    {
        if ($counter < 1) {
            throw new InvalidArgumentException('A Sale number counter starts at 1 (SCH-20).');
        }

        return str_replace('-', '', $date->toString()).'-'.str_pad((string) $counter, self::MIN_DIGITS, '0', STR_PAD_LEFT);
    }
}
