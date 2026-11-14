import { quantityFromEntry, type ProductRow } from '@/lib/catalog/products';
import { formatCount } from '@/lib/format';
import { t } from '@/lib/i18n';
import { QUANTITY_DIGITS_MAX } from '@/lib/inventory/stock';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';
import type { TranslationKey } from '@/types/translation-keys';

// The onboarding pages (ROUTE_CONTRACTS RP-412, RP-413, RD-01 `opening`; INTERACTION_CONTRACTS RC-11 to
// RC-14, RC-48 and section 3.1; COPY_DECK sections 9.13 and 9.14; BR-INV-23 to BR-INV-28, BR-INV-47,
// BR-INV-48; D-071, D-072, D-087; TASK-035 scope item 5): the onboarding state and the checklist as the
// server sends them, the commands with their families, the eligibility of a product in the context
// `opening` with its words, and the rules of a row of an opening entry. The draft of an entry lives in
// the page's memory only, never in the browser's storage (SEC-CH-05).

export const ONBOARDING_PAGE = '/stock/onboarding';

export const OPENING_ENTRY_PAGE = '/stock/onboarding/entry';

/** Where the onboarding pages stand in the shell (INFORMATION_ARCHITECTURE section 4; UXI-016). */
export const ONBOARDING_SHELL = { current: 'stock', area: 'stock', subCurrent: 'onboarding' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** RC-12, family Onboarding set or release (INTERACTION_CONTRACTS section 1.4); it needs recent authentication (section 1.5). */
export const START_COMMAND = { operation: 'onboarding.start', family: 'onboarding', recentAuth: true } as const;

/** RC-13, which shares its family with RC-12; it needs recent authentication. */
export const RELEASE_COMMAND = { operation: 'onboarding.release', family: 'onboarding', recentAuth: true } as const;

/** RC-14, family Opening stock (D-087); it needs recent authentication. */
export const WITHDRAW_COMMAND = { operation: 'onboarding.withdraw_zero_count', family: 'opening_stock', recentAuth: true } as const;

/** RC-11, family Opening stock. */
export const OPENING_COMMAND = { operation: 'opening_stock.confirm', family: 'opening_stock' } as const;

/** RC-48, family Opening stock (D-087); it needs recent authentication. */
export const REPLACE_COMMAND = { operation: 'opening_stock.replace', family: 'opening_stock', recentAuth: true } as const;

/** The most rows of one opening entry (BR-INV-25). */
export const MAX_OPENING_ROWS = 100;

/** The longest count reference and reason (INTERACTION_CONTRACTS RC-11, RC-14, RC-48). */
export const COUNT_REFERENCE_MAX = 100;

export const REASON_MAX = 1000;

/** The onboarding states of SCHEMA section 5.7. */
export type OnboardingCode = 'NOT_STARTED' | 'ACTIVE' | 'RELEASED';

/** The onboarding of RP-412: its state and version, whether a restore holds it, its start and its release. */
export interface OnboardingInfo {
    readonly state: OnboardingCode;
    readonly version: number;
    readonly held: boolean;
    readonly startedAt: string | null;
    readonly releasedAt: string | null;
}

/**
 * What UXP-412 shows (INTERACTION_INVENTORY UXI-285, UXI-286, UXI-289): the completed onboarding, read
 * only, once a release is recorded — also when restored data still show it active (D-071, D-072); held
 * while a restore waits for its reconciliation (UXS-50); otherwise running or not started.
 */
export type HubView = 'released' | 'held' | 'active' | 'not_started';

export function hubView(onboarding: OnboardingInfo): HubView {
    if (onboarding.state === 'RELEASED' || onboarding.releasedAt !== null) {
        return 'released';
    }

    if (onboarding.held) {
        return 'held';
    }

    return onboarding.state === 'ACTIVE' ? 'active' : 'not_started';
}

/** The filter of the checklist (UXI-287). */
export type ChecklistFilter = 'counted' | 'not_counted';

/** The filters of UXI-287, in their order, with their words (COPY_DECK section 9.13). */
export const CHECKLIST_FILTERS: readonly { readonly filter: ChecklistFilter; readonly label: TranslationKey }[] = [
    { filter: 'counted', label: 'inventory.onboarding.filter_counted' },
    { filter: 'not_counted', label: 'inventory.onboarding.filter_not_counted' },
];

/** A row's state (App\Queries\Inventory\OnboardingChecklist): counted, counted zero, its OPENING reversed, or still to be counted. */
export type ChecklistState = 'COUNTED' | 'ZERO_COUNTED' | 'REPLACEABLE' | 'NOT_COUNTED';

/** A row of the checklist of RP-412: the cost is its status only, never an amount. */
export interface ChecklistRow {
    readonly product: { readonly id: number; readonly name: string; readonly sku: string; readonly baseUnit: string; readonly quantityPrecision: number; readonly active: boolean };
    readonly state: ChecklistState;
    readonly quantity: string | null;
    readonly cost: 'KNOWN' | 'UNKNOWN' | null;
    readonly movementId: number | null;
    readonly zeroCountId: number | null;
}

/** The counts of the release review (UXI-289; BR-INV-28). */
export interface OnboardingCounts {
    readonly counted: number;
    readonly notCounted: number;
    readonly unknownCost: number;
}

/** The address of UXP-412 with its filter and a cursor; what is not set is absent (NC-10). */
export function onboardingAddress(filter: ChecklistFilter | null, cursor: { readonly after?: string; readonly before?: string } = {}): string {
    const query = new URLSearchParams();

    if (filter !== null) {
        query.set('filter', filter);
    }

    if (cursor.after !== undefined) {
        query.set('after', cursor.after);
    }

    if (cursor.before !== undefined) {
        query.set('before', cursor.before);
    }

    const search = query.toString();

    return search === '' ? ONBOARDING_PAGE : `${ONBOARDING_PAGE}?${search}`;
}

/** UXP-413, with the product whose reversed OPENING it records again (UXI-291, "Catat ulang stok awal"). */
export function openingEntryPage(product: number | null = null): string {
    return product === null ? OPENING_ENTRY_PAGE : `${OPENING_ENTRY_PAGE}?${new URLSearchParams({ product: String(product) }).toString()}`;
}

/** The eligibility of D-087 that RD-01 gives in the context `opening` (INTERACTION_CONTRACTS section 3.1). */
export type Eligibility = 'ELIGIBLE' | 'ZERO_COUNTED' | 'COUNTED' | 'REPLACEABLE' | 'HAS_MOVEMENT';

const ELIGIBILITIES: readonly Eligibility[] = ['ELIGIBLE', 'ZERO_COUNTED', 'COUNTED', 'REPLACEABLE', 'HAS_MOVEMENT'];

/** A product of RD-01 in the context `opening`: with the version that RC-11 and RC-48 expect and its eligibility. */
export interface OpeningProduct {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly version: number;
    readonly active: boolean;
    readonly eligibility: Eligibility;
    /** The line that a replacement would replace (RC-48 `replacesLineId`), for REPLACEABLE only. */
    readonly replacesLineId: number | null;
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function keyOrNull(value: unknown): number | null {
    return typeof value === 'number' && Number.isSafeInteger(value) ? value : null;
}

/** A row of RD-01 in the context `opening`, or null when it lacks its version or eligibility. */
export function openingProductOf(row: ProductRow): OpeningProduct | null {
    const extra = row as ProductRow & { readonly version?: unknown; readonly opening?: unknown };
    const version = keyOrNull(extra.version);
    const opening = isRecord(extra.opening) ? extra.opening : null;
    const eligibility = opening === null ? undefined : ELIGIBILITIES.find((candidate) => candidate === opening.eligibility);

    if (version === null || opening === null || eligibility === undefined) {
        return null;
    }

    return {
        id: row.id,
        name: row.name,
        sku: row.sku,
        baseUnit: row.baseUnit,
        quantityPrecision: row.quantityPrecision,
        version,
        active: row.active,
        eligibility,
        replacesLineId: eligibility === 'REPLACEABLE' ? keyOrNull(opening.replacesLineId) : null,
    };
}

/**
 * The words of a product that a new row cannot take (UXI-292; COPY_DECK section 9.14), or null when it
 * can: another movement first (BR-INV-26); counted, with an OPENING or a zero count that is effective
 * (G-22); or its OPENING reversed, recorded again by its replacement entry (RC-48).
 */
export function eligibilityWord(eligibility: Eligibility): TranslationKey | null {
    switch (eligibility) {
        case 'ELIGIBLE':
            return null;
        case 'ZERO_COUNTED':
            return 'inventory.opening_entry.already_zero';
        case 'COUNTED':
            return 'inventory.onboarding.filter_counted';
        case 'REPLACEABLE':
            return 'inventory.opening_entry.replace_eligible';
        case 'HAS_MOVEMENT':
            return 'inventory.opening_entry.not_eligible';
    }
}

/**
 * Why a row's quantity cannot be sent (COPY_DECK section 16.1), or null: a count in the product's
 * precision within the largest, where 0 records a zero count (BR-INV-27) unless `positive` asks for
 * more, as a replacement does (RC-48). A text that is no number is refused as QuantityRule refuses it.
 */
export function countProblem(text: string, precision: number, positive: boolean): string | null {
    const entry = text.trim();

    if (entry === '') {
        return t('ux.validation.required');
    }

    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(quantityFromEntry(entry));

    if (match === null || (positive && /^0+$/.test(`${match[1] ?? ''}${match[2] ?? ''}`))) {
        return t('ux.validation.quantity_positive');
    }

    if ((match[2] ?? '').replace(/0+$/, '').length > precision) {
        return precision === 0 ? t('ux.validation.quantity_whole') : t('ux.validation.quantity_decimals', { desimal: formatCount(precision) });
    }

    return (match[1] ?? '').replace(/^0+/, '').length > QUANTITY_DIGITS_MAX ? t('ux.validation.quantity_max') : null;
}

/** Whether a row's quantity as typed is a count of zero (BR-INV-27). */
export function isZeroCount(text: string): boolean {
    return /^0+(?:\.0+)?$/.test(quantityFromEntry(text));
}
