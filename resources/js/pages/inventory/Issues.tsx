import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { EmptyState, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { blockingRequest } from '@/lib/commands/families';
import { useUnresolved } from '@/lib/commands/unresolved';
import { formatCount, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { postingBlockedReason } from '@/lib/inventory/Parts';
import {
    datesOutOfOrder,
    ISSUE_COMMAND,
    ISSUE_REASONS,
    issuePage,
    issueReasonOf,
    ISSUES_SHELL,
    issuesAddress,
    NEW_ISSUE_PAGE,
    reasonWord,
    type IssueFilters,
    type IssueRow,
    type KeysetPage,
} from '@/lib/inventory/stock';

// UXP-407 "Barang keluar" (ROUTE_CONTRACTS RP-407; COPY_DECK section 9.8; INTERACTION_INVENTORY
// UXI-252 to UXI-255; NAVIGATION_CONTRACTS UXP-407; UI_STATES section 6; BR-INV-18 to BR-INV-22;
// D-018; TASK-023 scope item 4): the confirmed manual issues, newest first, paged on the server
// with "Berikutnya" and "Sebelumnya"; the filters "Tanggal", one day of Asia/Jakarta, and "Alasan",
// one of the five of BR-INV-19. It is the Owner's explicit "Barang keluar" entry, a named item of
// the Stok sub-navigation (D-018). "Catat barang keluar" (UXI-253) is disabled with its reason
// while the onboarding freeze is on (UXS-27) or while the user holds an unresolved receiving,
// manual issue or adjustment request, with the way to UXP-006 (NC-13). The filters and the page
// stand in the address (NC-10). No cost is shown (ADR-008 section 6).

interface IssuesProps {
    readonly filters: IssueFilters;
    readonly issues: KeysetPage<IssueRow>;
    readonly postingFrozen: boolean;
}

export default function Issues(props: IssuesProps) {
    const title = t('inventory.issues.title');

    return (
        <AppShell title={title} {...ISSUES_SHELL}>
            <IssuesList title={title} {...props} />
        </AppShell>
    );
}

function IssuesList({ title, filters, issues, postingFrozen }: IssuesProps & { title: string }) {
    const unresolved = useUnresolved();
    const [visiting, setVisiting] = useState(false);
    const blocked = blockingRequest(unresolved.union, ISSUE_COMMAND.operation, ISSUE_COMMAND.family) !== null;
    const reason = postingBlockedReason(postingFrozen, blocked);
    const label = t('inventory.issue_new.title');

    const visit = (next: IssueFilters, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(issuesAddress(next, cursor), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    const chooseDay = (value: string): void => visit({ ...filters, from: value === '' ? null : value, to: value === '' ? null : value });

    const columns: readonly Column<IssueRow>[] = [
        {
            id: 'time',
            header: t('inventory.issues.col_time'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={issuePage(row.id)}>
                    {formatDateTime(row.confirmedAt)}
                </a>
            ),
        },
        { id: 'reason', header: t('inventory.issues.col_reason'), cell: (row) => reasonWord(row.reasonCode) },
        { id: 'lines', header: t('inventory.issues.col_lines'), numeric: true, cell: (row) => formatCount(row.lineCount) },
        { id: 'actor', header: t('inventory.issues.col_actor'), cell: (row) => row.actorDisplayName },
    ];

    const action = reason === undefined ? <ButtonLink href={NEW_ISSUE_PAGE} label={label} variant={'primary'} /> : <Button label={label} variant={'primary'} enterActivates={false} disabledReason={reason} />;
    const filtered = filters.from !== null || filters.to !== null || filters.reason !== null;
    const clear = (): void => visit({ from: null, to: null, reason: null });

    return (
        <ListLayout
            header={<PageHeader title={title} actions={action} />}
            toolbar={
                <>
                    <Field label={t('inventory.issues.filter_date')} error={datesOutOfOrder(filters.from, filters.to) ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={filters.from ?? ''} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                    <Field label={t('inventory.issues.filter_reason')}>
                        <SelectInput value={filters.reason ?? ''} onChange={(event) => visit({ ...filters, reason: issueReasonOf(event.target.value) })}>
                            <option value={''} />
                            {ISSUE_REASONS.map((entry) => (
                                <option key={entry.reason} value={entry.reason}>
                                    {t(entry.label)}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                </>
            }
            more={
                issues.previousCursor !== null || issues.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {issues.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { before: issues.previousCursor ?? undefined })} />}
                        {issues.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { after: issues.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {issues.rows.length > 0 && <DataTable caption={title} columns={columns} rows={issues.rows} rowKey={(row) => String(row.id)} />}
                {issues.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {issues.rows.length === 0 && !filtered && <EmptyState body={t('inventory.issues.empty')} />}
            </div>
        </ListLayout>
    );
}
