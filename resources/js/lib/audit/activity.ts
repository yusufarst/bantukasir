import { userPage } from '@/lib/access/users';
import { sessionPage } from '@/lib/cash/sessions';
import { productPage } from '@/lib/catalog/products';
import { t } from '@/lib/i18n';
import { issuePage, movementPage, openerOf, receivingPage } from '@/lib/inventory/stock';
import { salePage } from '@/lib/sales/sales';
import type { TranslationKey } from '@/types/translation-keys';

// The words and addresses of the audit views, UXP-612 "Riwayat aktivitas" and UXP-613 "Riwayat keamanan"
// (ROUTE_CONTRACTS RP-612, RP-613; COPY_DECK sections 11.12 to 11.14; INTERACTION_CONTRACTS section 8;
// NAVIGATION_CONTRACTS UXP-612, UXP-613; TASK-042): the label of every action code, the event types and
// outcomes of the security log, who acted, the page of an event's reference (UXI-379), the addresses of
// both lists with their filters and page (NC-10), and the way back to UXP-612 of the pages it opens
// (UXI-177, UXI-251, UXI-267, UXI-349).

export const ACTIVITY_PAGE = '/settings/activity';

export const SECURITY_PAGE = '/settings/security-log';

export const SETTINGS_PAGE = '/settings';

export const AUDIT_PER_PAGE = 25;

/** The label of every action code of INTERACTION_CONTRACTS section 8 (COPY_DECK section 11.14), as App\Domain\Audit\AuditActions holds it. */
export const ACTION_LABELS = {
    SALE_CHECKOUT: 'audit.action.sale',
    CASH_SESSION_OPEN: 'audit.action.open_session',
    CASH_SESSION_CLOSE: 'audit.action.close_session',
    CASH_SESSION_CLOSE_ASSISTED: 'audit.action.assisted_close',
    SALE_CORRECTION: 'audit.action.refund',
    RECEIVING_CONFIRM: 'audit.action.receipt',
    MANUAL_ISSUE_CONFIRM: 'audit.action.manual_issue',
    ADJUSTMENT_CONFIRM: 'audit.action.adjustment',
    REVERSAL_CONFIRM: 'audit.action.reversal',
    OPENING_STOCK_CONFIRM: 'audit.action.opening_stock',
    OPENING_STOCK_REPLACE: 'audit.action.opening_stock_replace',
    ZERO_COUNT_WITHDRAW: 'audit.action.zero_count_withdraw',
    ONBOARDING_START: 'audit.action.onboarding_start',
    ONBOARDING_RELEASE: 'audit.action.onboarding_release',
    COST_CORRECTION: 'audit.action.cost_correction',
    COST_INCOMPLETE_MARK: 'audit.action.cost_incomplete_mark',
    RECONCILIATION_BLOCK: 'audit.action.reconciliation_block',
    POSTING_REOPEN: 'audit.action.reopen',
    PRODUCT_CREATE: 'audit.action.product_create',
    PRODUCT_EDIT: 'audit.action.product_edit',
    PRICE_CHANGE: 'audit.action.price_change',
    PRODUCT_DEACTIVATE: 'audit.action.product_deactivate',
    PRODUCT_REACTIVATE: 'audit.action.product_reactivate',
    CATEGORY_CREATE: 'audit.action.category_create',
    CATEGORY_RENAME: 'audit.action.category_rename',
    CATEGORY_DEACTIVATE: 'audit.action.category_deactivate',
    CATEGORY_REACTIVATE: 'audit.action.category_reactivate',
    PHOTO_CHANGE: 'audit.action.photo_change',
    PHOTO_REMOVE: 'audit.action.photo_remove',
    BARCODE_ADD: 'audit.action.barcode_add',
    BARCODE_GENERATE: 'audit.action.barcode_generate',
    BARCODE_RETIRE: 'audit.action.barcode_retire',
    BARCODE_CORRECTION: 'audit.action.barcode_correction',
    CSV_IMPORT: 'audit.action.csv_import',
    PROFILE_CHANGE: 'audit.action.profile_change',
    REGISTER_CREATE: 'audit.action.register_create',
    REGISTER_CHANGE: 'audit.action.register_change',
    USER_CREATE: 'audit.action.user_create',
    ROLE_CHANGE: 'audit.action.role_change',
    DETAILS_CHANGE: 'audit.action.details_change',
    USER_DEACTIVATE: 'audit.action.user_deactivate',
    USER_REACTIVATE: 'audit.action.user_reactivate',
    USER_ANONYMIZE: 'audit.action.user_anonymize',
    LINK_ISSUE: 'audit.action.link_issue',
    LINK_REVOKE: 'audit.action.link_revoke',
    LINK_CONSUME: 'audit.action.link_consume',
    PASSWORD_CHANGE: 'audit.action.password_change',
    OWNER_RECOVERY: 'audit.action.owner_recovery',
    OWNER_REPLACEMENT: 'audit.action.owner_replacement',
    REVIEW_NOTE_ADD: 'audit.action.review_note',
    PRINT: 'audit.action.print',
    EXPORT: 'audit.action.export',
    ONBOARDING_RESTORE_HOLD: 'audit.action.onboarding_restore_hold',
    RESTORE_RECONCILIATION: 'audit.action.restore_reconciliation',
    PROJECTION_REBUILT: 'audit.action.projection_rebuilt',
} as const satisfies Readonly<Record<string, TranslationKey>>;

export type ActionCode = keyof typeof ACTION_LABELS;

/** The action codes in the order of section 8, for the filter "Jenis" (UXI-377). */
export const ACTION_CODES = Object.keys(ACTION_LABELS) as readonly ActionCode[];

export function isActionCode(code: string): code is ActionCode {
    return Object.prototype.hasOwnProperty.call(ACTION_LABELS, code);
}

/** The label of an action code; a code that section 8 does not list keeps its code, never an empty cell. */
export function actionWord(code: string): string {
    return isActionCode(code) ? t(ACTION_LABELS[code]) : code;
}

/** The nine event types of the security log (SCHEMA section 5.4) with their labels (UXI-383). */
export const SECURITY_TYPES = [
    { type: 'SIGN_IN_SUCCEEDED', label: 'audit.security.type.sign_in_succeeded' },
    { type: 'SIGN_IN_FAILED', label: 'audit.security.type.sign_in_failed' },
    { type: 'THROTTLED', label: 'audit.security.type.throttled' },
    { type: 'LINK_FAILED', label: 'audit.security.type.link_failed' },
    { type: 'PASSWORD_CONFIRMATION_FAILED', label: 'audit.security.type.password_confirmation_failed' },
    { type: 'CSRF_FAILED', label: 'audit.security.type.csrf_failed' },
    { type: 'AUTHORIZATION_REFUSED', label: 'audit.security.type.authorization_refused' },
    { type: 'SESSION_REVOKED', label: 'audit.security.type.session_revoked' },
    { type: 'OPERATOR_PROCEDURE_RUN', label: 'audit.security.type.operator_procedure_run' },
] as const satisfies readonly { readonly type: string; readonly label: TranslationKey }[];

export type SecurityType = (typeof SECURITY_TYPES)[number]['type'];

export function securityTypeOf(value: string): SecurityType | null {
    return SECURITY_TYPES.find((entry) => entry.type === value)?.type ?? null;
}

export function securityTypeWord(type: string): string {
    const entry = SECURITY_TYPES.find((one) => one.type === type);

    return entry === undefined ? type : t(entry.label);
}

/** SUCCEEDED or REFUSED (INTERACTION_CONTRACTS section 8); a further code has no label until change control gives it one. */
export function outcomeWord(outcome: string): string {
    switch (outcome) {
        case 'SUCCEEDED':
            return t('audit.security.outcome.succeeded');
        case 'REFUSED':
            return t('audit.security.outcome.refused');
        default:
            return outcome;
    }
}

/** Who acted, as an event names it (SCHEMA section 3.4). */
export interface AuditActor {
    readonly kind: string;
    readonly name: string | null;
}

/**
 * Who acted: a user by the name the record holds — the business audit froze it at that moment (G-16), the
 * security log names the account now; an operator procedure; a scheduled command; before sign-in.
 */
export function actorWord(actor: AuditActor, log: 'activity' | 'security'): string {
    switch (actor.kind) {
        case 'USER':
            return actor.name ?? '';
        case 'OPERATOR_PROCEDURE':
            return t('ux.actor.operator');
        case 'SCHEDULED_COMMAND':
            return log === 'security' ? t('audit.security.actor.scheduled') : t('ux.actor.system');
        default:
            return t('audit.security.actor.anonymous');
    }
}

/** The reference of an event (UXI-379): the object's page when it has one, with its number or name. */
export interface AuditReference {
    readonly text: string | null;
    readonly kind: 'sale' | 'session' | 'product' | 'movement' | 'receiving' | 'issue' | 'user' | null;
    readonly id: number | null;
}

/** The title of the object's page, the link's text when the object has no number or name. */
const REFERENCE_TITLES: Readonly<Record<NonNullable<AuditReference['kind']>, TranslationKey>> = {
    sale: 'sales.detail.title',
    session: 'cash.session_detail.title',
    product: 'catalog.product_detail.title',
    movement: 'inventory.movement.title',
    receiving: 'inventory.receipt_detail.title',
    issue: 'inventory.issue_detail.title',
    user: 'access.user_detail.title',
};

/** The page of an event's reference, or null for an object without one, shown as text. */
export function referenceLink(reference: AuditReference): { readonly href: string; readonly text: string } | null {
    if (reference.kind === null || reference.id === null) {
        return null;
    }

    const text = reference.text ?? t(REFERENCE_TITLES[reference.kind]);

    switch (reference.kind) {
        case 'sale':
            return { href: salePage(reference.id), text };
        case 'session':
            return { href: sessionPage(reference.id), text };
        case 'product':
            return { href: productPage(reference.id), text };
        case 'movement':
            return { href: movementPage(reference.id), text };
        case 'receiving':
            return { href: receivingPage(reference.id), text };
        case 'issue':
            return { href: issuePage(reference.id), text };
        case 'user':
            return { href: userPage(reference.id), text };
    }
}

/** The filters of UXP-612 as they stand in its address (RP-612). */
export interface ActivityFilters {
    readonly from: string | null;
    readonly to: string | null;
    readonly user: number | null;
    readonly action: string | null;
    readonly product: number | null;
    readonly session: string | null;
    readonly reference: string | null;
    readonly perPage: number;
}

/** The filters of UXP-613 as they stand in its address (RP-613). */
export interface SecurityFilters {
    readonly from: string | null;
    readonly to: string | null;
    readonly type: string | null;
    readonly user: number | null;
    readonly perPage: number;
}

type Cursor = { readonly after?: string; readonly before?: string };

function withCursor(query: URLSearchParams, perPage: number, cursor: Cursor): string {
    if (perPage !== AUDIT_PER_PAGE) {
        query.set('perPage', String(perPage));
    }

    if (cursor.after !== undefined) {
        query.set('after', cursor.after);
    } else if (cursor.before !== undefined) {
        query.set('before', cursor.before);
    }

    return query.toString();
}

/** The address of UXP-612 with its filters and page; what is not set, and the first page's size, are absent. */
export function activityAddress(filters: ActivityFilters, cursor: Cursor = {}): string {
    const query = new URLSearchParams();

    for (const [name, value] of [
        ['from', filters.from],
        ['to', filters.to],
        ['user', filters.user === null ? null : String(filters.user)],
        ['action', filters.action],
        ['product', filters.product === null ? null : String(filters.product)],
        ['session', filters.session],
        ['reference', filters.reference],
    ] as const) {
        if (value !== null && value !== '') {
            query.set(name, value);
        }
    }

    const search = withCursor(query, filters.perPage, cursor);

    return search === '' ? ACTIVITY_PAGE : `${ACTIVITY_PAGE}?${search}`;
}

/** The address of UXP-613 with its filters and page; what is not set, and the first page's size, are absent. */
export function securityAddress(filters: SecurityFilters, cursor: Cursor = {}): string {
    const query = new URLSearchParams();

    for (const [name, value] of [
        ['from', filters.from],
        ['to', filters.to],
        ['type', filters.type],
        ['user', filters.user === null ? null : String(filters.user)],
    ] as const) {
        if (value !== null && value !== '') {
            query.set(name, value);
        }
    }

    const search = withCursor(query, filters.perPage, cursor);

    return search === '' ? SECURITY_PAGE : `${SECURITY_PAGE}?${search}`;
}

/** The account of the filter "Pengguna" that a select's value names, or null when it was emptied. */
export function accountOf(accounts: readonly { readonly id: number }[], value: string): number | null {
    return accounts.find((account) => String(account.id) === value)?.id ?? null;
}

/**
 * The way back to UXP-612 of a page that it opened (UXI-177, UXI-251, UXI-267, UXI-349; NC-01, NC-10): its
 * address with the filters and page it showed, and its title; null when another page opened this one.
 */
export function activityBack(referrer: string, origin: string): { readonly href: string; readonly page: string } | null {
    const opener = openerOf(referrer, origin);

    return opener !== null && opener.pathname === ACTIVITY_PAGE ? { href: `${opener.pathname}${opener.search}`, page: t('audit.activity.title') } : null;
}
