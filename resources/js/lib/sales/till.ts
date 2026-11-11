import { leaveSignedInSession } from '@/lib/browserSession';
import type { ProductRow } from '@/lib/catalog/products';
import { requestJson } from '@/lib/commands/client';

// The till's pages (ROUTE_CONTRACTS RP-101 to RP-103, RD-01 `till`, RC-01; INTERACTION_CONTRACTS sections
// 1.4 and 3.1; TASK-029): their paths, the command of the Sale checkout with its family, the till's
// Product Search, and what passes from the receipt to the next sale.

/** RP-101, UXP-101 "Kasir". */
export const TILL_PAGE = '/till';

/** RP-102, UXP-102 "Pembayaran", a view of the till's page. */
export const PAYMENT_PAGE = '/till/payment';

/** RP-104, UXP-104 "Buka sesi kas". */
export const OPEN_SESSION_PAGE = '/till/open-session';

/** RP-006, UXP-006 "Permintaan belum pasti". */
export const UNRESOLVED_PAGE = '/requests';

/** The views of the till's page (App\Http\Controllers\Sales\TillController). */
export const TILL_VIEW = 'till';

export const PAYMENT_VIEW = 'payment';

export type TillView = typeof TILL_VIEW | typeof PAYMENT_VIEW;

/** RC-01, of the family Sale checkout, without recent authentication (INTERACTION_CONTRACTS sections 1.4 and 1.5). */
export const SALE_COMMAND = { operation: 'sale.checkout', family: 'sale', recentAuth: false } as const;

/** The session of the till's header (COPY_DECK `sales.till.session`). */
export interface TillSession {
    readonly id: number;
    readonly number: string;
    readonly register: string;
}

/** A category of the search's filter (UXI-069). */
export interface TillCategory {
    readonly id: number;
    readonly name: string;
}

/** What a read of RD-01 in the context `till` answered. */
export type TillSearchAnswer =
    | { readonly kind: 'read'; readonly rows: readonly ProductRow[]; readonly hasMore: boolean }
    /** VALIDATION_FAILED, with the field entries of RT-07. */
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    /** NO_OPEN_SESSION: the user's session closed meanwhile (BR-CAT-33, UXS-29). */
    | { readonly kind: 'no_session' }
    | { readonly kind: 'failed' };

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** The next value of `X-Search-Sequence` of this document (INTERACTION_CONTRACTS section 3.1). */
let lastSequence = 0;

/** RD-01 in the context `till`, `mode=exact` for a scan or a code, `search` otherwise (UXI-066, UXI-068, UXI-069). */
export async function searchTill(parameters: Readonly<Record<string, string>>): Promise<TillSearchAnswer> {
    lastSequence += 1;

    const query = new URLSearchParams({ context: 'till', ...parameters });
    const arrived = await requestJson('GET', `/data/products/search?${query.toString()}`, undefined, { waitMs: 30_000, headers: { 'X-Search-Sequence': String(lastSequence) } });
    const body = arrived !== null && isRecord(arrived.body) ? arrived.body : null;

    if (arrived?.status === 401) {
        leaveSignedInSession();

        return { kind: 'failed' };
    }

    if (arrived?.status === 200 && body !== null) {
        return { kind: 'read', rows: Array.isArray(body.rows) ? (body.rows as readonly ProductRow[]) : [], hasMore: body.hasMore === true };
    }

    if (body !== null && body.code === 'NO_OPEN_SESSION') {
        return { kind: 'no_session' };
    }

    if (arrived?.status === 422 && body !== null && isRecord(body.fields)) {
        return { kind: 'invalid', fields: body.fields };
    }

    return { kind: 'failed' };
}

let heldScan: string | null = null;

/** A scan or search on the receipt page starts the next sale with it (UXI-089, UXA-11): held in page memory only, never stored. */
export function holdScan(code: string): void {
    heldScan = code;
}

/** The till takes the held scan once. */
export function takeScan(): string | null {
    const code = heldScan;
    heldScan = null;

    return code;
}

/** The receipt's paper sizes (UXI-091; UXA-23). */
export const RECEIPT_SIZES = ['58', '80', 'a4'] as const;

export type ReceiptSize = (typeof RECEIPT_SIZES)[number];

/** The key of the last size chosen, a harmless interface preference for the browser session (SEC-CH-05). */
const SIZE_KEY = 'bk.receipt.size';

function sessionStore(): Storage | null {
    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
}

export function rememberedSize(): ReceiptSize {
    let stored: string | null = null;

    try {
        stored = sessionStore()?.getItem(SIZE_KEY) ?? null;
    } catch {
        stored = null;
    }

    return sizeOf(stored);
}

function sizeOf(value: string | null): ReceiptSize {
    return RECEIPT_SIZES.find((size) => size === value) ?? '58';
}

export function rememberSize(size: ReceiptSize): void {
    try {
        sessionStore()?.setItem(SIZE_KEY, size);
    } catch {
        // A browser that keeps nothing still shows the size chosen on this page.
    }
}

/** The path of RP-103 of a Sale (ROUTE_CONTRACTS RP-103). */
export function receiptPage(saleId: number): string {
    return `/sales/${String(saleId)}/receipt`;
}
