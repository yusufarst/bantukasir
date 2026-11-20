import { formatCount, formatDateTime, formatDuration } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// The system status of UXP-611 (ROUTE_CONTRACTS RP-611; COPY_DECK section 11.11; OPERATIONS_RUNBOOK
// section 5; UI_STATES UXS-22 and UXS-43; TASK-039): the items as the server judged them
// (App\Queries\Operations\SystemStatus) and the words of each. The client decides no state: it shows the
// server's, and an item the server could not prove is "Belum Terverifikasi", never a passing look.

/** The page that holds the settings (UXI-376). */
export const SETTINGS_PAGE = '/settings';

/** The reconciliation incidents (UXI-375; RP-414). */
export const INCIDENTS_PAGE = '/stock/incidents';

/** The prop of the status, reloaded by name (UXI-374; Operations\StatusController::STATUS). */
export const STATUS_PROP = 'status';

export type ItemState = 'OK' | 'FAILED' | 'UNVERIFIED';

export interface StatusItem {
    readonly state: ItemState;
    /** The instant of the evidence: the run or drill that the state names. */
    readonly at: string | null;
    readonly ageMinutes: number | null;
    /** Free bytes of the file volume, or the store's used bytes. */
    readonly bytes: number | null;
    readonly totalBytes: number | null;
}

export type ItemId = 'backup' | 'restore' | 'disk' | 'reconciliation' | 'backupStore';

export type SystemStatusRead = Readonly<Record<ItemId, StatusItem>> & { readonly incidentsOpen: boolean };

/** The items in the order of COPY_DECK section 11.11, with their names. */
export const ITEMS: readonly { readonly id: ItemId; readonly label: TranslationKey }[] = [
    { id: 'backup', label: 'config.system_status.backup' },
    { id: 'restore', label: 'config.system_status.restore' },
    { id: 'disk', label: 'config.system_status.disk' },
    { id: 'reconciliation', label: 'config.system_status.reconciliation' },
    { id: 'backupStore', label: 'config.system_status.backup_store' },
];

const GIGABYTE = 1_000_000_000;

const MEGABYTE = 1_000_000;

/**
 * A size in decimal units, with one decimal rounded half up and dropped when it is zero: "12,5 GB",
 * "10 GB", "850 MB". The unit symbols are SI symbols, not words, as "Rp" is (resources/js/lib/format.ts).
 */
export function formatSize(bytes: number): string {
    if (!Number.isSafeInteger(bytes) || bytes < 0) {
        throw new RangeError('A size is a whole number of bytes.');
    }

    const unit = bytes >= GIGABYTE ? GIGABYTE : MEGABYTE;
    const symbol = unit === GIGABYTE ? 'GB' : 'MB';
    const tenths = Math.floor((bytes * 10 + unit / 2) / unit);
    const whole = Math.floor(tenths / 10);
    const decimal = tenths % 10;

    return `${formatCount(whole)}${decimal === 0 ? '' : `,${decimal.toString()}`} ${symbol}`;
}

/** The value of an item that is not UNVERIFIED, in the words of COPY_DECK section 11.11. */
export function itemValue(id: ItemId, item: StatusItem): string | null {
    if (id === 'disk' || id === 'backupStore') {
        if (item.bytes === null || item.totalBytes === null) {
            return null;
        }

        const sizes = { ukuran: formatSize(item.bytes), total: formatSize(item.totalBytes) };

        return id === 'disk' ? t('config.system_status.disk_free', sizes) : t('config.system_status.backup_store_usage', sizes);
    }

    if (item.at === null) {
        return null;
    }

    return item.state === 'FAILED' ? t('config.system_status.failed_at', { waktu: formatDateTime(item.at) }) : t('config.system_status.ok_at', { waktu: formatDateTime(item.at) });
}

/**
 * ":durasi yang lalu" in minutes, the largest unit COPY_DECK section 1.3 has (CC-21; COPY_DECK section 22.2
 * leaves a larger unit to its change control, OPEN_ITEMS T-25).
 */
export function itemAge(item: StatusItem): string | null {
    return item.ageMinutes === null ? null : t('config.system_status.age', { durasi: formatDuration(item.ageMinutes, 'minute') });
}
