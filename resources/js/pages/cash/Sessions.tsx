import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { StatusWord } from '@/components/ui/Status';
import { EmptyState, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { VarianceWord } from '@/lib/cash/Parts';
import { choiceKey, choiceOf, CLOSED, OPEN, registerText, sessionPage, sessionsAddress, sessionsShell, statusOf, statusWord, type FilterChoice, type SessionFilters, type SessionPage, type SessionRow } from '@/lib/cash/sessions';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { personaOf, type RoleCode } from '@/lib/navigation';

// UXP-205 "Sesi kas" (ROUTE_CONTRACTS RP-205; COPY_DECK section 7.10; INTERACTION_INVENTORY UXI-135 to
// UXI-138; NAVIGATION_CONTRACTS UXP-205; UI_STATES section 6; BR-CASH-21; TASK-030 scope item 3): the
// sessions in the viewer's scope, newest first, paged on the server with "Berikutnya" and "Sebelumnya";
// the filters "Status" and "Tanggal", one day of Asia/Jakarta of the opening, and for the Owner "Laci
// kas" and "Penanggung jawab" (UXI-136). A cashier and the combined account see only their own
// sessions, which the page says. Each row leads to UXP-206 (UXI-137) and shows the register, the
// responsible user, the opening and close times — "Dibuka" while still OPEN — and the variance in
// words. It is the top-level "Sesi kas" of a cashier and the combined account (UXI-004) and the
// Owner's item of the Laporan sub-navigation (UXI-019). The filters, the page's size and the page stand
// in the address (NC-10), which a session's way back returns to (UXI-148).

interface SessionsProps {
    readonly filters: SessionFilters;
    readonly sessions: SessionPage;
    readonly registers: readonly { readonly id: number; readonly code: string; readonly label: string }[];
    readonly responsibles: readonly { readonly id: number; readonly displayName: string }[];
}

interface SharedProps {
    readonly auth: { readonly user: { readonly roles: readonly RoleCode[] } } | null;
    readonly [key: string]: unknown;
}

export default function Sessions(props: SessionsProps) {
    const title = t('cash.sessions.title');
    const owner = personaOf(usePage<SharedProps>().props.auth?.user.roles ?? []) === 'owner';

    return (
        <AppShell title={title} {...sessionsShell(owner)}>
            <SessionList title={title} owner={owner} {...props} />
        </AppShell>
    );
}

function SessionList({ title, owner, filters, sessions, registers, responsibles }: SessionsProps & { title: string; owner: boolean }) {
    const [visiting, setVisiting] = useState(false);
    const choice = choiceOf(filters);

    const visit = (next: FilterChoice, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(sessionsAddress(next, cursor), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    const chooseDay = (value: string): void => visit({ ...choice, from: value === '' ? null : value, to: value === '' ? null : value });
    const chooseStatus = (value: string): void => visit({ ...choice, status: statusOf(value) });
    const chooseRegister = (value: string): void => visit({ ...choice, register: choiceKey(value) });
    const chooseResponsible = (value: string): void => visit({ ...choice, responsible: choiceKey(value) });
    const clear = (): void => visit({ status: null, from: null, to: null, register: null, responsible: null, perPage: choice.perPage });
    const filtered = filters.status !== null || filters.from !== null || filters.to !== null || filters.register !== null || filters.responsible !== null;

    const columns: readonly Column<SessionRow>[] = [
        {
            id: 'number',
            header: t('cash.sessions.col_number'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={sessionPage(row.id)}>
                    {row.number}
                </a>
            ),
        },
        { id: 'register', header: t('cash.sessions.col_register'), cell: (row) => registerText(row.registerCode, row.registerLabel) },
        { id: 'responsible', header: t('cash.sessions.col_responsible'), cell: (row) => row.responsible },
        { id: 'opened', header: t('cash.sessions.col_opened'), cell: (row) => formatDateTime(row.openedAt) },
        { id: 'closed', header: t('cash.sessions.col_closed'), cell: (row) => (row.closedAt === null ? <StatusWord tone={'info'} label={statusWord(row.status)} /> : formatDateTime(row.closedAt)) },
        { id: 'variance', header: t('cash.sessions.col_variance'), numeric: true, cell: (row) => (row.variance === null ? null : <VarianceWord recorded={row.variance} />) },
    ];

    const statusChoices = [
        { value: OPEN, label: statusWord(OPEN) },
        { value: CLOSED, label: statusWord(CLOSED) },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} {...(owner ? {} : { subtitle: t('cash.sessions.own_scope') })} />}
            toolbar={
                <>
                    <Field label={t('cash.sessions.filter_status')}>
                        <SelectInput value={choice.status ?? ''} onChange={(event) => chooseStatus(event.target.value)}>
                            <option value={''} />
                            {statusChoices.map((entry) => (
                                <option key={entry.value} value={entry.value}>
                                    {entry.label}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label={t('cash.sessions.filter_date')} error={filters.from !== null && filters.to !== null && filters.from > filters.to ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={filters.from ?? ''} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                    {owner && (
                        <>
                            <Field label={t('cash.sessions.filter_register')}>
                                <SelectInput value={choice.register ?? ''} onChange={(event) => chooseRegister(event.target.value)}>
                                    <option value={''} />
                                    {registers.map((register) => (
                                        <option key={register.id} value={String(register.id)}>
                                            {registerText(register.code, register.label)}
                                        </option>
                                    ))}
                                </SelectInput>
                            </Field>
                            <Field label={t('cash.sessions.filter_responsible')}>
                                <SelectInput value={choice.responsible ?? ''} onChange={(event) => chooseResponsible(event.target.value)}>
                                    <option value={''} />
                                    {responsibles.map((person) => (
                                        <option key={person.id} value={String(person.id)}>
                                            {person.displayName}
                                        </option>
                                    ))}
                                </SelectInput>
                            </Field>
                        </>
                    )}
                </>
            }
            more={
                sessions.previousCursor !== null || sessions.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {sessions.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(choice, { before: sessions.previousCursor ?? undefined })} />}
                        {sessions.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(choice, { after: sessions.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {sessions.rows.length > 0 && <DataTable caption={title} columns={columns} rows={sessions.rows} rowKey={(row) => String(row.id)} />}
                {sessions.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {sessions.rows.length === 0 && !filtered && <EmptyState body={t('cash.sessions.empty')} />}
            </div>
        </ListLayout>
    );
}
