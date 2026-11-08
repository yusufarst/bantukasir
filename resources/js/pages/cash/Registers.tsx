import { useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Committed, EmptyState, StateMark } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { NEW_REGISTER_PAGE, registerEditPage, registerName, SETTINGS_PAGE, type RegisterRow } from '@/lib/cash/registers';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-609 "Laci kas" (ROUTE_CONTRACTS RP-609; COPY_DECK section 11.9; INTERACTION_INVENTORY UXI-367
// to UXI-369; NAVIGATION_CONTRACTS UXP-609; UI_STATES section 6; BR-CASH-01 to BR-CASH-03; TASK-027
// scope item 4): every register with its code, label, status and the responsible user of its OPEN
// session. "Tambah laci kas" leads to UXP-610; a row's "Ubah laci kas" names its register and leads
// to UXP-610 of it, disabled with its reason while a session is OPEN on it (UXS-13; BR-CASH-02).
// Rows become cards on a narrow container (DataTable). The way back is Pengaturan.

/** The destination of the navigation that holds this page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

export default function Registers({ registers }: { registers: readonly RegisterRow[] }) {
    const title = t('cash.registers.title');
    const [note] = useState(takeSavedNote);
    const columns: readonly Column<RegisterRow>[] = [
        { id: 'code', header: t('cash.registers.col_code'), isHead: true, cell: (row) => <span className={'bk-mono'}>{row.code}</span> },
        { id: 'label', header: t('cash.registers.col_label'), cell: (row) => row.label },
        { id: 'status', header: t('cash.registers.col_status'), cell: (row) => (row.active ? <StatusWord tone={'success'} label={t('catalog.status.active')} /> : <StateMark kind={'inactive'} />) },
        {
            id: 'session',
            header: t('cash.registers.col_session'),
            cell: (row) => (row.openBy === null ? t('cash.registers.session_none') : t('cash.registers.session_open', { nama: row.openBy })),
        },
        {
            id: 'actions',
            header: t('ux.action.more_actions'),
            isAction: true,
            cell: (row) =>
                row.openBy === null ? (
                    <a className={'bk-btn bk-btn--secondary'} data-variant={'secondary'} href={registerEditPage(row.id)} aria-label={t('cash.register_form.edit_named', { laci: registerName(row) })}>
                        <span>{t('cash.register_form.title_edit')}</span>
                    </a>
                ) : (
                    <Button
                        label={t('cash.register_form.title_edit')}
                        accessibleName={t('cash.register_form.edit_named', { laci: registerName(row) })}
                        variant={'secondary'}
                        disabledReason={t('cash.registers.edit_disabled')}
                        onClick={() => undefined}
                    />
                ),
        },
    ];

    return (
        <AppShell title={title} current={DESTINATION}>
            <ListLayout header={<PageHeader title={title} back={{ href: SETTINGS_PAGE, page: t('config.settings.title') }} actions={<ButtonLink href={NEW_REGISTER_PAGE} label={t('cash.register_form.title_new')} variant={'primary'} />} />}>
                {note !== null && <Committed message={note} />}
                {registers.length > 0 ? <DataTable caption={title} columns={columns} rows={registers} rowKey={(row) => String(row.id)} /> : <EmptyState body={t('cash.registers.empty')} />}
            </ListLayout>
        </AppShell>
    );
}
