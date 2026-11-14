import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { LoadMore } from '@/components/ui/Data';
import { Notice } from '@/components/ui/Notice';
import { EmptyState, LoadFailed, Loading, RecoveryPanel } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import type { CommandResult } from '@/lib/commands/answers';
import { announceSettled, RECORD_CHECK_WAIT_MINUTES } from '@/lib/commands/client';
import { familyLabel, familyMustNot, isLabelledFamily } from '@/lib/commands/families';
import { releaseReference } from '@/lib/commands/references';
import { CheckNote, useRowCheck } from '@/lib/commands/RowCheck';
import { forgetReceipt, readReceipts, refreshReceipts, useUnresolved, type ReceiptRow, type UnresolvedRequest } from '@/lib/commands/unresolved';
import { formatCount, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';

// RP-006, UXP-006 "Permintaan belum pasti" (INTERACTION_CONTRACTS sections 2.1,
// 2.2 and 2.4; NAVIGATION_CONTRACTS UXP-006 and NC-13 (e); INTERACTION_INVENTORY
// UXI-055, UXI-057 and UXI-058; COPY_DECK sections 6.7 and 14; ADR-009 sections
// 2 and 4; TASK-009 scope item 6). One card per request: the union of the
// user's references in this browser and the user's own unresolved receipts
// (RD-04, scope=own), each with its family label, its time and "Cek status";
// a request that only the server knows says so. For the Owner, every other
// user's unresolved receipt (RD-04, scope=all), with the user's name and the
// Owner's "Cek status", whose NOT_COMMITTED writes the fence. A conclusive
// answer releases the reference and the card disappears; IN_PROGRESS repeats
// the check after 3, 6 and 12 seconds and then every 15 seconds; a check
// without an answer leaves the request as it was. A request resolved as
// committed says so here, with "Lihat hasil" (UXI-056) to its result page
// where one exists: RP-406 of a receiving (TASK-022), RP-409 of a manual issue
// (TASK-023), RP-103 of a Sale (TASK-029), RP-206 of a close (TASK-030) and of
// an Owner-assisted close (TASK-032), RP-204 of a refund or return (TASK-034); the Tasks of the other result pages add
// their operations to RESULT_PAGES. The result page is authorized again when
// it opens (SEC-AZ-06). Each card's status check is lib/commands/RowCheck.tsx.

interface SharedProps {
    readonly auth: { readonly user: { readonly id: number; readonly roles: readonly string[] } };
    readonly recoveryEpoch: string | null;
    readonly [key: string]: unknown;
}

/** The home of every role (RN-01), named "halaman awal" (NC-12). */
const HOME = '/';

/**
 * The operations whose result page exists (UXI-056; INTERACTION_CONTRACTS section 9): RP-206 of a close since TASK-030, of an
 * assisted one since TASK-032; RP-204 of a refund or return, drawer-funded or not, since TASK-034, where a drawer-funded
 * refund's cash is taken only now (RACE-15; D-068).
 */
const RESULT_PAGES: readonly string[] = ['receiving.confirm', 'manual_issue.confirm', 'sale.checkout', 'cash_session.close', 'cash_session.close_assisted', 'sale_correction.drawer_funded', 'sale_correction.other'];

/** The result page of a committed request, from its answer's location, or null. */
function resultPageOf(operation: string, result: CommandResult | null): string | null {
    const location = result?.location;

    return RESULT_PAGES.includes(operation) && typeof location === 'string' && location.startsWith('/') && !location.startsWith('//') ? location : null;
}

/** What a check concluded: its text and, for a committed request, its result page. */
interface Conclusion {
    readonly text: string;
    readonly href: string | null;
}

/** The key of a card: its operation and intent, one card per request (section 2.2). */
function intentKey(request: { operation: string; sourceIntentId: string }): string {
    return `${request.operation} ${request.sourceIntentId}`;
}

export default function Requests() {
    const title = t('commands.unresolved.title');

    return (
        <AppShell title={title}>
            <RequestsPage title={title} />
        </AppShell>
    );
}

type Others = { readonly state: 'loading' } | { readonly state: 'failed' } | { readonly state: 'loaded'; readonly rows: readonly ReceiptRow[]; readonly next: string | null; readonly more: boolean };

function RequestsPage({ title }: { title: string }) {
    const props = usePage<SharedProps>().props;
    const userId = props.auth.user.id;
    const owner = props.auth.user.roles.includes('SUPER_ADMIN');
    const requests = useUnresolved();
    const announce = useAnnounce();
    const [own, setOwn] = useState<'loading' | 'loaded' | 'failed'>('loading');
    const [others, setOthers] = useState<Others>({ state: 'loading' });
    const [result, setResult] = useState<Conclusion | null>(null);

    const readOwn = useCallback((): void => {
        void refreshReceipts(false).then((read) => setOwn(read ? 'loaded' : 'failed'));
    }, []);

    const readOthers = useCallback(
        (after: string | null): void => {
            if (!owner) {
                return;
            }

            if (after !== null) {
                setOthers((current) => (current.state === 'loaded' ? { ...current, more: true } : current));
            }

            void readReceipts('all', after).then((page) => {
                if (page === null) {
                    setOthers((current) => (after !== null && current.state === 'loaded' ? { ...current, more: false } : { state: 'failed' }));

                    return;
                }

                const rows = page.rows.filter((row) => row.user !== undefined && row.user.id !== userId && isLabelledFamily(row.family));
                setOthers((current) => ({ state: 'loaded', rows: after !== null && current.state === 'loaded' ? [...current.rows, ...rows] : rows, next: page.nextCursor, more: false }));
            });
        },
        [owner, userId],
    );

    useEffect(() => {
        readOwn();
        readOthers(null);
    }, [readOwn, readOthers]);

    const concluded = useCallback(
        (text: string, href: string | null = null): void => {
            setResult({ text, href });
            announce(text, false);
        },
        [announce],
    );

    const removeOther = useCallback((row: ReceiptRow): void => {
        setOthers((current) => (current.state === 'loaded' ? { ...current, rows: current.rows.filter((kept) => kept !== row) } : current));
    }, []);

    const mine = requests.union.filter((request) => isLabelledFamily(request.family));
    // A page of RD-04 may hold only the Owner's own receipts or print requests,
    // which this list leaves out: while a cursor remains, the list is not
    // empty and "Muat lebih banyak" stays (pull request #15's review).
    const othersEmpty = !owner || (others.state === 'loaded' && others.rows.length === 0 && others.next === null);
    const lostHelp = t('commands.requests.lost_help', { menit: formatCount(RECORD_CHECK_WAIT_MINUTES) });

    return (
        <PageContainer width={'form'}>
            <div className={'bk-stack'}>
                <PageHeader title={title} back={{ href: HOME }} subtitle={t('commands.unresolved.intro')} />
                {result !== null && (
                    <Notice tone={'neutral'} urgency={'none'} actions={result.href === null ? undefined : <ButtonLink href={result.href} label={t('ux.action.view_result')} variant={'secondary'} />}>
                        <p>{result.text}</p>
                    </Notice>
                )}
                {own === 'loading' && mine.length === 0 ? (
                    <Loading />
                ) : (
                    <>
                        {own === 'failed' && <LoadFailed onRetry={readOwn} />}
                        {mine.length > 0 && (
                            <ul className={'bk-stack'} role={'list'}>
                                {mine.map((request) => (
                                    <li key={intentKey(request)}>
                                        <OwnRequest request={request} userId={userId} epoch={props.recoveryEpoch} onConcluded={concluded} />
                                    </li>
                                ))}
                            </ul>
                        )}
                        {mine.length === 0 && own === 'loaded' && othersEmpty && <EmptyState body={t('commands.unresolved.empty')} />}
                    </>
                )}
                {owner && others.state === 'loading' && <Loading />}
                {owner && others.state === 'failed' && <LoadFailed onRetry={() => readOthers(null)} />}
                {owner && others.state === 'loaded' && others.rows.length > 0 && (
                    <ul className={'bk-stack'} role={'list'}>
                        {others.rows.map((row) => (
                            <li key={intentKey(row)}>
                                <OtherRequest row={row} epoch={props.recoveryEpoch} onConcluded={(text, href) => (concluded(text, href ?? null), removeOther(row))} />
                            </li>
                        ))}
                    </ul>
                )}
                {owner && others.state === 'loaded' && others.next !== null && <LoadMore busy={others.more} onLoad={() => readOthers(others.next)} />}
                <p className={'bk-meta'}>{lostHelp}</p>
            </div>
        </PageContainer>
    );
}

/** A card of the user's own request (UXI-055). */
function OwnRequest({ request, userId, epoch, onConcluded }: { request: UnresolvedRequest; userId: number; epoch: string | null; onConcluded: (text: string, href?: string | null) => void }) {
    const resolved = useCallback((): void => {
        releaseReference(userId, request);
        forgetReceipt(request);
        announceSettled();
    }, [userId, request]);
    const { view, check } = useRowCheck(request, epoch, onConcluded, resolved, resultPageOf);

    if (!isLabelledFamily(request.family)) {
        return null;
    }

    const row = t('commands.unresolved.row', { jenis: familyLabel(request.family), waktu: formatDateTime(request.at) });
    const held = view.kind === 'epoch_stale' || request.receipt?.outcome === 'HELD';
    const kind = view.kind === 'epoch_stale' || (held && request.receipt?.epoch === 'earlier') ? 'epoch_stale' : held ? 'idempotency_conflict' : 'uncertain';

    return (
        <Card>
            <div className={'bk-stack'}>
                <p>
                    <strong>{row}</strong>
                </p>
                {!request.local && <p className={'bk-meta'}>{t('commands.requests.from_server')}</p>}
                <RecoveryPanel kind={kind} mustNot={familyMustNot(request.family)} onCheckStatus={check} busy={view.kind === 'checking'} />
                <CheckNote view={view} />
            </div>
        </Card>
    );
}

/** A card of another user's request, for the Owner (UXI-058). */
function OtherRequest({ row, epoch, onConcluded }: { row: ReceiptRow; epoch: string | null; onConcluded: (text: string, href?: string | null) => void }) {
    const resolved = useCallback((): void => announceSettled(), []);
    const { view, check } = useRowCheck({ ...row, recoveryEpoch: null }, epoch, onConcluded, resolved, resultPageOf);

    if (!isLabelledFamily(row.family)) {
        return null;
    }

    const name = row.user?.displayName ?? '';
    const text = t('commands.unresolved.row', { jenis: familyLabel(row.family), waktu: formatDateTime(row.receivedAt) });

    return (
        <Card>
            <div className={'bk-stack'}>
                <p>
                    <strong>{t('commands.requests.other_user', { nama: name })}</strong>
                </p>
                <p>{text}</p>
                {view.kind === 'epoch_stale' && <p>{t('ux.state.held.epoch_stale.body')}</p>}
                <p className={'bk-meta'}>{t('commands.requests.other_user_help')}</p>
                <div className={'bk-actions'}>
                    <Button label={t('ux.action.check_status')} rowName={name} variant={'secondary'} enterActivates={false} busy={view.kind === 'checking'} onClick={check} />
                </div>
                <CheckNote view={view} />
            </div>
        </Card>
    );
}
