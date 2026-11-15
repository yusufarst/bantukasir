import { router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Dialog } from '@/components/ui/Dialog';
import { Field, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { Committed, EmptyState, NoResults, StateMark, StateNotice } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { blockingRequest, committedText } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand } from '@/lib/commands/useCommand';
import { rowLinkName } from '@/lib/costing/Parts';
import { formatCount, formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    CHECKLIST_FILTERS,
    hubView,
    ONBOARDING_SHELL,
    onboardingAddress,
    OPENING_COMMAND,
    OPENING_ENTRY_PAGE,
    openingEntryPage,
    REASON_MAX,
    RELEASE_COMMAND,
    START_COMMAND,
    WITHDRAW_COMMAND,
    type ChecklistFilter,
    type ChecklistRow,
    type OnboardingCounts,
    type OnboardingInfo,
} from '@/lib/inventory/onboarding';
import { movementPage, REQUESTS_PAGE, type KeysetPage } from '@/lib/inventory/stock';

// UXP-412 "Stok awal" (ROUTE_CONTRACTS RP-412, RC-12 to RC-14; COPY_DECK section 9.13; INTERACTION_INVENTORY
// UXI-285 to UXI-291; NAVIGATION_CONTRACTS UXP-412; UI_STATES section 6, UXS-27 and UXS-50; BR-INV-23 to
// BR-INV-29, BR-INV-47; D-071, D-072, D-087; TASK-035 scope item 5), the Owner's alone (CAP-15). The
// onboarding state: not started, with "Mulai masa input stok awal" (UXI-285); running since its start, with
// the banner of UXS-27 that the shell shows, "Input stok awal" (UXI-286) and "Akhiri masa input stok awal"
// (UXI-289); completed at its release, read only, with the corrections that remain (BR-INV-29) — also when
// restored data still show it running (D-072); or held while a restore waits for its reconciliation
// (UXS-50), where nothing starts, fills or ends it. Each command asks in its dialog, which Enter never
// confirms, and needs recent authentication (UXS-11); an unresolved request of its family disables it with
// the way to UXP-006 (NC-13), and an uncertain one shows its panel (UXI-290). The checklist (UXI-287) lists
// the counted products, with the quantity of their OPENING or "Dihitung nol" and the status of the cost,
// never an amount, and those still to be counted, filtered by "Sudah dihitung" or "Belum dihitung" and paged
// both ways; an OPENING's row leads to UXP-403 (UXI-288), where it is reversed (UXI-228), and before the
// release each entry is corrected on its row (UXI-291): "Tarik hitungan nol" for a zero count, with its
// reason, and "Catat ulang stok awal" for a reversed OPENING, on UXP-413. A success says so on the page.

const STALE_STATE: StateId = 'UXS-17';

const HELD_STATE: StateId = 'UXS-50';

const STALE_VERSION = 'STALE_VERSION';

/** The page's commands: the start, the release, and the withdrawal of a zero count of a row. */
type Action = { readonly id: 'start' } | { readonly id: 'release' } | { readonly id: 'withdraw'; readonly zeroCountId: number; readonly product: string };

const START: Action = { id: 'start' };

const RELEASE: Action = { id: 'release' };

const COMMAND_OF = { start: START_COMMAND, release: RELEASE_COMMAND, withdraw: WITHDRAW_COMMAND } as const;

interface OnboardingProps {
    readonly onboarding: OnboardingInfo;
    readonly filters: { readonly filter: ChecklistFilter | null };
    readonly checklist: KeysetPage<ChecklistRow>;
    readonly counts: OnboardingCounts;
}

export default function Onboarding(props: OnboardingProps) {
    const title = t('inventory.onboarding.title');

    return (
        <AppShell title={title} {...ONBOARDING_SHELL}>
            <OnboardingHub title={title} {...props} />
        </AppShell>
    );
}

/** Why a control waits for an unresolved request of the user, with the way to UXP-006 (NC-13; UXS-13). */
function unresolvedReason(text: string): ReactNode {
    return (
        <>
            {text} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
}

/** The counted quantity of a row (UXI-287): its OPENING's, "Dihitung nol", or what it still waits for. */
function countedText(row: ChecklistRow): string {
    switch (row.state) {
        case 'COUNTED':
            return row.quantity === null ? '' : formatQuantity(row.quantity, row.product.quantityPrecision, row.product.baseUnit);
        case 'ZERO_COUNTED':
            return t('inventory.onboarding.zero_count');
        case 'REPLACEABLE':
            return t('inventory.opening_entry.replace_eligible');
        case 'NOT_COUNTED':
            return t('inventory.onboarding.filter_not_counted');
    }
}

function OnboardingHub({ title, onboarding, filters, checklist, counts }: OnboardingProps & { title: string }) {
    const [note, setNote] = useState(takeSavedNote);
    const [stale, setStale] = useState(false);
    const [action, setAction] = useState<Action | null>(null);
    // A new key for each opening: its command starts again with a new intent.
    const [opening, setOpening] = useState(0);
    const [visiting, setVisiting] = useState(false);
    const unresolved = useUnresolved();
    const view = hubView(onboarding);
    const active = onboarding.state === 'ACTIVE' && (view === 'active' || view === 'held');
    const held = t('inventory.onboarding.disabled_held');
    // UXI-285, UXI-289: an unresolved start or release; UXI-286, UXI-291: an unresolved opening stock entry, replacement or withdrawal.
    const onboardingReason = view === 'held' ? held : blockingRequest(unresolved.union, START_COMMAND.operation, START_COMMAND.family) === null ? undefined : unresolvedReason(t('inventory.onboarding.disabled_unresolved'));
    const entryReason = view === 'held' ? held : blockingRequest(unresolved.union, OPENING_COMMAND.operation, OPENING_COMMAND.family) === null ? undefined : unresolvedReason(t('inventory.opening_entry.disabled_unresolved'));

    const run = (next: Action): void => {
        setNote(null);
        setStale(false);
        setOpening((current) => current + 1);
        setAction(next);
    };

    const visit = (filter: ChecklistFilter | null, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(onboardingAddress(filter, cursor), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    const corrections = (row: ChecklistRow): ReactNode => {
        if (view !== 'active' && view !== 'held') {
            return null;
        }

        if (row.state === 'ZERO_COUNTED' && row.zeroCountId !== null) {
            const withdrawal: Action = { id: 'withdraw', zeroCountId: row.zeroCountId, product: row.product.name };

            return <Button label={t('inventory.onboarding.withdraw_zero')} rowName={row.product.name} variant={'secondary'} enterActivates={false} disabledReason={entryReason} onClick={() => run(withdrawal)} />;
        }

        if (row.state === 'REPLACEABLE') {
            const label = t('inventory.opening_entry.replace');

            return entryReason === undefined ? (
                <a className={'bk-table-link'} href={openingEntryPage(row.product.id)} aria-label={rowLinkName(label, row.product.name)}>
                    {label}
                </a>
            ) : (
                <Button label={label} rowName={row.product.name} variant={'secondary'} enterActivates={false} disabledReason={entryReason} />
            );
        }

        return null;
    };

    const columns: readonly Column<ChecklistRow>[] = [
        { id: 'product', header: t('inventory.onboarding.col_product'), isHead: true, cell: (row) => row.product.name },
        { id: 'counted', header: t('inventory.onboarding.col_counted'), numeric: true, cell: countedText },
        { id: 'cost', header: t('inventory.onboarding.col_cost'), cell: (row) => (row.cost === 'UNKNOWN' ? <StateMark kind={'cost_unknown'} /> : row.cost === 'KNOWN' ? t('costing.status.known') : null) },
        { id: 'correct', header: t('ux.action.more_actions'), isAction: true, cell: corrections },
        {
            id: 'movement',
            header: t('inventory.movement.title'),
            isAction: true,
            cell: (row) =>
                row.movementId === null ? null : (
                    <a className={'bk-table-link'} href={movementPage(row.movementId)} aria-label={rowLinkName(t('inventory.movement.title'), row.product.name)}>
                        {t('inventory.movement.title')}
                    </a>
                ),
        },
    ];

    const entryAction =
        entryReason === undefined ? (
            <ButtonLink href={OPENING_ENTRY_PAGE} label={t('inventory.opening_entry.title')} variant={'primary'} />
        ) : (
            <Button label={t('inventory.opening_entry.title')} variant={'primary'} enterActivates={false} disabledReason={entryReason} />
        );

    return (
        <PageContainer>
            <PageHeader title={title} />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                {stale && <StateNotice state={STALE_STATE} />}
                {view === 'held' && <StateNotice state={HELD_STATE} />}
                <Card>
                    <div className={'bk-stack'}>
                        {view === 'not_started' && <p>{t('inventory.onboarding.not_started')}</p>}
                        {view === 'active' && <p>{t('inventory.onboarding.active_since', { waktu: onboarding.startedAt === null ? '' : formatDateTime(onboarding.startedAt) })}</p>}
                        {view === 'released' && <p>{t('inventory.onboarding.completed', { waktu: onboarding.releasedAt === null ? '' : formatDateTime(onboarding.releasedAt) })}</p>}
                        {view === 'released' && <p className={'bk-meta'}>{t('inventory.onboarding.corrections_help')}</p>}
                        {view === 'not_started' && (
                            <div className={'bk-actions'}>
                                <Button label={t('inventory.onboarding.start')} variant={'secondary'} enterActivates={false} disabledReason={onboardingReason} onClick={() => run(START)} />
                            </div>
                        )}
                        {active && (
                            <div className={'bk-actions'}>
                                {entryAction}
                                <Button label={t('inventory.onboarding.release')} variant={'secondary'} enterActivates={false} disabledReason={onboardingReason} onClick={() => run(RELEASE)} />
                            </div>
                        )}
                    </div>
                </Card>
                {action !== null && (
                    <ActionRun
                        key={opening}
                        action={action}
                        onboarding={onboarding}
                        counts={counts}
                        onClose={() => setAction(null)}
                        onDone={(text) => {
                            setAction(null);
                            setNote(text);
                            router.reload();
                        }}
                        onStale={() => {
                            setAction(null);
                            setStale(true);
                            router.reload();
                        }}
                    />
                )}
                <Section title={t('inventory.onboarding.checklist')}>
                    <div className={'bk-stack'}>
                        <FilterChips label={t('inventory.onboarding.checklist')}>
                            {CHECKLIST_FILTERS.map((entry) => (
                                <FilterChip key={entry.filter} label={t(entry.label)} pressed={filters.filter === entry.filter} onPressedChange={(pressed) => visit(pressed ? entry.filter : null)} />
                            ))}
                        </FilterChips>
                        {checklist.rows.length > 0 && <DataTable caption={t('inventory.onboarding.checklist')} columns={columns} rows={checklist.rows} rowKey={(row) => String(row.product.id)} />}
                        {checklist.rows.length === 0 && filters.filter === 'not_counted' && <NoResults onClear={() => visit(null)} />}
                        {checklist.rows.length === 0 && filters.filter !== 'not_counted' && <EmptyState body={t('inventory.onboarding.checklist_empty')} />}
                        {(checklist.previousCursor !== null || checklist.nextCursor !== null) && (
                            <div className={'bk-actions'}>
                                {checklist.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters.filter, { before: checklist.previousCursor ?? undefined })} />}
                                {checklist.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters.filter, { after: checklist.nextCursor ?? undefined })} />}
                            </div>
                        )}
                    </div>
                </Section>
            </div>
        </PageContainer>
    );
}

/** The words of each dialog (COPY_DECK section 9.13). */
function wordsOf(action: Action, counts: OnboardingCounts): { title: string; body: ReactNode; confirm: string; done: string } {
    switch (action.id) {
        case 'start':
            return { title: t('inventory.onboarding.start_confirm.title'), body: <p>{t('inventory.onboarding.start_confirm.body')}</p>, confirm: t('inventory.onboarding.start'), done: t('inventory.onboarding.started') };
        case 'release':
            return {
                title: t('inventory.onboarding.release_review.title'),
                body: (
                    <>
                        <p>{t('inventory.onboarding.release_review.checks')}</p>
                        <p>{t('inventory.onboarding.release_review.counts', { dihitung: formatCount(counts.counted), belum: formatCount(counts.notCounted), biaya: formatCount(counts.unknownCost) })}</p>
                        <p>{t('inventory.onboarding.release_review.warning')}</p>
                    </>
                ),
                confirm: t('inventory.onboarding.release'),
                done: t('inventory.onboarding.released'),
            };
        case 'withdraw':
            return { title: t('inventory.onboarding.withdraw_zero_review.title'), body: <p>{t('inventory.onboarding.withdraw_zero_review.body')}</p>, confirm: t('inventory.onboarding.withdraw_zero'), done: t('inventory.onboarding.zero_withdrawn') };
    }
}

/**
 * One command of the page: its dialog until the Owner confirms, then its panels — the recent-authentication
 * dialog, UXS-14 and UXS-15 with their actions (UXI-290), and a refusal's state. Its hook lives while the
 * action runs, so that a new opening is a new intent (ADR-007 section 2). A changed onboarding state or zero
 * count (STALE_VERSION) reads the page again (UXS-17).
 */
function ActionRun({ action, onboarding, counts, onClose, onDone, onStale }: { action: Action; onboarding: OnboardingInfo; counts: OnboardingCounts; onClose: () => void; onDone: (text: string) => void; onStale: () => void }) {
    const control = useCommand(COMMAND_OF[action.id]);
    const { phase } = control;
    const [reason, setReason] = useState('');
    const words = wordsOf(action, counts);
    const asking = phase.kind === 'idle' || phase.kind === 'submitting';
    const stale = phase.kind === 'rejected' && phase.refusal.code === STALE_VERSION;
    const committed = phase.kind === 'committed';

    useEffect(() => {
        if (stale) {
            onStale();
        }
    }, [stale]);

    useEffect(() => {
        // A request of the family that this run only adopted is another command's: the page says what is true of it (R-01).
        if (committed) {
            onDone(control.ownRequest ? words.done : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    const confirm = (): void => {
        const expected = { onboardingVersion: onboarding.version };

        control.submit(action.id === 'withdraw' ? { zeroCountId: action.zeroCountId, reason: reason.trim() } : {}, expected);
    };

    const busy = phase.kind === 'submitting';
    const needsReason = action.id === 'withdraw' && reason.trim() === '';

    return (
        <>
            <Dialog
                open={asking}
                onOpenChange={(next) => (next ? undefined : onClose())}
                title={words.title}
                description={words.body}
                dismissible={!busy}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} data-initial-focus={true} onClick={onClose} />
                        <Button
                            label={words.confirm}
                            variant={'primary'}
                            {...(action.id === 'release' ? { shortcut: 'confirm' as const } : {})}
                            enterActivates={false}
                            busy={busy}
                            disabledReason={needsReason ? t('ux.disabled.form_incomplete') : undefined}
                            onClick={confirm}
                        />
                    </>
                }
            >
                {action.id === 'withdraw' && (
                    <>
                        <p className={'bk-meta'}>{action.product}</p>
                        <Field label={t('inventory.onboarding.withdraw_zero_review.reason')} required={true}>
                            <TextInput value={reason} maxLength={REASON_MAX} autoComplete={'off'} disabled={busy} onChange={(event) => setReason(event.target.value)} />
                        </Field>
                    </>
                )}
            </Dialog>
            {!asking && !stale && !committed && (
                <div className={'bk-stack'}>
                    <CommandPanels control={control} />
                    {(phase.kind === 'rejected' || phase.kind === 'not_committed' || phase.kind === 'throttled') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={onClose} />}
                </div>
            )}
        </>
    );
}
