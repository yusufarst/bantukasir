import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { LoadMore, RefreshLine } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { EmptyState, LoadFailed, NoResults, StaleRead, StateNotice, UnknownCode } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { categoryOption, CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, type Category } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { SETTLED_EVENT } from '@/lib/commands/client';
import { setStockAttention, SHELL_REFRESH_MS } from '@/lib/commands/unresolved';
import { formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    MAX_PER_PAGE,
    productHistory,
    readStock,
    readStockRows,
    STOCK_PER_PAGE,
    STOCK_SHELL,
    STOCK_STATES,
    stateWord,
    stockAddress,
    type StockFilters,
    type StockPage,
    type StockRow,
    type StockState,
} from '@/lib/inventory/stock';

// UXP-401 "Stok" (ROUTE_CONTRACTS RP-401, RD-02; KS-08; COPY_DECK section 9.2; INTERACTION_INVENTORY
// UXI-213 to UXI-216 and UXI-218; NAVIGATION_CONTRACTS UXP-401; UI_STATES section 6; BR-INV-40 to
// BR-INV-43; TASK-022 scope item 2). The stock attention of the active products: product, SKU,
// stock, minimum and state, with the search, the state chips and the category, and "Muat lebih
// banyak". The first page comes with the page; RD-02 then refreshes it at least every 30 seconds
// while the page is visible, when the tab regains focus and after any committed command, as
// background reads (RT-10), and the badge of "Stok" takes the same read's numbers, so that both
// agree (BR-INV-41). A failed refresh keeps the last rows with their time (UXS-21), never an empty
// list, a zero or "Normal" of its own. The Enter that ends a scanned code searches for it, and an
// unknown code stays in the field (UXS-33). A row leads to the product's history (UXI-216). The
// filters and how many rows were shown stand in the address and props (NC-10). "Sesuaikan stok"
// (UXI-217) is TASK-024's. No cost is ever shown (CAP-14).

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const INVALID_STATE: StateId = 'UXS-05';

/**
 * What the list shows: its rows stay through a later read that fails, and `filters` says which
 * filters they answer, so that no next page of other filters is added to them.
 */
interface Shown {
    readonly page: StockPage;
    readonly filters: StockFilters;
    readonly unknownCode: string | null;
}

export default function Stock({ filters, categories, stock }: { filters: StockFilters; categories: readonly Category[]; stock: StockPage }) {
    const title = t('inventory.stock.title');

    return (
        <AppShell title={title} {...STOCK_SHELL}>
            <StockList title={title} filters={filters} categories={categories} stock={stock} />
        </AppShell>
    );
}

function StockList({ title, filters, categories, stock }: { title: string; filters: StockFilters; categories: readonly Category[]; stock: StockPage }) {
    const [applied, setApplied] = useState(filters);
    const [q, setQ] = useState(filters.q);
    const [shown, setShown] = useState<Shown>({ page: stock, filters, unknownCode: null });
    const [problem, setProblem] = useState<'invalid' | 'failed' | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Readonly<Record<string, unknown>>>({});
    const [loadingMore, setLoadingMore] = useState(false);
    const [reading, setReading] = useState(false);
    const latest = useRef(0);
    const searchField = useRef<HTMLInputElement | null>(null);
    const current = useRef(shown);

    current.current = shown;
    useScanCapture(searchField);

    /** The page's state in its address and in the encrypted history state, with no request to the server. */
    const keep = useCallback((next: StockFilters, page: StockPage): void => {
        router.replace({ url: stockAddress(next), props: (props) => ({ ...props, filters: next, stock: page }), preserveState: true, preserveScroll: true });
    }, []);

    /** Shows a read: its rows, and the badge's number from the same read. */
    const show = useCallback(
        (next: StockFilters, page: StockPage, unknownCode: string | null): void => {
            setProblem(null);
            setFieldErrors({});
            setShown({ page, filters: next, unknownCode });
            setStockAttention(page.outCount + page.lowCount);
            keep(next, page);
        },
        [keep],
    );

    /** Reads the list for these filters, as many rows as were shown; `scanned` says that an empty answer is an unknown code. */
    const load = useCallback(
        async (next: StockFilters, scanned: string | null, background: boolean): Promise<void> => {
            latest.current += 1;
            const mine = latest.current;

            setLoadingMore(false);
            setReading(!background);

            const answer = await readStockRows(next, next.perPage, background);

            if (mine !== latest.current) {
                return;
            }

            setReading(false);

            switch (answer.kind) {
                case 'read':
                    show(next, answer.body, answer.body.rows.length === 0 ? scanned : null);

                    return;
                case 'invalid':
                    setProblem('invalid');
                    setFieldErrors(answer.fields);

                    return;
                case 'failed':
                    setProblem('failed');
            }
        },
        [show],
    );

    /** The automatic refresh of the filters shown, as background reads (RT-10). */
    const refresh = useCallback((): void => {
        const { filters: of, page } = current.current;

        void load({ ...of, perPage: Math.max(page.rows.length, STOCK_PER_PAGE) }, null, true);
    }, [load]);

    // The page opens with its first page; it is read again in the background at least every 30
    // seconds while visible, on focus and after a committed command (INTERACTION_CONTRACTS 3.3).
    useEffect(() => {
        setStockAttention(stock.outCount + stock.lowCount);
        refresh();

        const refreshVisible = (): void => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        };
        const timer = window.setInterval(refreshVisible, SHELL_REFRESH_MS);

        window.addEventListener('focus', refreshVisible);
        document.addEventListener('visibilitychange', refreshVisible);
        window.addEventListener(SETTLED_EVENT, refresh);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('focus', refreshVisible);
            document.removeEventListener('visibilitychange', refreshVisible);
            window.removeEventListener(SETTLED_EVENT, refresh);
        };
    }, []);

    const change = useCallback(
        (patch: Partial<StockFilters>, scanned: string | null = null): void => {
            const next = { ...applied, ...patch, perPage: STOCK_PER_PAGE };

            setApplied(next);
            void load(next, scanned, false);
        },
        [applied, load],
    );

    // A typed search runs once the keys pause; a newer one supersedes it.
    useEffect(() => {
        const text = q.trim();

        if (text === applied.q) {
            return undefined;
        }

        const timer = window.setTimeout(() => change({ q: text }), TYPING_MS);

        return () => window.clearTimeout(timer);
    }, [q, applied, change]);

    /** The Enter that ends a code: a search for it now, whose empty answer is an unknown code (UXS-33). */
    const lookUp = (): void => {
        const code = q.trim();

        if (code !== '') {
            change({ q: code }, code);
        }
    };

    /** "Muat lebih banyak": the next rows after the last, added to them; a failure keeps them (UXS-22). */
    const loadMore = (): void => {
        const { page, filters: of } = shown;

        if (page.nextCursor === null) {
            return;
        }

        latest.current += 1;
        const mine = latest.current;

        setLoadingMore(true);
        void readStock(of, page.nextCursor, STOCK_PER_PAGE, false).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            setLoadingMore(false);

            if (answer.kind === 'read') {
                const rows = [...page.rows, ...answer.body.rows];
                const next = { ...of, perPage: Math.min(rows.length, MAX_PER_PAGE) };

                setApplied(next);
                show(next, { ...answer.body, rows }, null);
            } else {
                setProblem('failed');
            }
        });
    };

    const clearAll = (): void => {
        setQ('');
        change({ q: '', state: null, category: null });
    };

    const columns: readonly Column<StockRow>[] = [
        {
            id: 'product',
            header: t('inventory.stock.col_product'),
            isHead: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={productHistory(row.id, 'stock')}>
                    {row.name}
                </a>
            ),
        },
        { id: 'sku', header: t('catalog.field.sku'), cell: (row) => <span className={'bk-mono'}>{row.sku}</span> },
        { id: 'stock', header: t('inventory.stock.col_stock'), numeric: true, cell: (row) => formatQuantity(row.onHand, row.quantityPrecision, row.baseUnit) },
        { id: 'minimum', header: t('catalog.field.min_stock'), numeric: true, cell: (row) => formatQuantity(row.minimum, row.quantityPrecision, row.baseUnit) },
        {
            id: 'state',
            header: t('inventory.stock.col_state'),
            cell: (row) => {
                const word = stateWord(row.state);

                return <StatusWord tone={word.tone} label={t(word.label)} />;
            },
        },
    ];

    const filtered = applied.q !== '' || applied.state !== null || applied.category !== null;
    const rows = shown.page.rows;
    const sameFilters = shown.filters.q === applied.q && shown.filters.state === applied.state && shown.filters.category === applied.category;
    const chooseState = (state: StockState, pressed: boolean): void => change({ state: pressed ? state : null });

    return (
        <ListLayout
            header={<PageHeader title={title} />}
            toolbar={
                <>
                    <CodeField label={t('inventory.stock.search')} hint={t('catalog.products.search_hint')} error={catalogFieldMessage(fieldErrors.q)} value={q} onValueChange={setQ} onEnter={lookUp} inputRef={searchField} />
                    <Field label={t('catalog.field.category')}>
                        <SelectInput value={applied.category === null ? '' : String(applied.category)} onChange={(event) => change({ category: categories.find((category) => String(category.id) === event.target.value)?.id ?? null })}>
                            <option value={''} />
                            {categories.map((category) => (
                                <option key={category.id} value={String(category.id)}>
                                    {categoryOption(category)}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <FilterChips label={t('inventory.stock.filter_state')}>
                        {STOCK_STATES.map((state) => (
                            <FilterChip key={state} label={t(stateWord(state).label)} pressed={applied.state === state} onPressedChange={(pressed) => chooseState(state, pressed)} />
                        ))}
                    </FilterChips>
                </>
            }
            refresh={<RefreshLine at={formatDateTime(shown.page.asOf)} onReload={() => void load({ ...shown.filters, perPage: Math.max(rows.length, STOCK_PER_PAGE) }, null, false)} />}
            more={shown.page.nextCursor !== null && sameFilters ? <LoadMore onLoad={loadMore} busy={loadingMore || reading} /> : undefined}
        >
            <div className={'bk-stack'}>
                {problem === 'invalid' && <StateNotice state={INVALID_STATE} />}
                {problem === 'failed' && rows.length > 0 && <StaleRead at={formatDateTime(shown.page.asOf)} onReload={() => void load({ ...shown.filters, perPage: Math.max(rows.length, STOCK_PER_PAGE) }, null, false)} />}
                {rows.length > 0 && <DataTable caption={title} columns={columns} rows={rows} rowKey={(row) => String(row.id)} />}
                {rows.length === 0 && shown.unknownCode !== null && <UnknownCode code={shown.unknownCode} />}
                {rows.length === 0 && shown.unknownCode === null && problem === null && filtered && <NoResults onClear={clearAll} />}
                {rows.length === 0 && shown.unknownCode === null && problem === null && !filtered && <EmptyState body={t('inventory.stock.empty')} />}
                {rows.length === 0 && problem === 'failed' && <LoadFailed onRetry={() => void load({ ...shown.filters, perPage: STOCK_PER_PAGE }, null, false)} />}
            </div>
        </ListLayout>
    );
}
