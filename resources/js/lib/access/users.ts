import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// The Owner's user pages (COPY_DECK sections 3, 6.6 and 11.2 to 11.6; TASK-011
// scope item 4): the accounts as App\DTO\Access sends them, the words of their
// roles and statuses, the commands of RC-35 to RC-42, the messages of their
// fields, and the two things that pass from one page to the next in page memory
// only: a one-time link (SEC-ACC-05), taken once by UXP-606, and the note that a
// change was just saved.

export type RoleCode = 'SUPER_ADMIN' | 'OPERATIONS_ADMIN' | 'CASHIER';

export type StatusCode = 'ACTIVE' | 'DEACTIVATED' | 'ANONYMIZED';

export type StaffRoleName = 'cashier' | 'operationsAdmin';

/** The family and recent authentication of every user-management command (INTERACTION_CONTRACTS section 1.5). */
export const USER_COMMANDS = {
    create: { operation: 'user.create', family: 'users', recentAuth: true },
    update: { operation: 'user.update', family: 'users', recentAuth: true },
    ownerDetails: { operation: 'owner.update_details', family: 'users', recentAuth: true },
    deactivate: { operation: 'user.deactivate', family: 'users', recentAuth: true },
    reactivate: { operation: 'user.reactivate', family: 'users', recentAuth: true },
    anonymize: { operation: 'user.anonymize', family: 'users', recentAuth: true },
    issueLink: { operation: 'user.issue_link', family: 'users', recentAuth: true },
    revokeLink: { operation: 'user.revoke_link', family: 'users', recentAuth: true },
} as const;

export const USERS_PAGE = '/settings/users';

export function userPage(id: number): string {
    return `${USERS_PAGE}/${String(id)}`;
}

/** Where UXP-602 keeps its filters for its child pages (NC-10): a harmless interface preference, cleared at sign-out (SEC-CH-05). */
const FILTERS_KEY = 'bk.users.filters';

/** What the filters of UXP-602 may be: the two allowlisted parameters of RP-602, nothing else. */
const FILTERS_QUERY = /^\?(?:(?:status|role)=[a-z_]+)(?:&(?:status|role)=[a-z_]+)?$/;

/** UXP-602 remembers the filters it shows, as they stand in its address. */
export function rememberUsersFilters(search: string): void {
    try {
        window.sessionStorage.setItem(FILTERS_KEY, FILTERS_QUERY.test(search) ? search : '');
    } catch {
        // Without the storage, the way back simply shows the whole list.
    }
}

/** The address of UXP-602 with the filters it last showed, for the way back of its child pages (NC-01, NC-10). */
export function usersListAddress(): string {
    try {
        const search = window.sessionStorage.getItem(FILTERS_KEY) ?? '';

        return FILTERS_QUERY.test(search) ? `${USERS_PAGE}${search}` : USERS_PAGE;
    } catch {
        return USERS_PAGE;
    }
}

const ROLE_LABELS: Readonly<Record<RoleCode, TranslationKey>> = {
    SUPER_ADMIN: 'access.role.super_admin',
    OPERATIONS_ADMIN: 'access.role.operations_admin',
    CASHIER: 'access.role.cashier',
};

const STATUS_LABELS: Readonly<Record<StatusCode, TranslationKey>> = {
    ACTIVE: 'access.user.status.active',
    DEACTIVATED: 'access.user.status.deactivated',
    ANONYMIZED: 'access.user.status.anonymized',
};

/** The roles of one account, joined by a comma (COPY_DECK section 3). */
export function rolesText(roles: readonly RoleCode[]): string {
    return t('access.role.list', { peran: roles.map((role) => t(ROLE_LABELS[role])).join(', ') });
}

export function statusText(status: StatusCode): string {
    return t(STATUS_LABELS[status]);
}

/** The roles of a staff form, with their words (UXI-336, UXI-350). */
export const STAFF_ROLES: readonly { readonly name: StaffRoleName; readonly code: RoleCode; readonly label: TranslationKey; readonly description: TranslationKey }[] = [
    { name: 'cashier', code: 'CASHIER', label: 'access.role.cashier', description: 'access.role.cashier.description' },
    { name: 'operationsAdmin', code: 'OPERATIONS_ADMIN', label: 'access.role.operations_admin', description: 'access.role.operations_admin.description' },
];

/** The message of a field's first refusal (COPY_DECK sections 11.3 and 16), or none for a key these forms do not show. */
export function fieldMessage(entries: unknown): string | undefined {
    if (!Array.isArray(entries)) {
        return undefined;
    }

    const first: unknown = entries[0];

    if (typeof first !== 'object' || first === null || !('key' in first)) {
        return undefined;
    }

    const params: unknown = 'params' in first ? first.params : {};
    const limit = typeof params === 'object' && params !== null && 'maks' in params ? params.maks : '';

    switch (first.key) {
        case 'ux.validation.required':
            return t('ux.validation.required');
        case 'ux.validation.text_max':
            return t('ux.validation.text_max', { maks: typeof limit === 'string' || typeof limit === 'number' ? String(limit) : '' });
        case 'ux.validation.email':
            return t('ux.validation.email');
        case 'access.user_form.email_taken':
            return t('access.user_form.email_taken');
        case 'access.user_form.email_taken_deactivated':
            return t('access.user_form.email_taken_deactivated');
        default:
            return undefined;
    }
}

/** A one-time link on its way to UXP-606, with what the page says about it. */
export interface HeldLink {
    readonly userId: number;
    readonly url: string;
    readonly expiresAt: string;
    readonly kind: 'FIRST_PASSWORD' | 'RESET';
}

let heldLink: HeldLink | null = null;

/** RC-35 or RC-41 answered with the link: it is held here, in this page's memory, until UXP-606 takes it. */
export function holdLink(link: HeldLink): void {
    heldLink = link;
}

/** UXP-606 takes the link once; a reload, Back or a new visit finds none and shows UXS-49 (SEC-ACC-05). */
export function takeLink(userId: number): HeldLink | null {
    const taken = heldLink;
    heldLink = null;

    return taken?.userId === userId ? taken : null;
}

let savedNote: string | null = null;

/** A page leads to the next one after a command, which shows that it was saved (UXS-04). */
export function leaveSavedNote(note: string): void {
    savedNote = note;
}

/** The note of the page before, read once. */
export function takeSavedNote(): string | null {
    const note = savedNote;
    savedNote = null;

    return note;
}
