import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { StatusWord } from '@/components/ui/Status';
import { Committed } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { CloseSessionEntry, VarianceWord } from '@/lib/cash/Parts';
import { OPEN, refundsText, registerText, sessionsListFrom, sessionsShell, statusWord, type SessionFigures } from '@/lib/cash/sessions';
import { ExportControl, PrintControl } from '@/lib/documents/Printing';
import { PRINT_SESSION_REPORT_COMMAND } from '@/lib/documents/requests';
import { formatCount, formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { personaOf, type RoleCode } from '@/lib/navigation';

// UXP-206 "Detail sesi kas" (ROUTE_CONTRACTS RP-206; COPY_DECK sections 4.1, 7.11 and 17.2; DOCUMENT_CONTRACTS
// DC-02; INTERACTION_INVENTORY UXI-139 and UXI-148; NAVIGATION_CONTRACTS UXP-206; UI_STATES section 6;
// BR-CASH-19 to BR-CASH-21; TASK-030 scope item 3): one session in the viewer's scope. An OPEN session
// shows its running figures — those of the close preview, never a cost — and, to its responsible user,
// "Tutup sesi kas" (UXI-139), disabled with its reason while this user holds an unresolved close request
// (NC-13). A CLOSED session shows its immutable report as the close froze it, rows 3 to 11 of DC-02, the
// variance in words. Arriving from the close, the page says the session was closed. The way back is
// "Kembali ke Sesi kas" (UXI-148): the list with the filters and the page it stood at when it opened
// this session (NC-10), else the parent, also after the close (NC-01). A CLOSED session's report has
// "Cetak laporan" (UXI-141), which records RC-45 and opens RP-206P, and "Unduh CSV" (UXI-142), RC-46,
// whose answer is the file of DC-03 (TASK-031). The Owner's review notes and assisted close are
// TASK-032's, and the list of the drawer-funded refunds beside the variance TASK-034's.

interface SessionProps {
    readonly session: {
        readonly id: number;
        readonly number: string;
        readonly status: string;
        readonly registerCode: string;
        readonly registerLabel: string;
        readonly responsible: string;
        readonly openedAt: string;
        readonly closedAt: string | null;
        readonly canClose: boolean;
    };
    readonly figures: SessionFigures;
    /** The count of the report, every field null while the session is OPEN. */
    readonly count: {
        readonly physicalCash: string | null;
        readonly variance: string | null;
        readonly closedBy: string | null;
        readonly closeReason: string | null;
        readonly notes: string | null;
    };
}

interface SharedProps {
    readonly auth: { readonly user: { readonly roles: readonly RoleCode[] } } | null;
    readonly [key: string]: unknown;
}

export default function Session(props: SessionProps) {
    const title = t('cash.session_detail.title');
    const owner = personaOf(usePage<SharedProps>().props.auth?.user.roles ?? []) === 'owner';

    return (
        <AppShell title={title} {...sessionsShell(owner)}>
            <SessionDetail title={title} {...props} />
        </AppShell>
    );
}

function SessionDetail({ title, session, figures, count }: SessionProps & { title: string }) {
    const [note] = useState(takeSavedNote);
    const [back] = useState(() => sessionsListFrom(document.referrer, window.location.origin));
    const open = session.status === OPEN;
    // UXI-139: the responsible user's own OPEN session, waiting while a close request is unresolved (NC-13 (a)).
    const action = session.canClose ? <CloseSessionEntry variant={'primary'} /> : undefined;

    const identity: KeyValue[] = [
        { key: 'status', label: t('cash.sessions.filter_status'), value: <StatusWord tone={open ? 'info' : 'neutral'} label={statusWord(session.status)} /> },
        { key: 'register', label: t('documents.session_report.register'), value: registerText(session.registerCode, session.registerLabel) },
        { key: 'responsible', label: t('documents.session_report.responsible'), value: session.responsible },
        { key: 'opened', label: t('documents.session_report.opened_at'), value: formatDateTime(session.openedAt) },
    ];

    return (
        <PageContainer width={'form'}>
            <div className={'bk-stack'}>
                <PageHeader
                    title={title}
                    subtitle={t('cash.session_detail.subtitle', { nomor: session.number, laci: session.registerLabel })}
                    back={{ href: back, page: t('cash.sessions.title') }}
                    actions={action}
                />
                {note !== null && <Committed message={note} />}
                {open ? (
                    <Section title={statusWord(session.status)} titleHidden={true}>
                        <div className={'bk-stack'}>
                            <KeyValueList items={identity} />
                            <OpenFigures figures={figures} />
                        </div>
                    </Section>
                ) : (
                    <Section title={t('documents.session_report.title')}>
                        <div className={'bk-stack'}>
                            <div className={'bk-print-row'}>
                                <PrintControl command={PRINT_SESSION_REPORT_COMMAND} payload={{ sessionId: session.id }} label={t('cash.session_detail.print')} variant={'primary'} />
                                <ExportControl sessionId={session.id} number={session.number} />
                            </div>
                            <ReportFigures session={session} figures={figures} count={count} />
                        </div>
                    </Section>
                )}
            </div>
        </PageContainer>
    );
}

/** The running figures of an OPEN session, those of the close preview (COPY_DECK section 7.5). */
function OpenFigures({ figures }: { figures: SessionFigures }) {
    const items: KeyValue[] = [
        { key: 'opening', label: t('cash.figure.opening_cash'), value: formatMoney(figures.openingCash) },
        { key: 'count', label: t('cash.figure.sale_count'), value: formatCount(figures.saleCount) },
        { key: 'cash', label: t('cash.figure.cash_payments'), value: formatMoney(figures.cashPayments) },
        { key: 'transfer', label: t('cash.figure.transfer_payments'), value: formatMoney(figures.transferPayments) },
        { key: 'refunds', label: t('cash.figure.drawer_refunds'), value: refundsText(figures.drawerRefunds) },
        { key: 'expected', label: t('cash.figure.expected_cash'), value: formatMoney(figures.expectedCash), strong: true },
    ];

    return <KeyValueList items={items} />;
}

/** The report of a CLOSED session as it was frozen, rows 3 to 11 of DC-02 with the labels of COPY_DECK section 17.2. */
function ReportFigures({ session, figures, count }: Pick<SessionProps, 'session' | 'figures' | 'count'>) {
    const closedAt = session.closedAt === null ? '' : formatDateTime(session.closedAt);
    const items: KeyValue[] = [
        { key: 'register', label: t('documents.session_report.register'), value: registerText(session.registerCode, session.registerLabel) },
        { key: 'responsible', label: t('documents.session_report.responsible'), value: session.responsible },
        { key: 'opened', label: t('documents.session_report.opened_at'), value: formatDateTime(session.openedAt) },
        { key: 'cutoff', label: t('documents.session_report.cutoff'), value: closedAt },
        { key: 'closed', label: t('documents.session_report.closed_at'), value: closedAt },
        { key: 'opening', label: t('documents.session_report.opening_cash'), value: formatMoney(figures.openingCash) },
        { key: 'count', label: t('documents.session_report.sale_count'), value: formatCount(figures.saleCount) },
        { key: 'value', label: t('documents.session_report.sale_value'), value: formatMoney(figures.saleValue) },
        { key: 'cash', label: t('documents.session_report.cash_payments'), value: formatMoney(figures.cashPayments) },
        { key: 'transfer', label: t('documents.session_report.transfer_payments'), value: formatMoney(figures.transferPayments) },
        { key: 'refunds', label: t('documents.session_report.drawer_refunds'), value: refundsText(figures.drawerRefunds) },
        { key: 'expected', label: t('documents.session_report.expected_cash'), value: formatMoney(figures.expectedCash) },
        { key: 'physical', label: t('documents.session_report.physical_cash'), value: count.physicalCash === null ? '' : formatMoney(count.physicalCash) },
        { key: 'variance', label: t('documents.session_report.variance'), value: count.variance === null ? '' : <VarianceWord recorded={count.variance} />, strong: true },
        { key: 'closedBy', label: t('documents.session_report.closed_by'), value: count.closedBy ?? '' },
        ...(count.closeReason === null ? [] : [{ key: 'reason', label: t('documents.session_report.close_reason'), value: count.closeReason }]),
        ...(count.notes === null ? [] : [{ key: 'notes', label: t('documents.session_report.notes'), value: count.notes }]),
    ];

    return <KeyValueList items={items} />;
}
