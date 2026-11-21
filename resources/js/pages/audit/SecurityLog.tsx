import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { EmptyState, LoadFailed, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { accountOf, actorWord, outcomeWord, SECURITY_TYPES, securityAddress, securityTypeOf, securityTypeWord, SETTINGS_PAGE, type AuditActor, type SecurityFilters } from '@/lib/audit/activity';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-613 "Riwayat keamanan" (ROUTE_CONTRACTS RP-613; COPY_DECK section 11.13; INTERACTION_INVENTORY UXI-382 to
// UXI-385; NAVIGATION_CONTRACTS UXP-613; UI_STATES section 6; REQ-AUD-02, REQ-NFR-07; SECURITY_MODEL SEC-LOG-03;
// TASK-042 scope item 2), the Owner's alone (CAP-32): the security log, newest first, paged on the server with
// "Berikutnya" and "Sebelumnya". The filters (UXI-382): "Tanggal", one day of Asia/Jakarta; "Jenis", one of the
// nine event types; "Pengguna", an account, found as the one who acted or the one concerned. Each row (UXI-383)
// shows the time, the event type, who acted and the outcome, SUCCEEDED or REFUSED, and opens on demand the
// account concerned and the network address; never a secret, a token or a password. The filters and the page
// stand in the address (NC-10). The way back is "Kembali ke Pengaturan" (UXI-385).

/** The destination of the navigation that holds the page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

interface SecurityRow {
    readonly id: number;
    readonly occurredAt: string;
    readonly type: string;
    readonly actor: AuditActor;
    readonly subject: string | null;
    readonly outcome: string;
    readonly address: string | null;
}

interface SecurityProps {
    readonly filters: SecurityFilters;
    readonly users: readonly { readonly id: number; readonly displayName: string }[];
    readonly entries: { readonly rows: readonly SecurityRow[]; readonly nextCursor: string | null; readonly previousCursor: string | null };
}

export default function SecurityLog(props: SecurityProps) {
    const title = t('audit.security.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <SecurityList title={title} {...props} />
        </AppShell>
    );
}

function SecurityList({ title, filters, users, entries }: SecurityProps & { title: string }) {
    const [visiting, setVisiting] = useState(false);
    // UXS-22: the address whose read failed, read again by "Coba lagi"; never the earlier rows.
    const [failed, setFailed] = useState<string | null>(null);

    const go = (address: string): void => {
        const refused = (): boolean => {
            setFailed(address);

            return false;
        };

        setVisiting(true);
        router.visit(address, { preserveScroll: true, preserveState: true, onSuccess: () => setFailed(null), onHttpException: refused, onNetworkError: refused, onFinish: () => setVisiting(false) });
    };

    const visit = (next: SecurityFilters, cursor: { readonly after?: string; readonly before?: string } = {}): void => go(securityAddress(next, cursor));

    const apply = (patch: Partial<SecurityFilters>): void => visit({ ...filters, ...patch });
    const chooseDay = (value: string): void => apply({ from: value === '' ? null : value, to: value === '' ? null : value });

    const filtered = filters.from !== null || filters.to !== null || filters.type !== null || filters.user !== null;
    const clear = (): void => visit({ from: null, to: null, type: null, user: null, perPage: filters.perPage });

    const columns: readonly Column<SecurityRow>[] = [
        { id: 'time', header: t('audit.security.col_time'), isHead: true, cell: (row) => formatDateTime(row.occurredAt) },
        { id: 'type', header: t('audit.security.col_type'), cell: (row) => securityTypeWord(row.type) },
        { id: 'actor', header: t('audit.security.col_actor'), cell: (row) => actorWord(row.actor, 'security') },
        { id: 'outcome', header: t('audit.security.col_outcome'), cell: (row) => outcomeWord(row.outcome) },
        { id: 'details', header: t('audit.activity.details'), cell: (row) => <Details row={row} /> },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} back={{ href: SETTINGS_PAGE, page: t('config.settings.title') }} />}
            toolbar={
                <>
                    <Field label={t('audit.security.filter_date')} error={filters.from !== null && filters.to !== null && filters.from > filters.to ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={filters.from ?? ''} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                    <Field label={t('audit.security.filter_type')}>
                        <SelectInput value={filters.type ?? ''} onChange={(event) => apply({ type: securityTypeOf(event.target.value) })}>
                            <option value={''} />
                            {SECURITY_TYPES.map((entry) => (
                                <option key={entry.type} value={entry.type}>
                                    {t(entry.label)}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label={t('audit.security.filter_user')}>
                        <SelectInput value={filters.user === null ? '' : String(filters.user)} onChange={(event) => apply({ user: accountOf(users, event.target.value) })}>
                            <option value={''} />
                            {users.map((person) => (
                                <option key={person.id} value={String(person.id)}>
                                    {person.displayName}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                </>
            }
            more={
                entries.previousCursor !== null || entries.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {entries.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { before: entries.previousCursor ?? undefined })} />}
                        {entries.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { after: entries.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {failed !== null && <LoadFailed onRetry={() => go(failed)} />}
                {failed === null && entries.rows.length > 0 && <DataTable caption={title} columns={columns} rows={entries.rows} rowKey={(row) => String(row.id)} wide={true} />}
                {failed === null && entries.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {failed === null && entries.rows.length === 0 && !filtered && <EmptyState body={t('audit.security.empty')} />}
            </div>
        </ListLayout>
    );
}

/** UXI-383: the account the entry concerns and its network address, opened on demand; nothing to open without either. */
function Details({ row }: { row: SecurityRow }) {
    const items: KeyValue[] = [
        ...(row.subject === null ? [] : [{ key: 'subject', label: t('audit.security.col_subject'), value: row.subject }]),
        ...(row.address === null ? [] : [{ key: 'address', label: t('audit.security.col_address'), value: row.address }]),
    ];

    if (items.length === 0) {
        return null;
    }

    return (
        <details className={'bk-version bk-audit-detail'}>
            <summary>{t('audit.activity.expand')}</summary>
            <KeyValueList items={items} />
        </details>
    );
}
