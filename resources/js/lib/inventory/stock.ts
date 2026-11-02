import { leaveSignedInSession } from '@/lib/browserSession';
import { quantityFromEntry, type ReadAnswer } from '@/lib/catalog/products';
import { requestJson } from '@/lib/commands/client';
import { formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';
import type { TranslationKey } from '@/types/translation-keys';

// The stock and receiving pages (ROUTE_CONTRACTS RP-401 to RP-406, RD-02; INTERACTION_CONTRACTS
// sections 3.2 and 3.3, RC-07; COPY_DECK sections 9.1 to 9.7; TASK-022): the records as the
// server sends them, the read RD-02, the command RC-07, the words of the states and types,
// and what passes from a page to the page it opens. A filter or a page stands in the page's
// address and props, which the encrypted history state holds, so that the browser's Back finds
// them (NC-10). The way back of a child page is a new entry of the history, so it takes its
// opener from the document's referrer, the address that opened it, as the catalogue's pages
// do: nothing of a list or a draft is in the browser's storage (SEC-CH-05, BR-INV-17). No
// cost is ever read here but the purchase cost of a receiving document (CAP-12).

export const STOCK_PAGE = '/stock';

export const HISTORY_PAGE = '/stock/history';

export const RECEIVINGS_PAGE = '/stock/receivings';

export const NEW_RECEIVING_PAGE = '/stock/receivings/new';

export const ISSUES_PAGE = '/stock/issues';

export const NEW_ISSUE_PAGE = '/stock/issues/new';

export const REQUESTS_PAGE = '/requests';

export function movementPage(id: number): string {
    return `/stock/movements/${String(id)}`;
}

export function receivingPage(id: number): string {
    return `${RECEIVINGS_PAGE}/${String(id)}`;
}

export function issuePage(id: number): string {
    return `${ISSUES_PAGE}/${String(id)}`;
}

/** Where the stock pages stand in the shell (INFORMATION_ARCHITECTURE section 4). */
export const STOCK_SHELL = { current: 'stock', area: 'stock', subCurrent: 'stock' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

export const HISTORY_SHELL = { current: 'stock', area: 'stock', subCurrent: 'history' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

export const RECEIVINGS_SHELL = { current: 'stock', area: 'stock', subCurrent: 'receipts' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

export const ISSUES_SHELL = { current: 'stock', area: 'stock', subCurrent: 'issues' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** RC-07's family (INTERACTION_CONTRACTS section 1.4). */
export const RECEIVING_COMMAND = { operation: 'receiving.confirm', family: 'stock_posting' } as const;

/** RC-08's family (INTERACTION_CONTRACTS section 1.4), which it shares with RC-07. */
export const ISSUE_COMMAND = { operation: 'manual_issue.confirm', family: 'stock_posting' } as const;

/** The most lines of a receiving (TECHNICAL_RULES section 11.2). */
export const MAX_RECEIVING_LINES = 500;

/** The most lines of a manual issue (TECHNICAL_RULES section 11.2). */
export const MAX_ISSUE_LINES = 200;

export type IssueReason = 'DAMAGED' | 'INTERNAL_USE' | 'LOST' | 'SAMPLE' | 'OTHER';

/** The reasons of BR-INV-19, in their order, with their words (COPY_DECK section 9.1). */
export const ISSUE_REASONS: readonly { readonly reason: IssueReason; readonly label: TranslationKey }[] = [
    { reason: 'DAMAGED', label: 'inventory.issue_reason.damaged' },
    { reason: 'INTERNAL_USE', label: 'inventory.issue_reason.internal_use' },
    { reason: 'LOST', label: 'inventory.issue_reason.lost' },
    { reason: 'SAMPLE', label: 'inventory.issue_reason.sample' },
    { reason: 'OTHER', label: 'inventory.issue_reason.other' },
];

export function reasonWord(reason: string): string {
    const found = ISSUE_REASONS.find((entry) => entry.reason === reason);

    return found === undefined ? reason : t(found.label);
}

/** A reason of the list, or null for any other text. */
export function issueReasonOf(value: string): IssueReason | null {
    return ISSUE_REASONS.find((entry) => entry.reason === value)?.reason ?? null;
}

/** The largest whole part of a quantity (TECHNICAL_RULES section 11.1): eight digits. */
export const QUANTITY_DIGITS_MAX = 8;

/**
 * A draft's quantity as typed, in the canonical form of its shortest digits — "01" is "1" and
 * "2,50" is "2.5" — when it is a quantity of the product's precision within the largest; null
 * while it is not one yet: emptied, half typed such as "1,", or with more decimals. The review
 * formats only this, never the entry as typed, so that no edit of a line can end the page and
 * its draft (pull request #31, finding P1).
 */
export function draftQuantity(text: string, precision: number): string | null {
    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(quantityFromEntry(text));

    if (match === null) {
        return null;
    }

    const whole = (match[1] ?? '').replace(/^0+(?=[0-9])/, '');
    const fraction = (match[2] ?? '').replace(/0+$/, '');

    if (fraction.length > precision || whole.replace(/^0+/, '').length > QUANTITY_DIGITS_MAX) {
        return null;
    }

    return fraction === '' ? whole : `${whole}.${fraction}`;
}

/**
 * A draft's quantity as its command receives it: the canonical form that the review shows, so
 * that what was reviewed is what is sent — "01" and "1,0" are "1" — since the server refuses a
 * quantity that is not canonical (QuantityRule). A text that is no quantity yet is sent as typed,
 * and the server refuses it at its field (pull request #32, finding P2).
 */
export function sentQuantity(text: string, precision: number): string {
    return draftQuantity(text, precision) ?? quantityFromEntry(text);
}

export type StockState = 'OUT' | 'LOW' | 'NORMAL';

export const STOCK_STATES: readonly StockState[] = ['OUT', 'LOW', 'NORMAL'];

/** A row of RD-02 (App\Queries\Inventory\StockList). */
export interface StockRow {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly onHand: string;
    readonly minimum: string;
    readonly state: StockState;
}

/** RD-02's answer: the rows, the numbers of Habis and Menipis, the as-of time and the next page. */
export interface StockPage {
    readonly rows: readonly StockRow[];
    readonly outCount: number;
    readonly lowCount: number;
    readonly asOf: string;
    readonly nextCursor: string | null;
}

/** The filters of UXP-401 (ROUTE_CONTRACTS RP-401); `perPage` keeps how many rows were shown. */
export interface StockFilters {
    readonly q: string;
    readonly state: StockState | null;
    readonly category: number | null;
    readonly perPage: number;
}

export const STOCK_PER_PAGE = 25;

export const MAX_PER_PAGE = 100;

/** The word and tone of a state (COPY_DECK section 9.1); "Normal" is never shown for a failed read (BR-INV-41). */
export function stateWord(state: StockState): { readonly label: TranslationKey; readonly tone: 'danger' | 'warning' | 'neutral' } {
    switch (state) {
        case 'OUT':
            return { label: 'inventory.state.out', tone: 'danger' };
        case 'LOW':
            return { label: 'inventory.state.low', tone: 'warning' };
        case 'NORMAL':
            return { label: 'inventory.state.normal', tone: 'neutral' };
    }
}

export type MovementType = 'OPENING' | 'RECEIPT' | 'SALE_ISSUE' | 'MANUAL_ISSUE' | 'ADJUSTMENT' | 'REVERSAL' | 'RETURN_RECEIPT';

/** The movement types of INVENTORY section 2, in its order, with their words (COPY_DECK section 9.1). */
export const MOVEMENT_TYPES: readonly { readonly type: MovementType; readonly label: TranslationKey }[] = [
    { type: 'OPENING', label: 'inventory.type.opening' },
    { type: 'RECEIPT', label: 'inventory.type.receipt' },
    { type: 'SALE_ISSUE', label: 'inventory.type.sale_issue' },
    { type: 'MANUAL_ISSUE', label: 'inventory.type.manual_issue' },
    { type: 'ADJUSTMENT', label: 'inventory.type.adjustment' },
    { type: 'REVERSAL', label: 'inventory.type.reversal' },
    { type: 'RETURN_RECEIPT', label: 'inventory.type.return_receipt' },
];

export function typeWord(type: string): string {
    const found = MOVEMENT_TYPES.find((entry) => entry.type === type);

    return found === undefined ? type : t(found.label);
}

/** A signed quantity of a movement, "+5 pcs" or "−2 pcs" (COPY_DECK section 9.3). */
export function signedQuantity(value: string, precision: number, unit: string): string {
    const negative = value.startsWith('-');
    const magnitude = negative ? value.slice(1) : value;

    return `${negative ? '−' : '+'}${formatQuantity(magnitude, precision, unit)}`;
}

/** A source of a movement within the viewer's scope (UXI-224): a receiving document, or a manual issue (TASK-023). */
export interface MovementSource {
    readonly kind: 'RECEIVING' | 'MANUAL_ISSUE';
    readonly id: number;
    readonly at: string;
}

/** The page of a movement's source and the words of its link (UXI-224; COPY_DECK section 9.4). */
export function sourceLink(source: MovementSource): { readonly href: string; readonly label: string } {
    const when = formatDateTime(source.at);

    return source.kind === 'MANUAL_ISSUE'
        ? { href: issuePage(source.id), label: t('inventory.movement.source_issue', { waktu: when }) }
        : { href: receivingPage(source.id), label: t('inventory.movement.source_receipt', { waktu: when }) };
}

/** A row of RP-402 (App\Queries\Inventory\StockHistory). */
export interface HistoryRow {
    readonly id: number;
    readonly productSequence: number;
    readonly recordedAt: string;
    readonly type: MovementType;
    readonly quantity: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    readonly actorDisplayName: string;
    readonly source: MovementSource | null;
}

/** The filters of UXP-402 (ROUTE_CONTRACTS RP-402). */
export interface HistoryFilters {
    readonly product: number | null;
    readonly type: MovementType | null;
    readonly from: string | null;
    readonly to: string | null;
}

/** The pages that may open UXP-402 for its way back (UXI-223); UXP-415 joins with TASK-036. */
export type HistoryOpener = 'stock' | 'product' | 'requests';

/** The address of UXP-402 with its filters, a cursor and its opener; what is not set is absent. */
export function historyAddress(filters: HistoryFilters, cursor: { readonly after?: string; readonly before?: string } = {}, opener: HistoryOpener | null = null): string {
    const query = new URLSearchParams();

    if (filters.product !== null) {
        query.set('product', String(filters.product));
    }

    if (filters.type !== null) {
        query.set('type', filters.type);
    }

    if (filters.from !== null) {
        query.set('from', filters.from);
    }

    if (filters.to !== null) {
        query.set('to', filters.to);
    }

    if (cursor.after !== undefined) {
        query.set('after', cursor.after);
    }

    if (cursor.before !== undefined) {
        query.set('before', cursor.before);
    }

    if (opener !== null) {
        query.set('from_page', opener);
    }

    const search = query.toString();

    return search === '' ? HISTORY_PAGE : `${HISTORY_PAGE}?${search}`;
}

/** The history of one product, opened from a page that names itself (UXI-167, UXI-216). */
export function productHistory(product: number, opener: HistoryOpener): string {
    return historyAddress({ product, type: null, from: null, to: null }, {}, opener);
}

/** A document of RP-404 (App\Queries\Inventory\ReceivingDocuments). */
export interface ReceivingRow {
    readonly id: number;
    readonly confirmedAt: string;
    readonly reference: string | null;
    readonly lineCount: number;
    readonly actorDisplayName: string;
}

/** The filters of UXP-404 (ROUTE_CONTRACTS RP-404). */
export interface ReceivingFilters {
    readonly from: string | null;
    readonly to: string | null;
    readonly q: string;
}

export function receivingsAddress(filters: ReceivingFilters, cursor: { readonly after?: string; readonly before?: string } = {}): string {
    const query = new URLSearchParams();

    if (filters.from !== null) {
        query.set('from', filters.from);
    }

    if (filters.to !== null) {
        query.set('to', filters.to);
    }

    if (filters.q !== '') {
        query.set('q', filters.q);
    }

    if (cursor.after !== undefined) {
        query.set('after', cursor.after);
    }

    if (cursor.before !== undefined) {
        query.set('before', cursor.before);
    }

    const search = query.toString();

    return search === '' ? RECEIVINGS_PAGE : `${RECEIVINGS_PAGE}?${search}`;
}

/** An issue of RP-407 (App\Queries\Inventory\ManualIssueRecords). */
export interface IssueRow {
    readonly id: number;
    readonly confirmedAt: string;
    readonly reasonCode: IssueReason;
    readonly lineCount: number;
    readonly actorDisplayName: string;
}

/** The filters of UXP-407 (ROUTE_CONTRACTS RP-407). */
export interface IssueFilters {
    readonly from: string | null;
    readonly to: string | null;
    readonly reason: IssueReason | null;
}

export function issuesAddress(filters: IssueFilters, cursor: { readonly after?: string; readonly before?: string } = {}): string {
    const query = new URLSearchParams();

    if (filters.from !== null) {
        query.set('from', filters.from);
    }

    if (filters.to !== null) {
        query.set('to', filters.to);
    }

    if (filters.reason !== null) {
        query.set('reason', filters.reason);
    }

    if (cursor.after !== undefined) {
        query.set('after', cursor.after);
    }

    if (cursor.before !== undefined) {
        query.set('before', cursor.before);
    }

    const search = query.toString();

    return search === '' ? ISSUES_PAGE : `${ISSUES_PAGE}?${search}`;
}

/** Thousandths: a quantity has at most three decimals (BR-NUM-02). */
const THOUSANDTHS = 1000n;

/** A canonical non-negative quantity in thousandths, or null. */
function thousandthsOf(text: string): bigint | null {
    const match = /^([0-9]+)(?:\.([0-9]{1,3}))?$/.exec(text);

    return match === null ? null : BigInt(match[1] ?? '0') * THOUSANDTHS + BigInt((match[2] ?? '').padEnd(3, '0'));
}

/** Thousandths as the shortest canonical quantity: 1500n is "1.5". */
function quantityOfThousandths(value: bigint): string {
    const fraction = (value % THOUSANDTHS).toString().padStart(3, '0').replace(/0+$/, '');

    return `${(value / THOUSANDTHS).toString()}${fraction === '' ? '' : `.${fraction}`}`;
}

/** One line of the review of an issue: the stock before it and after it, or that the stock is short. */
export interface IssueStep {
    readonly before: string;
    /** Null when the stock is short for the product's lines up to this one (UXS-31). */
    readonly after: string | null;
}

/**
 * The review of a draft of an issue, line by line (BR-INV-07, BR-INV-19): from the stock on hand
 * that the search gave for the product, the lines of one product taken in their order, the stock
 * before the line and after it, in exact digits; the stock after is null once the lines of the
 * product ask for more than it holds, which the server refuses (BR-INV-20). A step is null for a
 * line whose quantity is not one yet, or whose product's stock the search did not give.
 */
export function issueSteps(lines: readonly { readonly productId: number; readonly quantity: string | null; readonly onHand: string | null }[]): readonly (IssueStep | null)[] {
    const left = new Map<number, bigint>();

    return lines.map((line) => {
        const known = left.get(line.productId);
        const start = known ?? (line.onHand === null ? null : thousandthsOf(line.onHand));
        const quantity = line.quantity === null ? null : thousandthsOf(line.quantity);

        if (start === null || quantity === null) {
            return null;
        }

        const after = start - quantity;

        left.set(line.productId, after < 0n ? -1n : after);

        return { before: quantityOfThousandths(start < 0n ? 0n : start), after: start < 0n || after < 0n ? null : quantityOfThousandths(after) };
    });
}
export interface KeysetPage<Row> {
    readonly rows: readonly Row[];
    readonly nextCursor: string | null;
    readonly previousCursor: string | null;
}

/** Whether a day of the date filter comes after the other (`ux.validation.date_order`; TECHNICAL_RULES section 11.4). */
export function datesOutOfOrder(from: string | null, to: string | null): boolean {
    return from !== null && to !== null && from > to;
}

/** The page of this application that opened this document, or null. */
export function openerOf(referrer: string, origin: string): URL | null {
    try {
        const opener = new URL(referrer);

        return opener.origin === origin ? opener : null;
    } catch {
        return null;
    }
}

/** The address of the opener when it is one of these pages, with its filters; else the default. */
export function backFrom(referrer: string, origin: string, matches: (path: string) => boolean, fallback: string): string {
    const opener = openerOf(referrer, origin);

    return opener !== null && matches(opener.pathname) ? `${opener.pathname}${opener.search}` : fallback;
}

/** The address of UXP-401 with its filters; what is not set, and the first page's size, are absent. */
export function stockAddress(filters: StockFilters): string {
    const query = new URLSearchParams();

    if (filters.q !== '') {
        query.set('q', filters.q);
    }

    if (filters.state !== null) {
        query.set('state', filters.state);
    }

    if (filters.category !== null) {
        query.set('category', String(filters.category));
    }

    if (filters.perPage !== STOCK_PER_PAGE) {
        query.set('perPage', String(filters.perPage));
    }

    const search = query.toString();

    return search === '' ? STOCK_PAGE : `${STOCK_PAGE}?${search}`;
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** RD-02's answer, when it has its fields. */
function stockPageOf(body: Readonly<Record<string, unknown>>): StockPage | null {
    const { rows, outCount, lowCount, asOf, nextCursor } = body;

    if (!Array.isArray(rows) || typeof outCount !== 'number' || typeof lowCount !== 'number' || typeof asOf !== 'string' || (nextCursor !== null && typeof nextCursor !== 'string')) {
        return null;
    }

    return { rows: rows as readonly StockRow[], outCount, lowCount, asOf, nextCursor };
}

/**
 * RD-02 with these filters after a row, as the page asks it: a background read when the page
 * refreshes itself (RT-10), a user's read otherwise. A signed-out answer ends the page; any
 * other answer that is not the list is a failed read, never an empty list or a zero.
 */
export async function readStock(filters: Pick<StockFilters, 'q' | 'state' | 'category'>, after: string | null, perPage: number, background: boolean): Promise<ReadAnswer<StockPage>> {
    const query = new URLSearchParams();

    if (filters.q !== '') {
        query.set('q', filters.q);
    }

    if (filters.state !== null) {
        query.set('state', filters.state);
    }

    if (filters.category !== null) {
        query.set('category', String(filters.category));
    }

    if (after !== null) {
        query.set('after', after);
    }

    query.set('perPage', String(perPage));

    const arrived = await requestJson('GET', `/data/stock?${query.toString()}`, undefined, { background, waitMs: 30_000 });

    if (arrived?.status === 401) {
        leaveSignedInSession();

        return { kind: 'failed' };
    }

    if (arrived?.status === 200 && isRecord(arrived.body)) {
        const page = stockPageOf(arrived.body);

        return page === null ? { kind: 'failed' } : { kind: 'read', body: page };
    }

    if (arrived?.status === 422 && isRecord(arrived.body) && isRecord(arrived.body.fields)) {
        return { kind: 'invalid', fields: arrived.body.fields };
    }

    return { kind: 'failed' };
}

/**
 * The first rows of RD-02, as many as a list had shown (NC-10), in as few reads as RD-02's
 * largest page allows, with the counts and time of the first read.
 */
export async function readStockRows(filters: Pick<StockFilters, 'q' | 'state' | 'category'>, wanted: number, background: boolean): Promise<ReadAnswer<StockPage>> {
    const first = await readStock(filters, null, Math.min(Math.max(wanted, STOCK_PER_PAGE), MAX_PER_PAGE), background);

    if (first.kind !== 'read' || first.body.rows.length >= wanted || first.body.nextCursor === null) {
        return first;
    }

    const rows = [...first.body.rows];
    let cursor: string | null = first.body.nextCursor;

    while (cursor !== null && rows.length < wanted) {
        const next = await readStock(filters, cursor, MAX_PER_PAGE, background);

        if (next.kind !== 'read') {
            return next;
        }

        rows.push(...next.body.rows);
        cursor = next.body.nextCursor;
    }

    return { kind: 'read', body: { ...first.body, rows, nextCursor: cursor } };
}
