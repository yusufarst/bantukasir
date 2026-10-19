<?php

namespace Tests\Unit\Support\Differential;

use App\Support\Formatting\Format;
use App\Support\Numbers\CanonicalNumber;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Translation\Translator;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * The server's outcome of every differential case of TASK-003: the canonical
 * string or text it computes, or the short name of the exception that refuses
 * the input. expected.php writes these once into the table of cases;
 * DifferentialCasesTest computes them again and requires the same, and the
 * client's run compares its own outcomes with the same table.
 */
final class ServerOutcomes
{
    /**
     * @param  array<string, list<mixed>>  $cases
     * @return array<string, list<array{ok?: string, err?: string}>>
     */
    public static function all(array $cases): array
    {
        return [
            'parse' => self::each($cases['parse'], fn (array $c): string => CanonicalNumber::format(CanonicalNumber::parse(self::string($c['s']), self::int($c['scale']), $c['signed'] === true, 'x'))),
            'parseKind' => self::each($cases['parse'], fn (array $c): string => self::parseKind(self::string($c['s']), self::int($c['scale']), $c['signed'] === true)),
            'lineTotal' => self::each($cases['lineTotal'], fn (array $c): string => Money::lineTotal(Quantity::of(self::string($c['q']), self::int($c['p'])), Money::of(self::string($c['price'])))->toCanonical()),
            'sums' => self::each($cases['sums'], fn (array $c): string => Money::sum(...array_map(fn (mixed $amount): Money => Money::of(self::string($amount)), $c))->toCanonical()),
            'minus' => self::each($cases['pairs'], fn (array $c): string => Money::of(self::string($c[0]))->minus(Money::of(self::string($c[1])))->toCanonical()),
            'compare' => self::each($cases['pairs'], fn (array $c): string => (string) Money::of(self::string($c[0]))->compareTo(Money::of(self::string($c[1])))),
            'round' => self::each($cases['round'], fn (array $c): string => CanonicalNumber::format(CanonicalNumber::parse(self::string($c['s']), 12, true, 'x')->toScale(self::scale($c['scale']), RoundingMode::HalfUp))),
            'mulDiv' => self::each($cases['mulDiv'], fn (array $c): string => Cost::mulDiv(Cost::of(self::string($c['v'])), Quantity::of(self::string($c['q']), self::int($c['p'])), Quantity::of(self::string($c['Q']), self::int($c['p'])))->toCanonical()),
            'placeholders' => self::each($cases['placeholders'], fn (array $c): string => self::replace(self::string($c['text']), self::strings($c['replacements']))),
            ...self::formats($cases),
        ];
    }

    /**
     * The formatting categories alone, which the test runs again under other
     * time zones and locales.
     *
     * @param  array<string, list<mixed>>  $cases
     * @return array<string, list<array{ok?: string, err?: string}>>
     */
    public static function formats(array $cases): array
    {
        return [
            'formatMoney' => self::each($cases['money'], fn (string $s): string => Format::money(Money::of($s))),
            'formatVariance' => self::each($cases['money'], fn (string $s): string => Format::variance(Money::of($s))),
            'formatQuantity' => self::each($cases['quantity'], fn (array $c): string => Format::quantity(Quantity::of(self::string($c['v']), self::int($c['p'])), $c['unit'] === null ? null : self::string($c['unit']))),
            'formatCostFigure' => self::each($cases['cost'], fn (string $s): string => Format::cost(CanonicalNumber::parse($s, Cost::SCALE, true, 'cost figure'))),
            'formatDate' => self::each($cases['dates'], fn (string $d): string => Format::date(BusinessDate::fromString($d))),
            'formatDayName' => self::each($cases['dates'], fn (string $d): string => Format::dayName(BusinessDate::fromString($d))),
            'formatDateTime' => self::each($cases['instants'], fn (string $i): string => Format::dateTime(CarbonImmutable::parse($i))),
            'formatTimeOfDay' => self::each($cases['instants'], fn (string $i): string => Format::timeOfDay(CarbonImmutable::parse($i))),
            'formatCount' => self::each($cases['counts'], fn (string $n): string => Format::count(self::integer($n))),
            'formatDuration' => self::each($cases['durations'], fn (array $c): string => Format::duration(self::integer(self::string($c[0])), self::string($c[1]))),
        ];
    }

    /**
     * @template T
     *
     * @param  list<mixed>  $inputs
     * @param  callable(T): string  $compute
     * @return list<array{ok?: string, err?: string}>
     */
    private static function each(array $inputs, callable $compute): array
    {
        return array_map(function (mixed $input) use ($compute): array {
            try {
                return ['ok' => $compute($input)];
            } catch (Throwable $error) {
                return ['err' => (new ReflectionClass($error))->getShortName()];
            }
        }, $inputs);
    }

    /**
     * The value object of a parse configuration: money (scale 0, signed), a
     * quantity (scale 1 to 3, signed) or a cost (scale 6, unsigned).
     */
    private static function parseKind(string $s, int $scale, bool $signed): string
    {
        return match (true) {
            $scale === 0 && $signed => Money::of($s)->toCanonical(),
            $scale <= 3 && $signed => Quantity::of($s, $scale)->toCanonical(),
            $scale === 6 && ! $signed => Cost::of($s)->toCanonical(),
            default => 'n/a',
        };
    }

    /**
     * Laravel's own replacement of :name placeholders (Translator::makeReplacements).
     *
     * @param  array<string, string>  $replacements
     */
    private static function replace(string $text, array $replacements): string
    {
        $result = (new ReflectionMethod(Translator::class, 'makeReplacements'))->invoke(app('translator'), $text, $replacements);

        return is_string($result) ? $result : throw new LogicException('makeReplacements gives a string.');
    }

    /**
     * A whole number written in decimal digits, as a PHP int.
     */
    private static function integer(string $digits): int
    {
        return preg_match('/^(0|[1-9][0-9]{0,18})$/D', $digits) === 1 && strcmp(str_pad($digits, 19, '0', STR_PAD_LEFT), (string) PHP_INT_MAX) <= 0
            ? intval($digits)
            : throw new LogicException("{$digits} is not a PHP integer.");
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException('A string was expected in the cases.');
    }

    private static function int(mixed $value): int
    {
        return is_int($value) ? $value : throw new LogicException('An integer was expected in the cases.');
    }

    /**
     * @return int<0, max>
     */
    private static function scale(mixed $value): int
    {
        return is_int($value) && $value >= 0 ? $value : throw new LogicException('A scale of 0 or more was expected in the cases.');
    }

    /**
     * @return array<string, string>
     */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LogicException('An object of strings was expected in the cases.');
        }

        return array_map(fn (mixed $text): string => self::string($text), array_filter($value, is_string(...), ARRAY_FILTER_USE_KEY));
    }
}
