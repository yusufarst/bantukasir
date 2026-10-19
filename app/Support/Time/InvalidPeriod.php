<?php

namespace App\Support\Time;

use InvalidArgumentException;

/**
 * A custom period that TECHNICAL_RULES section 11.4 refuses: its start after
 * its end, its end after today in Asia/Jakarta, or more than 366 days. The
 * page refuses it at its date fields; the kernel refuses it again.
 */
final class InvalidPeriod extends InvalidArgumentException
{
    public const string START_AFTER_END = 'START_AFTER_END';

    public const string END_AFTER_TODAY = 'END_AFTER_TODAY';

    public const string TOO_LONG = 'TOO_LONG';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function startAfterEnd(): self
    {
        return new self(self::START_AFTER_END, 'A custom period starts after its end.');
    }

    public static function endAfterToday(): self
    {
        return new self(self::END_AFTER_TODAY, 'A custom period ends after today in Asia/Jakarta.');
    }

    public static function tooLong(): self
    {
        return new self(self::TOO_LONG, 'A custom period is longer than 366 days.');
    }
}
