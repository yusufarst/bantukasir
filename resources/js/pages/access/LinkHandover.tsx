import { useMemo, useState } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { Committed, StateNotice } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { qrMatrix, qrPath } from '@/lib/access/qrCode';
import { takeLink, takeSavedNote, userPage, type HeldLink } from '@/lib/access/users';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-606 "Tautan kata sandi" (ROUTE_CONTRACTS RP-606; COPY_DECK section 11.6;
// INTERACTION_INVENTORY UXI-354 to UXI-357; NAVIGATION_CONTRACTS UXP-606; SECURITY_MODEL
// SEC-ACC-05; THR-21; D-056; TASK-011 scope item 4). A client view: the server sends
// whose link it is for and nothing of the link, which exists only in this page's memory,
// taken once from the answer of RC-35 or RC-41 — never in props, history state, the
// address, browser storage or a log. Loaded any other way — a reload, Back, a new visit —
// it shows UXS-49. With the link it shows its purpose, its expiry, that it is shown once,
// the code to scan, the text with "Salin tautan", and the handover warning. "Selesai"
// leads to UXP-604, and the link is gone with the page.

const DESTINATION: DestinationId = 'settings';

const GONE_STATE: StateId = 'UXS-49';

/** The quiet zone of the code on each side, in modules (qrPath). */
const QUIET_ZONE = 4;

interface Target {
    readonly id: number;
    readonly displayName: string;
}

export default function LinkHandover({ user }: { user: Target }) {
    const title = t('access.link_handover.title');
    const [link] = useState(() => takeLink(user.id));
    const [note] = useState(takeSavedNote);
    const detail = userPage(user.id);

    return (
        <AppShell title={title} current={DESTINATION}>
            <PageContainer width={'focused'}>
                <PageHeader title={title} />
                <div className={'bk-stack'}>
                    {link === null ? (
                        <StateNotice state={GONE_STATE} actions={<ButtonLink href={detail} label={t('ux.action.back_to', { halaman: t('access.user_detail.title') })} variant={'secondary'} />} />
                    ) : (
                        <Handover link={link} name={user.displayName} note={note} done={detail} />
                    )}
                </div>
            </PageContainer>
        </AppShell>
    );
}

function Handover({ link, name, note, done }: { link: HeldLink; name: string; note: string | null; done: string }) {
    const announce = useAnnounce();
    const matrix = useMemo(() => qrMatrix(link.url), [link.url]);
    const purpose = link.kind === 'FIRST_PASSWORD' ? t('access.link.purpose_first') : t('access.link.purpose_reset');
    const size = String((matrix?.length ?? 0) + QUIET_ZONE * 2);
    const viewBox = `0 0 ${size} ${size}`;

    const copy = (): void => {
        void navigator.clipboard.writeText(link.url).then(() => announce(t('access.link_handover.copied'), false));
    };

    return (
        <>
            {note !== null && <Committed message={note} />}
            <Card>
                <div className={'bk-stack'}>
                    <p>
                        <strong>{t('access.link_handover.for', { tujuan: purpose, nama: name })}</strong>
                    </p>
                    <p className={'bk-meta'}>{t('access.link_handover.expires', { waktu: formatDateTime(link.expiresAt) })}</p>
                    <p className={'bk-meta'}>{t('access.link_handover.once')}</p>
                    {matrix !== null && (
                        <svg className={'bk-qr'} role={'img'} aria-label={t('access.link_handover.qr_alt', { nama: name })} viewBox={viewBox}>
                            <path d={qrPath(matrix)} />
                        </svg>
                    )}
                    <p className={'bk-link-text'}>{link.url}</p>
                    <div className={'bk-actions'}>
                        <Button label={t('access.link_handover.copy')} variant={'secondary'} onClick={copy} />
                        <ButtonLink href={done} label={t('ux.action.done')} variant={'primary'} />
                    </div>
                </div>
            </Card>
            <Notice tone={'warning'} urgency={'none'}>
                <p>{t('access.link_handover.guidance', { nama: name })}</p>
            </Notice>
        </>
    );
}
