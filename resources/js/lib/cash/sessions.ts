import { subtractMoney } from '@/lib/exact';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';

// The close and the session pages (COPY_DECK sections 7.5, 7.10, 7.11 and 17.2; TASK-030): the
// session's figures as the server sends them, RC-03 with its family (INTERACTION_CONTRACTS section
// 1.4), the paths of RP-105, RP-205 and RP-206, the shell of each, and the words of a session's state.

/**
 * The running figures of an OPEN session, or its report's once CLOSED (BR-CASH-12, BR-CASH-20):
 * every amount a canonical string of whole rupiah, never a cost.
 */
export interface SessionFigures {
    readonly openingCash: string;
    readonly saleCount: number;
    readonly saleValue: string;
    readonly cashPayments: string;
    readonly transferPayments: string;
    readonly drawerRefunds: string;
    readonly expectedCash: string;
}

/** The close preview of RP-105, a deferred prop; null once the session is no longer OPEN. */
export interface ClosePreview {
    readonly sessionId: number;
    readonly activityVersion: number;
    readonly figures: SessionFigures;
    /** The user's checkout receipts that the server holds PENDING or HELD (G-27). */
    readonly unresolvedCheckouts: number;
}

/** A row of RP-205 (UXI-137): a CLOSED session's register as its report froze it, its close time and its variance. */
export interface SessionRow {
    readonly id: number;
    readonly number: string;
    readonly status: string;
    readonly registerCode: string;
    readonly registerLabel: string;
    readonly responsible: string;
    readonly openedAt: string;
    readonly closedAt: string | null;
    readonly variance: string | null;
}

/** The filters of RP-205 (UXI-135, UXI-136), as the page read them; `register` and `responsible` the Owner's; the page's size. */
export interface SessionFilters {
    readonly status: string | null;
    readonly from: string | null;
    readonly to: string | null;
    readonly register: number | null;
    readonly responsible: number | null;
    readonly perPage: number;
}

/** A page of a keyset list (TR-PAGE-02). */
export interface SessionPage {
    readonly rows: readonly SessionRow[];
    readonly nextCursor: string | null;
    readonly previousCursor: string | null;
}

/** The filters as the page changes them: a register or a person by the text of its key, none as null; the page's size. */
export interface FilterChoice {
    readonly status: string | null;
    readonly from: string | null;
    readonly to: string | null;
    readonly register: string | null;
    readonly responsible: string | null;
    readonly perPage: number;
}

/** RP-205's page size when the address names none (ROUTE_CONTRACTS RP-205). */
export const SESSIONS_PER_PAGE = 25;

/** The filters that the page read, as the page changes them. */
export function choiceOf(filters: SessionFilters): FilterChoice {
    return {
        status: filters.status,
        from: filters.from,
        to: filters.to,
        register: filters.register === null ? null : String(filters.register),
        responsible: filters.responsible === null ? null : String(filters.responsible),
        perPage: filters.perPage,
    };
}

/**
 * The address of RP-205 with its filters and a cursor (ROUTE_CONTRACTS RP-205): the filters, the
 * page's size other than the default and the page stand in the address, so that they are kept when
 * the user pages and when the user returns from a session (NC-10).
 */
export function sessionsAddress(filters: FilterChoice, cursor: { readonly after?: string; readonly before?: string } = {}): string {
    const query = new URLSearchParams();

    for (const [name, value] of [
        ['status', filters.status],
        ['from', filters.from],
        ['to', filters.to],
        ['register', filters.register],
        ['responsible', filters.responsible],
        ['perPage', filters.perPage === SESSIONS_PER_PAGE ? null : String(filters.perPage)],
        ['after', cursor.after ?? null],
        ['before', cursor.before ?? null],
    ] as const) {
        if (value !== null) {
            query.set(name, value);
        }
    }

    const search = query.toString();

    return search === '' ? SESSIONS_PAGE : `${SESSIONS_PAGE}?${search}`;
}

/** A status of the filter, or null for every one. */
export function statusOf(value: string): string | null {
    return value === OPEN || value === CLOSED ? value : null;
}

/** A key of a filter's choice, or null for none (the empty choice). */
export function choiceKey(value: string): string | null {
    return /^[1-9][0-9]{0,18}$/.test(value) ? value : null;
}

/** RC-03's family; no recent authentication (INTERACTION_CONTRACTS section 1.5). */
export const CLOSE_SESSION_COMMAND = { operation: 'cash_session.close', family: 'close_session', recentAuth: false } as const;

/** RC-01's family, whose unresolved request keeps "Lanjut hitung" disabled (UXI-103; BR-CASH-16). */
export const CHECKOUT_FAMILY = { operation: 'sale.checkout', family: 'sale' } as const;

/** RP-105, RP-205 and RP-101 (ROUTE_CONTRACTS section 2). */
export const CLOSE_SESSION_PAGE = '/till/close-session';

export const SESSIONS_PAGE = '/cash-sessions';

export const TILL_PAGE = '/till';

/** RP-006, where an unresolved request is checked (NC-13 (a)). */
export const UNRESOLVED_PAGE = '/requests';

/** RP-206 of a session by its internal key. */
export function sessionPage(id: number): string {
    return `${SESSIONS_PAGE}/${String(id)}`;
}

/**
 * The way back of UXP-206, "Kembali ke Sesi kas" (UXI-148): UXP-205 with the filters and the page it
 * stood at when it opened this document (NAVIGATION_CONTRACTS UXP-205, NC-10); else the whole list,
 * the default and the way after the close's success (NC-01).
 */
export function sessionsListFrom(referrer: string, origin: string): string {
    try {
        const opener = new URL(referrer);

        return opener.origin === origin && opener.pathname === SESSIONS_PAGE ? `${SESSIONS_PAGE}${opener.search}` : SESSIONS_PAGE;
    } catch {
        return SESSIONS_PAGE;
    }
}

/**
 * Whether the close page's session is no longer OPEN, which the page shows as UXS-30: the close was
 * refused SESSION_NOT_OPEN, or the preview read again is null — the session closed elsewhere — or names
 * another session, opened since, whose figures are never counted under this session's header.
 */
export function closedMeanwhile(sessionId: number, preview: ClosePreview | null | undefined, refusalCode: string | null): boolean {
    return refusalCode === 'SESSION_NOT_OPEN' || preview === null || (preview !== undefined && preview.sessionId !== sessionId);
}

export const OPEN = 'OPEN';

export const CLOSED = 'CLOSED';

/**
 * The shell of UXP-205 and UXP-206: the destination "Sesi kas" of a cashier and the combined
 * account (UXI-004), and for the Owner the item "Sesi kas" of the Laporan sub-navigation (UXI-019).
 */
export function sessionsShell(owner: boolean): { current: DestinationId; area?: AreaId; subCurrent?: SubDestinationId } {
    return owner ? { current: 'reports', area: 'reports', subCurrent: 'sessions' } : { current: 'sessions' };
}

/** A session's state in words (COPY_DECK section 7.10). */
export function statusWord(status: string): string {
    return status === OPEN ? t('cash.session.status_open') : t('cash.session.status_closed');
}

/** How a register is named on screens: its code and its label (as the cash pages name it). */
export function registerText(code: string, label: string): string {
    return code === label ? code : `${code} · ${label}`;
}

/**
 * The variance of a count: physical cash minus expected cash, exact on whole rupiah (BR-CASH-12),
 * as the canonical string that the review and the report show in words. A blank or malformed amount
 * gives none.
 */
export function varianceOf(physical: string, expected: string): string | null {
    const whole = /^-?[0-9]+$/;

    return whole.test(physical) && whole.test(expected) ? subtractMoney(physical, expected) : null;
}

/**
 * The tone of a variance, as the approved KS-07 gives it: even a success, short or over a warning —
 * danger is for errors (DESIGN_SYSTEM section 3.2) — and never colour alone (UXA-18).
 */
export function varianceTone(variance: string): 'warning' | 'success' {
    return /^0+$/.test(variance) ? 'success' : 'warning';
}

/** The drawer-funded refunds as the figures show them: taken from expected cash, "−Rp50.000" (KS-07). */
export function refundsText(amount: string): string {
    return /^0+$/.test(amount) ? formatMoney(amount) : formatMoney(`-${amount}`);
}
