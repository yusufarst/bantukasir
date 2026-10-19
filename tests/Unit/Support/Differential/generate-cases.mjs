// The deterministic generator of the differential cases of TASK-003 (the
// class-A adversarial review's differential run, adopted): random and crafted
// inputs that the server (tests/Unit/Support/Differential/DifferentialCasesTest.php)
// and the client (tests/Unit/Support/differential.test.ts) both run, each
// against the expected outcomes that tests/Unit/Support/Differential/expected.php
// computes once with the server kernel and writes into the same file. Nothing
// is written while the tests run, and neither run depends on the other.
//
// Usage, from the project's root (in the node container):
//   node tests/Unit/Support/Differential/generate-cases.mjs [--scale=0.1] [--out=FILE]
// --scale multiplies the number of random cases: 0.1, the default, gives the
// committed table tests/Unit/Support/cases/differential.json; 1 gives the
// full-size run of the review, which is written outside the tracked tree.
// Then, in the app container: php tests/Unit/Support/Differential/expected.php FILE
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const SEED = 20261020;
const options = Object.fromEntries(
    process.argv.slice(2).map((argument) => {
        const [name, value] = argument.replace(/^--/, '').split('=');

        return [name, value];
    }),
);
const scale = options.scale === undefined ? 0.1 : Number.parseFloat(options.scale);
const out = options.out ?? 'tests/Unit/Support/cases/differential.json';

if (!(scale > 0 && scale <= 1)) {
    throw new RangeError('--scale is above 0 and at most 1.');
}

/** The number of random cases of a category at this scale. */
const sized = (full) => Math.max(1, Math.round(full * scale));

function mulberry32(seed) {
    let a = seed >>> 0;

    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const rand = mulberry32(SEED);
const int = (lo, hi) => lo + Math.floor(rand() * (hi - lo + 1));
const pick = (xs) => xs[int(0, xs.length - 1)];
const digit = () => String(int(0, 9));
const nonzero = () => String(int(1, 9));
const digits = (n) => Array.from({ length: n }, digit).join('');

function integerPart(maxDigits) {
    const r = rand();

    if (r < 0.08) {
        return '0';
    }

    const length = r < 0.3 ? maxDigits : int(1, maxDigits);

    if (length === 1) {
        return nonzero();
    }

    if (rand() < 0.12) {
        return '9'.repeat(length);
    }

    if (rand() < 0.08) {
        return `1${'0'.repeat(length - 1)}`;
    }

    return nonzero() + digits(length - 1);
}

function fractionPart(maxScale) {
    if (maxScale === 0) {
        return '';
    }

    const length = int(0, maxScale);

    if (length === 0) {
        return '';
    }

    const r = rand();

    if (r < 0.12) {
        return `5${'0'.repeat(length - 1)}`;
    }

    if (r < 0.18) {
        return '9'.repeat(length);
    }

    if (r < 0.24) {
        return `${'0'.repeat(length - 1)}1`;
    }

    if (r < 0.3) {
        return `${digits(length - 1)}0`;
    }

    return digits(length);
}

function canonical(maxIntDigits, maxScale, signed, negativeShare = 0.3) {
    const integer = integerPart(maxIntDigits);
    const fraction = fractionPart(maxScale);
    const text = fraction === '' ? integer : `${integer}.${fraction}`;

    return signed && !/^[0.]+$/.test(text) && rand() < negativeShare ? `-${text}` : text;
}

// Every non-canonical form of ADR-006 item 2 that the review named, and more.
const nonCanonical = [
    '', ' ', '-', '+', '.', '-.', '+0', '+1', '+0.5', '-0', '-00', '-0.0', '-0.000', '-0.000000', '00', '01', '007', '-01', '00.5',
    '0.', '1.', '-1.', '.0', '.5', '-.5', '1..5', '1.5.5', '--1', '+-1', '-+1', '1-', '1+',
    '1e3', '1E3', '1e-3', '1e+3', '1.5e3', '0e0', '1e', 'e1', '7.5e4', '1e-6', '0x10', '0X1A', '0b10', '0o17', '1_000', '1,000', '1,5', '1.000,5', "1'000",
    ' 1', '1 ', '1 000', '\t1', '1\t', '\n1', '1\n', '1\r', '1\r\n', '1\u0000', '\u0000', '\u000b1', '1\u000c',
    ' 1', '1 ', '1 000', '1 000', '​1', '1​', '﻿1', '1﻿', '‎1', '　1',
    '１', '１２', '١٢', '۱۲', '१२', '๑๒', '߁', '𝟏𝟐', '¹', '½', 'Ⅻ', '1٫5', '1·5', '1،5',
    '−1', '‐1', '‑1', '–1', '—1', '﹣1', '－1', '−0', '1−',
    'NaN', 'nan', 'Infinity', '-Infinity', 'INF', 'inf', 'null', 'true', 'false', 'undefined', '[]', '{}', '1/2', 'Rp1', 'Rp 1', '1rb', '1k',
    'UNKNOWN', 'unknown', ' UNKNOWN', 'UNKNOWN ', 'Unknown',
    '9'.repeat(40), '9'.repeat(41), `1${'0'.repeat(39)}`, `1${'0'.repeat(40)}`, `-${'9'.repeat(39)}`, `-${'9'.repeat(40)}`,
    `0.${'0'.repeat(37)}1`, `0.${'0'.repeat(38)}1`, `${'9'.repeat(30)}.${'9'.repeat(9)}`, `${'9'.repeat(34)}.123456`, `${'9'.repeat(33)}.123456`,
    '9'.repeat(10000),
];

const parseConfigs = [
    [0, true], [0, false], [1, true], [2, true], [3, true], [3, false], [6, false], [6, true],
];

const parse = [];

for (const s of nonCanonical) {
    for (const [maxScale, signed] of parseConfigs) {
        parse.push({ s, scale: maxScale, signed });
    }
}

for (let i = 0; i < sized(4000); i++) {
    const [maxScale, signed] = pick(parseConfigs);
    parse.push({ s: canonical(int(1, 42), int(0, 8), true, 0.3), scale: maxScale, signed });
}

// Line totals: quantity × unit price, over the whole range, with crafted ties.
const lineTotal = [];

for (let i = 0; i < sized(6000); i++) {
    const p = int(0, 3);
    lineTotal.push({ q: canonical(int(1, 9), p, rand() < 0.05, 1), p, price: canonical(int(1, 13), 0, rand() < 0.05, 1) });
}

for (let i = 0; i < sized(1500); i++) {
    const p = int(1, 3);
    const modulus = 10n ** BigInt(p);
    const half = modulus / 2n;
    const price = BigInt(nonzero() + digits(int(0, 11)));
    let k = BigInt(int(1, 999999));

    for (let step = 0; step < 2000; step++, k++) {
        const rest = (k * price) % modulus;
        const want = i % 3 === 0 ? half : i % 3 === 1 ? half - 1n : half + 1n;

        if (rest === want) {
            break;
        }
    }

    const units = k.toString().padStart(p + 1, '0');
    const q = `${units.slice(0, units.length - p).replace(/^0+(?=\d)/, '')}.${units.slice(units.length - p)}`;
    lineTotal.push({ q, p, price: price.toString() });
}

for (const [q, p, price] of [
    ['1.5', 1, '3333'], ['2.5', 1, '1'], ['0.001', 3, '500'], ['0.001', 3, '499'], ['0.003', 3, '4833'], ['0.495', 3, '1'],
    ['99999999.999', 3, '1'], ['99999999.999', 3, '10000'], ['99999999.999', 3, '10001'], ['1', 0, '999999999999'], ['1', 0, '1000000000000'],
    ['0.5', 1, '1999999999999'], ['0.5', 1, '1999999999998'], ['-1.5', 1, '-3333'], ['-1.5', 1, '3333'], ['1.5', 1, '-3333'], ['0', 0, '5'], ['0.000', 3, '5'],
]) {
    lineTotal.push({ q, p, price });
}

const sums = Array.from({ length: sized(1500) }, () => Array.from({ length: int(0, 6) }, () => canonical(int(1, 14), 0, true, 0.3)));
const pairs = Array.from({ length: sized(2500) }, () => [canonical(int(1, 14), 0, true), canonical(int(1, 14), 0, true)]);

// Rounding: half-up, ties away from zero, at scales 0 to 6.
const round = [];

for (let i = 0; i < sized(5000); i++) {
    const roundScale = int(0, 6);
    const extra = int(1, 6);
    const integer = integerPart(int(1, 16));
    let fraction = digits(roundScale);
    const r = rand();
    fraction += r < 0.25 ? `5${'0'.repeat(extra - 1)}` : r < 0.35 ? `4${'9'.repeat(extra - 1)}` : r < 0.45 ? `5${'0'.repeat(Math.max(0, extra - 2))}1` : digits(extra);
    let s = `${integer}.${fraction}`;
    s = rand() < 0.4 && !/^[0.]+$/.test(s) ? `-${s}` : s;
    round.push({ s, scale: roundScale });
}

const money = [
    '0', '1', '-1', '999', '1000', '-1000', '999999', '1000000', '999999999999', '-999999999999', '1000000000000', '-1000000000000',
    '9'.repeat(39), `-${'9'.repeat(39)}`, '9'.repeat(40), `1${'0'.repeat(39)}`,
    ...Array.from({ length: sized(3000) }, () => canonical(int(1, 39), 0, true, 0.4)),
];

const units = [null, 'kg', 'pcs', '', 'm²', 'Kotak isi 12'];
const quantity = [];

for (let i = 0; i < sized(4000); i++) {
    const p = int(0, 3);
    quantity.push({ v: canonical(int(1, 14), p, true, 0.3), p, unit: pick(units) });
}

for (const [v, p] of [['1250.500', 3], ['1.50', 2], ['1.0', 0], ['0.000', 3], ['99999999.999', 3], ['9999999999.999', 3], ['-0.001', 3], ['1000', 0]]) {
    quantity.push({ v, p, unit: null });
}

const cost = [
    '0', '0.5', '-0.5', '0.499999', '-0.499999', '1.5', '-1.5', '2.5', '-2.5', '11999.5', '-10.5', '-10.499999', '999999999999.999999',
    '999999999999999.999999', '-999999999999999.999999', '0.000001', '-0.000001', '0.4999999', '-0.5000001',
    ...Array.from({ length: sized(4000) }, () => canonical(int(1, 16), 6, true, 0.35)),
];

// Dates: every year from 0001 to 9999 is a business date on both sides.
const isLeap = (y) => (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;
const monthDays = (y, m) => [31, isLeap(y) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1];
const pad = (n, w) => String(n).padStart(w, '0');

function randomYear() {
    const r = rand();

    if (r < 0.15) {
        return int(1, 99);
    }

    if (r < 0.3) {
        return int(100, 1899);
    }

    if (r < 0.5) {
        return int(1900, 1970);
    }

    if (r < 0.85) {
        return int(2026, 2100);
    }

    return int(2101, 9999);
}

const dates = [
    '0001-01-01', '0004-02-29', '0050-06-15', '0099-12-31', '0100-01-01', '0100-03-01', '1900-02-28', '1963-12-31', '1964-01-01', '2026-10-19', '2028-02-29',
    '2100-02-28', '2400-02-29', '9999-12-31', '0000-01-01', '0100-02-29', '2026-02-29', '10000-01-01',
];

for (let i = 0; i < sized(3000); i++) {
    const y = randomYear();
    const m = int(1, 12);
    dates.push(`${pad(y, 4)}-${pad(m, 2)}-${pad(int(1, monthDays(y, m)), 2)}`);
}

const instants = [
    '2026-10-18T16:59:59.999999Z', '2026-10-18T17:00:00.000000Z', '2026-10-18T17:00:00Z', '2026-12-31T16:59:59Z', '2026-12-31T17:00:00Z',
    '2028-02-28T17:00:00Z', '2028-02-29T16:59:59.9Z', '1963-12-31T16:29:59Z', '1963-12-31T16:30:00Z', '1963-12-31T16:59:59.999999Z', '1963-12-31T17:00:00Z',
    '1960-06-01T00:00:00Z', '1944-01-01T00:00:00Z', '1900-01-01T00:00:00Z', '0050-06-15T03:04:05Z', '0099-12-31T16:59:59Z',
    '0100-01-01T00:00:00Z', '9999-12-31T16:59:59Z', '9999-12-31T17:00:00Z',
];

for (let i = 0; i < sized(4000); i++) {
    const y = randomYear();
    const m = int(1, 12);
    const fractionDigits = int(0, 6);
    const fraction = fractionDigits === 0 ? '' : `.${digits(fractionDigits)}`;
    const nearMidnight = rand() < 0.2;
    const hh = nearMidnight ? pick([16, 17]) : int(0, 23);
    const mm = nearMidnight ? pick([0, 59]) : int(0, 59);
    const ss = nearMidnight ? pick([0, 59]) : int(0, 59);
    instants.push(`${pad(y, 4)}-${pad(m, 2)}-${pad(int(1, monthDays(y, m)), 2)}T${pad(hh, 2)}:${pad(mm, 2)}:${pad(ss, 2)}${fraction}Z`);
}

const counts = ['0', '1', '999', '1000', '5000', '1234567', '9007199254740991', '9007199254740992', '9223372036854775807'];

for (let i = 0; i < sized(1500); i++) {
    counts.push(BigInt.asUintN(53, BigInt(int(0, 2 ** 30)) * BigInt(int(0, 2 ** 23)) + BigInt(int(0, 1000))).toString());
}

// Durations: an amount in minutes or seconds (COPY_DECK section 1.3, CC-21).
const durations = [
    ['0', 'minute'], ['1', 'minute'], ['60', 'minute'], ['1500', 'minute'], ['0', 'second'], ['1', 'second'], ['60', 'second'], ['1500', 'second'],
    ['30', 'second'], ['9223372036854775807', 'second'], ['60', 'hour'], ['60', 'minutes'], ['60', ''],
];

for (let i = 0; i < sized(1500); i++) {
    durations.push([String(int(0, 2 ** 31)), pick(['minute', 'second'])]);
}

// Cost arithmetic: round6(V × q / Q) (ADR-008 section 5), checked against an oracle.
const mulDiv = [];

for (let i = 0; i < sized(4000); i++) {
    const p = int(0, 3);
    const v = canonical(int(1, 15), 6, false);
    let Q = canonical(int(1, 8), p, false);

    if (/^[0.]+$/.test(Q)) {
        Q = '1';
    }

    let q = canonical(int(1, 8), p, false);

    if (rand() < 0.1) {
        q = Q;
    }

    mulDiv.push({ v, q, Q, p });
}

// Placeholders: Laravel's makeReplacements against the client's replacement.
// lang/id holds only lower-case placeholders, which the translation-types
// check enforces (TASK-003 finding A-15), so the texts and names here are
// those; the values may be anything.
const alphabet = [':', ':', ':', 'a', 'b', 'n', '_', ' ', '1', 'é', 'ß', 'ǆ', '𐐨', '.', '(', ')', 'R', 'p'];
const names = ['a', 'ab', 'a_b', 'b', 'n1', 'nama', 'jumlah', 'jumlah_baris'];
const values = ['', 'v', 'ß', 'élan', ':a', ':A', 'X y', 'ǆz', '𐐨x', 'ŉ', 'ﬁ', 'İ', 'i̇', '−Rp2.000', '$1', '\\'];
const placeholders = [];

for (let i = 0; i < sized(4000); i++) {
    const text = Array.from({ length: int(0, 24) }, () => pick(alphabet)).join('');
    const replacements = {};

    for (let n = int(1, 3); n > 0; n--) {
        replacements[pick(names)] = pick(values);
    }

    placeholders.push({ text, replacements });
}

const cases = { parse, lineTotal, sums, pairs, round, money, quantity, cost, dates, instants, counts, durations, mulDiv, placeholders };

mkdirSync(dirname(out), { recursive: true });
writeFileSync(
    out,
    `${JSON.stringify(
        {
            description:
                'The differential cases of TASK-003, generated by tests/Unit/Support/Differential/generate-cases.mjs with a fixed seed; the expected outcomes are the server kernel\'s, written once by tests/Unit/Support/Differential/expected.php. DifferentialCasesTest runs them on the server and differential.test.ts on the client; neither writes a file.',
            seed: SEED,
            scale,
            cases,
        },
        null,
        0,
    )}\n`,
);

console.log(`${out}: scale ${scale}; ${Object.entries(cases).map(([name, list]) => `${name} ${list.length}`).join(', ')}`);
