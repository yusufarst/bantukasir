import { describe, expect, it } from 'vitest';
import { addDays } from '@/lib/format';
import { dailyAddress, dateError, nextDay, previousDay } from '@/lib/reporting/daily';

// The date control of Rekap harian (AC-041-02; INTERACTION_CONTRACTS section 3.4; INTERACTION_INVENTORY UXI-306;
// NAVIGATION_CONTRACTS UXP-501; SALES_CASH BR-RPT-01; TASK-041 section 7, L4): Asia/Jakarta business dates moved
// one day at a time across months, years and a leap day; no day after today; a picked date refused at its field —
// missing, not in the calendar, after today — so that nothing is fetched; the date kept in the address.

describe('the date control of the daily summary', () => {
    const today = '2026-11-21';

    it('moves one day at a time across a month, a year and a leap day', () => {
        expect(previousDay('2026-11-01')).toBe('2026-10-31');
        expect(previousDay('2027-01-01')).toBe('2026-12-31');
        expect(previousDay('2028-03-01')).toBe('2028-02-29');
        expect(addDays('2028-02-28', 1)).toBe('2028-02-29');
        expect(addDays('2026-02-28', 1)).toBe('2026-03-01');
        expect(addDays('2026-11-21', -365)).toBe('2025-11-21');
    });

    it('offers no day after today', () => {
        expect(nextDay('2026-11-20', today)).toBe(today);
        expect(nextDay(today, today)).toBeNull();
        expect(nextDay('2026-10-31', today)).toBe('2026-11-01');
    });

    it('refuses at the field a date that is missing, not in the calendar or after today, and passes today and earlier', () => {
        expect(dateError(today, today)).toBeNull();
        expect(dateError('2020-02-29', today)).toBeNull();
        expect(dateError('', today)).toBe('ux.validation.required');
        expect(dateError('2026-02-30', today)).toBe('ux.validation.required');
        expect(dateError('21-11-2026', today)).toBe('ux.validation.required');
        expect(dateError('2026-11-22', today)).toBe('ux.state.validation.title');
    });

    it('keeps the date in the address', () => {
        expect(dailyAddress('2026-11-20')).toBe('/reports/daily?date=2026-11-20');
    });

    it('refuses a shift outside the years of a business date, or not by whole days', () => {
        expect(() => addDays('0001-01-01', -1)).toThrow(RangeError);
        expect(() => addDays('9999-12-31', 1)).toThrow(RangeError);
        expect(() => addDays('2026-11-21', 0.5)).toThrow(RangeError);
    });
});
