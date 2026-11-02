import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, TextInput } from '@/components/ui/Field';
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
    NEW_RECEIVING_PAGE,
    RECEIVING_COMMAND,
    receivingPage,
    RECEIVINGS_SHELL,
    receivingsAddress,
    type KeysetPage,
    type ReceivingFilters,
    type ReceivingRow,
} from '@/lib/inventory/stock';

// UXP-404 "Barang masuk" (ROUTE_CONTRACTS RP-404; COPY_DECK section 9.5; INTERACTION_INVENTORY
// UXI-230 to UXI-233; NAVIGATION_CONTRACTS UXP-404; UI_STATES section 6; BR-INV-17; D-069 (a);
// TASK-022 scope item 1): the confirmed receiving documents, newest first, paged on the server
// with "Berikutnya" and "Sebelumnya"; the filter "Tanggal", one day of Asia/Jakarta, and the search
// "Cari referensi atau produk". There is no list of unsaved drafts (D-069). "Catat barang masuk"
// (UXI-231) is disabled with its reason while the onboarding freeze is on (UXS-27) or while the
// user holds an unresolved receiving, manual issue or adjustment request, with the way to UXP-006
// (NC-13). The filters and the page stand in the address (NC-10).

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

interface ReceivingsProps {
    readonly filters: ReceivingFilters;
    readonly receivings: KeysetPage<ReceivingRow>;
    readonly postingFrozen: boolean;
}

export default function Receivings(props: ReceivingsProps) {
    const title = t('inventory.receipts.title');

    return (
        <AppShell title={title} {...RECEIVINGS_SHELL}>
            <ReceivingsList title={title} {...props} />
        </AppShell>
    );
}

function ReceivingsList({ title, filters, receivings, postingFrozen }: ReceivingsProps & { title: string }) {
    const unresolved = useUnresolved();
    const [q, setQ] = useState(filters.q);
    const [visiting, setVisiting] = useState(false);
    const blocked = blockingRequest(unresolved.union, RECEIVING_COMMAND.operation, RECEIVING_COMMAND.family) !== null;
    const reason = postingBlockedReason(postingFrozen, blocked);
    const label = t('inventory.receipt_new.title');

    const visit = (next: ReceivingFilters, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(receivingsAddress(next, cursor), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    // A typed search runs once the keys pause.
    useEffect(() => {
        const text = q.trim();

        if (text === filters.q) {
            return undefined;
        }

        const timer = window.setTimeout(() => visit({ ...filters, q: text }), TYPING_MS);

        return () => window.clearTimeout(timer);
    }, [q, filters]);

    const chooseDay = (value: string): void => visit({ ...filters, from: value === '' ? null : value, to: value === '' ? null : value });

    const columns: readonly Column<ReceivingRow>[] = [
        {
            id: 'time',
            header: t('inventory.receipts.col_time'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={receivingPage(row.id)}>
                    {formatDateTime(row.confirmedAt)}
                </a>
            ),
        },
        { id: 'reference', header: t('inventory.receipts.col_reference'), cell: (row) => row.reference },
        { id: 'lines', header: t('inventory.receipts.col_lines'), numeric: true, cell: (row) => formatCount(row.lineCount) },
        { id: 'actor', header: t('inventory.receipts.col_actor'), cell: (row) => row.actorDisplayName },
    ];

    const action = reason === undefined ? <ButtonLink href={NEW_RECEIVING_PAGE} label={label} variant={'primary'} /> : <Button label={label} variant={'primary'} enterActivates={false} disabledReason={reason} />;
    const filtered = filters.from !== null || filters.to !== null || filters.q !== '';
    const clear = (): void => {
        setQ('');
        visit({ from: null, to: null, q: '' });
    };

    return (
        <ListLayout
            header={<PageHeader title={title} actions={action} />}
            toolbar={
                <>
                    <Field label={t('inventory.receipts.filter_date')} error={datesOutOfOrder(filters.from, filters.to) ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={filters.from ?? ''} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                    <Field label={t('inventory.receipts.search')}>
                        <TextInput type={'search'} autoComplete={'off'} maxLength={128} icon={'search'} value={q} onChange={(event) => setQ(event.target.value)} />
                    </Field>
                </>
            }
            more={
                receivings.previousCursor !== null || receivings.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {receivings.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { before: receivings.previousCursor ?? undefined })} />}
                        {receivings.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { after: receivings.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {receivings.rows.length > 0 && <DataTable caption={title} columns={columns} rows={receivings.rows} rowKey={(row) => String(row.id)} />}
                {receivings.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {receivings.rows.length === 0 && !filtered && <EmptyState body={t('inventory.receipts.empty')} />}
            </div>
        </ListLayout>
    );
}
