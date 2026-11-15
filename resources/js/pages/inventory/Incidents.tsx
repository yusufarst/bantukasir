import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusWord } from '@/components/ui/Status';
import { EmptyState, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { INCIDENT_STATUSES, INCIDENTS_SHELL, incidentPage, incidentsAddress, statusLabel, type IncidentRow, type IncidentStatus } from '@/lib/inventory/incidents';

// UXP-414 "Insiden rekonsiliasi" (ROUTE_CONTRACTS RP-414; COPY_DECK section 9.15; INTERACTION_INVENTORY
// UXI-301 and UXI-302; NAVIGATION_CONTRACTS UXP-414; UI_STATES section 6; BR-INV-45; TASK-036), the
// Owner's alone (CAP-18): the products whose postings the daily reconciliation blocked, newest first,
// paged on the server with "Berikutnya". The filter "Status" (UXI-301) keeps one state, "Mutasi diblokir"
// or "Mutasi dibuka kembali", and stands in the address (NC-10). A row leads to its incident (UXI-302).

interface IncidentsProps {
    readonly filters: { readonly status: IncidentStatus | null };
    readonly incidents: { readonly rows: readonly IncidentRow[]; readonly nextCursor: string | null };
}

export default function Incidents(props: IncidentsProps) {
    const title = t('inventory.incidents.title');

    return (
        <AppShell title={title} {...INCIDENTS_SHELL}>
            <IncidentsList title={title} {...props} />
        </AppShell>
    );
}

function IncidentsList({ title, filters, incidents }: IncidentsProps & { title: string }) {
    const [visiting, setVisiting] = useState(false);

    const visit = (status: IncidentStatus | null, after?: string): void => {
        setVisiting(true);
        router.visit(incidentsAddress(status, after), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    const columns: readonly Column<IncidentRow>[] = [
        {
            id: 'product',
            header: t('inventory.incidents.col_product'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={incidentPage(row.id)}>
                    {row.product.name}
                </a>
            ),
        },
        { id: 'detected', header: t('inventory.incidents.col_detected'), cell: (row) => formatDateTime(row.detectedAt) },
        { id: 'status', header: t('inventory.incidents.col_status'), cell: (row) => <StatusWord tone={row.status === 'BLOCKED' ? 'danger' : 'success'} label={t(statusLabel(row.status))} /> },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} />}
            toolbar={
                <FilterChips label={t('inventory.incidents.filter_status')}>
                    {INCIDENT_STATUSES.map((entry) => (
                        <FilterChip key={entry.status} label={t(entry.label)} pressed={filters.status === entry.status} onPressedChange={(pressed) => visit(pressed ? entry.status : null)} />
                    ))}
                </FilterChips>
            }
            more={
                incidents.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters.status, incidents.nextCursor ?? undefined)} />
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {incidents.rows.length > 0 && <DataTable caption={title} columns={columns} rows={incidents.rows} rowKey={(row) => String(row.id)} />}
                {incidents.rows.length === 0 && filters.status !== null && <NoResults onClear={() => visit(null)} />}
                {incidents.rows.length === 0 && filters.status === null && <EmptyState body={t('inventory.incidents.empty')} />}
            </div>
        </ListLayout>
    );
}
