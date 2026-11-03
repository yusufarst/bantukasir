import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';

// The cost pages' shared parts (TASK-025): RC-15's family, the addresses of RP-503 to RP-505 with
// the page that opened them, the values of a cost source and the checks of the correction's draft.

/** RC-15's family (INTERACTION_CONTRACTS section 1.4); it needs recent authentication (section 1.5). */
export const COST_CORRECTION_COMMAND = { operation: 'cost_correction.confirm', family: 'cost_correction', recentAuth: true } as const;

/** CAP-19, the Owner's correction or completion of a cost (SECURITY_MODEL section 6). */
export const CORRECT_CAPABILITY = 'CAP-19';

/** CAP-20, the Owner's cost evidence, history, valuation and cost reports (SECURITY_MODEL section 6). */
export const COST_HISTORY_CAPABILITY = 'CAP-20';

/** RP-503. */
export const WORKLIST_PAGE = '/reports/costs-to-complete';

/** Where the worklist stands in the shell: the Laporan destination's sub-navigation (INFORMATION_ARCHITECTURE section 4). */
export const WORKLIST_SHELL = { current: 'reports', area: 'reports', subCurrent: 'worklist' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** The pages that open UXP-504 (NAVIGATION_CONTRACTS UXP-504; RP-504 `from_page`). */
export type CorrectionOpener = 'worklist' | 'history' | 'receiving' | 'movement';

/** The pages that open UXP-505 (NAVIGATION_CONTRACTS UXP-505; RP-505 `from_page`). */
export type HistoryOpener = 'product' | 'worklist' | 'requests';

/** The largest whole rupiah of a cost (TECHNICAL_RULES section 11.1): twelve digits. */
export const COST_DIGITS_MAX = 12;

/** A cost as the cost pages carry it: its status and its canonical amount, none for UNKNOWN (BR-COST-03). */
export interface CostValue {
    readonly status: 'KNOWN' | 'UNKNOWN';
    readonly amount: string | null;
}

/** One correction of a cost source, the Owner's history (UXI-247; COPY_DECK `costing.field.correction_entry`). */
export interface CorrectionEntry {
    readonly correctionNo: number;
    /** Null when the cost was UNKNOWN before it, a completion. */
    readonly oldAmount: string | null;
    readonly newAmount: string;
    readonly zeroNote: string | null;
    readonly reason: string;
    readonly recordedAt: string;
    readonly actorDisplayName: string;
}

/** RP-504: the cost source to correct or complete. */
export function correctionPage(evidenceId: number, opener: CorrectionOpener): string {
    return `/costs/${String(evidenceId)}/correct?from_page=${opener}`;
}

/** RP-505: the cost history of a product. */
export function costHistoryPage(productId: number, opener: HistoryOpener): string {
    return `/products/${String(productId)}/cost-history?from_page=${opener}`;
}

/** The address of UXP-503 with its filter and a page of it; the first page's size is absent. */
export function worklistAddress(product: number | null, cursor: { readonly after?: string; readonly before?: string } = {}): string {
    const query = new URLSearchParams();

    if (product !== null) {
        query.set('product', String(product));
    }

    if (cursor.after !== undefined) {
        query.set('after', cursor.after);
    } else if (cursor.before !== undefined) {
        query.set('before', cursor.before);
    }

    const search = query.toString();

    return search === '' ? WORKLIST_PAGE : `${WORKLIST_PAGE}?${search}`;
}

/** "Lengkapi biaya" while the cost is UNKNOWN, "Koreksi biaya" otherwise (COPY_DECK section 4.1). */
export function correctionTitle(cost: CostValue): 'costing.correction.title_complete' | 'costing.correction.title_correct' {
    return cost.status === 'UNKNOWN' ? 'costing.correction.title_complete' : 'costing.correction.title_correct';
}

/** Whether a canonical amount is zero. */
export function isZeroAmount(amount: string | null): boolean {
    return amount !== null && /^0+(?:\.0+)?$/.test(amount);
}

/**
 * Why the new cost of UXI-317 cannot be sent, as a key, or null: required, at most twelve whole
 * digits (TECHNICAL_RULES section 11.1). The field takes whole rupiah, digits only (MoneyInput).
 */
export function newCostProblem(amount: string | null): 'ux.validation.required' | 'ux.validation.money_max' | null {
    if (amount === null || amount === '') {
        return 'ux.validation.required';
    }

    return amount.replace(/^0+(?=[0-9])/, '').length > COST_DIGITS_MAX ? 'ux.validation.money_max' : null;
}

/**
 * The payload of RC-15 for a draft whose fields passed their checks: the cost source, the new total —
 * KNOWN, an explicit zero with its evidence or reason and no other with one (BR-COST-04) — and the
 * reason, trimmed.
 */
export function correctionPayload(evidenceId: number, amount: string, zeroEvidence: string, reason: string): Readonly<Record<string, unknown>> {
    const digits = amount.replace(/^0+(?=[0-9])/, '');

    return {
        evidenceId,
        newCost: isZeroAmount(digits) ? { amount: digits, zeroEvidence: zeroEvidence.trim() } : { amount: digits },
        reason: reason.trim(),
    };
}
