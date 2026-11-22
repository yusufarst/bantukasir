import type { TranslationKey } from '@/types/translation-keys';
import { t } from '@/lib/i18n';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';

// The reconciliation incidents of UXP-414 and UXP-415 (ROUTE_CONTRACTS RP-414, RP-415 and RC-16;
// COPY_DECK sections 9.15 and 9.16; BR-INV-45, BR-INV-46; TASK-036): the Owner's alone (CAP-18). An
// incident is a posting block of the daily reconciliation: "Mutasi diblokir" while it is open, "Mutasi
// dibuka kembali" once the Owner reopened it.

/** RP-414 "Insiden rekonsiliasi". */
export const INCIDENTS_PAGE = '/stock/incidents';

/** RP-415 "Detail insiden" of one incident. */
export function incidentPage(id: number): string {
    return `${INCIDENTS_PAGE}/${String(id)}`;
}

/** Whether a path is that of an incident's page, the opener of UXP-402 (UXI-223, UXI-303). */
export function isIncidentPage(path: string): boolean {
    return /^\/stock\/incidents\/[1-9][0-9]*$/.test(path);
}

/** Both pages stand under Stok, with "Insiden rekonsiliasi" current (UXI-017). */
export const INCIDENTS_SHELL = { current: 'stock', area: 'stock', subCurrent: 'incidents' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** RC-16, family Reconciliation block or reopening (INTERACTION_CONTRACTS section 1.4); it needs recent authentication (section 1.5). */
export const REOPEN_COMMAND = { operation: 'posting_block.reopen', family: 'reopen', recentAuth: true } as const;

/** The states of UXI-301 (SCHEMA section 5.7: a block with or without its reopening). */
export type IncidentStatus = 'BLOCKED' | 'REOPENED';

export const INCIDENT_STATUSES: readonly { readonly status: IncidentStatus; readonly label: TranslationKey }[] = [
    { status: 'BLOCKED', label: 'inventory.incident.status_blocked' },
    { status: 'REOPENED', label: 'inventory.incident.status_reopened' },
];

/** The words of a state (COPY_DECK section 9.15). */
export function statusLabel(status: IncidentStatus): TranslationKey {
    return status === 'BLOCKED' ? 'inventory.incident.status_blocked' : 'inventory.incident.status_reopened';
}

/**
 * The label of each detail code of a block (BR-INV-45), as COPY_DECK section 11.15.2 approved it for "Yang tidak
 * cocok" (GATE-CC3): UXP-415 shows the label, never the stored code (TASK-051 finding F-1, D-138).
 */
const MISMATCH_LABELS = {
    BALANCE_MISMATCH: 'audit.change.value.mismatch_balance',
    VALUATION_MISMATCH: 'audit.change.value.mismatch_valuation',
    ISSUE_PROJECTION_MISMATCH: 'audit.change.value.mismatch_issue_projection',
    RETURN_PROJECTION_MISMATCH: 'audit.change.value.mismatch_return_projection',
    HISTORY_INVALID: 'audit.change.value.mismatch_history',
} as const satisfies Readonly<Record<string, TranslationKey>>;

function isMismatchCode(code: string): code is keyof typeof MISMATCH_LABELS {
    return Object.prototype.hasOwnProperty.call(MISMATCH_LABELS, code);
}

/** The words of a block's detail code; a code without an approved label shows nothing. */
export function mismatchWord(code: string): string {
    return isMismatchCode(code) ? t(MISMATCH_LABELS[code]) : '';
}

/** A row of RP-414. */
export interface IncidentRow {
    readonly id: number;
    readonly detectedAt: string;
    readonly status: IncidentStatus;
    readonly product: { readonly id: number; readonly name: string };
}

/** RP-415's incident. */
export interface IncidentDetail {
    readonly id: number;
    readonly detectedAt: string;
    readonly detailCode: string;
    readonly status: IncidentStatus;
    readonly reopenedAt: string | null;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
}

/** The address of RP-414 with its filter and a cursor; what is not set is absent (NC-10). */
export function incidentsAddress(status: IncidentStatus | null, after?: string): string {
    const query = new URLSearchParams();

    if (status !== null) {
        query.set('status', status);
    }

    if (after !== undefined) {
        query.set('after', after);
    }

    const search = query.toString();

    return search === '' ? INCIDENTS_PAGE : `${INCIDENTS_PAGE}?${search}`;
}
