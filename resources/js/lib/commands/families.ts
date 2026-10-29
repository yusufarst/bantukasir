import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// The command families of the client (INTERACTION_CONTRACTS section 1.4;
// NAVIGATION_CONTRACTS NC-13; COPY_DECK section 14.1; TASK-009): the family of
// each operation of ROUTE_CONTRACTS section 6 — the same table as the server's
// App\Domain\Commands\Operations, which a unit test compares — the family's
// label and the text of what must not happen meanwhile, and the rule of NC-13
// that says whether an unresolved request blocks a new command.

/** The family of every V1 operation, as App\Domain\Commands\Operations::FAMILIES lists it. */
export const OPERATION_FAMILIES = {
    'sale.checkout': 'sale',
    'cash_session.open': 'open_session',
    'cash_session.close': 'close_session',
    'cash_session.close_assisted': 'close_session',
    'sale_correction.drawer_funded': 'refund_drawer',
    'sale_correction.other': 'refund_other',
    'receiving.confirm': 'stock_posting',
    'manual_issue.confirm': 'stock_posting',
    'adjustment.confirm': 'stock_posting',
    'reversal.confirm': 'reversal',
    'opening_stock.confirm': 'opening_stock',
    'onboarding.start': 'onboarding',
    'onboarding.release': 'onboarding',
    'onboarding.withdraw_zero_count': 'opening_stock',
    'cost_correction.confirm': 'cost_correction',
    'posting_block.reopen': 'reopen',
    'product.create': 'product_create',
    'product.update': 'product_edit',
    'product.deactivate': 'product_edit',
    'product.reactivate': 'product_edit',
    'category.create': 'category',
    'category.rename': 'category',
    'category.deactivate': 'category',
    'category.reactivate': 'category',
    'product_photo.set': 'photo',
    'product_photo.remove': 'photo',
    'barcode.add': 'barcode',
    'barcode.generate': 'barcode',
    'barcode.retire': 'barcode',
    'barcode.correct_misassignment': 'barcode_correction',
    'csv_import.confirm': 'csv_import',
    'business_profile.change': 'profile',
    'cash_register.create': 'register',
    'cash_register.update': 'register',
    'user.create': 'users',
    'user.update': 'users',
    'owner.update_details': 'users',
    'user.deactivate': 'users',
    'user.reactivate': 'users',
    'user.anonymize': 'users',
    'user.issue_link': 'users',
    'user.revoke_link': 'users',
    'review_note.add': 'review_note',
    'document.print_receipt': 'print',
    'document.print_session_report': 'print',
    'document.export_session_report': 'print',
    'document.print_labels': 'print',
    'opening_stock.replace': 'opening_stock',
    'posting_block.block': 'reopen',
} as const;

export type Operation = keyof typeof OPERATION_FAMILIES;

export type FamilyId = (typeof OPERATION_FAMILIES)[Operation];

/** Print, reprint and export: no label, no panel, exempt from the block (NC-13; UI_STATES section 4). */
export const EXEMPT_FAMILY = 'print';

export type LabelledFamily = Exclude<FamilyId, typeof EXEMPT_FAMILY>;

/** The label of each family, which also names a request on UXP-006 (COPY_DECK section 14.1). */
const LABELS: Readonly<Record<LabelledFamily, TranslationKey>> = {
    sale: 'commands.family.sale',
    open_session: 'commands.family.open_session',
    close_session: 'commands.family.close_session',
    refund_drawer: 'commands.family.refund_drawer',
    refund_other: 'commands.family.refund_other',
    stock_posting: 'commands.family.stock_posting',
    opening_stock: 'commands.family.opening_stock',
    reversal: 'commands.family.reversal',
    cost_correction: 'commands.family.cost_correction',
    product_create: 'commands.family.product_create',
    product_edit: 'commands.family.product_edit',
    category: 'commands.family.category',
    photo: 'commands.family.photo',
    barcode: 'commands.family.barcode',
    barcode_correction: 'commands.family.barcode_correction',
    csv_import: 'commands.family.csv_import',
    onboarding: 'commands.family.onboarding',
    profile: 'commands.family.profile',
    register: 'commands.family.register',
    users: 'commands.family.users',
    reopen: 'commands.family.reopen',
    review_note: 'commands.family.review_note',
};

/** What must not happen while a request of the family is uncertain (COPY_DECK section 14.1). */
const MUST_NOT: Readonly<Record<LabelledFamily, TranslationKey>> = {
    sale: 'commands.family.sale.must_not',
    open_session: 'commands.family.open_session.must_not',
    close_session: 'commands.family.close_session.must_not',
    refund_drawer: 'commands.family.refund_drawer.must_not',
    refund_other: 'commands.family.refund_other.must_not',
    stock_posting: 'commands.family.stock_posting.must_not',
    opening_stock: 'commands.family.opening_stock.must_not',
    reversal: 'commands.family.reversal.must_not',
    cost_correction: 'commands.family.cost_correction.must_not',
    product_create: 'commands.family.product_create.must_not',
    product_edit: 'commands.family.edit.must_not',
    category: 'commands.family.edit.must_not',
    photo: 'commands.family.edit.must_not',
    barcode: 'commands.family.edit.must_not',
    barcode_correction: 'commands.family.barcode_correction.must_not',
    csv_import: 'commands.family.csv_import.must_not',
    onboarding: 'commands.family.onboarding.must_not',
    profile: 'commands.family.edit.must_not',
    register: 'commands.family.edit.must_not',
    users: 'commands.family.users.must_not',
    reopen: 'commands.family.reopen.must_not',
    review_note: 'commands.family.review_note.must_not',
};

/** A family that the client labels; the exempt family and anything else are not. */
export function isLabelledFamily(family: string): family is LabelledFamily {
    return Object.hasOwn(LABELS, family);
}

function isOperation(operation: string): operation is Operation {
    return Object.hasOwn(OPERATION_FAMILIES, operation);
}

/** The family of an operation of the server's list, or null for anything else. */
export function familyOf(operation: string): FamilyId | null {
    return isOperation(operation) ? OPERATION_FAMILIES[operation] : null;
}

export function familyLabel(family: LabelledFamily): string {
    return t(LABELS[family]);
}

export function familyMustNot(family: LabelledFamily): string {
    return t(MUST_NOT[family]);
}

/**
 * The operations of the user-management family that stay available while
 * another operation of that family is unresolved, because they only end
 * access (NC-13 (d); D-056, D-069 (d)).
 */
const ACCESS_ENDING: readonly string[] = ['user.deactivate', 'user.revoke_link'];

/** An unresolved request as the block reads it: its operation and its family. */
export interface Unresolved {
    readonly operation: string;
    readonly family: string;
}

/**
 * The unresolved request that blocks a new command of this operation and
 * family (NC-13), or null: any unresolved request of the same family does,
 * except that print, reprint and export are never blocked and never block,
 * and that a deactivation or a revocation of a link is blocked only by an
 * unresolved request of that same operation (NC-13 (d)). The caller gives
 * the user's own requests only, the Owner's included: other users' requests
 * never block the Owner (INTERACTION_CONTRACTS section 1.4).
 */
export function blockingRequest<U extends Unresolved>(unresolved: readonly U[], operation: string, family: string): U | null {
    if (family === EXEMPT_FAMILY) {
        return null;
    }

    for (const request of unresolved) {
        if (request.family !== family) {
            continue;
        }

        if (family === 'users' && ACCESS_ENDING.includes(operation) && request.operation !== operation) {
            continue;
        }

        return request;
    }

    return null;
}
