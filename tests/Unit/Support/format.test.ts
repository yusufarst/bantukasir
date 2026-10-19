import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
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
import { installDictionary } from '@/lib/i18n';
import table from './cases/formatting.json';

// The client's run of the one table of formatter cases (COPY_DECK sections
// 1.3 and 1.4, AC-003-04): every case, then every case again with the process
// in other time zones and with every source of locale data — Intl, the
// toLocale methods and navigator — made to throw if it is read.
// tests/Unit/Support/Formatting/FormatCasesTest.php runs the same table on the
// server.
type Case = (typeof table.cases)[number];

function format(entry: Case): string | null {
    const input = entry.input;

    try {
        switch (entry.format) {
            case 'money':
                return formatMoney(String(input));
            case 'quantity':
                return formatQuantity(String(input), entry.precision ?? -1, entry.unit);
            case 'cost':
                return formatCost(String(input));
            case 'date':
                return formatDate(String(input));
            case 'dateTime':
                return formatDateTime(String(input));
            case 'timeOfDay':
                return formatTimeOfDay(String(input));
            case 'dayName':
                return formatDayName(String(input));
            case 'count':
                return formatCount(typeof input === 'number' ? input : -1);
            case 'duration':
                // The table also holds amounts that are not integers and
                // unknown units, which the formatter refuses at run time.
                return formatDuration(input as unknown as number, (entry.unit ?? '') as DurationUnit);
            case 'variance':
                return formatVariance(String(input));
            default:
                throw new TypeError(`Unknown format ${entry.format}`);
        }
    } catch (error) {
        if (error instanceof TypeError) {
            throw error;
        }

        return null;
    }
}

function throwing(name: string): never {
    throw new Error(`${name} was read: formatting must not depend on locale data.`);
}

beforeAll(() => {
    installDictionary(table.dictionary);
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
});

describe('the formats of COPY_DECK sections 1.3 and 1.4', () => {
    it.each(table.cases.map((entry) => [`${entry.format} ${JSON.stringify(entry.input)}`, entry] as const))('%s', (_name, entry) => {
        expect(format(entry)).toBe(entry.expected);
    });
});

describe('the formats ignore the time zone and the locale data of the browser', () => {
    it.each(['America/Los_Angeles', 'Pacific/Kiritimati', 'Asia/Tokyo', 'UTC'])('in %s, without Intl or toLocaleString', (zone) => {
        vi.stubEnv('TZ', zone);
        vi.stubEnv('LANG', 'en_US.UTF-8');
        vi.stubEnv('LC_ALL', 'en_US.UTF-8');

        // The process now runs in the other zone.
        expect(new Date(Date.UTC(2026, 0, 1)).getTimezoneOffset()).toBe(zone === 'America/Los_Angeles' ? 480 : zone === 'Pacific/Kiritimati' ? -840 : zone === 'Asia/Tokyo' ? -540 : 0);

        const results = (() => {
            vi.stubGlobal('Intl', new Proxy({}, { get: () => throwing('Intl') }));
            vi.stubGlobal('navigator', new Proxy({}, { get: () => throwing('navigator') }));
            vi.spyOn(Date.prototype, 'toLocaleString').mockImplementation(() => throwing('Date.toLocaleString'));
            vi.spyOn(Date.prototype, 'toLocaleDateString').mockImplementation(() => throwing('Date.toLocaleDateString'));
            vi.spyOn(Date.prototype, 'toLocaleTimeString').mockImplementation(() => throwing('Date.toLocaleTimeString'));
            vi.spyOn(Number.prototype, 'toLocaleString').mockImplementation(() => throwing('Number.toLocaleString'));
            vi.spyOn(BigInt.prototype, 'toLocaleString').mockImplementation(() => throwing('BigInt.toLocaleString'));

            try {
                return table.cases.map((entry) => format(entry));
            } finally {
                vi.unstubAllGlobals();
                vi.restoreAllMocks();
            }
        })();

        expect(results).toEqual(table.cases.map((entry) => entry.expected));
    });
});
