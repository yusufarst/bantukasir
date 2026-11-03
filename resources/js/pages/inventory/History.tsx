import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { EmptyState, LoadFailed, Loading, NoResults, UnknownCode } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, productPage, searchProducts, type ProductRow } from '@/lib/catalog/products';
import { blockingRequest } from '@/lib/commands/families';
import { useUnresolved } from '@/lib/commands/unresolved';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { postingBlockedReason } from '@/lib/inventory/Parts';
import {
    ADJUST_CAPABILITY,
    ADJUSTMENT_COMMAND,
    adjustmentPage,
    backFrom,
    datesOutOfOrder,
    HISTORY_SHELL,
    historyAddress,
    MOVEMENT_TYPES,
    movementPage,
    REQUESTS_PAGE,
    signedQuantity,
    sourceLink,
    STOCK_PAGE,
    typeWord,
    type HistoryFilters,
    type HistoryOpener,
    type HistoryRow,
    type KeysetPage,
} from '@/lib/inventory/stock';

// UXP-402 "Riwayat stok" (ROUTE_CONTRACTS RP-402, RD-01 `stock`; COPY_DECK section 9.3;
// INTERACTION_INVENTORY UXI-219, UXI-220, UXI-222 and UXI-223; NAVIGATION_CONTRACTS UXP-402;
// UI_STATES section 6; BR-INV-02, BR-INV-44; TASK-022 scope item 2). The movements, newest
// first — in posting order within a product when filtered to one, otherwise by time — paged on
// the server with "Berikutnya" and "Sebelumnya" (keyset paging). The filters: the product,
// chosen through the shared Product Search in the context `stock`, whose Enter looks a scanned
// code up and never runs a command (BR-CAT-32); the movement type; and the days from and to,
// never in the wrong order (`ux.validation.date_order`). A row leads to its movement (UXI-220);
// its source, a receiving document or a manual issue, to that page (UXI-224). The filters and the page stand
// in the address (NC-10). "Kembali ke …" only when another page opened it (UXI-223), naming
// that page. The list may also be filtered to the products of an adjustment, RC-09's location
// (TASK-024), shown by their names in the filter. "Sesuaikan stok" (UXI-221; TASK-024), the
// Owner's alone, with one product filtered, opens UXP-410 with it; it is disabled with its reason
// while the onboarding freeze is on or an unresolved receiving, manual issue or adjustment of the
// user stands (UXS-13, UXS-27; NC-13). No cost (CAP-14).

const STOCK_CONTEXT = 'stock';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

interface HistoryProps {
    readonly filters: HistoryFilters;
    readonly products: readonly { readonly id: number; readonly name: string; readonly sku: string }[];
    readonly history: KeysetPage<HistoryRow>;
    readonly opener: HistoryOpener | null;
    readonly postingFrozen: boolean;
}

/** The signed-in user that every page shares (TASK-007). */
interface SharedProps {
    readonly auth: { readonly user: { readonly capabilities: readonly string[] } };
    readonly [key: string]: unknown;
}

export default function History(props: HistoryProps) {
    const title = t('inventory.history.title');

    return (
        <AppShell title={title} {...HISTORY_SHELL}>
            <HistoryList title={title} {...props} />
        </AppShell>
    );
}

/** The way back of UXI-223: the page that opened this one, with its filters, and its title. */
function backOf(opener: HistoryOpener | null, products: readonly number[]): { readonly href: string; readonly page: string } | undefined {
    const product = products.length === 1 ? (products[0] ?? null) : null;

    switch (opener) {
        case 'stock':
            return { href: backFrom(document.referrer, window.location.origin, (path) => path === STOCK_PAGE, STOCK_PAGE), page: t('inventory.stock.title') };
        case 'product':
            return product === null ? undefined : { href: productPage(product), page: t('catalog.product_detail.title') };
        case 'requests':
            return { href: REQUESTS_PAGE, page: t('commands.unresolved.title') };
        default:
            return undefined;
    }
}

function HistoryList({ title, filters, products, history, opener, postingFrozen }: HistoryProps & { title: string }) {
    const [day, setDay] = useState(filters.from ?? '');
    const [visiting, setVisiting] = useState(false);
    const [back] = useState(() => backOf(opener, filters.products));
    const owner = usePage<SharedProps>().props.auth.user.capabilities.includes(ADJUST_CAPABILITY);
    const unresolved = useUnresolved();
    const one = products.length === 1 ? (products[0] ?? null) : null;
    // A range comes only from an address; the field chooses one day, from and to alike.
    const outOfOrder = datesOutOfOrder(filters.from, filters.to);

    /** A visit of this page with other filters or another page of them, keeping its opener. */
    const visit = (next: HistoryFilters, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(historyAddress(next, cursor, opener), {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setVisiting(false),
        });
    };

    const apply = (patch: Partial<HistoryFilters>): void => visit({ ...filters, ...patch });

    /** "Tanggal" (UXI-219): the movements of one day of Asia/Jakarta, or every day when emptied. */
    const chooseDay = (value: string): void => {
        setDay(value);
        apply({ from: value === '' ? null : value, to: value === '' ? null : value });
    };

    const columns: readonly Column<HistoryRow>[] = [
        {
            id: 'time',
            header: t('inventory.history.col_time'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={movementPage(row.id)}>
                    {formatDateTime(row.recordedAt)}
                </a>
            ),
        },
        { id: 'product', header: t('inventory.history.col_product'), cell: (row) => row.product.name },
        { id: 'type', header: t('inventory.history.col_type'), cell: (row) => typeWord(row.type) },
        { id: 'quantity', header: t('inventory.history.col_quantity'), numeric: true, cell: (row) => signedQuantity(row.quantity, row.quantityPrecision, row.baseUnit) },
        { id: 'actor', header: t('inventory.history.col_actor'), cell: (row) => row.actorDisplayName },
        {
            id: 'source',
            header: t('inventory.history.col_source'),
            cell: (row) => {
                if (row.source === null) {
                    return null;
                }

                const link = sourceLink(row.source);

                return (
                    <a className={'bk-table-link'} href={link.href}>
                        {link.label}
                    </a>
                );
            },
        },
    ];

    const filtered = filters.products.length > 0 || filters.type !== null || filters.from !== null || filters.to !== null;
    const clear = (): void => {
        setDay('');
        visit({ products: [], type: null, from: null, to: null });
    };

    // UXI-221: the Owner's alone, with one product filtered (INTERACTION_INVENTORY UXP-402).
    const adjustReason = postingBlockedReason(postingFrozen, blockingRequest(unresolved.union, ADJUSTMENT_COMMAND.operation, ADJUSTMENT_COMMAND.family) !== null);
    const adjustLabel = t('inventory.adjustment.title');
    const adjust =
        owner && one !== null ? (
            adjustReason === undefined ? (
                <ButtonLink href={adjustmentPage(one.id, 'history')} label={adjustLabel} variant={'secondary'} />
            ) : (
                <Button label={adjustLabel} variant={'secondary'} enterActivates={false} disabledReason={adjustReason} />
            )
        ) : undefined;
    const chosen = products.length === 0 ? null : { name: products.map((product) => product.name).join(', ') };

    return (
        <ListLayout
            header={<PageHeader title={title} {...(back === undefined ? {} : { back })} {...(adjust === undefined ? {} : { actions: adjust })} />}
            toolbar={
                <>
                    <ProductFilter key={filters.products.join(',')} chosen={chosen} onChoose={(picked) => apply({ products: picked === null ? [] : [picked.id] })} />
                    <Field label={t('inventory.history.filter_type')}>
                        <SelectInput value={filters.type ?? ''} onChange={(event) => apply({ type: MOVEMENT_TYPES.find((entry) => entry.type === event.target.value)?.type ?? null })}>
                            <option value={''} />
                            {MOVEMENT_TYPES.map((entry) => (
                                <option key={entry.type} value={entry.type}>
                                    {t(entry.label)}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label={t('inventory.history.filter_date')} error={outOfOrder ? t('ux.validation.date_order') : undefined}>
                        <TextInput type={'date'} value={day} onChange={(event) => chooseDay(event.target.value)} />
                    </Field>
                </>
            }
            more={
                history.previousCursor !== null || history.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {history.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { before: history.previousCursor ?? undefined })} />}
                        {history.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters, { after: history.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {history.rows.length > 0 && <DataTable caption={title} columns={columns} rows={history.rows} rowKey={(row) => String(row.id)} wide={true} />}
                {history.rows.length === 0 && filtered && <NoResults onClear={clear} />}
                {history.rows.length === 0 && !filtered && <EmptyState body={t('inventory.history.empty')} />}
            </div>
        </ListLayout>
    );
}

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly rows: readonly ProductRow[]; readonly unknownCode: string | null }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string };

/**
 * The filter "Produk" (UXI-219): the shared Product Search in the context `stock`. Typing
 * searches once the keys pause; the Enter that ends a code looks it up exactly and chooses its
 * product; a newer read supersedes an older one (TR-PAGE-04). A choice applies the filter; an
 * emptied field removes it.
 */
function ProductFilter({ chosen, onChoose }: { chosen: { readonly name: string } | null; onChoose: (product: { readonly id: number } | null) => void }) {
    const [text, setText] = useState(chosen?.name ?? '');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const latest = useRef(0);
    const timer = useRef<number | null>(null);
    const field = useRef<HTMLInputElement | null>(null);

    const supersede = (): number => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        latest.current += 1;

        return latest.current;
    };

    useEffect(() => () => void supersede(), []);

    const search = (typed: string, exact: boolean): void => {
        const mine = supersede();

        setResults({ kind: 'searching' });
        void searchProducts({ context: STOCK_CONTEXT, q: typed, ...(exact ? { mode: 'exact' } : {}) }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'read') {
                const first = answer.body.rows[0];

                if (exact && first !== undefined) {
                    setResults({ kind: 'none' });
                    onChoose(first);

                    return;
                }

                setResults({ kind: 'rows', rows: answer.body.rows, unknownCode: exact ? typed : null });
            } else if (answer.kind === 'invalid') {
                setResults({ kind: 'invalid', fields: answer.fields });
            } else {
                setResults({ kind: 'failed', text: typed });
            }
        });
    };

    const edited = (next: string): void => {
        supersede();
        setText(next);

        const typed = next.trim();

        if (typed === '') {
            setResults({ kind: 'none' });

            if (chosen !== null) {
                onChoose(null);
            }

            return;
        }

        timer.current = window.setTimeout(() => {
            timer.current = null;
            search(typed, false);
        }, TYPING_MS);
    };

    const invalidText = results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined;

    return (
        <div className={'bk-stack'}>
            <CodeField label={t('inventory.history.filter_product')} hint={t('catalog.products.search_hint')} error={invalidText} value={text} onValueChange={edited} onEnter={() => (text.trim() === '' ? undefined : search(text.trim(), true))} inputRef={field} />
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && <LoadFailed onRetry={() => search(results.text, false)} />}
            {results.kind === 'rows' && results.unknownCode !== null && results.rows.length === 0 && <UnknownCode code={results.unknownCode} />}
            {results.kind === 'rows' && results.rows.length > 0 && (
                <ul className={'bk-stack'}>
                    {results.rows.map((row) => (
                        <li key={row.id}>
                            <Button label={row.name} rowName={row.sku} variant={'secondary'} block={true} enterActivates={true} onClick={() => onChoose(row)}>
                                <span className={'bk-meta'}>{row.sku}</span>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
