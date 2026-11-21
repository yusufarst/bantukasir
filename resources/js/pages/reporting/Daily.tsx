import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, TextInput } from '@/components/ui/Field';
import { StatusWord } from '@/components/ui/Status';
import { EmptyState, LoadFailed, Loading } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageHeader, ReportLayout } from '@/layouts/Page';
import { VarianceWord } from '@/lib/cash/Parts';
import { registerText, sessionPage, statusWord } from '@/lib/cash/sessions';
import { formatDate, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { DAILY_SHELL, dailyAddress, dateError, nextDay, previousDay, SUMMARY_PROP, type DailySession, type DailySummaryRead } from '@/lib/reporting/daily';
import { Completeness, ReportFigures } from '@/lib/reporting/Parts';
import type { TranslationKey } from '@/types/translation-keys';

// UXP-501 "Rekap harian" (ROUTE_CONTRACTS RP-501; COPY_DECK sections 10.1 and 10.2; INTERACTION_INVENTORY UXI-306
// and UXI-307; NAVIGATION_CONTRACTS UXP-501; UI_STATES section 6: UXS-01, UXS-02, UXS-07, UXS-22, UXS-25;
// INTERACTION_CONTRACTS section 3.4; SALES_CASH BR-RPT-01 to BR-RPT-04; TASK-041 scope item 2), the Owner's alone
// (CAP-22): the figures of one Asia/Jakarta business day, computed once on the server from the source records with
// the completeness of their cost before them (UXS-25), and the cash sessions of that day, each once with its own
// variance — those closed on it with their full span, one that crossed midnight marked so, and those still open
// apart (BR-RPT-02); opening cash and variance are never sales or profit, which the note says. The date control
// (UXI-306) moves a day at a time or picks a date, never after today: a date the field refuses is never fetched.
// Everything comes from one snapshot, whose time is the as-of line; a read that fails is UXS-22 with "Coba lagi",
// never zero. The date stands in the address, so it is kept on the way back from a session (UXI-307).

interface DailyProps {
    readonly date: string;
    /** Today in Asia/Jakarta at the request: the last date the control may choose. */
    readonly today: string;
    readonly summary: DailySummaryRead | null;
}

type Read = 'read' | 'reading' | 'failed';

const DATE_ID = 'daily-date';

export default function Daily(props: DailyProps) {
    const title = t('reporting.daily.title');

    return (
        <AppShell title={title} {...DAILY_SHELL}>
            <DailyPage title={title} {...props} />
        </AppShell>
    );
}

function DailyPage({ title, date, today, summary }: DailyProps & { title: string }) {
    const [read, setRead] = useState<Read>('read');
    // A visit to another date that fails leaves the applied date shown, so the control starts again from it.
    const [failures, setFailures] = useState(0);

    const failed = (): boolean => {
        setRead('failed');
        setFailures((count) => count + 1);

        return false;
    };

    const visit = (next: string): void => {
        setRead('reading');
        router.visit(dailyAddress(next), { preserveScroll: true, onHttpException: failed, onNetworkError: failed });
    };

    const retry = useCallback((): void => {
        const refused = (): boolean => {
            setRead('failed');

            return false;
        };

        setRead('reading');
        router.reload({ only: [SUMMARY_PROP], onSuccess: () => setRead('read'), onHttpException: refused, onNetworkError: refused });
    }, []);

    const shown = read === 'read' ? summary : null;

    return (
        <ReportLayout
            header={<PageHeader title={title} subtitle={shown === null ? undefined : t('reporting.as_of', { waktu: formatDateTime(shown.asOf) })} />}
            period={<DateChoice key={failures} date={date} today={today} busy={read === 'reading'} onChoose={visit} />}
            completeness={shown !== null && !shown.empty ? <Completeness completeness={shown.completeness} /> : undefined}
            figures={
                read === 'reading' ? (
                    <Loading lines={4} />
                ) : shown === null ? (
                    <LoadFailed onRetry={retry} />
                ) : shown.empty ? (
                    <EmptyState body={t('reporting.daily.empty')} />
                ) : (
                    <ReportFigures title={title} figures={shown.figures} complete={shown.completeness.complete} />
                )
            }
            chart={shown === null ? undefined : <DaySessions summary={shown} />}
        />
    );
}

/**
 * UXI-306: "Hari sebelumnya", "Hari berikutnya" — which today has not — and the date picked, Asia/Jakarta days
 * (BR-RPT-01). A picked date is checked at its field before anything is fetched: none, or one after today, says so.
 */
function DateChoice({ date, today, busy, onChoose }: { date: string; today: string; busy: boolean; onChoose: (date: string) => void }) {
    const [picked, setPicked] = useState(date);
    const [error, setError] = useState<TranslationKey | null>(null);
    const next = nextDay(date, today);

    const pick = (value: string): void => {
        setPicked(value);
        const refused = dateError(value, today);
        setError(refused);

        if (refused === null && value !== date) {
            onChoose(value);
        }
    };

    return (
        <section className={'bk-card bk-section'}>
            <div className={'bk-actions'}>
                <Button label={t('reporting.daily.previous_day')} variant={'secondary'} icon={'arrow-left'} busy={busy} onClick={() => onChoose(previousDay(date))} />
                {next !== null && <Button label={t('reporting.daily.next_day')} variant={'secondary'} busy={busy} onClick={() => onChoose(next)} />}
            </div>
            <Field id={DATE_ID} label={t('reporting.daily.pick_date')} error={error === null ? undefined : t(error)}>
                <TextInput type={'date'} max={today} value={picked} onChange={(event) => pick(event.target.value)} />
            </Field>
            <p className={'bk-meta'}>{formatDate(date)}</p>
        </section>
    );
}

/**
 * UXI-307: the day's cash sessions, each once with its register, responsible user, opening and close times, state
 * and its own variance, leading to its detail; a session that crossed midnight is marked and shows its full span;
 * the sessions still open apart (BR-RPT-02). Opening cash and variance are never sales or profit (BR-RPT-01).
 */
function DaySessions({ summary }: { summary: DailySummaryRead }) {
    const title = t('reporting.daily.sessions');
    const active = t('reporting.daily.active_sessions');

    return (
        <Section title={title}>
            <div className={'bk-stack'}>
                <p className={'bk-meta'}>{t('reporting.cash_note')}</p>
                {summary.sessions.length === 0 && summary.active.length === 0 ? (
                    <EmptyState body={t('cash.sessions.empty')} />
                ) : (
                    <>
                        {summary.sessions.length > 0 && <SessionTable caption={title} rows={summary.sessions} />}
                        {summary.active.length > 0 && (
                            <Section title={active} level={3} card={false}>
                                <SessionTable caption={active} rows={summary.active} />
                            </Section>
                        )}
                    </>
                )}
            </div>
        </Section>
    );
}

function SessionTable({ caption, rows }: { caption: string; rows: readonly DailySession[] }) {
    const columns: readonly Column<DailySession>[] = [
        {
            id: 'responsible',
            header: t('cash.sessions.col_responsible'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={sessionPage(row.id)}>
                    {row.responsible}
                </a>
            ),
        },
        { id: 'register', header: t('cash.sessions.col_register'), cell: (row) => registerText(row.registerCode, row.registerLabel) },
        { id: 'opened', header: t('cash.sessions.col_opened'), cell: (row) => formatDateTime(row.openedAt) },
        {
            id: 'closed',
            header: t('cash.sessions.col_closed'),
            cell: (row) => (
                <span className={'bk-stack'}>
                    {row.closedAt === null ? null : <span>{formatDateTime(row.closedAt)}</span>}
                    {row.crossesMidnight && <span className={'bk-meta'}>{t('reporting.daily.crosses_midnight')}</span>}
                </span>
            ),
        },
        { id: 'status', header: t('cash.sessions.filter_status'), cell: (row) => <StatusWord tone={row.closedAt === null ? 'info' : 'neutral'} label={statusWord(row.status)} /> },
        { id: 'variance', header: t('cash.sessions.col_variance'), numeric: true, cell: (row) => (row.variance === null ? null : <VarianceWord recorded={row.variance} />) },
    ];

    return <DataTable caption={caption} columns={columns} rows={rows} rowKey={(row) => String(row.id)} />;
}
