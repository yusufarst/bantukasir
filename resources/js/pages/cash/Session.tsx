import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useId, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { CommandForm, Field, TextArea } from '@/components/ui/Field';
import { StatusWord } from '@/components/ui/Status';
import { Committed, EmptyState, Loading } from '@/components/ui/States';
import { AppShell, useUnsavedChanges } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { cashFieldMessage } from '@/lib/cash/registers';
import { CloseSessionEntry, VarianceWord } from '@/lib/cash/Parts';
import { assistedClosePage, OPEN, REVIEW_NOTE_COMMAND, refundsText, registerText, sessionsListFrom, sessionsShell, statusWord, type ReviewNote, type SessionFigures } from '@/lib/cash/sessions';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
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
// whose answer is the file of DC-03 (TASK-031). For the Owner (TASK-032): another user's OPEN session has
// "Tutup sesi oleh Pemilik" (UXI-140), disabled with its reason while the Owner holds an unresolved close
// request; a CLOSED session has the section "Catatan tinjauan Pemilik" (UXI-144), every note in order with
// its author and time and no edit or delete, read as a deferred prop, with "Tambah catatan tinjauan"
// (UXI-145), which opens the inline form — "Catatan", "Simpan catatan" (UXI-146, RC-43) and "Batal"
// (UXI-147), which discards the text; after the save the new note appears in the list. The list of the
// drawer-funded refunds beside the variance is TASK-034's.

/** The Owner's deferred prop of the review notes (UXI-144), read again after a save. */
const NOTES_PROP = 'notes';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

/** The longest note of RC-43 (TECHNICAL_RULES section 11.3). */
const NOTE_MAX = 2000;

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
        readonly canAssistedClose: boolean;
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
    /** The Owner's review notes of a CLOSED session, a deferred prop: absent while they load, and for every other viewer. */
    readonly notes?: readonly ReviewNote[];
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
            <SessionDetail title={title} owner={owner} {...props} />
        </AppShell>
    );
}

function SessionDetail({ title, owner, session, figures, count, notes }: SessionProps & { title: string; owner: boolean }) {
    const [note] = useState(takeSavedNote);
    const [back] = useState(() => sessionsListFrom(document.referrer, window.location.origin));
    const open = session.status === OPEN;
    // UXI-139: the responsible user's own OPEN session; UXI-140: another user's, for the Owner. Each waits
    // while the viewer holds an unresolved close request (NC-13 (a)).
    const action = session.canClose ? (
        <CloseSessionEntry variant={'primary'} />
    ) : session.canAssistedClose ? (
        <CloseSessionEntry variant={'primary'} href={assistedClosePage(session.id)} label={t('cash.assisted_close.title')} />
    ) : undefined;

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
                {owner && !open && <ReviewNotes sessionId={session.id} notes={notes} />}
            </div>
        </PageContainer>
    );
}

/**
 * The Owner's section "Catatan tinjauan Pemilik" of a CLOSED session (UXI-144 to UXI-147; COPY_DECK section
 * 7.11; D-069 (f), BR-CASH-19): every note in order, with its author and time; append-only, without an edit
 * or a delete. "Tambah catatan tinjauan" opens a new form each time, whose note is a new request.
 */
function ReviewNotes({ sessionId, notes }: { sessionId: number; notes: readonly ReviewNote[] | undefined }) {
    const [opening, setOpening] = useState(0);
    const [writing, setWriting] = useState(false);
    const [saved, setSaved] = useState(false);

    const open = (): void => {
        setSaved(false);
        setOpening((current) => current + 1);
        setWriting(true);
    };

    // After the save the new note appears in the list on the same page (NAVIGATION_CONTRACTS UXP-206).
    const done = useCallback((): void => {
        setWriting(false);
        setSaved(true);
        router.reload({ only: [NOTES_PROP] });
    }, []);

    return (
        <Section title={t('cash.session_detail.notes')}>
            <div className={'bk-stack'}>
                {saved && <Committed message={t('cash.session_detail.note_saved')} />}
                <p className={'bk-meta'}>{t('cash.session_detail.notes_help')}</p>
                {notes === undefined ? (
                    <Loading lines={2} />
                ) : notes.length === 0 ? (
                    <EmptyState body={t('cash.session_detail.notes_empty')} />
                ) : (
                    <ol className={'bk-stack'}>
                        {notes.map((entry) => (
                            <li key={entry.id}>
                                <p className={'bk-text-lines'}>{entry.text}</p>
                                <p className={'bk-meta'}>{t('cash.session_detail.note_meta', { nama: entry.author, waktu: formatDateTime(entry.recordedAt) })}</p>
                            </li>
                        ))}
                    </ol>
                )}
                {writing ? (
                    <NoteForm key={opening} sessionId={sessionId} onSaved={done} onCancel={() => setWriting(false)} />
                ) : (
                    <div className={'bk-actions'}>
                        <Button label={t('cash.session_detail.add_note')} variant={'secondary'} enterActivates={false} onClick={open} />
                    </div>
                )}
            </div>
        </Section>
    );
}

/**
 * The inline form of one note (UXI-146, UXI-147): "Catatan", 1 to 2,000 characters with line breaks, sent
 * by "Simpan catatan" as RC-43 and never by Enter, which starts a new line; "Batal" discards the text. An
 * uncertain answer keeps the form on its panel (UXS-14), never written again.
 */
function NoteForm({ sessionId, onSaved, onCancel }: { sessionId: number; onSaved: () => void; onCancel: () => void }) {
    const control = useCommand(REVIEW_NOTE_COMMAND);
    const { phase } = control;
    const ids = useId();
    const [text, setText] = useState('');
    const [checked, setChecked] = useState(false);
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const throttled = phase.kind === 'throttled';
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const trimmed = text.trim();
    const refused = refusal?.code === VALIDATION_FAILED ? cashFieldMessage(refusal.fields['payload.text']) : undefined;
    const error = refused ?? (checked && trimmed === '' ? t('ux.validation.required') : undefined);
    const noteId = `${ids}-note`;

    useUnsavedChanges(trimmed !== '' && !committed, t('ux.state.unsaved.body'));

    useEffect(() => {
        if (committed) {
            onSaved();
        }
    }, [committed, onSaved]);

    const save = (): void => {
        setChecked(true);

        if (trimmed !== '') {
            control.submit({ sessionId, text: trimmed });
        }
    };

    return (
        <div className={'bk-stack'}>
            <CommandForm label={t('cash.session_detail.add_note')}>
                <Field id={noteId} label={t('cash.session_detail.note')} required={true} error={error}>
                    <TextArea rows={4} maxLength={NOTE_MAX} disabled={frozen} value={text} onChange={(event) => setText(event.target.value)} />
                </Field>
                <div className={'bk-actions'}>
                    <Button label={t('cash.session_detail.save_note')} variant={'primary'} enterActivates={false} busy={busy} unavailable={frozen && !throttled} onClick={save} />
                    <Button label={t('ux.action.cancel')} variant={'secondary'} enterActivates={false} unavailable={frozen} onClick={onCancel} />
                </div>
            </CommandForm>
            <CommandPanels control={control} />
        </div>
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
