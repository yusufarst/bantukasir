import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// The cash pages (COPY_DECK sections 7.4, 11.9 and 11.10; TASK-027 scope item 4): the registers as
// App\Queries\Cash\CashRegisterRows sends them, the commands RC-02, RC-33 and RC-34 with their
// families and recent authentication (INTERACTION_CONTRACTS sections 1.4 and 1.5), their paths, and
// the messages of their fields (COPY_DECK section 16.1).

/** A register of UXP-609 and UXP-610, with the responsible user of its OPEN session, if any. */
export interface RegisterRow {
    readonly id: number;
    readonly code: string;
    readonly label: string;
    readonly active: boolean;
    readonly version: number;
    readonly openBy: string | null;
}

/** A register a session may open on now (UXI-094). */
export interface AvailableRegister {
    readonly id: number;
    readonly code: string;
    readonly label: string;
}

export const REGISTER_COMMANDS = {
    create: { operation: 'cash_register.create', family: 'register', recentAuth: true },
    update: { operation: 'cash_register.update', family: 'register', recentAuth: true },
} as const;

export const OPEN_SESSION_COMMAND = { operation: 'cash_session.open', family: 'open_session', recentAuth: false } as const;

/** RP-609, RP-610 and RP-601 (ROUTE_CONTRACTS section 2). */
export const REGISTERS_PAGE = '/settings/cash-registers';

export const NEW_REGISTER_PAGE = `${REGISTERS_PAGE}/new`;

export const SETTINGS_PAGE = '/settings';

export function registerEditPage(id: number): string {
    return `${REGISTERS_PAGE}/${String(id)}/edit`;
}

/** How a register is named on screens: its code and its label (SCHEMA section 5.10). */
export function registerName(register: { readonly code: string; readonly label: string }): string {
    return register.code === register.label ? register.code : `${register.code} · ${register.label}`;
}

/** The keys of the fields of RC-02, RC-33 and RC-34 that take no parameter. */
const FIELD_KEYS = [
    'ux.validation.required',
    'ux.validation.choose_one',
    'ux.validation.money',
    'ux.validation.money_blank',
    'ux.validation.money_max',
    'ux.validation.money_min',
    'ux.validation.register_code_taken',
    'ux.validation.register_in_use',
    'ux.validation.register_taken',
] as const satisfies readonly TranslationKey[];

/** The message of a field's first refusal (COPY_DECK section 16.1), or none for a key these forms do not show. */
export function cashFieldMessage(entries: unknown): string | undefined {
    if (!Array.isArray(entries)) {
        return undefined;
    }

    const first: unknown = entries[0];

    if (typeof first !== 'object' || first === null || !('key' in first)) {
        return undefined;
    }

    const params: unknown = 'params' in first ? first.params : {};
    const limit = typeof params === 'object' && params !== null && 'maks' in params ? params.maks : '';

    if (first.key === 'ux.validation.text_max') {
        return t('ux.validation.text_max', { maks: typeof limit === 'string' || typeof limit === 'number' ? String(limit) : '' });
    }

    const known = FIELD_KEYS.find((key) => key === first.key);

    return known === undefined ? undefined : t(known);
}
