import { t } from '@/lib/i18n';
import type { ReceiptSize } from '@/lib/sales/till';

// The Sales pages (ROUTE_CONTRACTS RP-201, RP-202, RC-44; COPY_DECK sections 7.6 and 7.7; TASK-031): their
// paths, the list's rows and filters as the server sends them, the address that keeps the filters, and
// UXP-202's way back (NC-01, NC-10).

/** RP-201, UXP-201 "Penjualan". */
export const SALES_PAGE = '/sales';

/** RP-201's page size when the address names none (ROUTE_CONTRACTS RP-201). */
export const SALES_PER_PAGE = 25;

/** RP-202 of a Sale by its internal key. */
export function salePage(id: number): string {
    return `${SALES_PAGE}/${String(id)}`;
}

/** RC-44's size for a paper size of the page: `a4` is `A4` (INTERACTION_CONTRACTS, "RC-44 to RC-47"). */
export function sizePayload(size: ReceiptSize): '58' | '80' | 'A4' {
    return size === 'a4' ? 'A4' : size;
}

/** A row of RP-201 (UXI-115). */
export interface SaleRow {
    readonly id: number;
    readonly number: string;
    readonly committedAt: string;
    readonly total: string;
    readonly method: string;
    readonly cashier: string;
}

/** The filters of RP-201 as the page read them; `cashier` and `register` the Owner's; the page's size. */
export interface SaleFilters {
    readonly from: string | null;
    readonly to: string | null;
    readonly number: string | null;
    readonly cashier: number | null;
    readonly register: number | null;
    readonly perPage: number;
}

export interface SalePage {
    readonly rows: readonly SaleRow[];
    readonly nextCursor: string | null;
    readonly previousCursor: string | null;
}

/** The filters as the page changes them: a person or a register by the text of its key, none as null. */
export interface SaleChoice {
    readonly from: string | null;
    readonly to: string | null;
    readonly number: string | null;
    readonly cashier: string | null;
    readonly register: string | null;
    readonly perPage: number;
}

export function saleChoiceOf(filters: SaleFilters): SaleChoice {
    return {
        from: filters.from,
        to: filters.to,
        number: filters.number,
        cashier: filters.cashier === null ? null : String(filters.cashier),
        register: filters.register === null ? null : String(filters.register),
        perPage: filters.perPage,
    };
}

/**
 * The address of RP-201 with its filters and a cursor: the filters, the page's size other than the
 * default and the page stand in the address, so that they are kept when the user pages and when the user
 * returns from a Sale (NAVIGATION_CONTRACTS UXP-201; NC-10).
 */
export function salesAddress(filters: SaleChoice, cursor: { readonly after?: string; readonly before?: string } = {}): string {
    const query = new URLSearchParams();

    for (const [name, value] of [
        ['from', filters.from],
        ['to', filters.to],
        ['number', filters.number],
        ['cashier', filters.cashier],
        ['register', filters.register],
        ['perPage', filters.perPage === SALES_PER_PAGE ? null : String(filters.perPage)],
        ['after', cursor.after ?? null],
        ['before', cursor.before ?? null],
    ] as const) {
        if (value !== null) {
            query.set(name, value);
        }
    }

    const search = query.toString();

    return search === '' ? SALES_PAGE : `${SALES_PAGE}?${search}`;
}

/** A payment method in words (D-077). */
export function methodWord(method: string): string {
    return method === 'CASH' ? t('sales.payment.method_cash') : t('sales.payment.method_transfer');
}

/**
 * The way back of UXP-202, "Kembali ke …" (UXI-121; NC-01): the page it was opened from — UXP-201 with the
 * filters and the page it stood at (NC-10), the receipt (UXP-103) or a stock movement (UXP-403) — else
 * UXP-201, the default.
 */
export function saleBackFrom(referrer: string, origin: string): { readonly href: string; readonly page: string } {
    const list = { href: SALES_PAGE, page: t('sales.list.title') };

    try {
        const opener = new URL(referrer);

        if (opener.origin !== origin) {
            return list;
        }

        if (opener.pathname === SALES_PAGE) {
            return { href: `${SALES_PAGE}${opener.search}`, page: t('sales.list.title') };
        }

        if (/^\/sales\/[1-9][0-9]*\/receipt$/.test(opener.pathname)) {
            return { href: opener.pathname, page: t('sales.receipt.title') };
        }

        if (/^\/stock\/movements\/[1-9][0-9]*$/.test(opener.pathname)) {
            return { href: opener.pathname, page: t('inventory.movement.title') };
        }

        return list;
    } catch {
        return list;
    }
}
