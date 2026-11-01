import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { LoadMore } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { EmptyState, LoadFailed, Loading, NoResults, StateMark, StateNotice, UnknownCode } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import type { StateId } from '@/components/ui/stateCatalogue';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { categoryOption, CodeField, ProductThumb } from '@/lib/catalog/Parts';
import {
    catalogFieldMessage,
    NEW_PRODUCT_PAGE,
    productPage,
    PRODUCTS_SHELL,
    productsAddress,
    searchPages,
    searchParameters,
    searchProducts,
    type Category,
    type ProductFilters,
    type ProductRow,
} from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// UXP-301 "Produk" (ROUTE_CONTRACTS RP-301, RD-01; COPY_DECK section 8.2;
// INTERACTION_INVENTORY UXI-159 to UXI-162 and UXI-164; NAVIGATION_CONTRACTS UXP-301;
// UI_STATES section 6; TASK-017 scope item 1): the shared Product Search with its
// filters and paging. Typing searches; the Enter that ends a scanned or typed code looks
// it up exactly, without a filter (TASK-016 finding F-4), and otherwise searches for the
// text, which an unknown code stays in (UXS-33, BR-CAT-31). A newer read supersedes an
// older one (TR-PAGE-04). The filters and page stand in the page's props and address, so
// that the browser's Back and the way back of a child page return to them (NC-10). No
// cost is ever shown (BR-CAT-26), and an inactive product is marked (UXS-32). TASK-018
// adds "Cetak label barcode" (UXI-163), and TASK-022 the stock state of a row.

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const INVALID_STATE: StateId = 'UXS-05';

const STATUS_FILTERS: readonly { readonly value: string; readonly label: TranslationKey }[] = [
    { value: 'active', label: 'catalog.status.active' },
    { value: 'inactive', label: 'catalog.status.inactive' },
];

/** What the list shows: its rows stay through a later read that fails or is refused (INTERACTION_CONTRACTS section 3.2). */
type ListState =
    | { readonly kind: 'loading' }
    | { readonly kind: 'failed' }
    | { readonly kind: 'rows'; readonly rows: readonly ProductRow[]; readonly hasMore: boolean; readonly unknownCode: string | null };

export default function Products({ filters, categories }: { filters: ProductFilters; categories: readonly Category[] }) {
    const title = t('catalog.products.title');

    return (
        <AppShell title={title} {...PRODUCTS_SHELL}>
            <ProductsList title={title} filters={filters} categories={categories} />
        </AppShell>
    );
}

function ProductsList({ title, filters, categories }: { title: string; filters: ProductFilters; categories: readonly Category[] }) {
    const [applied, setApplied] = useState(filters);
    const [q, setQ] = useState(filters.q);
    const [brand, setBrand] = useState(filters.brand);
    const [list, setList] = useState<ListState>({ kind: 'loading' });
    const [problem, setProblem] = useState<'invalid' | 'failed' | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Readonly<Record<string, unknown>>>({});
    const [loadingMore, setLoadingMore] = useState(false);
    // While a read of new filters is out, the rows shown are the earlier ones: no next page of them.
    const [reading, setReading] = useState(true);
    const latest = useRef(0);
    const searchField = useRef<HTMLInputElement | null>(null);

    useScanCapture(searchField);

    /** The page's state in its address and in the encrypted history state, with no request to the server. */
    const keep = useCallback((next: ProductFilters): void => {
        setApplied(next);
        router.replace({ url: productsAddress(next), props: (props) => ({ ...props, filters: next }), preserveState: true, preserveScroll: true });
    }, []);

    /** Reads the list for these filters; `scanned` says that an empty answer is an unknown code. */
    const load = useCallback(async (next: ProductFilters, scanned: string | null): Promise<void> => {
        latest.current += 1;
        const mine = latest.current;

        setLoadingMore(false);
        setReading(true);
        setList((current) => (current.kind === 'rows' ? current : { kind: 'loading' }));

        const answer = await searchPages(next);

        if (mine !== latest.current) {
            return;
        }

        setReading(false);

        switch (answer.kind) {
            case 'read':
                setProblem(null);
                setFieldErrors({});
                setList({ kind: 'rows', rows: answer.body.rows, hasMore: answer.body.hasMore, unknownCode: answer.body.rows.length === 0 ? scanned : null });

                return;
            case 'invalid':
                setProblem('invalid');
                setFieldErrors(answer.fields);
                setList((current) => (current.kind === 'loading' ? { kind: 'rows', rows: [], hasMore: false, unknownCode: null } : current));

                return;
            case 'failed':
                setProblem('failed');
                setList((current) => (current.kind === 'rows' ? current : { kind: 'failed' }));
        }
    }, []);

    // The list reads once when the page opens, and again when the filters change below.
    useEffect(() => {
        void load(filters, null);
    }, []);

    // A typed search runs once the keys pause; a newer one supersedes it.
    useEffect(() => {
        const text = q.trim();
        const typedBrand = brand.trim();

        if (text === applied.q && typedBrand === applied.brand) {
            return undefined;
        }

        const timer = window.setTimeout(() => {
            const next = { ...applied, q: text, brand: typedBrand, page: 1 };

            keep(next);
            void load(next, null);
        }, TYPING_MS);

        return () => window.clearTimeout(timer);
    }, [q, brand, applied, keep, load]);

    const change = (patch: Partial<ProductFilters>): void => {
        const next = { ...applied, ...patch, page: 1 };

        keep(next);
        void load(next, null);
    };

    /** The Enter that ends a code: the one product of that exact code, else a search for the text (UXA-10, BR-CAT-31). */
    const lookUp = (): void => {
        const code = q.trim();

        if (code === '') {
            return;
        }

        latest.current += 1;
        const mine = latest.current;
        const next = { ...applied, q: code, page: 1 };

        keep(next);
        setReading(true);
        void searchProducts({ q: code, mode: 'exact' }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'read' && answer.body.rows.length > 0) {
                setReading(false);
                setProblem(null);
                setList({ kind: 'rows', rows: answer.body.rows, hasMore: false, unknownCode: null });
            } else {
                void load(next, code);
            }
        });
    };

    /** "Muat lebih banyak": the next page, added to the rows; a failure keeps them (UXS-22). */
    const loadMore = (): void => {
        if (list.kind !== 'rows') {
            return;
        }

        latest.current += 1;
        const mine = latest.current;
        const page = applied.page + 1;
        const rows = list.rows;

        setLoadingMore(true);
        void searchProducts({ ...searchParameters(applied), page: String(page) }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            setLoadingMore(false);

            if (answer.kind === 'read') {
                setProblem(null);
                keep({ ...applied, page });
                setList({ kind: 'rows', rows: [...rows, ...answer.body.rows], hasMore: answer.body.hasMore, unknownCode: null });
            } else {
                setProblem('failed');
            }
        });
    };

    const clearAll = (): void => {
        setQ('');
        setBrand('');
        change({ q: '', category: null, brand: '', status: null });
    };

    const columns: readonly Column<ProductRow>[] = [
        {
            id: 'name',
            header: t('catalog.field.name'),
            isHead: true,
            cell: (row) => (
                <span className={'bk-product-cell'}>
                    <ProductThumb name={row.name} src={row.thumbnail} />
                    <a className={'bk-table-link'} href={productPage(row.id)}>
                        {row.name}
                    </a>
                </span>
            ),
        },
        { id: 'sku', header: t('catalog.field.sku'), cell: (row) => row.sku },
        { id: 'price', header: t('catalog.field.selling_price'), numeric: true, cell: (row) => formatMoney(row.price.amount) },
        { id: 'status', header: t('catalog.field.status'), cell: (row) => (row.active ? <StatusWord tone={'success'} label={t('catalog.status.active')} /> : <StateMark kind={'inactive'} />) },
    ];

    const filtered = applied.q !== '' || applied.category !== null || applied.brand !== '' || applied.status !== null;

    return (
        <ListLayout
            header={<PageHeader title={title} actions={<ButtonLink href={NEW_PRODUCT_PAGE} label={t('catalog.product_create.title')} variant={'primary'} />} />}
            toolbar={
                <>
                    <CodeField label={t('catalog.products.search')} hint={t('catalog.products.search_hint')} error={catalogFieldMessage(fieldErrors.q)} value={q} onValueChange={setQ} onEnter={lookUp} inputRef={searchField} />
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
                    <Field label={t('catalog.field.brand')} error={catalogFieldMessage(fieldErrors.brand)}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={100} value={brand} onChange={(event) => setBrand(event.target.value)} />
                    </Field>
                    <FilterChips label={t('catalog.field.status')}>
                        {STATUS_FILTERS.map((filter) => (
                            <FilterChip key={filter.value} label={t(filter.label)} pressed={applied.status === filter.value} onPressedChange={(pressed) => change({ status: pressed ? filter.value : null })} />
                        ))}
                    </FilterChips>
                </>
            }
            more={list.kind === 'rows' && list.hasMore ? <LoadMore onLoad={loadMore} busy={loadingMore || reading} /> : undefined}
        >
            <div className={'bk-stack'}>
                {problem === 'invalid' && <StateNotice state={INVALID_STATE} />}
                {problem === 'failed' && list.kind === 'rows' && <LoadFailed onRetry={() => void load(applied, null)} />}
                {list.kind === 'loading' && <Loading />}
                {list.kind === 'failed' && <LoadFailed onRetry={() => void load(applied, null)} />}
                {list.kind === 'rows' && list.rows.length > 0 && <DataTable caption={title} columns={columns} rows={list.rows} rowKey={(row) => String(row.id)} />}
                {list.kind === 'rows' && list.rows.length === 0 && list.unknownCode !== null && <UnknownCode code={list.unknownCode} />}
                {list.kind === 'rows' && list.rows.length === 0 && list.unknownCode === null && problem === null && filtered && <NoResults onClear={clearAll} />}
                {list.kind === 'rows' && list.rows.length === 0 && problem === null && !filtered && (
                    <EmptyState
                        body={
                            <>
                                {t('catalog.products.empty')} {t('catalog.products.empty_action')}
                            </>
                        }
                    />
                )}
            </div>
        </ListLayout>
    );
}
