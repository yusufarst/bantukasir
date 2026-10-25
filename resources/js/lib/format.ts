// The client's formatting module (ADR-005 item 6): the formats of COPY_DECK
// sections 1.3 and 1.4, from canonical strings and instants. Month, day and
// variance words come from the `ux.format` keys through t(); nothing reads the
// browser's locale data or time zone — no Intl, no toLocaleString, no Date at
// all: the calendar is computed in integers — and times are shown in
// Asia/Jakarta. app/Support/Formatting/Format is the server's twin, proven
// equal on one table of cases and on the differential cases.
import { parseExact, parseMoney, parseQuantity, roundHalfUp, toCanonical, UNKNOWN_COST, type Exact } from '@/lib/exact';
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

/** The minus sign of COPY_DECK section 1.3, U+2212. */
const MINUS = '−';

const RUPIAH = 'Rp';

/** The currency symbol of COPY_DECK section 1.3, for the prefix of a rupiah field (TASK-005). */
export const RUPIAH_SYMBOL = RUPIAH;

/**
 * Asia/Jakarta is UTC+07:00, Western Indonesia Time, without daylight saving
 * since 1964, so the client shifts an instant by this offset instead of
 * reading a time-zone database (R-12). An instant before 1964-01-01 00:00 in
 * Asia/Jakarta, 1963-12-31T17:00:00Z, is refused on both sides (TASK-003
 * finding A-14, reading R-31).
 */
const JAKARTA_OFFSET_SECONDS = 7 * 60 * 60;

const FIRST_INSTANT = '1963-12-31T17:00:00';

const SECONDS_PER_DAY = 24 * 60 * 60;

const MONTHS = [
    'ux.format.month.january',
    'ux.format.month.february',
    'ux.format.month.march',
    'ux.format.month.april',
    'ux.format.month.may',
    'ux.format.month.june',
    'ux.format.month.july',
    'ux.format.month.august',
    'ux.format.month.september',
    'ux.format.month.october',
    'ux.format.month.november',
    'ux.format.month.december',
] as const satisfies readonly TranslationKey[];

/** By the day of the week of Date.getUTCDay(), Sunday first. */
const DAYS = [
    'ux.format.day.sunday',
    'ux.format.day.monday',
    'ux.format.day.tuesday',
    'ux.format.day.wednesday',
    'ux.format.day.thursday',
    'ux.format.day.friday',
    'ux.format.day.saturday',
] as const satisfies readonly TranslationKey[];

const DATE = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/;

/** An instant in UTC as the server serializes it, with up to six decimals of a second. */
const INSTANT = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]{1,6})?Z$/;

/** Money: "Rp1.234.567"; a negative amount "−Rp2.000". */
export function formatMoney(value: string): string {
    return money(parseMoney(value));
}

/**
 * A quantity: "1.250,5", with the product's unit when given, "1.250,5 kg";
 * a value with more decimals than its precision is refused, never rounded.
 */
export function formatQuantity(value: string, precision: number, unit?: string): string {
    const text = decimal(toCanonical(parseQuantity(value, precision)));

    return unit === undefined ? text : `${text} ${unit}`;
}

/**
 * A cost on screen: whole rupiah, rounded half-up — ties away from zero, also
 * for a negative figure — once, at the end (BR-NUM-03, ADR-008 section 5):
 * "Rp12.000", "−Rp11". It takes a cost value or a signed cost figure such as
 * a gross profit; an UNKNOWN cost is never shown as an amount (UXS-24).
 */
export function formatCost(value: string): string {
    if (value === UNKNOWN_COST) {
        throw new Error('An UNKNOWN cost has no amount to show (BR-COST-03, UXS-24).');
    }

    return money(roundHalfUp(parseExact(value, 6, true, 'cost figure'), 0));
}

/** A business date, `YYYY-MM-DD`: "01 Oktober 2026". */
export function formatDate(date: string): string {
    const { year, month, day } = calendarDate(date);

    return `${twoDigits(day)} ${monthName(month)} ${fourDigits(year)}`;
}

/** An instant, in Asia/Jakarta: "01 Oktober 2026, 09.15.00". */
export function formatDateTime(instant: string): string {
    const local = jakarta(instant);

    return `${twoDigits(local.day)} ${monthName(local.month)} ${fourDigits(local.year)}, ${twoDigits(local.hours)}.${twoDigits(local.minutes)}.${twoDigits(local.seconds)}`;
}

/** The time of day of an instant, in Asia/Jakarta: "13.45". */
export function formatTimeOfDay(instant: string): string {
    const local = jakarta(instant);

    return `${twoDigits(local.hours)}.${twoDigits(local.minutes)}`;
}

/** The day name of a business date: "Senin" to "Minggu". */
export function formatDayName(date: string): string {
    const { year, month, day } = calendarDate(date);
    // 1970-01-01 was a Thursday, the fifth day of DAYS, which starts on Sunday.
    const key = DAYS[modulo(daysFromCivil(year, month, day) + 4, 7)];

    if (key === undefined) {
        throw new Error('A day of the week is 0 to 6.');
    }

    return t(key);
}

/** The number of a count, "5.000"; the counted noun is in the copy that holds it. */
export function formatCount(count: number | bigint): string {
    if (typeof count !== 'bigint' && !(typeof count === 'number' && Number.isSafeInteger(count))) {
        throw new RangeError('A count is a whole number.');
    }

    if (count < 0) {
        throw new RangeError('A count is never negative.');
    }

    return group(count.toString());
}

/** The unit of a duration, chosen by the caller (COPY_DECK section 1.3, CC-21). */
export type DurationUnit = 'minute' | 'second';

const DURATION_UNITS = {
    minute: 'ux.format.duration.minute',
    second: 'ux.format.duration.second',
} as const satisfies Readonly<Record<DurationUnit, TranslationKey>>;

/**
 * A duration: the amount as a count, a space and the unit word, "60 menit",
 * "30 detik", "1.500 detik". There is no conversion between units and no
 * combined unit; a negative amount, an amount that is not a whole number and
 * an unknown unit are refused (TASK-003 evidence, reading R-25).
 */
export function formatDuration(amount: number | bigint, unit: DurationUnit): string {
    if (unit !== 'minute' && unit !== 'second') {
        throw new RangeError('A duration is in minutes or seconds (COPY_DECK section 1.3).');
    }

    return `${formatCount(amount)} ${t(DURATION_UNITS[unit])}`;
}

/** A cash variance with its word (COPY_DECK section 1.4): "−Rp2.000 (kurang)". */
export function formatVariance(value: string): string {
    const variance = parseMoney(value);
    const word =
        variance.units < 0n
            ? t('ux.format.variance_short')
            : variance.units > 0n
              ? t('ux.format.variance_over')
              : t('ux.format.variance_even');

    return t('ux.format.variance', { uang: money(variance), keterangan: word });
}

/** Money from whole rupiah units. */
function money(value: Exact): string {
    const negative = value.units < 0n;
    const digits = (negative ? -value.units : value.units).toString();

    return `${negative ? MINUS : ''}${RUPIAH}${group(digits)}`;
}

/** A canonical decimal with a decimal comma and dot thousands separators. */
function decimal(canonical: string): string {
    const negative = canonical.startsWith('-');
    const [integer = '', fraction] = (negative ? canonical.slice(1) : canonical).split('.');
    const text = `${negative ? MINUS : ''}${group(integer)}`;

    return fraction === undefined ? text : `${text},${fraction}`;
}

/** Digits grouped by three with a dot: "1234567" to "1.234.567". */
function group(digits: string): string {
    return digits.replace(/\B(?=([0-9]{3})+(?![0-9]))/g, '.');
}

function monthName(month: number): string {
    const key = MONTHS[month - 1];

    if (key === undefined) {
        throw new Error('A month is 1 to 12.');
    }

    return t(key);
}

/** A whole number from ASCII digits, without Number() or parseInt (ADR-006 item 5). */
function digitsValue(digits: string): number {
    let value = 0;

    for (const digit of digits) {
        value = value * 10 + (digit.charCodeAt(0) - 48);
    }

    return value;
}

interface CalendarDate {
    year: number;
    month: number;
    day: number;
}

/**
 * A business date of the years 0001 to 9999, the range the server accepts
 * (BusinessDate::fromString), checked in the proleptic Gregorian calendar
 * without Date.UTC, which maps the years 0 to 99 to 1900 to 1999 (TASK-003
 * finding A-05, reading R-27).
 */
function calendarDate(date: string): CalendarDate {
    const match = DATE.exec(date);

    if (match === null) {
        throw new Error('A business date is written YYYY-MM-DD.');
    }

    const [year, month, day] = [match[1], match[2], match[3]].map((part) => digitsValue(part ?? ''));

    if (year === undefined || month === undefined || day === undefined || !exists(year, month, day)) {
        throw new Error('A business date exists in the calendar.');
    }

    return { year, month, day };
}

/**
 * The instant shifted to Asia/Jakarta by the fixed offset, computed in
 * integers, so that neither the browser's time zone nor Date takes part.
 */
function jakarta(instant: string): CalendarDate & { hours: number; minutes: number; seconds: number } {
    const match = INSTANT.exec(instant);

    if (match === null) {
        throw new Error('An instant is written YYYY-MM-DDTHH:MM:SS, with up to six decimals, in UTC (Z).');
    }

    const [year, month, day, hours, minutes, seconds] = match.slice(1, 7).map((part) => digitsValue(part ?? ''));

    if (year === undefined || month === undefined || day === undefined || hours === undefined || minutes === undefined || seconds === undefined) {
        throw new Error('An instant has six parts.');
    }

    if (!exists(year, month, day) || hours > 23 || minutes > 59 || seconds > 59) {
        throw new Error('An instant exists in the calendar.');
    }

    if (instant.slice(0, FIRST_INSTANT.length) < FIRST_INSTANT) {
        throw new RangeError('An instant before 1964 in Asia/Jakarta is outside the formats (R-12).');
    }

    const local = hours * 3600 + minutes * 60 + seconds + JAKARTA_OFFSET_SECONDS;
    const date = civilFromDays(daysFromCivil(year, month, day) + Math.floor(local / SECONDS_PER_DAY));
    const time = local % SECONDS_PER_DAY;

    return { ...date, hours: Math.floor(time / 3600), minutes: Math.floor((time % 3600) / 60), seconds: time % 60 };
}

/** Whether a date exists: years 0001 to 9999, the months and their days. */
function exists(year: number, month: number, day: number): boolean {
    return year >= 1 && year <= 9999 && month >= 1 && month <= 12 && day >= 1 && day <= daysInMonth(year, month);
}

function daysInMonth(year: number, month: number): number {
    const leap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;

    return [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1] ?? 0;
}

/** The days since 1970-01-01 of a proleptic Gregorian date (Howard Hinnant's days_from_civil). */
function daysFromCivil(year: number, month: number, day: number): number {
    const y = month <= 2 ? year - 1 : year;
    const era = Math.floor(y / 400);
    const yearOfEra = y - era * 400;
    const dayOfYear = Math.floor((153 * (month + (month > 2 ? -3 : 9)) + 2) / 5) + day - 1;
    const dayOfEra = yearOfEra * 365 + Math.floor(yearOfEra / 4) - Math.floor(yearOfEra / 100) + dayOfYear;

    return era * 146097 + dayOfEra - 719468;
}

/** The proleptic Gregorian date of a day since 1970-01-01 (Howard Hinnant's civil_from_days). */
function civilFromDays(days: number): CalendarDate {
    const z = days + 719468;
    const era = Math.floor(z / 146097);
    const dayOfEra = z - era * 146097;
    const yearOfEra = Math.floor((dayOfEra - Math.floor(dayOfEra / 1460) + Math.floor(dayOfEra / 36524) - Math.floor(dayOfEra / 146096)) / 365);
    const dayOfYear = dayOfEra - (365 * yearOfEra + Math.floor(yearOfEra / 4) - Math.floor(yearOfEra / 100));
    const shiftedMonth = Math.floor((5 * dayOfYear + 2) / 153);
    const day = dayOfYear - Math.floor((153 * shiftedMonth + 2) / 5) + 1;
    const month = shiftedMonth + (shiftedMonth < 10 ? 3 : -9);

    return { year: yearOfEra + era * 400 + (month <= 2 ? 1 : 0), month, day };
}

/** The remainder of a division, never negative. */
function modulo(value: number, divisor: number): number {
    return ((value % divisor) + divisor) % divisor;
}

function twoDigits(value: number): string {
    return value.toString().padStart(2, '0');
}

function fourDigits(value: number): string {
    return value.toString().padStart(4, '0');
}
