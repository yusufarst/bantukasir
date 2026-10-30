import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DEFAULT_ACCENT, isAccentToken, type AccentToken } from '@/lib/accent';
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// The BusinessProfile on the Owner's pages UXP-607 and UXP-608 (COPY_DECK
// sections 11.7 and 11.8; TASK-010): a revision as App\DTO\Configuration\
// RevisionData sends it, its fields as a key-value list, the names of the
// accent set (TASK-002, CC-05), and the note that a change was just saved,
// which UXP-608 leaves for UXP-607 when it leads there (NAVIGATION_CONTRACTS
// UXP-608: "UXP-607 showing the new version in force").

/** A BusinessProfile revision (App\DTO\Configuration\RevisionData). */
export interface Revision {
    readonly id: number;
    readonly number: number;
    readonly shopName: string;
    readonly address: string | null;
    readonly contact: string | null;
    readonly email: string | null;
    readonly documentHeader: string | null;
    readonly documentFooter: string | null;
    readonly documentIdentity: string | null;
    readonly accentToken: string;
    /** The path of RD-07 that delivers the logo, or null. */
    readonly logo: string | null;
    readonly createdAt: string;
    readonly actorDisplayName: string;
}

/** The text fields of a revision with their labels, in the order of COPY_DECK section 11.7. */
export const TEXT_FIELDS = [
    ['shopName', 'config.profile.shop_name'],
    ['address', 'config.profile.address'],
    ['contact', 'config.profile.contact'],
    ['email', 'config.profile.email'],
    ['documentHeader', 'config.profile.header_text'],
    ['documentFooter', 'config.profile.footer_text'],
    ['documentIdentity', 'config.profile.document_label'],
] as const satisfies readonly (readonly [keyof Revision, TranslationKey])[];

export type TextField = (typeof TEXT_FIELDS)[number][0];

/** The names of the accent set (TASK-002, CC-05). */
export const ACCENT_NAMES: Readonly<Record<AccentToken, TranslationKey>> = {
    green: 'config.profile.accent_option.green',
    teal: 'config.profile.accent_option.teal',
    blue: 'config.profile.accent_option.blue',
    purple: 'config.profile.accent_option.purple',
    graphite: 'config.profile.accent_option.graphite',
};

/** The name of an accent of DESIGN_SYSTEM section 3.3, never its colour alone (UXA-18). */
export function accentName(token: string): string {
    return t(ACCENT_NAMES[isAccentToken(token) ? token : DEFAULT_ACCENT]);
}

/**
 * The fields of a revision: the texts it holds — an absent optional field is
 * left out — its accent by name and its logo, delivered by RD-07.
 */
export function RevisionFields({ revision }: { revision: Revision }) {
    const items: KeyValue[] = [];

    for (const [field, label] of TEXT_FIELDS) {
        const value = revision[field];

        if (value !== null) {
            items.push({ key: field, label: t(label), value });
        }
    }

    items.push({ key: 'accent', label: t('config.profile.accent'), value: accentName(revision.accentToken) });

    if (revision.logo !== null) {
        items.push({ key: 'logo', label: t('config.profile.logo'), value: <img className={'bk-logo'} src={revision.logo} alt={t('config.profile.logo')} /> });
    }

    return <KeyValueList items={items} />;
}

let saved = false;

/** UXP-608 leads to UXP-607 after a new revision was committed. */
export function markSaved(): void {
    saved = true;
}

/** Whether UXP-607 opens just after a new revision was saved; read once. */
export function takeSaved(): boolean {
    const was = saved;
    saved = false;

    return was;
}
