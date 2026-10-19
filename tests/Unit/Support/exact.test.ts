import { describe, expect, it } from 'vitest';
import {
    compareMoney,
    lineTotal,
    NonCanonicalNumberError,
    NumberOutOfRangeError,
    parseCost,
    parseExact,
    parseMoney,
    parseQuantity,
    roundHalfUp,
    subtractMoney,
    sumMoney,
    toCanonical,
    UNKNOWN_COST,
    unitsAt,
    type Exact,
} from '@/lib/exact';
import table from './cases/numbers.json';

// The client's run of the shared table of arithmetic, canonical-string and
// rounding cases (AC-003-01, AC-003-02; FIN01), with scaled BigInt (ADR-006
// item 6). tests/Unit/Support/Numbers/NumberCasesTest.php runs the same table
// on the server.
function refusedAsNull(operation: () => string): string | null {
    try {
        return operation();
    } catch (error) {
        if (error instanceof NonCanonicalNumberError || error instanceof NumberOutOfRangeError) {
            return null;
        }

        throw error;
    }
}

function parse(kind: string, input: string, precision: number | undefined): Exact {
    switch (kind) {
        case 'money':
            return parseMoney(input);
        case 'quantity':
            return parseQuantity(input, precision ?? -1);
        case 'cost':
            return parseCost(input);
        default:
            throw new Error(`Unknown kind ${kind}`);
    }
}

describe('canonical strings', () => {
    it.each(table.canonical.map((entry) => [`${entry.kind} ${'precision' in entry ? entry.precision : ''} ${JSON.stringify(entry.input)}`, entry] as const))(
        '%s',
        (_name, entry) => {
            const precision = 'precision' in entry ? entry.precision : undefined;

            expect(refusedAsNull(() => toCanonical(parse(entry.kind, entry.input, precision)))).toBe(entry.expected);
        },
    );

    it('refuses the UNKNOWN marker as a number', () => {
        expect(() => parseCost(UNKNOWN_COST)).toThrow(NonCanonicalNumberError);
    });

    it('refuses a precision outside 0 to 3', () => {
        expect(() => parseQuantity('1', 4)).toThrow(RangeError);
    });
});

describe('line totals, half-up once per line', () => {
    it.each(table.lineTotals.map((entry) => [entry.case, entry] as const))('%s', (_name, entry) => {
        expect(refusedAsNull(() => lineTotal(entry.quantity, entry.precision, entry.unitPrice))).toBe(entry.expected);
    });

    it.each(table.saleTotals.map((entry) => [entry.case, entry] as const))('%s', (_name, entry) => {
        expect(sumMoney(...entry.lines)).toBe(entry.expected);
    });
});

describe('half-up rounding, ties away from zero', () => {
    it.each(table.rounding.map((entry) => [`${entry.input} to ${entry.scale}`, entry] as const))('%s', (_name, entry) => {
        expect(toCanonical(roundHalfUp(parseExact(entry.input, 20, true, 'value'), entry.scale))).toBe(entry.expected);
    });
});

describe('money previews', () => {
    it('computes change and compares exactly', () => {
        const total = sumMoney('75000', '5000');

        expect(total).toBe('80000');
        expect(subtractMoney('100000', total)).toBe('20000');
        expect(subtractMoney(total, '100000')).toBe('-20000');
        expect(compareMoney('100000', total)).toBe(1);
        expect(compareMoney(total, '80000')).toBe(0);
        expect(compareMoney('-1', '0')).toBe(-1);
    });

    it('stays exact beyond the range of a JavaScript number', () => {
        expect(sumMoney('9007199254740993', '1')).toBe('9007199254740994');
        // 99999999.999 × 9999 = 999899999990.001, beyond 2^53.
        expect(lineTotal('99999999.999', 3, '9999')).toBe('999899999990');
    });

    it('never rescales to fewer decimals without a rounding rule', () => {
        expect(() => unitsAt({ units: 15n, scale: 1 }, 0)).toThrow(RangeError);
        expect(unitsAt({ units: 15n, scale: 1 }, 3)).toBe(1500n);
    });
});
