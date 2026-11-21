import { addDays } from '@/lib/format';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';
import type { FinanceFigures } from '@/lib/reporting/finance';
import type { TranslationKey } from '@/types/translation-keys';

// Rekap harian, UXP-501 (ROUTE_CONTRACTS RP-501; INTERACTION_CONTRACTS section 3.4; COPY_DECK section 10.2;
// INTERACTION_INVENTORY UXI-306 and UXI-307; NAVIGATION_CONTRACTS UXP-501; SALES_CASH BR-RPT-01 to BR-RPT-04;
// TASK-041): the address of a date, the day before and after, the check of a chosen date at its field before
// anything is fetched, and the summary as the server reads it (App\Queries\Reporting\DailySummary). The client
// computes no figure: it shows the server's.

/** RP-501. */
export const DAILY_PAGE = '/reports/daily';

/** The prop of the summary, reloaded by name with "Coba lagi" (Reporting\DailyController::SUMMARY). */
export const SUMMARY_PROP = 'summary';

/** Where the summary stands in the shell: the Laporan destination's first item (INFORMATION_ARCHITECTURE section 4). */
export const DAILY_SHELL = { current: 'reports', area: 'reports', subCurrent: 'daily' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** A session of the summary (UXI-307), listed once with its own variance; null fields while it is OPEN. */
export interface DailySession {
    readonly id: number;
    readonly number: string;
    readonly status: string;
    readonly registerCode: string;
    readonly registerLabel: string;
    readonly responsible: string;
    readonly openedAt: string;
    readonly closedAt: string | null;
    readonly variance: string | null;
    readonly crossesMidnight: boolean;
}

export interface DailySummaryRead {
    readonly asOf: string;
    readonly date: string;
    readonly figures: FinanceFigures;
    readonly completeness: { readonly complete: boolean; readonly unknownLines: number; readonly markedProducts: number; readonly lastCorrectionAt: string | null };
    readonly empty: boolean;
    /** The sessions closed on the date, with their full span. */
    readonly sessions: readonly DailySession[];
    /** The sessions still OPEN, opened before the end of the date. */
    readonly active: readonly DailySession[];
}

/** The address of a date's summary; the date stands in the address, so that it is kept on the way back (UXP-501). */
export function dailyAddress(date: string): string {
    return `${DAILY_PAGE}?${new URLSearchParams({ date }).toString()}`;
}

/** The day before a date. */
export function previousDay(date: string): string {
    return addDays(date, -1);
}

/** The day after a date, or null when that is after today: the summary of a later day is refused (RP-501). */
export function nextDay(date: string, today: string): string | null {
    const next = addDays(date, 1);

    return next > today ? null : next;
}

const DATE = /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/;

/**
 * The message of a chosen date at its field, none when it may be fetched (INTERACTION_CONTRACTS section 3.4): a
 * date is required and may not be after today in Asia/Jakarta at the request. COPY_DECK has no text of its own
 * for a date after today, so the field says "Periksa isian", the title of UXS-05, as UXP-502's end date does
 * (OPEN_ITEMS T-52).
 */
export function dateError(value: string, today: string): TranslationKey | null {
    if (!DATE.test(value)) {
        return 'ux.validation.required';
    }

    try {
        addDays(value, 0);
    } catch {
        return 'ux.validation.required';
    }

    return value > today ? 'ux.state.validation.title' : null;
}
