import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { EmptyState, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { registerText } from '@/lib/cash/sessions';
import { formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { personaOf, type DestinationId, type RoleCode } from '@/lib/navigation';
import { methodWord, saleChoiceOf, salePage, salesAddress, type SaleChoice, type SaleFilters, type SalePage, type SaleRow } from '@/lib/sales/sales';

// UXP-201 "Penjualan" (ROUTE_CONTRACTS RP-201; COPY_DECK section 7.6; INTERACTION_INVENTORY UXI-112 to
// UXI-116; NAVIGATION_CONTRACTS UXP-201; UI_STATES section 6; BR-DOC-06; REQ-SALE-12; TASK-031 scope item
// 4): the Sales in the viewer's scope, newest first, paged on the server with "Berikutnya" and
// "Sebelumnya"; the filters "Tanggal", one business day of Asia/Jakarta, and "Nomor penjualan", whose Enter
// runs the search only, and for the Owner "Kasir" and "Laci kas" (UXI-114). A cashier and the combined
// account see only their own Sales, which the page says. Each row leads to UXP-202 (UXI-115). The filters,
// the page's size and the page stand in the address (NC-10), which a Sale's way back returns to (UXI-121).

const DESTINATION: DestinationId = 'sales';

interface SalesProps {
    readonly filters: SaleFilters;
    readonly sales: SalePage;
    readonly registers: readonly { readonly id: number; readonly code: string; readonly label: string }[];
    readonly cashiers: readonly { readonly id: number; readonly displayName: string }[];
}

interface SharedProps {
    readonly auth: { readonly user: { readonly roles: readonly RoleCode[] } } | null;
    readonly [key: string]: unknown;
}

export default function Index(props: SalesProps) {
    const title = t('sales.list.title');
    const owner = personaOf(usePage<SharedProps>().props.auth?.user.roles ?? []) === 'owner';

    return (
        <AppShell title={title} current={DESTINATION}>
            <SaleList title={title} owner={owner} {...props} />
        </AppShell>
    );
}

/** A key of a filter's choice, or null for none (the empty choice). */
function choiceKey(value: string): string | null {
    return /^[1-9][0-9]{0,18}$/.test(value) ? value : null;
}

function SaleList({ title, owner, filters, sales, registers, cashiers }: SalesProps & { title: string; owner: boolean }) {
    const [visiting, setVisiting] = useState(false);
    const [number, setNumber] = useState(filters.number ?? '');
    const choice = saleChoiceOf(filters);

    const visit = (next: SaleChoice, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(salesAddress(next, cursor), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    const chooseDay = (value: string): void => visit({ ...choice, from: value === '' ? null : value, to: value === '' ? null : value });
    const chooseCashier = (value: string): void => visit({ ...choice, cashier: choiceKey(value) });
    const chooseRegister = (value: string): void => visit({ ...choice, register: choiceKey(value) });

    // UXI-113: Enter in the search field runs the search only.
    const search = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const entry = number.trim();
        visit({ ...choice, number: entry === '' ? null : entry });
    };

    const clear = (): void => {
        setNumber('');
        visit({ from: null, to: null, number: null, cashier: null, register: null, perPage: choice.perPage });
    };

    const filtered = filters.from !== null || filters.to !== null || filters.number !== null || filters.cashier !== null || filters.register !== null;

    const columns: readonly Column<SaleRow>[] = [
        {
            id: 'number',
            header: t('sales.list.col_number'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={salePage(row.id)}>
                    {row.number}
                </a>
            ),
        },
        { id: 'time', header: t('sales.list.col_time'), cell: (row) => formatDateTime(row.committedAt) },
        { id: 'total', header: t('sales.list.col_total'), numeric: true, cell: (row) => formatMoney(row.total) },
        { id: 'method', header: t('sales.list.col_method'), cell: (row) => methodWord(row.method) },
        { id: 'cashier', header: t('sales.list.col_cashier'), cell: (row) => row.cashier },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} {...(owner ? {} : { subtitle: t('sales.list.own_scope') })} />}
            toolbar={
                <>
                    <Field label={t('sales.list.filter_date')} error={filters.from !== null && filters.to !== null && filters.from > filters.to ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={filters.from ?? ''} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                    <form role={'search'} onSubmit={search}>
                        <Field label={t('sales.list.search_number')}>
                            <TextInput type={'search'} inputMode={'numeric'} autoComplete={'off'} maxLength={32} value={number} onChange={(event) => setNumber(event.target.value)} />
                        </Field>
                    </form>
                    {owner && (
                        <>
                            <Field label={t('sales.list.filter_cashier')}>
                                <SelectInput value={choice.cashier ?? ''} onChange={(event) => chooseCashier(event.target.value)}>
                                    <option value={''} />
                                    {cashiers.map((person) => (
                                        <option key={person.id} value={String(person.id)}>
                                            {person.displayName}
                                        </option>
                                    ))}
                                </SelectInput>
                            </Field>
                            <Field label={t('sales.list.filter_register')}>
                                <SelectInput value={choice.register ?? ''} onChange={(event) => chooseRegister(event.target.value)}>
                                    <option value={''} />
                                    {registers.map((register) => (
                                        <option key={register.id} value={String(register.id)}>
                                            {registerText(register.code, register.label)}
                                        </option>
                                    ))}
                                </SelectInput>
                            </Field>
                        </>
                    )}
                </>
            }
            more={
                sales.previousCursor !== null || sales.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {sales.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(choice, { before: sales.previousCursor ?? undefined })} />}
                        {sales.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(choice, { after: sales.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {sales.rows.length > 0 && <DataTable caption={title} columns={columns} rows={sales.rows} rowKey={(row) => String(row.id)} />}
                {sales.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {sales.rows.length === 0 && !filtered && <EmptyState body={t('sales.list.empty')} />}
            </div>
        </ListLayout>
    );
}
