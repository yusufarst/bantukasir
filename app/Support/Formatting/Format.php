<?php

namespace App\Support\Formatting;

use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;

/**
 * The server's formatting module (ADR-005 item 6): the formats of COPY_DECK
 * sections 1.3 and 1.4, from exact values. Month, day and variance words come
 * from the `ux.format` keys of lang/id; nothing reads the server's locale data,
 * and times are shown in Asia/Jakarta. resources/js/lib/format.ts is the
 * client's twin, proven equal on one table of cases.
 */
final class Format
{
    /** The minus sign of COPY_DECK section 1.3, U+2212. */
    private const string MINUS = "\u{2212}";

    private const string RUPIAH = 'Rp';

    /**
     * Money: "Rp1.234.567"; a negative amount "−Rp2.000".
     */
    public static function money(Money $amount): string
    {
        $sign = $amount->isNegative() ? self::MINUS : '';

        return $sign.self::RUPIAH.self::group($amount->toBigInteger()->abs()->toString());
    }

    /**
     * A quantity: "1.250,5", with the product's unit when given, "1.250,5 kg";
     * never more decimals than its precision (the value object holds that).
     */
    public static function quantity(Quantity $quantity, ?string $unit = null): string
    {
        $text = self::decimal($quantity->toCanonical());

        return $unit === null ? $text : $text.' '.$unit;
    }

    /**
     * A cost on screen: whole rupiah, rounded half-up — ties away from zero,
     * also for a negative figure — once, at the end (BR-NUM-03, ADR-008
     * section 5): "Rp12.000", "−Rp11". The six-decimal value is never shown,
     * and an UNKNOWN cost is never shown as an amount (UXS-24).
     */
    public static function cost(Cost|BigDecimal $figure): string
    {
        $figure = $figure instanceof Cost ? $figure->amount() : $figure;

        return self::money(Money::fromBigInteger($figure->toScale(0, RoundingMode::HalfUp)->toBigInteger()));
    }

    /**
     * A date: "01 Oktober 2026".
     */
    public static function date(BusinessDate $date): string
    {
        return sprintf('%02d %s %04d', $date->day(), self::monthName($date->month()), $date->year());
    }

    /**
     * A date and time, in Asia/Jakarta: "01 Oktober 2026, 09.15.00".
     */
    public static function dateTime(DateTimeInterface $instant): string
    {
        $local = self::jakarta($instant);

        return sprintf('%s %s %s, %s', $local->format('d'), self::monthName(intval($local->format('n'))), $local->format('Y'), $local->format('H.i.s'));
    }

    /**
     * A time of day, in Asia/Jakarta: "13.45".
     */
    public static function timeOfDay(DateTimeInterface $instant): string
    {
        return self::jakarta($instant)->format('H.i');
    }

    /**
     * The day name of a date: "Senin" to "Minggu".
     */
    public static function dayName(BusinessDate $date): string
    {
        return self::string(match ($date->dayOfWeek()) {
            1 => __('ux.format.day.monday'),
            2 => __('ux.format.day.tuesday'),
            3 => __('ux.format.day.wednesday'),
            4 => __('ux.format.day.thursday'),
            5 => __('ux.format.day.friday'),
            6 => __('ux.format.day.saturday'),
            default => __('ux.format.day.sunday'),
        });
    }

    /**
     * The number of a count, "5.000"; the counted noun is in the copy that
     * holds the count (COPY_DECK section 1.3).
     */
    public static function count(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('A count is never negative.');
        }

        return self::group((string) $count);
    }

    /**
     * A duration: the amount as a count, a space and the unit word, "60
     * menit", "30 detik", "1.500 detik" (COPY_DECK section 1.3, CC-21). The
     * caller chooses the unit, `minute` or `second`; there is no conversion
     * between units and no combined unit. A negative amount, an amount that is
     * not an integer and an unknown unit are refused (TASK-003 evidence,
     * reading R-25).
     *
     * @param  int  $amount  a whole number of 0 or more; any other value is refused at run time
     */
    public static function duration(mixed $amount, string $unit): string
    {
        $word = match ($unit) {
            'minute' => __('ux.format.duration.minute'),
            'second' => __('ux.format.duration.second'),
            default => throw new InvalidArgumentException('A duration is in minutes or seconds (COPY_DECK section 1.3).'),
        };

        return self::count(self::wholeNumber($amount, 'A duration')).' '.self::string($word);
    }

    /**
     * A cash variance with its word (COPY_DECK section 1.4): "−Rp2.000
     * (kurang)", "Rp5.000 (lebih)", "Rp0 (sesuai)".
     */
    public static function variance(Money $variance): string
    {
        $word = match (true) {
            $variance->isNegative() => __('ux.format.variance_short'),
            $variance->isPositive() => __('ux.format.variance_over'),
            default => __('ux.format.variance_even'),
        };

        return self::string(__('ux.format.variance', ['uang' => self::money($variance), 'keterangan' => self::string($word)]));
    }

    private static function monthName(int $month): string
    {
        return self::string(match ($month) {
            1 => __('ux.format.month.january'),
            2 => __('ux.format.month.february'),
            3 => __('ux.format.month.march'),
            4 => __('ux.format.month.april'),
            5 => __('ux.format.month.may'),
            6 => __('ux.format.month.june'),
            7 => __('ux.format.month.july'),
            8 => __('ux.format.month.august'),
            9 => __('ux.format.month.september'),
            10 => __('ux.format.month.october'),
            11 => __('ux.format.month.november'),
            12 => __('ux.format.month.december'),
            default => throw new InvalidArgumentException('A month is 1 to 12.'),
        });
    }

    /**
     * A canonical decimal with a decimal comma and dot thousands separators.
     */
    private static function decimal(string $canonical): string
    {
        $parts = CanonicalNumber::split($canonical) ?? throw new LogicException('A value object gives a canonical string.');
        $text = ($parts['negative'] ? self::MINUS : '').self::group($parts['integer']);

        return $parts['fraction'] === '' ? $text : $text.','.$parts['fraction'];
    }

    /**
     * Digits grouped by three with a dot: "1234567" to "1.234.567".
     */
    private static function group(string $digits): string
    {
        return strrev(implode('.', str_split(strrev($digits), 3)));
    }

    /**
     * A whole number of 0 or more, given as a PHP int: a float, a numeric
     * string or any other value is refused, never truncated (ADR-006 item 5).
     */
    private static function wholeNumber(mixed $value, string $what): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('%s is a whole number given as an integer.', $what));
        }

        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('%s is never negative.', $what));
        }

        return $value;
    }

    private static function jakarta(DateTimeInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(BusinessDate::TIME_ZONE);
    }

    /**
     * A text of the dictionary, which is always a string for a key that
     * leads to one.
     */
    private static function string(mixed $text): string
    {
        return is_string($text) ? $text : throw new LogicException('A format key leads to a text.');
    }
}
