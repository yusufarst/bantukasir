import { daysBetween } from '@/lib/format';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';
import type { TranslationKey } from '@/types/translation-keys';

// The finance report of UXP-502 (ROUTE_CONTRACTS RP-502; INTERACTION_CONTRACTS section 3.4; COPY_DECK
// section 10.3; NAVIGATION_CONTRACTS UXP-502; TECHNICAL_RULES section 11.4; BR-COST-37; TASK-040): the
// periods, the address of each, the checks of a custom range at its date fields before anything is
// fetched, the applied period kept for the sign-in session, and the report as the server reads it
// (App\Queries\Reporting\FinanceReport). The client computes no figure: it shows the server's.

/** RP-502. */
export const FINANCE_PAGE = '/reports/finance';

/** The prop of the report, reloaded by name with "Coba lagi" (Reporting\FinanceController::REPORT). */
export const REPORT_PROP = 'report';

/** Where the report stands in the shell: the Laporan destination's sub-navigation (INFORMATION_ARCHITECTURE section 4). */
export const FINANCE_SHELL = { current: 'reports', area: 'reports', subCurrent: 'finance' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** The longest custom range, in days, both dates included (TECHNICAL_RULES section 11.4). */
export const MAX_DAYS = 366;

export type PeriodKind = 'today' | '7d' | 'this_month' | 'last_month' | 'custom';

/** The period choices of UXI-308 and UXI-309, in the order of COPY_DECK section 10.3. */
export const PERIODS: readonly { readonly kind: PeriodKind; readonly label: TranslationKey }[] = [
    { kind: 'today', label: 'reporting.period.today' },
    { kind: '7d', label: 'reporting.period.last_7_days' },
    { kind: 'this_month', label: 'reporting.period.this_month' },
    { kind: 'last_month', label: 'reporting.period.last_month' },
    { kind: 'custom', label: 'reporting.period.custom' },
];

/** The period the page shows: a custom one with its dates, inclusive. */
export interface AppliedPeriod {
    readonly kind: PeriodKind;
    readonly from: string | null;
    readonly to: string | null;
}

export interface FinanceFigures {
    readonly grossSales: string;
    readonly refunds: string;
    readonly netSales: string;
    readonly saleCount: number;
    readonly paymentsCash: string;
    readonly paymentsTransfer: string;
    readonly refundPayoutsCash: string;
    readonly refundPayoutsTransfer: string;
    readonly quantitySold: string;
    readonly quantityReturned: string;
    /** Cost of goods sold, a cost figure of six decimals; incomplete while `complete` is false. */
    readonly cogs: string;
    readonly grossProfit: string;
    /** In percent, one decimal; null unless cost is complete and net sales are above zero. */
    readonly margin: string | null;
}

export interface FinanceReportRead {
    readonly asOf: string;
    readonly period: { readonly kind: PeriodKind; readonly from: string; readonly to: string };
    readonly figures: FinanceFigures;
    readonly completeness: { readonly complete: boolean; readonly unknownLines: number; readonly markedProducts: number; readonly lastCorrectionAt: string | null };
    readonly trend: readonly { readonly date: string; readonly netSales: string }[];
    readonly empty: boolean;
}

/** The address of a period: the period, and a custom one's dates. */
export function financeAddress(period: AppliedPeriod): string {
    const query = new URLSearchParams({ period: period.kind });

    if (period.kind === 'custom' && period.from !== null && period.to !== null) {
        query.set('from', period.from);
        query.set('to', period.to);
    }

    return `${FINANCE_PAGE}?${query.toString()}`;
}

/** The messages of a custom range's fields, none when it may be fetched. */
export interface RangeErrors {
    readonly from?: TranslationKey;
    readonly to?: TranslationKey;
}

const DATE = /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/;

/**
 * The checks of a custom range at its date fields (INTERACTION_CONTRACTS section 3.4; TECHNICAL_RULES
 * section 11.4), against today in Asia/Jakarta at the request: both dates required; the start not after
 * the end; the end not after today; at most 366 days, both dates included. A range with any message is
 * never fetched. COPY_DECK has no text of its own for an end after today, so that field says "Periksa
 * isian", the title of UXS-05 (TASK-040 evidence).
 */
export function rangeErrors(from: string, to: string, today: string): RangeErrors {
    const errors: { from?: TranslationKey; to?: TranslationKey } = {};

    if (!isDate(from)) {
        errors.from = 'ux.validation.required';
    }

    if (!isDate(to)) {
        errors.to = 'ux.validation.required';
    }

    if (errors.from !== undefined || errors.to !== undefined) {
        return errors;
    }

    if (from > to) {
        return { from: 'ux.validation.date_order' };
    }

    if (to > today) {
        return { to: 'ux.state.validation.title' };
    }

    return daysBetween(from, to) + 1 > MAX_DAYS ? { to: 'ux.validation.date_range_max' } : {};
}

/** Whether a field holds a date that exists, as the date control gives it. */
function isDate(value: string): boolean {
    if (!DATE.test(value)) {
        return false;
    }

    try {
        daysBetween(value, value);

        return true;
    } catch {
        return false;
    }
}

/** Where UXP-502 keeps its applied period for the sign-in session (NAVIGATION_CONTRACTS UXP-502): an interface preference, cleared at sign-out (SEC-CH-05). */
const PERIOD_KEY = 'bk.reporting.finance.period';

/** What the kept period may be: the parameters of RP-502, nothing else. */
const PERIOD_QUERY = /^\?period=(?:today|7d|this_month|last_month|custom&from=[0-9]{4}-[0-9]{2}-[0-9]{2}&to=[0-9]{4}-[0-9]{2}-[0-9]{2})$/;

/** Keeps the period the page shows. */
export function rememberPeriod(period: AppliedPeriod): void {
    const search = financeAddress(period).slice(FINANCE_PAGE.length);

    try {
        window.sessionStorage.setItem(PERIOD_KEY, PERIOD_QUERY.test(search) ? search : '');
    } catch {
        // Without the storage, the page simply opens on Bulan Ini.
    }
}

/** The address of the period kept for the sign-in session, or null without one. */
export function rememberedAddress(): string | null {
    try {
        const search = window.sessionStorage.getItem(PERIOD_KEY) ?? '';

        return PERIOD_QUERY.test(search) ? `${FINANCE_PAGE}${search}` : null;
    } catch {
        return null;
    }
}
