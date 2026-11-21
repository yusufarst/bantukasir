import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { Figure, Figures, LinkRow, LinkRows } from '@/components/ui/Data';
import { LoadFailed, Loading } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { HomeLayout, PageHeader } from '@/layouts/Page';
import { sessionPage } from '@/lib/cash/sessions';
import { useUnresolved } from '@/lib/commands/unresolved';
import { formatCount, formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { DAILY_PAGE } from '@/lib/reporting/daily';
import { ATTENTION_PROP, attentionItems, attentionTitle, HOME_SHELL, OPEN_SESSIONS_PROP, splitStep, TODAY_PROP, type AttentionPanel, type OpenSessionRow, type SplitBasis, type TodayPanel } from '@/lib/reporting/home';

// UXP-007 "Beranda" (KEY_SCREENS KS-02 with its states `default`, `quiet`, `failed` and `loading`; ROUTE_CONTRACTS
// RP-007; COPY_DECK sections 6.8 and 10.1; INTERACTION_INVENTORY UXI-059 to UXI-065; NAVIGATION_CONTRACTS UXP-007;
// UI_STATES section 6: UXS-01, UXS-02, UXS-22; INFORMATION_ARCHITECTURE UXP-007; TASK-041 scope item 1), the Owner's
// home (CAP-22): the panels stacked in the order of the contract — "Hari ini" with net sales, the number of Sales and
// the cash and bank-transfer payments and its link "Rekap harian" (UXI-059); the open cash sessions, each leading to
// its detail (UXI-060); and "Perlu perhatian", each item exactly when its evidence says so (UXI-061 to UXI-065). Each
// panel is a deferred prop read after the page by a request of its own: until it arrives it waits (UXS-01); a read
// that fails says so with "Coba lagi", which reads that panel alone again, never a zero, while the other panels stand
// (UXS-22, BR-DOM-08). The as-of line is today's snapshot's.

interface HomeProps {
    readonly today?: TodayPanel | null;
    readonly openSessions?: readonly OpenSessionRow[] | null;
    readonly attention?: AttentionPanel | null;
}

type Read = 'read' | 'reading' | 'failed';

export default function Home(props: HomeProps) {
    const title = t('reporting.home.title');

    return (
        <AppShell title={title} {...HOME_SHELL}>
            <HomePage title={title} {...props} />
        </AppShell>
    );
}

function HomePage({ title, today, openSessions, attention }: HomeProps & { title: string }) {
    return (
        <HomeLayout
            header={<PageHeader title={title} subtitle={today === undefined || today === null ? undefined : t('reporting.as_of', { waktu: formatDateTime(today.asOf) })} />}
            panels={
                <>
                    <TodayCard today={today} />
                    <div className={'bk-grid-2'}>
                        <OpenSessionsCard sessions={openSessions} />
                        <AttentionCard attention={attention} />
                    </div>
                </>
            }
        />
    );
}

/** A panel's own read again, by name ("Coba lagi"): reading, then read or failed. */
function usePanel(prop: string): { read: Read; retry: () => void } {
    const [read, setRead] = useState<Read>('read');

    const retry = useCallback((): void => {
        const failed = (): boolean => {
            setRead('failed');

            return false;
        };

        setRead('reading');
        router.reload({ only: [prop], onSuccess: () => setRead('read'), onHttpException: failed, onNetworkError: failed });
    }, [prop]);

    return { read, retry };
}

/** A panel's content: waiting until its prop arrives, failed with "Coba lagi", or its value. */
function PanelBody<Value>({ value, read, retry, children }: { value: Value | null | undefined; read: Read; retry: () => void; children: (value: Value) => ReactNode }) {
    if (read === 'reading' || (read === 'read' && value === undefined)) {
        return <Loading lines={3} />;
    }

    if (read === 'failed' || value === null || value === undefined) {
        return <LoadFailed onRetry={retry} />;
    }

    return <>{children(value)}</>;
}

function loaded(text: string): { status: 'loaded'; text: string } {
    return { status: 'loaded', text };
}

/** "Hari ini" (KS-02): the four figures of today, and "Rekap harian" for today (UXI-059). */
function TodayCard({ today }: { today: TodayPanel | null | undefined }) {
    const { read, retry } = usePanel(TODAY_PROP);

    return (
        <Section title={t('reporting.home.today')} actions={<ButtonLink href={DAILY_PAGE} label={t('reporting.daily.title')} variant={'quiet'} />}>
            <PanelBody value={today} read={read} retry={retry}>
                {(figures) => (
                    <Figures>
                        <Figure label={t('reporting.figure.net_sales')} value={loaded(formatMoney(figures.netSales))} hint={t('reporting.figure.net_sales_help')} />
                        <Figure label={t('reporting.figure.sale_count')} value={loaded(formatCount(figures.saleCount))} />
                        <Figure label={t('reporting.figure.payments_cash')} value={loaded(formatMoney(figures.paymentsCash))} />
                        <Figure label={t('reporting.figure.payments_transfer')} value={loaded(formatMoney(figures.paymentsTransfer))} />
                    </Figures>
                )}
            </PanelBody>
        </Section>
    );
}

/** "Sesi kas yang sedang dibuka" (UXI-060): each OPEN session leads to its detail; none, the empty state (UXS-02). */
function OpenSessionsCard({ sessions }: { sessions: readonly OpenSessionRow[] | null | undefined }) {
    const { read, retry } = usePanel(OPEN_SESSIONS_PROP);
    const label = t('reporting.home.open_sessions');

    return (
        <Section title={label}>
            <PanelBody value={sessions} read={read} retry={retry}>
                {(rows) =>
                    rows.length === 0 ? (
                        <p className={'bk-empty'} data-state={'empty'}>
                            {t('reporting.home.open_sessions_empty')}
                        </p>
                    ) : (
                        <LinkRows label={label}>
                            {rows.map((row) => (
                                <li key={row.id}>
                                    <LinkRow href={sessionPage(row.id)} icon={'drawer'} title={t('reporting.home.open_session_row', { laci: row.registerLabel, nama: row.responsible, waktu: formatDateTime(row.openedAt) })} />
                                </li>
                            ))}
                        </LinkRows>
                    )
                }
            </PanelBody>
        </Section>
    );
}

/**
 * The panel read again in the background, marked as such (RT-10), with each later read of RD-03 (`splitStep`), so that the restock item's link
 * follows its count (UXI-061, BR-INV-41); true once such a read failed, until one succeeds. The panel stays as it is
 * while it reads; an answer of the server whose read failed shows the panel's failed state, never a zero (UXS-22).
 */
function useRestockSplit(attention: AttentionPanel | null | undefined, shellAsOf: string | null): boolean {
    const [failed, setFailed] = useState(false);
    const basis = useRef<SplitBasis | null>(null);

    useEffect(() => {
        const step = splitStep(basis.current, attention, shellAsOf);
        basis.current = step.basis;

        if (!step.reread) {
            return;
        }

        const failedRead = (): boolean => {
            setFailed(true);

            return false;
        };

        // A background read, never the user's activity: it extends neither the sign-in session nor the shell's idle count (RT-10, SEC-SESS-02).
        router.reload({ only: [ATTENTION_PROP], async: true, showProgress: false, headers: { 'X-Background-Request': '1' }, onSuccess: () => setFailed(false), onHttpException: failedRead, onNetworkError: failedRead });
    }, [attention, shellAsOf]);

    return failed;
}

/** "Perlu perhatian" (UXI-061 to UXI-065): the items its evidence shows; none, the sentence that nothing needs it. */
function AttentionCard({ attention }: { attention: AttentionPanel | null | undefined }) {
    const { read, retry } = usePanel(ATTENTION_PROP);
    // The stock badge's live read of RD-03, which the shell refreshes (UXI-061, BR-INV-41).
    const shell = useUnresolved();
    const splitStale = useRestockSplit(attention, shell.asOf);
    const label = t('reporting.home.attention');

    return (
        <Section title={label}>
            <PanelBody value={attention} read={read} retry={retry}>
                {(evidence) => {
                    const items = attentionItems(evidence, { count: shell.stockAttention, failed: shell.failed, splitStale });

                    return items.length === 0 ? (
                        <p className={'bk-empty'} data-state={'empty'}>
                            {t('reporting.home.attention_empty')}
                        </p>
                    ) : (
                        <LinkRows label={label}>
                            {items.map((item) => (
                                <li key={item.id} data-attention={item.id}>
                                    <LinkRow href={item.href} icon={item.icon} tone={item.tone} title={attentionTitle(item)} {...(item.stale === true ? { meta: t('ux.state.stale_read.title') } : {})} />
                                </li>
                            ))}
                        </LinkRows>
                    );
                }}
            </PanelBody>
        </Section>
    );
}
