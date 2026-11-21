import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { EmptyState, LoadFailed, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import {
    accountOf,
    ACTION_CODES,
    ACTION_LABELS,
    actionWord,
    activityAddress,
    actorWord,
    isActionCode,
    referenceLink,
    SETTINGS_PAGE,
    type ActivityFilters,
    type AuditActor,
    type AuditReference,
} from '@/lib/audit/activity';
import { ProductFilter } from '@/lib/catalog/ProductFilter';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-612 "Riwayat aktivitas" (ROUTE_CONTRACTS RP-612; COPY_DECK sections 11.12 and 11.14; INTERACTION_INVENTORY
// UXI-377 to UXI-381; NAVIGATION_CONTRACTS UXP-612; UI_STATES section 6; REQ-AUD-01, REQ-AUD-02; BR-AUD-02 to
// BR-AUD-04; TASK-042 scope item 1), the Owner's alone (CAP-32): the business audit, newest first, paged on the
// server with "Berikutnya" and "Sebelumnya". The filters (UXI-377): "Tanggal", one day of Asia/Jakarta; "Pengguna",
// every account by its name now; "Jenis", the label of an action code; "Produk", through the shared Product
// Search; "Sesi kas", a session's number, and "Referensi", each searched on Enter. Each row (UXI-378) shows the
// time, who acted by the name the event froze, the action's label and its reference — a link to the object's page
// where it has one (UXI-379), else text — and opens on demand its reason, the Owner-only reason of a cost
// correction among them. The fields of an event's change data are not shown: COPY_DECK has no label for them, and
// a stored field name is never interface copy (AGENTS rule 14, EXC-51; OPEN_ITEMS T-56). The filters and the page
// stand in the address (NC-10), so that the way back of a linked page keeps them.
// The way back is "Kembali ke Pengaturan" (UXI-381).

/** The destination of the navigation that holds the page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

interface ActivityRow {
    readonly id: number;
    readonly occurredAt: string;
    readonly action: string;
    readonly actor: AuditActor;
    readonly reference: AuditReference;
    readonly reason: string | null;
}

interface ActivityProps {
    readonly filters: ActivityFilters;
    readonly users: readonly { readonly id: number; readonly displayName: string }[];
    readonly product: { readonly id: number; readonly name: string; readonly sku: string } | null;
    readonly events: { readonly rows: readonly ActivityRow[]; readonly nextCursor: string | null; readonly previousCursor: string | null };
}

export default function Activity(props: ActivityProps) {
    const title = t('audit.activity.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <ActivityList title={title} {...props} />
        </AppShell>
    );
}

function ActivityList({ title, filters, users, product, events }: ActivityProps & { title: string }) {
    const [visiting, setVisiting] = useState(false);
    // UXS-22: the address whose read failed, read again by "Coba lagi"; never the earlier rows.
    const [failed, setFailed] = useState<string | null>(null);
    const [session, setSession] = useState(filters.session ?? '');
    const [reference, setReference] = useState(filters.reference ?? '');

    const go = (address: string): void => {
        const refused = (): boolean => {
            setFailed(address);

            return false;
        };

        setVisiting(true);
        router.visit(address, { preserveScroll: true, preserveState: true, onSuccess: () => setFailed(null), onHttpException: refused, onNetworkError: refused, onFinish: () => setVisiting(false) });
    };

    const visit = (next: ActivityFilters, cursor: { readonly after?: string; readonly before?: string } = {}): void => go(activityAddress(next, cursor));

    const apply = (patch: Partial<ActivityFilters>): void => visit({ ...filters, ...patch });
    const chooseDay = (value: string): void => apply({ from: value === '' ? null : value, to: value === '' ? null : value });

    // "Sesi kas" and "Referensi": Enter in the field searches it, and runs nothing else.
    const searchSession = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ session: session.trim() === '' ? null : session.trim() });
    };

    const searchReference = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ reference: reference.trim() === '' ? null : reference.trim() });
    };

    const filtered = filters.from !== null || filters.to !== null || filters.user !== null || filters.action !== null || filters.product !== null || filters.session !== null || filters.reference !== null;
    const clear = (): void => {
        setSession('');
        setReference('');
        visit({ from: null, to: null, user: null, action: null, product: null, session: null, reference: null, perPage: filters.perPage });
    };

    const columns: readonly Column<ActivityRow>[] = [
        { id: 'time', header: t('audit.activity.col_time'), isHead: true, cell: (row) => formatDateTime(row.occurredAt) },
        { id: 'user', header: t('audit.activity.col_user'), cell: (row) => actorWord(row.actor, 'activity') },
        { id: 'action', header: t('audit.activity.col_action'), cell: (row) => actionWord(row.action) },
        { id: 'reference', header: t('audit.activity.col_reference'), cell: (row) => <ReferenceCell reference={row.reference} /> },
        { id: 'details', header: t('audit.activity.details'), cell: (row) => <Details row={row} /> },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} back={{ href: SETTINGS_PAGE, page: t('config.settings.title') }} />}
            toolbar={
                <>
                    <Field label={t('audit.activity.filter_date')} error={filters.from !== null && filters.to !== null && filters.from > filters.to ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={filters.from ?? ''} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                    <Field label={t('audit.activity.filter_user')}>
                        <SelectInput value={filters.user === null ? '' : String(filters.user)} onChange={(event) => apply({ user: accountOf(users, event.target.value) })}>
                            <option value={''} />
                            {users.map((person) => (
                                <option key={person.id} value={String(person.id)}>
                                    {person.displayName}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label={t('audit.activity.filter_type')}>
                        <SelectInput value={filters.action ?? ''} onChange={(event) => apply({ action: isActionCode(event.target.value) ? event.target.value : null })}>
                            <option value={''} />
                            {ACTION_CODES.map((code) => (
                                <option key={code} value={code}>
                                    {t(ACTION_LABELS[code])}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <ProductFilter key={String(filters.product ?? '')} label={t('audit.activity.filter_product')} chosen={product === null ? null : { name: product.name }} onChoose={(picked) => apply({ product: picked === null ? null : picked.id })} />
                    {/* Two search landmarks on one page, each named by its field's label (axe landmark-unique). */}
                    <form role={'search'} aria-label={t('audit.activity.filter_session')} onSubmit={searchSession}>
                        <Field label={t('audit.activity.filter_session')}>
                            <TextInput type={'search'} autoComplete={'off'} maxLength={40} value={session} onChange={(event) => setSession(event.target.value)} />
                        </Field>
                    </form>
                    <form role={'search'} aria-label={t('audit.activity.filter_reference')} onSubmit={searchReference}>
                        <Field label={t('audit.activity.filter_reference')}>
                            <TextInput type={'search'} autoComplete={'off'} maxLength={100} value={reference} onChange={(event) => setReference(event.target.value)} />
                        </Field>
                    </form>
                </>
            }
            more={
                events.previousCursor !== null || events.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {events.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { before: events.previousCursor ?? undefined })} />}
                        {events.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { after: events.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {failed !== null && <LoadFailed onRetry={() => go(failed)} />}
                {failed === null && events.rows.length > 0 && <DataTable caption={title} columns={columns} rows={events.rows} rowKey={(row) => String(row.id)} wide={true} />}
                {failed === null && events.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {failed === null && events.rows.length === 0 && !filtered && <EmptyState body={t('audit.activity.empty')} />}
            </div>
        </ListLayout>
    );
}

/** UXI-379: the object's page where it has one, its number or name as the link's text; else text. */
function ReferenceCell({ reference }: { reference: AuditReference }) {
    const link = referenceLink(reference);

    if (link === null) {
        return reference.text === null ? null : <>{reference.text}</>;
    }

    return (
        <a className={'bk-table-link'} href={link.href}>
            {t('audit.activity.reference_link', { referensi: link.text })}
        </a>
    );
}

/** UXI-378: the event's reason, opened on demand; nothing to open without one. */
function Details({ row }: { row: ActivityRow }) {
    if (row.reason === null) {
        return null;
    }

    return (
        <details className={'bk-version bk-audit-detail'}>
            <summary>{t('audit.activity.expand')}</summary>
            <div className={'bk-stack'}>
                <p className={'bk-meta'}>{t('audit.activity.reason')}</p>
                <p>{row.reason}</p>
            </div>
        </details>
    );
}
