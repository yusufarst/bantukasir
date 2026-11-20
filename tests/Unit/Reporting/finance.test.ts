import { afterEach, describe, expect, it, vi } from 'vitest';
import { daysBetween } from '@/lib/format';
import { financeAddress, MAX_DAYS, PERIODS, rangeErrors, rememberedAddress, rememberPeriod } from '@/lib/reporting/finance';

// The period choice of UXP-502 (AC-040-02; INTERACTION_CONTRACTS section 3.4; TECHNICAL_RULES section 11.4;
// BR-COST-37; COPY_DECK section 10.3; NAVIGATION_CONTRACTS UXP-502; TASK-040 section 7, L4 period validation):
// the five periods in their order; the address of each; a custom range refused at its date fields — its start
// after its end, its end after today, longer than 366 days, a date missing — so that nothing is fetched; and the
// applied period kept for the sign-in session, never anything but RP-502's parameters.

afterEach(() => {
    vi.unstubAllGlobals();
});

/** A session storage of one page, as the browser keeps it. */
function storage(): Storage {
    const values = new Map<string, string>();

    return {
        get length() {
            return values.size;
        },
        clear: () => values.clear(),
        getItem: (key) => values.get(key) ?? null,
        key: () => null,
        removeItem: (key) => void values.delete(key),
        setItem: (key, value) => void values.set(key, value),
    };
}

describe('the periods of the finance report', () => {
    it('are the five of COPY_DECK section 10.3, in its order, with the values of RP-502', () => {
        expect(PERIODS.map((entry) => [entry.kind, entry.label])).toEqual([
            ['today', 'reporting.period.today'],
            ['7d', 'reporting.period.last_7_days'],
            ['this_month', 'reporting.period.this_month'],
            ['last_month', 'reporting.period.last_month'],
            ['custom', 'reporting.period.custom'],
        ]);
    });

    it('each has its address; a custom one carries its dates', () => {
        expect(financeAddress({ kind: 'today', from: null, to: null })).toBe('/reports/finance?period=today');
        expect(financeAddress({ kind: '7d', from: null, to: null })).toBe('/reports/finance?period=7d');
        expect(financeAddress({ kind: 'last_month', from: null, to: null })).toBe('/reports/finance?period=last_month');
        expect(financeAddress({ kind: 'custom', from: '2026-10-01', to: '2026-10-17' })).toBe('/reports/finance?period=custom&from=2026-10-01&to=2026-10-17');
    });
});

describe('a custom range at its date fields', () => {
    const today = '2026-11-21';

    it('passes within 366 days, ending today at the latest, a single day included', () => {
        expect(rangeErrors('2026-11-01', '2026-11-21', today)).toEqual({});
        expect(rangeErrors('2026-11-21', '2026-11-21', today)).toEqual({});
        expect(rangeErrors('2025-11-21', '2026-11-21', today)).toEqual({});
        expect(daysBetween('2025-11-21', '2026-11-21') + 1).toBe(MAX_DAYS);
    });

    it('refuses a start after its end at the start date', () => {
        expect(rangeErrors('2026-11-10', '2026-11-09', today)).toEqual({ from: 'ux.validation.date_order' });
    });

    it('refuses an end after today at the end date', () => {
        expect(rangeErrors('2026-11-01', '2026-11-22', today)).toEqual({ to: 'ux.state.validation.title' });
    });

    it('refuses more than 366 days at the end date, the leap day counted', () => {
        expect(rangeErrors('2025-11-20', '2026-11-21', today)).toEqual({ to: 'ux.validation.date_range_max' });
        expect(rangeErrors('2028-01-01', '2028-12-31', '2029-01-01')).toEqual({});
        expect(rangeErrors('2027-12-31', '2028-12-31', '2029-01-01')).toEqual({ to: 'ux.validation.date_range_max' });
    });

    it('refuses a date missing, or one that does not exist, at its field', () => {
        expect(rangeErrors('', '2026-11-21', today)).toEqual({ from: 'ux.validation.required' });
        expect(rangeErrors('2026-11-01', '', today)).toEqual({ to: 'ux.validation.required' });
        expect(rangeErrors('', '', today)).toEqual({ from: 'ux.validation.required', to: 'ux.validation.required' });
        expect(rangeErrors('2026-02-30', '2026-03-01', today)).toEqual({ from: 'ux.validation.required' });
    });
});

describe('the applied period kept for the sign-in session', () => {
    it('is kept and read back as its address', () => {
        vi.stubGlobal('window', { sessionStorage: storage() });

        expect(rememberedAddress()).toBeNull();
        rememberPeriod({ kind: '7d', from: null, to: null });
        expect(rememberedAddress()).toBe('/reports/finance?period=7d');
        rememberPeriod({ kind: 'custom', from: '2026-10-01', to: '2026-10-17' });
        expect(rememberedAddress()).toBe('/reports/finance?period=custom&from=2026-10-01&to=2026-10-17');
    });

    it('is never anything but the parameters of RP-502', () => {
        const kept = storage();

        vi.stubGlobal('window', { sessionStorage: kept });
        kept.setItem('bk.reporting.finance.period', '?period=today&x=1');
        expect(rememberedAddress()).toBeNull();
        kept.setItem('bk.reporting.finance.period', '//elsewhere.test/?period=today');
        expect(rememberedAddress()).toBeNull();
    });

    it('is no address without the storage', () => {
        vi.stubGlobal('window', {
            get sessionStorage(): Storage {
                throw new Error('storage refused');
            },
        });

        expect(rememberedAddress()).toBeNull();
        expect(() => rememberPeriod({ kind: 'today', from: null, to: null })).not.toThrow();
    });
});
