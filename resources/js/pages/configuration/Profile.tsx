import { useState } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { Notice } from '@/components/ui/Notice';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { RevisionFields, takeSaved, type Revision } from '@/lib/configuration/profile';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-607 "Profil usaha" (ROUTE_CONTRACTS RP-607; COPY_DECK section 11.7;
// INTERACTION_INVENTORY UXI-358 to UXI-360; NAVIGATION_CONTRACTS UXP-607;
// BR-CFG-02; TASK-010 scope item 5): the revision in force and the earlier
// ones, never deleted, each opened on demand; "Ubah profil usaha" leads to
// UXP-608; the way back is "Kembali ke Pengaturan". After a change it shows
// that the new version was saved (UXS-04 → UXP-607).

const SETTINGS = '/settings';

const EDIT = '/settings/business-profile/edit';

/** The destination of the navigation that holds the page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

export default function Profile({ current, earlier }: { current: Revision; earlier: readonly Revision[] }) {
    const title = t('config.profile.title');
    const [saved] = useState(takeSaved);

    return (
        <AppShell title={title} current={DESTINATION}>
            <PageContainer width={'form'}>
                <PageHeader
                    title={title}
                    back={{ href: SETTINGS, page: t('config.settings.title') }}
                    actions={<ButtonLink href={EDIT} label={t('config.profile_edit.title')} variant={'primary'} />}
                />
                <div className={'bk-stack'}>
                    {saved && (
                        <Notice tone={'success'} urgency={'status'}>
                            <p>{t('config.profile_edit.success')}</p>
                        </Notice>
                    )}
                    <Section title={t('config.profile.current_version')}>
                        <p className={'bk-meta'}>{versionRow(current)}</p>
                        <RevisionFields revision={current} />
                    </Section>
                    {earlier.length > 0 && (
                        <Section title={t('config.profile.versions')}>
                            <ul className={'bk-versions'}>
                                {earlier.map((revision) => (
                                    <li key={revision.id}>
                                        <details className={'bk-version'}>
                                            <summary>
                                                <span className={'bk-sr-only'}>{t('config.profile.version_expand', { nomor: String(revision.number) })} </span>
                                                <span>{versionRow(revision)}</span>
                                            </summary>
                                            <RevisionFields revision={revision} />
                                        </details>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}
                </div>
            </PageContainer>
        </AppShell>
    );
}

/** "Versi :nomor · :waktu · oleh :nama" (UXI-359). */
function versionRow(revision: Revision): string {
    return t('config.profile.version_row', { nomor: String(revision.number), waktu: formatDateTime(revision.createdAt), nama: revision.actorDisplayName });
}
