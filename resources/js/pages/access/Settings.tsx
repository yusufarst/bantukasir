import { LinkRow, LinkRows } from '@/components/ui/Data';
import type { IconName } from '@/components/ui/icons';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import type { TranslationKey } from '@/types/translation-keys';

// UXP-601 "Pengaturan" (ROUTE_CONTRACTS RP-601; COPY_DECK section 11.1;
// INTERACTION_INVENTORY UXP-601; NAVIGATION_CONTRACTS UXP-601; TASK-010 scope
// item 5): one list of the Owner's settings and oversight pages, reached from
// the navigation, with no way back of its own. The server sends only the
// entries whose page exists and whose capability the user holds, in their
// order (App\Http\Controllers\Access\SettingsController); each later Task
// adds its entry there and its words here.

/** The destination of the navigation that is this page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

interface Entry {
    readonly id: string;
    readonly href: string;
}

/** The words and icon of each entry (COPY_DECK section 11.1). */
const ENTRIES: Readonly<Record<string, { readonly title: TranslationKey; readonly description: TranslationKey; readonly icon: IconName }>> = {
    // UXI-326 "Pengguna".
    users: { title: 'access.users.title', description: 'config.settings.users_desc', icon: 'user' },
    // UXI-327 "Profil usaha".
    profile: { title: 'config.profile.title', description: 'config.settings.profile_desc', icon: 'image' },
};

export default function Settings({ entries }: { entries: readonly Entry[] }) {
    const title = t('config.settings.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <PageContainer width={'form'}>
                <PageHeader title={title} />
                <LinkRows label={title}>
                    {entries.map((entry) => {
                        const words = ENTRIES[entry.id];

                        return words === undefined ? null : (
                            <li key={entry.id}>
                                <LinkRow href={entry.href} title={t(words.title)} meta={t(words.description)} icon={words.icon} />
                            </li>
                        );
                    })}
                </LinkRows>
            </PageContainer>
        </AppShell>
    );
}
