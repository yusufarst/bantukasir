import { beforeAll, describe, expect, it } from 'vitest';
import { installDictionary } from '@/lib/i18n';
import { formatSize, itemAge, itemValue, ITEMS, type StatusItem } from '@/lib/operations/status';

// The words of UXP-611 (AC-039-01; COPY_DECK section 11.11; OPERATIONS_RUNBOOK section 5; TASK-039): the
// items in their order; a size in decimal units; each value as the server judged its item, the age in
// minutes; and nothing to show for an item without its evidence.

const ITEM: StatusItem = { state: 'OK', at: null, ageMinutes: null, bytes: null, totalBytes: null };

beforeAll(() => {
    installDictionary({
        'ux.format.month.november': 'November',
        'ux.format.duration.minute': 'menit',
        'config.system_status.ok_at': 'Berhasil :waktu',
        'config.system_status.failed_at': 'Gagal :waktu',
        'config.system_status.age': ':durasi yang lalu',
        'config.system_status.disk_free': 'Tersisa :ukuran dari :total',
        'config.system_status.backup_store_usage': 'Terpakai :ukuran dari :total',
    });
});

describe('the items of the system status', () => {
    it('are the five of COPY_DECK section 11.11, in its order', () => {
        expect(ITEMS.map((item) => [item.id, item.label])).toEqual([
            ['backup', 'config.system_status.backup'],
            ['restore', 'config.system_status.restore'],
            ['disk', 'config.system_status.disk'],
            ['reconciliation', 'config.system_status.reconciliation'],
            ['backupStore', 'config.system_status.backup_store'],
        ]);
    });
});

describe('a size', () => {
    it('is written in GB from a gigabyte on and in MB below, with one decimal when it is not zero', () => {
        expect(formatSize(10_000_000_000)).toBe('10 GB');
        expect(formatSize(12_345_000_000)).toBe('12,3 GB');
        expect(formatSize(12_350_000_000)).toBe('12,4 GB');
        expect(formatSize(1_234_000_000_000)).toBe('1.234 GB');
        expect(formatSize(1_000_000_000)).toBe('1 GB');
        expect(formatSize(999_999_999)).toBe('1.000 MB');
        expect(formatSize(850_000_000)).toBe('850 MB');
        expect(formatSize(0)).toBe('0 MB');
    });

    it('is refused when it is not a whole, safe number of bytes', () => {
        for (const bytes of [-1, 1.5, Number.MAX_SAFE_INTEGER + 1]) {
            expect(() => formatSize(bytes)).toThrow(RangeError);
        }
    });
});

describe('the value of an item', () => {
    it('says when its evidence succeeded or failed, in Asia/Jakarta, and how long ago in minutes', () => {
        expect(itemValue('backup', { ...ITEM, at: '2026-11-22T04:00:00.000000Z', ageMinutes: 60 })).toBe('Berhasil 22 November 2026, 11.00.00');
        expect(itemValue('reconciliation', { ...ITEM, state: 'FAILED', at: '2026-11-22T04:00:00.000000Z' })).toBe('Gagal 22 November 2026, 11.00.00');
        expect(itemAge({ ...ITEM, ageMinutes: 1560 })).toBe('1.560 menit yang lalu');
        expect(itemAge({ ...ITEM, ageMinutes: 0 })).toBe('0 menit yang lalu');
    });

    it('gives the free and the used space with their totals', () => {
        expect(itemValue('disk', { ...ITEM, bytes: 30_000_000_000, totalBytes: 100_000_000_000 })).toBe('Tersisa 30 GB dari 100 GB');
        expect(itemValue('backupStore', { ...ITEM, state: 'FAILED', bytes: 8_000_000_000, totalBytes: 10_000_000_000 })).toBe('Terpakai 8 GB dari 10 GB');
    });

    it('has nothing to show without its evidence, and no age without one', () => {
        expect(itemValue('backup', ITEM)).toBeNull();
        expect(itemValue('disk', { ...ITEM, bytes: 1 })).toBeNull();
        expect(itemValue('backupStore', { ...ITEM, totalBytes: 1 })).toBeNull();
        expect(itemAge(ITEM)).toBeNull();
    });
});
