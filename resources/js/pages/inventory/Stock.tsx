import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { LoadMore, RefreshLine } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput } from '@/components/ui/Field';
import { Menu, type MenuEntry } from '@/components/ui/Menu';
import type { StateId } from '@/components/ui/stateCatalogue';
import { EmptyState, LoadFailed, NoResults, StaleRead, StateNotice, UnknownCode } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { categoryOption, CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, type Category } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { SETTLED_EVENT } from '@/lib/commands/client';
import { blockingRequest } from '@/lib/commands/families';
import { setStockAttention, SHELL_REFRESH_MS, useUnresolved } from '@/lib/commands/unresolved';
import { formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    ADJUST_CAPABILITY,
    ADJUSTMENT_COMMAND,
    adjustmentPage,
    MAX_PER_PAGE,
    productHistory,
    readStock,
    readStockRows,
    REQUESTS_PAGE,
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
// filters and how many rows were shown stand in the address and props (NC-10). The Owner's row
// overflow "Sesuaikan stok" (UXI-217; TASK-024) opens UXP-410 with the row's product; it is
// unavailable with its reason while the onboarding freeze is on or an unresolved receiving, manual
// issue or adjustment of the user stands, with the way to UXP-006 beside it (UXS-13, UXS-27;
// NC-13). No cost is ever shown (CAP-14).

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

/** The signed-in user that every page shares (TASK-007). */
interface SharedProps {
    readonly auth: { readonly user: { readonly capabilities: readonly string[] } };
    readonly [key: string]: unknown;
}

/** The row's overflow (UXI-217): UXP-410 with the product, or why it cannot open now. */
function adjustEntries(product: number, postingFrozen: boolean, blocked: boolean): MenuEntry[] {
    const label = t('inventory.adjustment.title');

    if (postingFrozen) {
        return [{ kind: 'unavailable', label, reason: t('ux.disabled.onboarding') }];
    }

    if (blocked) {
        return [
            { kind: 'unavailable', label, reason: t('inventory.disabled_unresolved_posting') },
            { kind: 'link', label: t('ux.action.open_unresolved'), href: REQUESTS_PAGE },
        ];
    }

    return [{ kind: 'link', label, href: adjustmentPage(product, 'stock') }];
}

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

export default function Stock({ filters, categories, stock, postingFrozen }: { filters: StockFilters; categories: readonly Category[]; stock: StockPage; postingFrozen: boolean }) {
    const title = t('inventory.stock.title');

    return (
        <AppShell title={title} {...STOCK_SHELL}>
            <StockList title={title} filters={filters} categories={categories} stock={stock} postingFrozen={postingFrozen} />
        </AppShell>
    );
}

function StockList({ title, filters, categories, stock, postingFrozen }: { title: string; filters: StockFilters; categories: readonly Category[]; stock: StockPage; postingFrozen: boolean }) {
    const owner = usePage<SharedProps>().props.auth.user.capabilities.includes(ADJUST_CAPABILITY);
    const unresolved = useUnresolved();
    const adjustBlocked = blockingRequest(unresolved.union, ADJUSTMENT_COMMAND.operation, ADJUSTMENT_COMMAND.family) !== null;
    const [applied, setApplied] = useState(filters);
    const [q, setQ] = useState(filters.q);
    const [shown, setShown] = useState<Shown>({ page: stock, filters, unknownCode: null });
    const [problem, setProblem] = useState<'invalid' | 'failed' | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Readonly<Record<string, unknown>>>({});
    const [loadingMore, setLoadingMore] = useState(false);
    const [reading, setReading] = useState(false);
    const latest = useRef(0);
    // The read that the user asked for and that is still out, by its number: a refresh in the
    // background waits for it and never supersedes it (pull request #31, finding P2).
    const userRead = useRef<number | null>(null);
    const searchField = useRef<HTMLInputElement | null>(null);
    const current = useRef(shown);
    // The filters last asked for, which a refresh reads, also when the list still shows others.
    const requested = useRef(applied);

    current.current = shown;
    requested.current = applied;
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

            if (!background) {
                userRead.current = mine;
            }

            setLoadingMore(false);
            setReading(!background);

            const answer = await readStockRows(next, next.perPage, background);

            if (userRead.current === mine) {
                userRead.current = null;
            }

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

    /** The filters last asked for, with as many rows as are shown when the list shows them. */
    const wanted = useCallback((): StockFilters => {
        const of = requested.current;
        const { filters: shownFilters, page } = current.current;
        const showsThem = shownFilters.q === of.q && shownFilters.state === of.state && shownFilters.category === of.category;

        return { ...of, perPage: showsThem ? Math.max(page.rows.length, STOCK_PER_PAGE) : STOCK_PER_PAGE };
    }, []);

    /**
     * The automatic refresh, as background reads (RT-10), of the filters last asked for; none
     * while a read the user asked for is out.
     */
    const refresh = useCallback((): void => {
        if (userRead.current === null) {
            void load(wanted(), null, true);
        }
    }, [load, wanted]);

    /** "Muat ulang" and "Coba lagi": the filters last asked for, as the refresh reads them (review finding R-15). */
    const reload = (): void => void load(wanted(), null, false);

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

            requested.current = next;
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

        userRead.current = mine;
        setLoadingMore(true);
        void readStock(of, page.nextCursor, STOCK_PER_PAGE, false).then((answer) => {
            if (userRead.current === mine) {
                userRead.current = null;
            }

            if (mine !== latest.current) {
                return;
            }

            setLoadingMore(false);

            if (answer.kind === 'read') {
                const rows = [...page.rows, ...answer.body.rows];
                const next = { ...of, perPage: Math.min(rows.length, MAX_PER_PAGE) };

                requested.current = next;
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
        // UXI-217: the row's overflow "Sesuaikan stok", the Owner's alone (TASK-024).
        ...(owner
            ? [
                  {
                      id: 'actions',
                      header: t('ux.action.more_actions'),
                      isAction: true,
                      cell: (row: StockRow) => <Menu label={t('ux.action.more_actions')} rowName={row.name} items={adjustEntries(row.id, postingFrozen, adjustBlocked)} />,
                  },
              ]
            : []),
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
            refresh={<RefreshLine at={formatDateTime(shown.page.asOf)} onReload={reload} />}
            more={shown.page.nextCursor !== null && sameFilters ? <LoadMore onLoad={loadMore} busy={loadingMore || reading} /> : undefined}
        >
            <div className={'bk-stack'}>
                {problem === 'invalid' && <StateNotice state={INVALID_STATE} />}
                {problem === 'failed' && rows.length > 0 && <StaleRead at={formatDateTime(shown.page.asOf)} onReload={reload} />}
                {rows.length > 0 && <DataTable caption={title} columns={columns} rows={rows} rowKey={(row) => String(row.id)} />}
                {rows.length === 0 && shown.unknownCode !== null && <UnknownCode code={shown.unknownCode} />}
                {rows.length === 0 && shown.unknownCode === null && problem === null && filtered && <NoResults onClear={clearAll} />}
                {rows.length === 0 && shown.unknownCode === null && problem === null && !filtered && <EmptyState body={t('inventory.stock.empty')} />}
                {rows.length === 0 && problem === 'failed' && <LoadFailed onRetry={reload} />}
            </div>
        </ListLayout>
    );
}
