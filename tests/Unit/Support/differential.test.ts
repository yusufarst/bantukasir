import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { compareMoney, lineTotal, parseCost, parseExact, parseMoney, parseQuantity, roundHalfUp, subtractMoney, sumMoney, toCanonical } from '@/lib/exact';
import {
    formatCost,
    formatCount,
    formatDate,
    formatDateTime,
    formatDayName,
    formatDuration,
    formatMoney,
    formatQuantity,
    formatTimeOfDay,
    formatVariance,
    type DurationUnit,
} from '@/lib/format';
import { installDictionary, replacePlaceholders } from '@/lib/i18n';

// The client's half of the differential run of TASK-003 (the class-A
// adversarial review's run, adopted: areas 4 and 5, AC-003-01 and AC-003-04):
// every case of the committed table tests/Unit/Support/cases/differential.json
// runs here and must give the server's expected outcome, which
// tests/Unit/Support/Differential/expected.php wrote once and
// DifferentialCasesTest proves on the server. Independent BigInt oracles check
// the line total, the rounding and round6(V × q / Q). The formats run again in
// other time zones with every source of locale data made to throw. The
// environment variable DIFFERENTIAL_CASES names another table, such as the
// full-size run's. Nothing is written.

type Outcome = { ok?: string; err?: string };

interface Cases {
    parse: { s: string; scale: number; signed: boolean }[];
    lineTotal: { q: string; p: number; price: string }[];
    sums: string[][];
    pairs: [string, string][];
    round: { s: string; scale: number }[];
    money: string[];
    quantity: { v: string; p: number; unit: string | null }[];
    cost: string[];
    dates: string[];
    instants: string[];
    counts: string[];
    durations: [string, string][];
    mulDiv: { v: string; q: string; Q: string; p: number }[];
    placeholders: { text: string; replacements: Record<string, string> }[];
}

interface Table {
    cases: Cases;
    expected: Record<string, Outcome[] | undefined>;
    dictionary: Record<string, string>;
}

const file = process.env.DIFFERENTIAL_CASES || 'tests/Unit/Support/cases/differential.json';
const table = JSON.parse(readFileSync(file, 'utf8')) as Table;
const { cases, expected } = table;

installDictionary(table.dictionary);

function outcomeOf(compute: () => string): Outcome {
    try {
        return { ok: compute() };
    } catch (error) {
        return { err: error instanceof Error ? error.name : 'non-error' };
    }
}

/** The server's exception for each client error class, where the two name the same refusal. */
const sameClass: Record<string, string> = { NonCanonicalNumberError: 'NonCanonicalNumber', NumberOutOfRangeError: 'NumberOutOfRange' };

function agree(server: Outcome | undefined, client: Outcome, compareClasses: boolean): boolean {
    if (server === undefined) {
        return false;
    }

    if (server.ok !== undefined || client.ok !== undefined) {
        return server.ok === client.ok;
    }

    return !compareClasses || sameClass[client.err ?? ''] === server.err;
}

interface Difference<T> {
    index: number;
    input: T;
    server: Outcome | undefined;
    client: Outcome;
}

function differences<T>(category: string, inputs: readonly T[], client: (input: T) => Outcome, compareClasses = false): Difference<T>[] {
    const found: Difference<T>[] = [];

    expect(expected[category]?.length, `${category}: the table's expected outcomes`).toBe(inputs.length);

    inputs.forEach((input, index) => {
        const mine = client(input);
        const theirs = expected[category]?.[index];

        if (!agree(theirs, mine, compareClasses)) {
            found.push({ index, input, server: theirs, client: mine });
        }
    });

    return found;
}

// Independent oracles in plain BigInt, written without resources/js/lib.
function scaled(text: string, scale: number): bigint {
    const negative = text.startsWith('-');
    const [integer = '0', fraction = ''] = (negative ? text.slice(1) : text).split('.');
    const units = BigInt(integer + fraction.padEnd(scale, '0'));

    return negative ? -units : units;
}

function halfUpDivide(numerator: bigint, denominator: bigint): bigint {
    const negative = numerator < 0n !== denominator < 0n;
    const n = numerator < 0n ? -numerator : numerator;
    const d = denominator < 0n ? -denominator : denominator;
    const q = n / d;
    const rounded = (n % d) * 2n >= d ? q + 1n : q;

    return negative ? -rounded : rounded;
}

function render(units: bigint, scale: number): string {
    const negative = units < 0n;
    const digits = (negative ? -units : units).toString().padStart(scale + 1, '0');
    let text = scale === 0 ? digits : `${digits.slice(0, digits.length - scale)}.${digits.slice(digits.length - scale)}`;

    if (text.includes('.')) {
        text = text.replace(/0+$/, '').replace(/\.$/, '');
    }

    return negative && text !== '0' ? `-${text}` : text;
}

/** Each format with its differences from the server's expected texts. */
const formats: [string, () => Difference<unknown>[]][] = [
    ['formatMoney', () => differences('formatMoney', cases.money, (s) => outcomeOf(() => formatMoney(s)))],
    ['formatVariance', () => differences('formatVariance', cases.money, (s) => outcomeOf(() => formatVariance(s)))],
    ['formatQuantity', () => differences('formatQuantity', cases.quantity, (c) => outcomeOf(() => formatQuantity(c.v, c.p, c.unit ?? undefined)))],
    ['formatCostFigure', () => differences('formatCostFigure', cases.cost, (s) => outcomeOf(() => formatCost(s)))],
    ['formatDate', () => differences('formatDate', cases.dates, (d) => outcomeOf(() => formatDate(d)))],
    ['formatDayName', () => differences('formatDayName', cases.dates, (d) => outcomeOf(() => formatDayName(d)))],
    ['formatDateTime', () => differences('formatDateTime', cases.instants, (i) => outcomeOf(() => formatDateTime(i)))],
    ['formatTimeOfDay', () => differences('formatTimeOfDay', cases.instants, (i) => outcomeOf(() => formatTimeOfDay(i)))],
    ['formatCount', () => differences('formatCount', cases.counts, (n) => outcomeOf(() => formatCount(BigInt(n))))],
    ['formatDuration', () => differences('formatDuration', cases.durations, ([amount, unit]) => outcomeOf(() => formatDuration(BigInt(amount), unit as DurationUnit)))],
];

function throwing(name: string): never {
    throw new Error(`${name} was read: formatting must not depend on locale data.`);
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
});

describe(`the differential run of ${file}`, () => {
    it('parses canonical strings exactly like the server, with the same refusals', () => {
        expect(differences('parse', cases.parse, (c) => outcomeOf(() => toCanonical(parseExact(c.s, c.scale, c.signed, 'x'))), true).slice(0, 10)).toEqual([]);
    });

    it('parses money, quantities and costs like the value objects, apart from the UNKNOWN marker', () => {
        const found = differences(
            'parseKind',
            cases.parse,
            (c) =>
                outcomeOf(() =>
                    c.scale === 0 && c.signed
                        ? toCanonical(parseMoney(c.s))
                        : c.scale <= 3 && c.signed
                          ? toCanonical(parseQuantity(c.s, c.scale))
                          : c.scale === 6 && !c.signed
                            ? toCanonical(parseCost(c.s))
                            : 'n/a',
                ),
            true,
        ).filter((difference) => difference.input.s !== 'UNKNOWN');

        // The client's parseCost refuses the marker by design: an UNKNOWN cost
        // is never a number on the client (exact.ts).
        expect(found.slice(0, 10)).toEqual([]);
    });

    it('computes line totals like the server, and the server like an independent oracle', () => {
        expect(differences('lineTotal', cases.lineTotal, (c) => outcomeOf(() => lineTotal(c.q, c.p, c.price))).slice(0, 10)).toEqual([]);

        const oracle = cases.lineTotal.flatMap((c, index) => {
            const server = expected.lineTotal?.[index];
            const total = halfUpDivide(scaled(c.q, c.p) * scaled(c.price, 0), 10n ** BigInt(c.p));
            const inRange = total >= 0n && total <= 999999999999n;
            const canonicalInput = /^(0|[1-9][0-9]*)(\.[0-9]+)?$/.test(c.q) && /^-?(0|[1-9][0-9]*)$/.test(c.price) && (c.q.split('.')[1] ?? '').length <= c.p;

            if (server?.ok !== undefined) {
                return inRange && server.ok === total.toString() ? [] : [{ input: c, server, oracle: total.toString() }];
            }

            return canonicalInput && inRange ? [{ input: c, server, oracle: total.toString() }] : [];
        });

        expect(oracle.slice(0, 10)).toEqual([]);
    });

    it('sums, subtracts and compares money like the server', () => {
        expect(differences('sums', cases.sums, (c) => outcomeOf(() => sumMoney(...c))).slice(0, 10)).toEqual([]);
        expect(differences('minus', cases.pairs, (c) => outcomeOf(() => subtractMoney(c[0], c[1]))).slice(0, 10)).toEqual([]);
        expect(differences('compare', cases.pairs, (c) => outcomeOf(() => String(compareMoney(c[0], c[1])))).slice(0, 10)).toEqual([]);
    });

    it('rounds half-up, ties away from zero, like the server, and the server like an oracle', () => {
        expect(differences('round', cases.round, (c) => outcomeOf(() => toCanonical(roundHalfUp(parseExact(c.s, 12, true, 'x'), c.scale)))).slice(0, 10)).toEqual([]);

        const oracle = cases.round.flatMap((c, index) => {
            const decimals = (c.s.split('.')[1] ?? '').length;
            const want = render(halfUpDivide(scaled(c.s, decimals), 10n ** BigInt(decimals - c.scale)), c.scale);

            return expected.round?.[index]?.ok === want ? [] : [{ input: c, server: expected.round?.[index], oracle: want }];
        });

        expect(oracle.slice(0, 10)).toEqual([]);
    });

    it('rounds round6(V × q / Q) once, half-up, on the server like an independent oracle (ADR-008 section 5)', () => {
        const oracle = cases.mulDiv.flatMap((c, index) => {
            const want = render(halfUpDivide(scaled(c.v, 6) * scaled(c.q, c.p), scaled(c.Q, c.p)), 6);

            return expected.mulDiv?.[index]?.ok === want ? [] : [{ input: c, server: expected.mulDiv?.[index], oracle: want }];
        });

        expect(oracle.slice(0, 10)).toEqual([]);
    });

    it('replaces lower-case placeholders exactly like Laravel', () => {
        expect(differences('placeholders', cases.placeholders, (c) => outcomeOf(() => replacePlaceholders(c.text, c.replacements))).slice(0, 10)).toEqual([]);
    });

    it.each(formats)('%s gives the server text, or refuses with it', (_category, run) => {
        expect(run().slice(0, 10)).toEqual([]);
    });

    it('business dates agree on every year from 0001 to 9999, and instants before 1964 are refused on both sides (A-05, A-14)', () => {
        const early = cases.dates.filter((date) => date >= '0001' && date < '0100');
        const beforeScope = cases.instants.filter((instant) => instant < '1963-12-31T17:00:00');

        expect(early.length).toBeGreaterThan(0);
        expect(beforeScope.length).toBeGreaterThan(0);
        expect(early.every((date) => formatDate(date).endsWith(date.slice(0, 4)))).toBe(true);
        expect(beforeScope.every((instant) => outcomeOf(() => formatDateTime(instant)).err !== undefined)).toBe(true);
        expect(beforeScope.every((instant) => expected.formatDateTime?.[cases.instants.indexOf(instant)]?.err !== undefined)).toBe(true);
    });

    it.each(['America/St_Johns', 'Pacific/Chatham', 'Asia/Kathmandu', 'Etc/GMT+12'])('formats the same in %s, without Intl or toLocaleString', (zone) => {
        vi.stubEnv('TZ', zone);
        vi.stubEnv('LANG', 'ar_EG.UTF-8');
        vi.stubEnv('LC_ALL', 'th_TH.UTF-8');
        vi.stubGlobal('Intl', new Proxy({}, { get: () => throwing('Intl') }));
        vi.stubGlobal('navigator', new Proxy({}, { get: () => throwing('navigator') }));
        vi.spyOn(Date.prototype, 'toLocaleString').mockImplementation(() => throwing('Date.toLocaleString'));
        vi.spyOn(Number.prototype, 'toLocaleString').mockImplementation(() => throwing('Number.toLocaleString'));
        vi.spyOn(BigInt.prototype, 'toLocaleString').mockImplementation(() => throwing('BigInt.toLocaleString'));

        const found = formats.flatMap(([, run]) => run());

        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        expect(found.slice(0, 10)).toEqual([]);
    });
});
