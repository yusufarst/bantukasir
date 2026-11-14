import { blockingRequest, type Unresolved } from '@/lib/commands/families';
import { compareExact, parseMoney, parseQuantity, sumMoney } from '@/lib/exact';
import { formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { draftQuantity } from '@/lib/inventory/stock';
import { salePage } from '@/lib/sales/sales';

// The refund pages (ROUTE_CONTRACTS RP-203, RP-204, RC-05, RC-06; INTERACTION_CONTRACTS RC-05, RC-06 and
// section 2.6; COPY_DECK sections 7.7 to 7.9; SALES_CASH BR-RFD-02 to BR-RFD-07, BR-RFD-16, BR-RFD-17;
// D-045, D-046, D-068, D-087; TASK-034): RC-05's and RC-06's families, the pages' paths, the Sale's lines
// and the OPEN sessions as RP-203 sends them, a committed correction as RP-202 and RP-204 receive it, and
// the draft of RP-203: what each line refunds and returns, the payout's method, source and reference, and
// the payload of the one command that the chosen source names — RC-05 for a drawer, RC-06 otherwise. The
// server checks every bound again under its locks (BR-RFD-03, BR-RFD-15).

/** RC-05's family, with recent authentication (INTERACTION_CONTRACTS section 1.5). */
export const DRAWER_REFUND_COMMAND = { operation: 'sale_correction.drawer_funded', family: 'refund_drawer', recentAuth: true } as const;

/** RC-06's family, with recent authentication. */
export const OTHER_REFUND_COMMAND = { operation: 'sale_correction.other', family: 'refund_other', recentAuth: true } as const;

/** RP-203 of a Sale (ROUTE_CONTRACTS RP-203). */
export function refundPage(saleId: number): string {
    return `${salePage(saleId)}/refund`;
}

/** RP-204 of a correction of a Sale (ROUTE_CONTRACTS RP-204). */
export function correctionPage(saleId: number, correctionId: number): string {
    return `${salePage(saleId)}/corrections/${String(correctionId)}`;
}

/**
 * The user's unresolved refund or return, of either family, that keeps "Pengembalian uang dan retur"
 * disabled (UXI-118; NC-13 (a); BR-RFD-17), or null.
 */
export function unresolvedRefund<U extends Unresolved>(unresolved: readonly U[]): U | null {
    return blockingRequest(unresolved, DRAWER_REFUND_COMMAND.operation, DRAWER_REFUND_COMMAND.family) ?? blockingRequest(unresolved, OTHER_REFUND_COMMAND.operation, OTHER_REFUND_COMMAND.family);
}

/** A line of the Sale with what remains on it to refund and to return (UXI-122; BR-RFD-03). */
export interface RefundLine {
    readonly saleLineId: number;
    readonly lineNo: number;
    readonly name: string;
    readonly sku: string;
    readonly unit: string;
    readonly quantityPrecision: number;
    readonly quantity: string;
    readonly lineTotal: string;
    readonly remainingAmount: string;
    readonly remainingQuantity: string;
}

/** An OPEN cash session that may fund a refund, with its expected cash now (UXI-124; BR-RFD-07). */
export interface FundingSession {
    readonly id: number;
    readonly number: string;
    readonly registerCode: string;
    readonly registerLabel: string;
    readonly responsible: string;
    readonly expectedCash: string;
}

/** A refund's payout as it was committed (BR-RFD-05); `drawer` the session of a drawer-funded refund. */
export interface CorrectionRefund {
    readonly amount: string;
    readonly method: string;
    readonly fundingSource: string | null;
    readonly reference: string;
    readonly drawer: { readonly sessionId: number; readonly sessionNumber: string; readonly registerCode: string; readonly registerLabel: string } | null;
}

/** What a correction refunded and returned on one line of the Sale. */
export interface CorrectionEntry {
    readonly saleLineId: number;
    readonly lineNo: number;
    readonly name: string;
    readonly unit: string;
    readonly quantityPrecision: number;
    readonly refundAmount: string;
    readonly returnedSaleable: string;
    readonly returnedNotSaleable: string;
}

/** A committed correction of the Sale (UXI-120, UXI-133). */
export interface Correction {
    readonly id: number;
    readonly recordedAt: string;
    readonly reason: string;
    readonly actor: string;
    readonly refund: CorrectionRefund | null;
    readonly lines: readonly CorrectionEntry[];
}

export const CASH = 'CASH';

export const BANK_TRANSFER = 'BANK_TRANSFER';

export const DRAWER = 'DRAWER';

export type Method = typeof CASH | typeof BANK_TRANSFER;

export type Condition = 'SALEABLE' | 'NOT_SALEABLE';

/** The source of a CASH refund as the choice holds it: the Owner's own funds, or a session by its key. */
export const OWNER_SOURCE = 'owner';

/** The choice of a session as the source of the money. */
export function sessionSource(id: number): string {
    return `session-${String(id)}`;
}

/** The session that a choice names, or null for the Owner's own funds or none. */
export function sourceSession(source: string | null, sessions: readonly FundingSession[]): FundingSession | null {
    return sessions.find((session) => sessionSource(session.id) === source) ?? null;
}

/**
 * What the draft pays out, as the Owner chose it: from the drawer of a listed OPEN session, sent by RC-05;
 * from the Owner's own funds or by transfer, by RC-06 with the payout made; a return without money, by
 * RC-06 without a payout.
 */
export type RefundRequest = { readonly kind: 'drawer'; readonly session: FundingSession } | { readonly kind: 'owner' } | { readonly kind: 'transfer' } | { readonly kind: 'return' };

/**
 * The request of the draft, or null while its payout is not chosen: no method for money refunded, or for
 * Tunai no source — and a session that the sessions read again no longer list counts as none, never as
 * the Owner's own funds or another session (BR-RFD-16, D-045; TASK-034 review R-01).
 */
export function refundRequest(money: boolean, method: Method | null, source: string | null, sessions: readonly FundingSession[]): RefundRequest | null {
    if (!money) {
        return { kind: 'return' };
    }

    if (method === BANK_TRANSFER) {
        return { kind: 'transfer' };
    }

    if (method !== CASH) {
        return null;
    }

    if (source === OWNER_SOURCE) {
        return { kind: 'owner' };
    }

    const session = sourceSession(source, sessions);

    return session === null ? null : { kind: 'drawer', session };
}

/**
 * The payload of a request: RC-05 names only the session, whose payout the server makes (D-087); RC-06 the
 * method and the reference of the payout made, or no payout for a return without money.
 */
export function refundPayload(request: RefundRequest, base: { readonly saleId: number; readonly lines: readonly SentLine[]; readonly reason: string }, reference: string): Readonly<Record<string, unknown>> {
    switch (request.kind) {
        case 'drawer':
            return { ...base, payout: { sessionId: request.session.id } };
        case 'owner':
            return { ...base, payout: { method: CASH, reference } };
        case 'transfer':
            return { ...base, payout: { method: BANK_TRANSFER, reference } };
        case 'return':
            return base;
    }
}

/** A line of the draft as typed: the money refunded, the quantity returned and its condition. */
export interface LineDraft {
    readonly amount: string | null;
    readonly quantity: string;
    readonly condition: Condition | null;
}

export const EMPTY_LINE: LineDraft = { amount: null, quantity: '', condition: null };

/** The money a line refunds, a canonical string; Rp0 when nothing was typed. */
export function lineAmount(draft: LineDraft): string {
    return draft.amount ?? '0';
}

/** The quantity a line returns in its canonical form, or null while it is not a quantity of the line's precision yet. */
export function lineQuantity(draft: LineDraft, line: RefundLine): string | null {
    return draft.quantity.trim() === '' ? '0' : draftQuantity(draft.quantity, line.quantityPrecision);
}

/** Whether a line returns goods: a quantity above zero. */
export function returnsGoods(draft: LineDraft, line: RefundLine): boolean {
    const quantity = lineQuantity(draft, line);

    return quantity !== null && compareExact(parseQuantity(quantity, line.quantityPrecision), { units: 0n, scale: 0 }) > 0;
}

/** Whether a line refunds money: an amount above zero. */
export function refundsMoney(draft: LineDraft): boolean {
    return compareExact(parseMoney(lineAmount(draft)), { units: 0n, scale: 0 }) > 0;
}

/**
 * A line's own problem before the review, as its message, or null: a quantity that is not one of the
 * product's precision, or goods returned without their condition (COPY_DECK section 16.1). The bounds are
 * the server's to check (BR-RFD-03).
 */
export function lineProblem(draft: LineDraft, line: RefundLine): { readonly quantity?: string; readonly condition?: string } {
    if (lineQuantity(draft, line) === null) {
        return { quantity: line.quantityPrecision === 0 ? t('ux.validation.quantity_whole') : t('ux.validation.quantity_decimals', { desimal: String(line.quantityPrecision) }) };
    }

    return returnsGoods(draft, line) && draft.condition === null ? { condition: t('ux.validation.choose_one') } : {};
}

/** The money the draft refunds in all, the sum of its lines (G-06). */
export function draftTotal(drafts: Readonly<Record<number, LineDraft>>, lines: readonly RefundLine[]): string {
    return sumMoney(...lines.map((line) => lineAmount(drafts[line.saleLineId] ?? EMPTY_LINE)));
}

/** A line of RC-05's or RC-06's payload. */
export interface SentLine {
    readonly saleLineId: number;
    readonly refundAmount: string;
    readonly returnedSaleable: string;
    readonly returnedNotSaleable: string;
}

/** The lines that the draft corrects, in the order of the Sale, each with what it refunds and returns. */
export function draftLines(drafts: Readonly<Record<number, LineDraft>>, lines: readonly RefundLine[]): readonly SentLine[] {
    const sent: SentLine[] = [];

    for (const line of lines) {
        const draft = drafts[line.saleLineId] ?? EMPTY_LINE;
        const quantity = lineQuantity(draft, line) ?? '0';
        const goods = returnsGoods(draft, line);

        if (!refundsMoney(draft) && !goods) {
            continue;
        }

        sent.push({
            saleLineId: line.saleLineId,
            refundAmount: lineAmount(draft),
            returnedSaleable: goods && draft.condition === 'SALEABLE' ? quantity : '0',
            returnedNotSaleable: goods && draft.condition === 'NOT_SALEABLE' ? quantity : '0',
        });
    }

    return sent;
}

/** A condition in words (COPY_DECK section 7.8). */
export function conditionWord(condition: Condition): string {
    return condition === 'SALEABLE' ? t('sales.refund.condition_saleable') : t('sales.refund.condition_damaged');
}

/** A session as the source of the money is named (COPY_DECK `sales.refund.source_session`). */
export function sessionWord(session: FundingSession): string {
    return t('sales.refund.source_session', { laci: session.registerLabel, nama: session.responsible, uang: formatMoney(session.expectedCash) });
}

/**
 * The goods the draft returns, for the review: each line's quantity and product with its condition,
 * "1 pcs Pulpen Hitam · Layak jual", in the order of the Sale.
 */
export function goodsSummary(drafts: Readonly<Record<number, LineDraft>>, lines: readonly RefundLine[]): string {
    const parts: string[] = [];

    for (const line of lines) {
        const draft = drafts[line.saleLineId] ?? EMPTY_LINE;
        const quantity = lineQuantity(draft, line);

        if (quantity !== null && draft.condition !== null && returnsGoods(draft, line)) {
            parts.push(`${formatQuantity(quantity, line.quantityPrecision, line.unit)} ${line.name} · ${conditionWord(draft.condition)}`);
        }
    }

    return parts.join(', ');
}

/** A payout's method in words (D-077). */
export function payoutMethodWord(method: string): string {
    return method === CASH ? t('sales.payment.method_cash') : t('sales.payment.method_transfer');
}

/**
 * Where a committed refund's money came from, as UXI-120 names it (COPY_DECK `sales.detail.refund_entry`):
 * the drawer's register, the Owner's own funds, or, for a bank transfer, the reference of the payout made.
 */
export function payoutSourceWord(refund: CorrectionRefund): string {
    if (refund.drawer !== null) {
        return refund.drawer.registerLabel;
    }

    return refund.method === CASH ? t('sales.refund.source_owner') : refund.reference;
}

/** A returned quantity as an entry names it, with its unit: "1 pcs". */
export function returnedWord(quantity: string, entry: CorrectionEntry): string {
    return formatQuantity(quantity, entry.quantityPrecision, entry.unit);
}

/** The keys of RC-05's and RC-06's fields that take no parameter (COPY_DECK section 16.1). */
const FIELD_KEYS = [
    'ux.validation.required',
    'ux.validation.choose_one',
    'ux.validation.money',
    'ux.validation.money_min',
    'ux.validation.money_max',
    'ux.validation.quantity_positive',
    'ux.validation.quantity_whole',
    'ux.validation.quantity_max',
    'ux.validation.lines_min',
] as const;

/** A safe parameter of a refusal as a text, or an empty one. */
export function paramText(params: Readonly<Record<string, unknown>>, name: string): string {
    const value = params[name];

    return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

/** The message of a field's first refusal, or none for a key these pages do not show. */
export function refundFieldMessage(entries: unknown): string | undefined {
    if (!Array.isArray(entries)) {
        return undefined;
    }

    const first: unknown = entries[0];

    if (typeof first !== 'object' || first === null || !('key' in first)) {
        return undefined;
    }

    const raw: unknown = 'params' in first ? first.params : {};
    const params: Readonly<Record<string, unknown>> = typeof raw === 'object' && raw !== null ? Object.fromEntries(Object.entries(raw)) : {};

    if (first.key === 'ux.validation.text_max') {
        return t('ux.validation.text_max', { maks: paramText(params, 'maks') });
    }

    if (first.key === 'ux.validation.quantity_decimals') {
        return t('ux.validation.quantity_decimals', { desimal: paramText(params, 'desimal') });
    }

    const known = FIELD_KEYS.find((key) => key === first.key);

    return known === undefined ? undefined : t(known);
}

/** Whether a canonical quantity is above zero. */
export function positive(quantity: string, precision: number): boolean {
    return compareExact(parseQuantity(quantity, precision), { units: 0n, scale: 0 }) > 0;
}
