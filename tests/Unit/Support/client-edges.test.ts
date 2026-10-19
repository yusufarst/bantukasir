import { describe, expect, it } from 'vitest';
import {
    compareMoney,
    lineTotal,
    NonCanonicalNumberError,
    parseCost,
    parseExact,
    parseMoney,
    parseQuantity,
    roundHalfUp,
    subtractMoney,
    sumMoney,
    toCanonical,
    unitsAt,
} from '@/lib/exact';
import { formatCost, formatCount, formatDateTime, formatMoney, formatQuantity, formatTimeOfDay, formatVariance } from '@/lib/format';
import { installDictionary, t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';
import table from './cases/formatting.json';

// The client's boundaries, from the class-A adversarial review of TASK-003
// (findings A-04 and A-17, and its section 5, areas 4, 6 and 8): a value that
// is not a string is never money, a quantity or a cost, whatever reaches the
// helpers — a JSON number above all; a scale is a non-negative integer; an
// instant is read only in the form of R-13; t() never prints a key.

installDictionary(table.dictionary);

const asString = (value: unknown) => value as string;

// 9007199254740993 as it reaches the browser in a JSON body: the number
// 9007199254740992, another amount.
const unsafeJsonNumber: unknown = JSON.parse('9007199254740993');

describe('a value that is not a string is never an exact value (ADR-006 item 2, A-04)', () => {
    const values: [string, unknown][] = [
        ['the JSON number 75000', 75000],
        ['the JSON number 1.5', 1.5],
        ['an unsafe JSON number', unsafeJsonNumber],
        ['a bigint', 75000n],
        ['null', null],
        ['undefined', undefined],
        ['true', true],
        ['an array', ['75000']],
        ['an object with a toString', { toString: () => '75000' }],
        ['a String object', new String('75000')],
    ];

    const helpers: [string, (value: string) => unknown][] = [
        ['parseExact', (value) => parseExact(value, 6, true, 'x')],
        ['parseMoney', (value) => parseMoney(value)],
        ['parseQuantity', (value) => parseQuantity(value, 1)],
        ['parseCost', (value) => parseCost(value)],
        ['formatMoney', (value) => formatMoney(value)],
        ['formatVariance', (value) => formatVariance(value)],
        ['formatQuantity', (value) => formatQuantity(value, 1)],
        ['formatCost', (value) => formatCost(value)],
        ['lineTotal as the quantity', (value) => lineTotal(value, 1, '3333')],
        ['lineTotal as the price', (value) => lineTotal('1.5', 1, value)],
        ['sumMoney', (value) => sumMoney('1', value)],
        ['subtractMoney', (value) => subtractMoney(value, '1')],
        ['compareMoney', (value) => compareMoney('1', value)],
    ];

    it.each(helpers.flatMap(([helper, call]) => values.map(([name, value]) => [`${helper}(${name})`, call, value] as const)))(
        '%s throws NonCanonicalNumberError',
        (_name, call, value) => {
            expect(() => call(asString(value))).toThrow(NonCanonicalNumberError);
        },
    );

    it('a big amount that arrives as a JSON number is refused, never shown as another amount', () => {
        expect(() => formatMoney(asString(unsafeJsonNumber))).toThrow(NonCanonicalNumberError);
        expect(formatMoney('9007199254740993')).toBe('Rp9.007.199.254.740.993');
    });
});

describe('a scale is a non-negative integer (A-17)', () => {
    const value = parseExact('12.5', 1, true, 'x');

    it.each([-1, -0.5, 0.5, 1.5, Number.NaN, Number.POSITIVE_INFINITY, Number.NEGATIVE_INFINITY, 2 ** 53])('roundHalfUp and unitsAt refuse the scale %s', (scale) => {
        expect(() => roundHalfUp(value, scale)).toThrow(RangeError);
        expect(() => unitsAt(value, scale)).toThrow(RangeError);
    });

    it('roundHalfUp never produces a non-canonical string', () => {
        for (const scale of [0, 1, 2, 6]) {
            expect(toCanonical(roundHalfUp(value, scale))).toMatch(/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/);
        }
    });

    it('rounds as before at a valid scale', () => {
        expect(toCanonical(roundHalfUp(value, 0))).toBe('13');
        expect(unitsAt(value, 3)).toBe(12500n);
    });
});

describe('instant parsing on the client accepts only YYYY-MM-DDTHH:MM:SS(.f{1,6})Z (R-13)', () => {
    const refused = [
        '2026-10-18T17:00:00', '2026-10-18T17:00:00+00:00', '2026-10-18T17:00:00+07:00', '2026-10-18T17:00:00.1234567Z', '2026-10-18 17:00:00Z',
        '2026-10-18t17:00:00z', '2026-10-18T17:00:00z', '2026-10-18T24:00:00Z', '2026-10-18T23:60:00Z', '2026-10-18T23:59:60Z', '2026-02-29T00:00:00Z',
        '2026-10-18T17:00:00.Z', '2026-10-18T17:00Z', '+2026-10-18T17:00:00Z', '02026-10-18T17:00:00Z', ' 2026-10-18T17:00:00Z', '2026-10-18T17:00:00Z ',
        '2026-10-18T17:00:00Z\n', '２０２６-10-18T17:00:00Z', '2026-10-18T17:00:00,5Z', '2026-1-18T17:00:00Z', '2026-10-18T7:00:00Z', '', 'Z',
        '1729270800', '2026-10-18', 'Sun, 18 Oct 2026 17:00:00 GMT',
    ];

    it.each(refused)('refuses %j', (instant) => {
        expect(() => formatDateTime(instant)).toThrow();
        expect(() => formatTimeOfDay(instant)).toThrow();
    });

    it('accepts 0 to 6 decimals of a second and truncates them', () => {
        expect(['', '.1', '.12', '.123', '.1234', '.12345', '.999999'].map((f) => formatDateTime(`2026-10-18T16:59:59${f}Z`))).toEqual(
            Array(7).fill('18 Oktober 2026, 23.59.59'),
        );
    });
});

describe('counts', () => {
    it('refuse an unsafe number, a fraction, a negative bigint and a string; accept a big bigint', () => {
        expect(() => formatCount(9007199254740992)).toThrow(RangeError);
        expect(() => formatCount(1.5)).toThrow(RangeError);
        expect(() => formatCount(-1n)).toThrow(RangeError);
        expect(() => formatCount(asString('5') as unknown as number)).toThrow(RangeError);
        expect(formatCount(9223372036854775807n)).toBe('9.223.372.036.854.775.807');
    });
});

describe('t() throws on a missing key and never prints it (ADR-005 item 5)', () => {
    it.each(['ux.format.nope', 'constructor', '__proto__', 'toString', 'hasOwnProperty', ''])('%j', (key) => {
        expect(() => t(key as TranslationKey)).toThrow(/missing from the dictionary/);
    });
});
