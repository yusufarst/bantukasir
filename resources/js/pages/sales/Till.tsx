import { router, usePage } from '@inertiajs/react';
import { useEffect, useId, useMemo, useRef, useState, type ReactNode, type RefObject } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { SegmentedControl, type SegmentedOption } from '@/components/ui/Choices';
import { Dialog } from '@/components/ui/Dialog';
import { Field, MoneyInput, SelectInput, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { StatusWord } from '@/components/ui/Status';
import { Committed, LoadFailed, Loading, NoResults, StateMark, StateNotice, TenderLow, UnknownCode } from '@/components/ui/States';
import { CartLine, CartRegion, ProductResult, ProductResults, QuantityStepper, ScanField, TillWorkspace } from '@/components/ui/Till';
import { AppShell, type ShellLayout } from '@/layouts/AppShell';
import { PageContainer } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { throttledBody } from '@/lib/authAnswer';
import { ProductThumb } from '@/lib/catalog/Parts';
import { catalogFieldMessage, type ProductRow } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand, type CommandControl } from '@/lib/commands/useCommand';
import { formatCount, formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId, RoleCode } from '@/lib/navigation';
import { useOnline } from '@/lib/online';
import {
    acceptNewPrices,
    addProduct,
    applyRefusal,
    BANK_TRANSFER,
    canDecrease,
    CASH,
    cartProductOf,
    cartTotal,
    checkoutRequest,
    clearCart,
    draftOf,
    EMPTY_DRAFT,
    lineTotalOf,
    linesValid,
    priceReviewPending,
    readQuantity,
    removeLine,
    setQuantity,
    stepQuantity,
    tenderOf,
    type CartLine as Line,
    type CartProduct,
    type PaymentDraft,
    type PaymentMethod,
    type QuantityProblem,
    type TillDraft,
} from '@/lib/sales/cart';
import { createScanQueue } from '@/lib/sales/scanQueue';
import { OPEN_SESSION_PAGE, PAYMENT_PAGE, PAYMENT_VIEW, SALE_COMMAND, searchTill, takeScans, TILL_PAGE, TILL_VIEW, UNRESOLVED_PAGE, type TillCategory, type TillSearchAnswer, type TillSession, type TillView } from '@/lib/sales/till';

// UXP-101 "Kasir" and UXP-102 "Pembayaran", one page with two views (ROUTE_CONTRACTS RP-101, RP-102, RD-01
// `till`, RC-01, RC-50; KEY_SCREENS KS-03 with its states `default`, `empty`, `price-changed`, `unresolved`
// and `opened`, KS-04 with `default`, `short`, `transfer`, `submitting`, `uncertain` and `held`; COPY_DECK
// sections 7.1, 7.2, 14.1 and 15; INTERACTION_INVENTORY UXI-066 to UXI-076 and UXI-078 to UXI-087;
// NAVIGATION_CONTRACTS UXP-101, UXP-102 and NC-13; UI_STATES section 6; ADAPTIVE_AND_ACCESSIBILITY UXA-07,
// UXA-09 to UXA-11, UXA-25; BR-SALE-05, BR-SALE-10 to BR-SALE-20, BR-CAT-28 to BR-CAT-34; D-040, D-067,
// D-075; WF-05, WF-07; TASK-029 scope items 1, 2 and 4).
//
// The till. A scan is ordinary keyboard input in "Cari atau scan produk" that ends with Enter: each
// complete scan looks its code up exactly and adds one base unit, the answers applied in the order of
// the scans, never one replaced by another (BR-CAT-29, BR-CAT-30); an empty terminator and a repeated
// Enter add nothing; an unknown code stays in the field (UXS-33). Typing searches once the keys pause; a
// newer search supersedes an older one. A product already in the cart adds to its own line (D-067); a
// fraction is only typed. Capture pauses while a dialog is open (BR-CAT-32). "Bayar" (F8, never Enter)
// opens the payment view while the cart holds valid lines, no new price waits for "Setuju harga baru"
// (UXS-18), the onboarding freeze is off (UXS-27) and the user holds no unresolved checkout (NC-13), which
// "Cek pembayaran yang belum pasti" opens instead.
//
// The payment. Tunai is chosen first (D-075); the change shows before the one final action (D-040), which
// is 64 px high, never the first focus and never activated by Enter; Ctrl+Enter or a press sends RC-01 with
// the prices reviewed. PRICE_CHANGED returns to the till with the changed lines marked; every other
// refusal stays here with its reason at its line (NC-09). An uncertain outcome freezes the request on its
// panel — never ask the customer to pay again — with "Kirim ulang" and "Cek status", a held one with "Cek
// status" only; COMMITTED, here or by a status check, discards the cart and opens the receipt (NC-13 (e)).
//
// The cart lives in the page's memory and in its encrypted history state (SEC-CH-04), never in the
// browser's storage; the two views are client visits of the same history entry, so that Back never
// returns to a cart a checkout may have paid: while a checkout of the cart is out or uncertain, and once it
// committed, the entry holds no cart.

const DESTINATION: DestinationId = 'till';

/** On the till's pages the side navigation is the icon rail (UXA-03; KS-03). */
const TILL_LAYOUT: ShellLayout = 'rail';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

/** The states of RC-01's refusals that the payment view shows (UI_STATES section 3). */
const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    ONBOARDING_FREEZE: 'UXS-27',
    POSTING_BLOCKED: 'UXS-28',
    NO_OPEN_SESSION: 'UXS-29',
    SESSION_NOT_OPEN: 'UXS-30-till',
    INSUFFICIENT_STOCK: 'UXS-31',
    PRODUCT_INACTIVE: 'UXS-32-product',
    METHOD_NOT_ALLOWED: 'UXS-46',
};

const FROZEN_STATE: StateId = 'UXS-27';

const PRICE_STATE: StateId = 'UXS-18';

const INACTIVE_STATE: StateId = 'UXS-32-product';

const PRICE_CHANGED = 'PRICE_CHANGED';

const TENDER_INSUFFICIENT = 'TENDER_INSUFFICIENT';

/** The stock of a line refused for stock whose answer named none. */
const NONE_AVAILABLE = '0';

interface TillProps {
    readonly view: string;
    readonly session: TillSession;
    readonly postingFrozen: boolean;
    readonly categories: readonly TillCategory[];
    /** The draft of this history entry, written by the page itself (client visits). */
    readonly draft?: unknown;
}

interface SharedProps {
    readonly auth: { readonly user: { readonly roles: readonly RoleCode[] } } | null;
    readonly [key: string]: unknown;
}

export default function Till(props: TillProps) {
    const view: TillView = props.view === PAYMENT_VIEW ? PAYMENT_VIEW : TILL_VIEW;
    const title = view === PAYMENT_VIEW ? t('sales.payment.title') : t('sales.till.title');

    return (
        <AppShell title={title} current={DESTINATION} layout={TILL_LAYOUT}>
            <TillPage view={view} session={props.session} postingFrozen={props.postingFrozen} categories={props.categories} stored={props.draft} />
        </AppShell>
    );
}

/** Moves between the two views within this history entry: no new entry, so Back leaves the till (NC-04, NC-13 (e)). */
function showView(next: TillView): void {
    router.replace({ url: next === PAYMENT_VIEW ? PAYMENT_PAGE : TILL_PAGE, props: (current) => ({ ...current, view: next }), preserveState: true });
}

function TillPage({ view, session, postingFrozen, categories, stored }: { view: TillView; session: TillSession; postingFrozen: boolean; categories: readonly TillCategory[]; stored: unknown }) {
    const control = useCommand(SALE_COMMAND);
    const { phase, blockedBy, startAgain } = control;
    const [draft, setDraftState] = useState<TillDraft>(() => draftOf(stored));
    const current = useRef<TillDraft>(draft);
    const [cartOut, setCartOut] = useState(false);
    const [leaving, setLeaving] = useState<string | null>(null);
    const [flash, setFlash] = useState<{ readonly productId: number; readonly at: number } | null>(null);
    const sentProducts = useRef<readonly number[]>([]);
    const searchField = useRef<HTMLInputElement | null>(null);
    const online = useOnline();

    /** Every change of the draft goes through here, so that a scan applied after another sees it at once. */
    const update = (change: (draft: TillDraft) => TillDraft): TillDraft => {
        current.current = change(current.current);
        setDraftState(current.current);

        return current.current;
    };

    // The history entry keeps the draft, except a cart that is out at checkout or may have been paid.
    useEffect(() => {
        if (leaving !== null) {
            return;
        }

        const kept = cartOut ? EMPTY_DRAFT : draft;

        router.replace({ props: (props) => ({ ...props, draft: kept }), preserveState: true, preserveScroll: true });
    }, [draft, cartOut, leaving]);

    // A checkout out of this page keeps its cart out of the history until a definite answer. A request refused
    // by 429 before any attempt reached the server (`throttled`) ran nowhere (review R-02).
    useEffect(() => {
        if (phase.kind === 'submitting') {
            setCartOut(true);
        } else if (phase.kind === 'rejected' || phase.kind === 'not_committed' || phase.kind === 'idle' || phase.kind === 'throttled') {
            setCartOut(false);
        }
    }, [phase.kind]);

    // While a checkout of the cart is out, unknown or committed, nothing changes the cart: what the payment
    // screen shows is what was sent (INTERACTION_CONTRACTS section 1.3; review R-01).
    const cartFrozen = useRef(false);

    cartFrozen.current =
        leaving !== null || phase.kind === 'submitting' || phase.kind === 'throttled' || (control.ownRequest && (phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'committed'));

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;

    // A definite refusal marks its lines (NC-09); PRICE_CHANGED returns to the till for the review (UXS-18).
    useEffect(() => {
        if (refusal === null) {
            return;
        }

        update((draft) => applyRefusal(draft, refusal.code, refusal.lines, sentProducts.current));

        if (refusal.code === PRICE_CHANGED) {
            startAgain();
            showView(TILL_VIEW);
        }
        // Once per refusal: the functions of this render act on the refusal that came with it.
    }, [refusal]);

    // COMMITTED, by the final action or by a status check: the cart is discarded and the receipt opens,
    // with no further step (BR-SALE-14; NC-13 (e)).
    useEffect(() => {
        if (phase.kind !== 'committed') {
            return;
        }

        const location = phase.result?.location;

        update(() => EMPTY_DRAFT);

        if (location !== undefined) {
            setLeaving(phase.result?.number ?? '');
            router.replace({ props: (props) => ({ ...props, draft: EMPTY_DRAFT }), preserveState: true, onSuccess: () => router.visit(location) });
        }
        // Once per answer.
    }, [phase]);

    const add = (product: CartProduct, announce: (text: string) => void): void => {
        if (cartFrozen.current) {
            return;
        }

        const next = update((draft) => addProduct(draft, product));
        const line = next.lines.find((candidate) => candidate.product.id === product.id);

        setFlash({ productId: product.id, at: Date.now() });

        if (line !== undefined) {
            announce(t('ux.announce.added', { produk: product.name, jumlah: shownQuantity(line) }));
        }
    };

    const pay = (): void => {
        const request = checkoutRequest(current.current, session.id);

        sentProducts.current = request.products;
        control.submit(request.payload, request.expected);
    };

    if (leaving !== null) {
        return (
            <PageContainer width={'focused'}>
                <Committed message={t('sales.receipt.saved', { nomor: leaving })} />
            </PageContainer>
        );
    }

    return view === PAYMENT_VIEW ? (
        <PaymentView
            draft={draft}
            control={control}
            online={online}
            postingFrozen={postingFrozen}
            onPayment={(change) => update((draft) => ({ ...draft, payment: change(draft.payment) }))}
            onPay={pay}
            onBack={() => {
                // A refusal is said here once; the cart that the user changes next is a new draft.
                startAgain();
                showView(TILL_VIEW);
            }}
        />
    ) : (
        <TillView
            session={session}
            categories={categories}
            postingFrozen={postingFrozen}
            draft={draft}
            blocked={blockedBy !== null || phase.kind === 'uncertain' || phase.kind === 'held'}
            flash={flash}
            searchField={searchField}
            onAdd={add}
            onChange={(change) => void update(change)}
        />
    );
}

/** A line's quantity with its unit, as the announcement and the receipt say it (COPY_DECK section 1.3). */
function shownQuantity(line: Line): string {
    const read = readQuantity(line.quantity, line.product.precision);

    return 'quantity' in read ? formatQuantity(read.quantity, line.product.precision, line.product.unit) : `${line.quantity} ${line.product.unit}`;
}

/** The message of a quantity's problem (COPY_DECK section 16.1). */
function quantityMessage(problem: QuantityProblem): string {
    switch (problem.kind) {
        case 'required':
            return t('ux.validation.required');
        case 'positive':
            return t('ux.validation.quantity_positive');
        case 'whole':
            return t('ux.validation.quantity_whole');
        case 'decimals':
            return t('ux.validation.quantity_decimals', { desimal: formatCount(problem.precision) });
        case 'max':
            return t('ux.validation.quantity_max');
    }
}

/** An amount of the server's safe values in the line's unit, or the text as it came. */
function stockText(available: string, line: Line): string {
    try {
        return formatQuantity(available, line.product.precision, line.product.unit);
    } catch {
        return `${available} ${line.product.unit}`;
    }
}

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    /** `text`: the search that the rows answer, which "Muat lebih banyak" continues (UXI-068). */
    | { readonly kind: 'rows'; readonly rows: readonly ProductRow[]; readonly hasMore: boolean; readonly page: number; readonly more: boolean; readonly text: string }
    | { readonly kind: 'failed'; readonly text: string }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> };

/** What the search area says about the last scan (UXS-33, UXS-32, UXS-22). */
type ScanNotice = { readonly kind: 'unknown'; readonly code: string } | { readonly kind: 'inactive'; readonly name: string } | { readonly kind: 'failed'; readonly code: string };

interface ScanAnswer {
    readonly code: string;
    readonly answer: TillSearchAnswer;
}

function TillView({
    session,
    categories,
    postingFrozen,
    draft,
    blocked,
    flash,
    searchField,
    onAdd,
    onChange,
}: {
    session: TillSession;
    categories: readonly TillCategory[];
    postingFrozen: boolean;
    draft: TillDraft;
    blocked: boolean;
    flash: { readonly productId: number; readonly at: number } | null;
    searchField: RefObject<HTMLInputElement | null>;
    onAdd: (product: CartProduct, announce: (text: string) => void) => void;
    onChange: (change: (draft: TillDraft) => TillDraft) => void;
}) {
    /** A change of the cart, after which the search field has the focus again (UXA-09). */
    const changeCart = (change: (draft: TillDraft) => TillDraft): void => {
        onChange(change);
        searchField.current?.focus();
    };
    const announce = useAnnounce();
    const ids = useId();
    const [note] = useState(takeSavedNote);
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const [notice, setNotice] = useState<ScanNotice | null>(null);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [category, setCategory] = useState('');
    const [brand, setBrand] = useState('');
    const [clearing, setClearing] = useState(false);
    /** The scans still to apply: "Bayar" waits for them, so that the payment shows every scan (review R-01). */
    const [lookups, setLookups] = useState(0);
    const [payAfter, setPayAfter] = useState(false);
    const latest = useRef(0);
    const timer = useRef<number | null>(null);
    const searchValue = useRef('');
    const resultList = useRef<HTMLDivElement | null>(null);
    const applyRef = useRef<(answer: ScanAnswer, scan: number) => void>(() => undefined);
    const queue = useMemo(() => createScanQueue<ScanAnswer>((answer, scan) => applyRef.current(answer, scan), setLookups), []);

    searchValue.current = search;
    useScanCapture(searchField, !clearing);

    /** Ends the typing's wait and every typed search still out: their answers are applied to nothing. */
    const supersede = (): number => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        latest.current += 1;

        return latest.current;
    };

    const filters = (): Record<string, string> => ({ ...(category === '' ? {} : { category }), ...(brand.trim() === '' ? {} : { brand: brand.trim() }) });

    const runSearch = (typed: string, page = 1, append: readonly ProductRow[] = []): void => {
        const mine = supersede();

        if (page === 1) {
            setResults({ kind: 'searching' });
        } else {
            setResults((previous) => (previous.kind === 'rows' ? { ...previous, more: true } : previous));
        }

        void searchTill({ ...(typed === '' ? {} : { q: typed }), ...filters(), ...(page > 1 ? { page: String(page) } : {}) }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'read') {
                setResults({ kind: 'rows', rows: [...append, ...answer.rows], hasMore: answer.hasMore, page, more: false, text: typed });
            } else if (answer.kind === 'invalid') {
                setResults({ kind: 'invalid', fields: answer.fields });
            } else if (answer.kind === 'no_session') {
                router.visit(TILL_PAGE);
            } else {
                setResults({ kind: 'failed', text: typed });
            }
        });
    };

    /** A scan's answer, in the order of the scans (BR-CAT-30). */
    applyRef.current = ({ code, answer }: ScanAnswer, scan: number): void => {
        // The code goes back to the field when no newer scan or typing took it (BR-CAT-31). A scan that adds
        // nothing keeps the till open, so that its notice is seen: "Bayar" pressed while it was out waits for
        // another press (review R-13).
        const keep = (): void => {
            setPayAfter(false);

            if (queue.isLatest(scan) && searchValue.current === '') {
                setSearch(code);
            }
        };

        if (answer.kind === 'no_session') {
            router.visit(TILL_PAGE);

            return;
        }

        if (answer.kind !== 'read') {
            keep();
            setNotice({ kind: 'failed', code });

            return;
        }

        const row = answer.rows[0];
        const product = row === undefined ? null : cartProductOf(row);

        if (row === undefined || product === null) {
            keep();
            setNotice({ kind: 'unknown', code });
            announce(t('ux.announce.unknown_code', { kode: code }));
            runSearch(code);

            return;
        }

        if (!row.active) {
            keep();
            setNotice({ kind: 'inactive', name: row.name });

            return;
        }

        setNotice(null);
        onAdd(product, announce);
    };

    /** The Enter that ends a scan or a code: its exact lookup, applied in its turn (UXI-066). */
    const lookUp = (code: string): void => {
        supersede();
        setSearch('');
        setNotice(null);
        queue.enqueue(searchTill({ q: code, mode: 'exact' }).then((answer) => ({ code, answer })));
    };

    const edited = (next: string): void => {
        supersede();
        setSearch(next);
        setNotice(null);

        const typed = next.trim();

        if (typed === '' && category === '' && brand.trim() === '') {
            setResults({ kind: 'none' });

            return;
        }

        timer.current = window.setTimeout(() => {
            timer.current = null;
            runSearch(typed);
        }, TYPING_MS);
    };

    // The search field has the focus on arrival (UXA-09); a scan held by the receipt page starts this sale (UXI-089).
    useEffect(() => {
        searchField.current?.focus();

        for (const held of takeScans()) {
            lookUp(held);
        }

        // Leaving the till's view forgets the scans still out: an answer after it changes no cart (review R-01).
        return () => {
            supersede();
            queue.reset();
        };
        // On arrival only.
    }, []);

    // A changed filter searches again.
    useEffect(() => {
        if (search.trim() !== '' || category !== '' || brand.trim() !== '') {
            supersede();
            timer.current = window.setTimeout(() => {
                timer.current = null;
                runSearch(searchValue.current.trim());
            }, TYPING_MS);
        }
        // The filters only: typing searches by itself.
    }, [category, brand]);

    const pick = (row: ProductRow): void => {
        const product = cartProductOf(row);

        if (product === null || !row.active) {
            return;
        }

        setNotice(null);
        setSearch('');
        onAdd(product, announce);
        searchField.current?.focus();
    };

    const firstResult = (): void => {
        resultList.current?.querySelector<HTMLButtonElement>('.bk-result:not([aria-disabled="true"])')?.focus();
    };

    const lines = draft.lines;
    const total = cartTotal(lines);
    const review = priceReviewPending(lines);
    const changedCount = lines.filter((line) => line.previousPrice !== null).length;
    const payReason: ReactNode =
        blocked ? (
            <>
                {t('sales.cart.disabled_unresolved')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
            </>
        ) : postingFrozen ? (
            t('sales.cart.disabled_onboarding')
        ) : lines.length === 0 && lookups === 0 ? (
            t('sales.cart.disabled_empty')
        ) : review ? (
            t('sales.cart.disabled_price_review')
        ) : !linesValid(lines) ? (
            t('sales.cart.disabled_invalid')
        ) : undefined;
    const payable = payReason === undefined && lines.length > 0;

    // "Bayar" while scans are still out opens the payment once they are applied, if the cart may still be paid
    // then: the payment always shows every scan, and no answer changes the cart after it (review R-01).
    useEffect(() => {
        if (payAfter && lookups === 0) {
            setPayAfter(false);

            if (payable) {
                showView(PAYMENT_VIEW);
            }
        }
    }, [payAfter, lookups, payable]);

    const toPayment = (): void => {
        if (queue.pending() > 0) {
            setPayAfter(true);
        } else if (payable) {
            showView(PAYMENT_VIEW);
        }
    };

    const confirmClear = (): void => {
        setClearing(false);
        changeCart(clearCart);
    };

    const categoryId = `${ids}-category`;
    const brandId = `${ids}-brand`;
    const filtersId = `${ids}-filters`;

    const searchArea = (
        <>
            {note !== null && <Committed message={note} />}
            <div className={'bk-till__head'}>
                <div className={'bk-till__title'}>
                    <h1 className={'bk-title'} tabIndex={-1}>
                        {t('sales.till.title')}
                    </h1>
                    <p className={'bk-meta'}>{t('sales.till.session', { nomor: session.number, laci: session.register })}</p>
                </div>
            </div>
            <div className={'bk-till__notices'}>
                {postingFrozen && <StateNotice state={FROZEN_STATE} />}
                {review && (
                    <StateNotice
                        state={PRICE_STATE}
                        body={t('ux.state.price_changed.body', { jumlah: formatCount(changedCount) })}
                        actions={<Button label={t('sales.cart.accept_new_prices')} onClick={() => changeCart(acceptNewPrices)} />}
                    >
                        <p>
                            <strong>{t('ux.state.price_changed.total', { uang: formatMoney(total) })}</strong>
                        </p>
                    </StateNotice>
                )}
            </div>
            <div className={'bk-till__search'}>
                <ScanField
                    label={t('sales.till.search')}
                    hint={t('sales.till.search_hint')}
                    value={search}
                    onValueChange={edited}
                    onLookup={lookUp}
                    onArrowDown={firstResult}
                    error={results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined}
                    inputRef={searchField}
                />
                <Button label={t('ux.action.filter')} variant={'secondary'} size={'lg'} icon={'filter'} aria-expanded={filtersOpen} aria-controls={filtersId} onClick={() => setFiltersOpen(!filtersOpen)} />
            </div>
            <div className={'bk-filters'} id={filtersId} hidden={!filtersOpen}>
                <Field id={categoryId} label={t('catalog.field.category')}>
                    <SelectInput value={category} onChange={(event) => setCategory(event.target.value)}>
                        <option value={''} />
                        {categories.map((choice) => (
                            <option key={choice.id} value={String(choice.id)}>
                                {choice.name}
                            </option>
                        ))}
                    </SelectInput>
                </Field>
                <Field id={brandId} label={t('catalog.field.brand')}>
                    <TextInput type={'search'} autoComplete={'off'} maxLength={100} value={brand} onChange={(event) => setBrand(event.target.value)} />
                </Field>
            </div>
            {notice?.kind === 'unknown' && <UnknownCode code={notice.code} />}
            {notice?.kind === 'inactive' && <StateNotice state={INACTIVE_STATE} body={t('ux.state.inactive.product.body', { produk: notice.name })} />}
            {notice?.kind === 'failed' && <LoadFailed onRetry={() => lookUp(notice.code)} />}
            <div ref={resultList}>
                {results.kind === 'searching' && <Loading lines={2} />}
                {results.kind === 'failed' && <LoadFailed onRetry={() => runSearch(results.text)} />}
                {results.kind === 'rows' && results.rows.length === 0 && (
                    <NoResults
                        onClear={() => {
                            setCategory('');
                            setBrand('');
                            edited('');
                        }}
                    />
                )}
                {results.kind === 'rows' && results.rows.length > 0 && (
                    <ProductResults label={t('sales.till.search')}>
                        {results.rows.map((row) => (
                            <ProductResult
                                key={row.id}
                                name={row.name}
                                thumb={<ProductThumb name={row.name} src={row.thumbnail} />}
                                accessibleName={row.active ? t('sales.till.add_to_cart_named', { produk: row.name }) : undefined}
                                addLabel={t('sales.till.add_to_cart')}
                                meta={
                                    <>
                                        <span className={'bk-mono'}>{row.sku}</span>
                                        {row.stock !== undefined && <span>{t('sales.till.result_stock', { jumlah: stockOf(row) })}</span>}
                                    </>
                                }
                                price={formatMoney(row.price.amount)}
                                disabledReason={row.active ? undefined : t('ux.state.inactive.label')}
                                onAdd={() => pick(row)}
                            />
                        ))}
                    </ProductResults>
                )}
                {results.kind === 'rows' && results.hasMore && (
                    <div className={'bk-load-more'}>
                        <Button label={t('ux.action.load_more')} variant={'secondary'} block={true} busy={results.more} onClick={() => runSearch(results.text, results.page + 1, results.rows)} />
                    </div>
                )}
            </div>
        </>
    );

    const cart = (
        <CartRegion
            label={t('sales.cart.title')}
            head={
                <>
                    <h2 className={'bk-h3'}>{t('sales.cart.title')}</h2>
                    {lines.length > 0 && <Button label={t('sales.cart.clear')} variant={'quiet'} onClick={() => setClearing(true)} />}
                </>
            }
            foot={
                <>
                    <p className={'bk-total'}>
                        <span className={'bk-total__label'}>{t('sales.cart.total')}</span>
                        <span className={'bk-total__value'}>{formatMoney(total)}</span>
                    </p>
                    {blocked && <ButtonLink href={UNRESOLVED_PAGE} label={t('sales.till.resolve_payment')} variant={'primary'} size={'xl'} className={'bk-btn--block'} />}
                    <Button label={t('sales.cart.pay')} size={'xl'} block={true} shortcut={'pay'} enterActivates={false} disabledReason={payReason} onClick={toPayment} />
                </>
            }
        >
            {lines.length === 0 ? (
                <li>
                    <p className={'bk-empty'}>{t('sales.cart.empty')}</p>
                </li>
            ) : (
                lines.map((line) => (
                    <TillLine
                        key={line.product.id}
                        line={line}
                        flash={flash !== null && flash.productId === line.product.id ? flash.at : null}
                        onType={onChange}
                        onChange={changeCart}
                        onEnter={() => searchField.current?.focus()}
                    />
                ))
            )}
        </CartRegion>
    );

    return (
        <div className={'bk-till-page'}>
            <TillWorkspace work={searchArea} cart={cart} />
            <Dialog
                open={clearing}
                onOpenChange={setClearing}
                title={t('sales.cart.clear_confirm.title')}
                description={<p>{t('sales.cart.clear_confirm.body')}</p>}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} size={'lg'} data-initial-focus={true} onClick={() => setClearing(false)} />
                        <Button label={t('sales.cart.clear')} variant={'danger'} size={'lg'} enterActivates={false} onClick={confirmClear} />
                    </>
                }
            />
        </div>
    );
}

/** The stock of a result that the role may see, in its unit (UXI-067; CAP-14). */
function stockOf(row: ProductRow): string {
    const onHand = row.stock?.onHand ?? '0';

    try {
        return formatQuantity(onHand, row.quantityPrecision, row.baseUnit);
    } catch {
        return `${onHand} ${row.baseUnit}`;
    }
}

/** One line of the cart (UXI-070 to UXI-072), with what it must say (UXS-18, UXS-31, UXS-32, UXS-28). */
function TillLine({
    line,
    flash,
    onType,
    onChange,
    onEnter,
}: {
    line: Line;
    flash: number | null;
    /** A quantity being typed: the focus stays in the field. */
    onType: (change: (draft: TillDraft) => TillDraft) => void;
    /** "+", "−" and remove: the search field has the focus again (UXA-09). */
    onChange: (change: (draft: TillDraft) => TillDraft) => void;
    /** Enter ends the edit and returns the focus to the search field (UXI-070). */
    onEnter: () => void;
}) {
    const ids = useId();
    const element = useRef<HTMLLIElement | null>(null);
    const total = lineTotalOf(line);
    const read = readQuantity(line.quantity, line.product.precision);
    const problem = 'problem' in read ? read.problem : null;
    const decreasable = canDecrease(line);
    const reasonId = `${ids}-decrease`;
    const errorId = `${ids}-error`;
    const id = line.product.id;
    // The field of each line is named with its product, as the stepper's group (UXA-13).
    const quantityLabel = `${t('sales.cart.quantity')} ${line.product.name}`;

    // The line just added is highlighted and kept in view (D-067).
    useEffect(() => {
        if (flash !== null) {
            element.current?.scrollIntoView({ block: 'nearest' });
        }
    }, [flash]);

    const notes = (
        <>
            {line.previousPrice !== null && (
                <p>
                    <StatusWord tone={'warning'} label={t('ux.state.price_changed.line', { lama: formatMoney(line.previousPrice), baru: formatMoney(line.product.price) })} />
                </p>
            )}
            {line.available !== null && (
                <p>
                    <StatusWord tone={'warning'} label={t('ux.state.insufficient_stock.line', { jumlah: stockText(line.available, line) })} />
                </p>
            )}
            {line.refusal === 'inactive' && (
                <p>
                    <StateMark kind={'inactive'} />
                </p>
            )}
            {line.refusal === 'blocked' && (
                <p>
                    <StatusWord tone={'danger'} label={t('ux.state.blocked.title')} />
                </p>
            )}
            {problem !== null && (
                <p className={'bk-error'} id={errorId}>
                    {quantityMessage(problem)}
                </p>
            )}
            {!decreasable && problem === null && (
                <p className={'bk-reason'} id={reasonId}>
                    {t('sales.cart.decrease_disabled')}
                </p>
            )}
        </>
    );

    return (
        <CartLine
            name={line.product.name}
            meta={
                <>
                    <span className={'bk-mono'}>{line.product.sku}</span> · {formatMoney(line.product.price)}
                </>
            }
            total={total === null ? '' : formatMoney(total)}
            totalLabel={t('sales.cart.line_total')}
            marked={line.previousPrice !== null || line.available !== null || line.refusal !== null}
            flash={flash !== null}
            lineRef={element}
            notes={notes}
            controls={
                <>
                    <QuantityStepper
                        label={quantityLabel}
                        value={line.quantity}
                        decimal={line.product.precision > 0}
                        onValueChange={(text) => onType((draft) => setQuantity(draft, id, text))}
                        onCommit={() => undefined}
                        onEnter={onEnter}
                        onIncrement={() => onChange((draft) => stepQuantity(draft, id, 1))}
                        onDecrement={() => onChange((draft) => stepQuantity(draft, id, -1))}
                        incrementLabel={t('sales.cart.increase', { satuan: line.product.unit, produk: line.product.name })}
                        decrementLabel={t('sales.cart.decrease', { satuan: line.product.unit, produk: line.product.name })}
                        canDecrement={decreasable}
                        decrementDescribedBy={!decreasable && problem === null ? reasonId : undefined}
                        invalid={problem !== null}
                        describedBy={problem === null ? undefined : errorId}
                    />
                    <span className={'bk-line__unit'}>{line.product.unit}</span>
                    <Button
                        label={t('sales.cart.remove')}
                        accessibleName={t('sales.cart.remove_named', { produk: line.product.name })}
                        variant={'quiet'}
                        icon={'trash'}
                        onClick={() => onChange((draft) => removeLine(draft, id))}
                    />
                </>
            }
        />
    );
}

function PaymentView({
    draft,
    control,
    online,
    postingFrozen,
    onPayment,
    onPay,
    onBack,
}: {
    draft: TillDraft;
    control: CommandControl;
    online: boolean;
    postingFrozen: boolean;
    onPayment: (change: (payment: PaymentDraft) => PaymentDraft) => void;
    onPay: () => void;
    /** "Kembali ke keranjang" (UXI-084, Esc) and, after NOT_COMMITTED, "Mulai lagi dari keranjang" (UXI-087): the same lines as a draft. */
    onBack: () => void;
}) {
    const { phase, blockedBy } = control;
    const ids = useId();
    const roles = usePage<SharedProps>().props.auth?.user.roles ?? [];
    const tenderedField = useRef<HTMLInputElement | null>(null);
    const methodId = `${ids}-method`;
    const lines = draft.lines;
    const payment = draft.payment;
    const total = cartTotal(lines);
    const tender = tenderOf(total, payment.tendered);
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const busy = phase.kind === 'submitting';
    const outstanding = phase.kind === 'uncertain' || phase.kind === 'held';
    const committed = phase.kind === 'committed';
    // What the screen shows is what is sent while a frozen request is out (INTERACTION_CONTRACTS section 1.3).
    const throttled = phase.kind === 'throttled';
    const frozen = busy || outstanding || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'reload';
    // After a 429 the final action repeats the frozen request (UXS-12), so the cart stays as it was sent until
    // then: the way back waits too (review R-02).
    const backBlocked = busy || outstanding || throttled;
    const hasCart = lines.length > 0;

    // Loaded without a cart, the payment view returns to the till (RP-102), unless a request of the family is
    // unresolved: then it shows that request's panel (NC-13 (b)). The user's references are read first.
    const known = useUnresolved().userId !== null;

    useEffect(() => {
        if (known && !hasCart && phase.kind === 'idle' && blockedBy === null) {
            showView(TILL_VIEW);
        }
    }, [known, hasCart, phase.kind, blockedBy]);

    // "Uang diterima" has the first focus with Tunai, the method with Transfer bank; never the final action (UXA-10).
    useEffect(() => {
        if (payment.method === CASH) {
            tenderedField.current?.focus();
        } else {
            document.getElementById(methodId)?.querySelector<HTMLElement>('[role="radio"][data-state="checked"]')?.focus();
        }
        // On arrival only.
    }, []);

    const choose = (method: PaymentMethod): void => {
        onPayment((current) => ({ ...current, method }));
    };

    const methodOptions: readonly SegmentedOption<PaymentMethod>[] = [
        { value: CASH, label: t('sales.payment.method_cash'), icon: 'cash', shortcut: 'method_cash', disabled: frozen },
        { value: BANK_TRANSFER, label: t('sales.payment.method_transfer'), icon: 'bank', shortcut: 'method_transfer', disabled: frozen },
    ];

    const finalReason: ReactNode =
        blockedBy !== null && !busy ? (
            <>
                {t('sales.cart.disabled_unresolved')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
            </>
        ) : postingFrozen ? (
            t('sales.cart.disabled_onboarding')
        ) : !online ? (
            t('ux.state.offline.body')
        ) : lines.length === 0 ? (
            // The till's own reasons hold here too: the final action never sends a cart that "Bayar" could not (review R-01).
            t('sales.cart.disabled_empty')
        ) : priceReviewPending(lines) ? (
            t('sales.cart.disabled_price_review')
        ) : !linesValid(lines) ? (
            t('sales.cart.disabled_invalid')
        ) : payment.method === CASH && tender.kind !== 'enough' ? (
            t('sales.payment.disabled_tender', { uang: formatMoney(tender.kind === 'short' ? tender.missing : total) })
        ) : undefined;

    const finalLabel = payment.method === CASH ? t('sales.payment.final_cash') : t('sales.payment.final_transfer');
    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const named = refusal === null ? [] : refusedNames(refusal.lines, lines);
    const refusalBody =
        refusal?.code === 'PRODUCT_INACTIVE'
            ? t('ux.state.inactive.product.body', { produk: named.join(', ') })
            : refusal?.code === 'POSTING_BLOCKED'
              ? `${t('ux.state.blocked.body', { produk: named.join(', ') })} ${roles.includes('SUPER_ADMIN') ? '' : t('ux.state.blocked.staff')}`.trim()
              : undefined;
    const refusalAction =
        refusal?.code === 'NO_OPEN_SESSION' || refusal?.code === 'SESSION_NOT_OPEN' ? <ButtonLink href={OPEN_SESSION_PAGE} label={t('cash.open.title')} variant={'secondary'} /> : undefined;
    const shortLines = refusal?.code === 'INSUFFICIENT_STOCK' ? lines.filter((line) => line.available !== null) : [];
    const referenceError = refusal === null ? undefined : catalogFieldMessage(refusal.fields['payload.payment.reference']);
    const customerError = refusal === null ? undefined : catalogFieldMessage(refusal.fields['payload.payment.customerName']);
    const tenderedError = refusal === null ? undefined : catalogFieldMessage(refusal.fields['payload.payment.tendered']);

    return (
        <PageContainer width={'focused'}>
            <header>
                <Button
                    label={t('sales.payment.back_to_cart')}
                    variant={'quiet'}
                    icon={'arrow-left'}
                    shortcut={'back'}
                    disabledReason={phase.kind === 'throttled' ? throttledBody(phase.seconds) : backBlocked ? t('sales.payment.back_disabled') : undefined}
                    onClick={onBack}
                />
                <div className={'bk-pagehead'}>
                    <div className={'bk-pagehead__text'}>
                        <h1 className={'bk-title'} tabIndex={-1}>
                            {t('sales.payment.title')}
                        </h1>
                    </div>
                </div>
            </header>
            <div className={'bk-stack'}>
                {refusalState !== undefined && (
                    <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} {...(refusalAction === undefined ? {} : { actions: refusalAction })}>
                        {shortLines.length > 0 && (
                            <ul>
                                {shortLines.map((line) => (
                                    <li key={line.product.id}>
                                        {line.product.name}: {t('ux.state.insufficient_stock.line', { jumlah: stockText(line.available ?? NONE_AVAILABLE, line) })}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </StateNotice>
                )}
                {refusal?.code === TENDER_INSUFFICIENT && <TenderLow missing={formatMoney(tender.kind === 'short' ? tender.missing : total)} />}
                {hasCart && (
                    <div className={'bk-pay'}>
                        <p className={'bk-pay__total'}>
                            <span className={'bk-pay__label'}>{t('sales.payment.total')}</span>
                            <span className={'bk-display'}>{formatMoney(total)}</span>
                        </p>
                        <div id={methodId}>
                            <SegmentedControl legend={t('sales.payment.method')} options={methodOptions} value={payment.method} onValueChange={choose} size={'xl'} />
                        </div>
                        {payment.method === CASH ? (
                            <div className={'bk-form'}>
                                <div className={'bk-tender'}>
                                    <Field label={t('sales.payment.tendered')} error={tenderedError}>
                                        <MoneyInput
                                            ref={tenderedField}
                                            size={'xl'}
                                            value={payment.tendered}
                                            disabled={frozen}
                                            onKeyDown={(event) => (event.key === 'Enter' && !event.ctrlKey ? event.preventDefault() : undefined)}
                                            onValueChange={(tendered) => onPayment((current) => ({ ...current, tendered }))}
                                        />
                                    </Field>
                                    <Button
                                        label={t('sales.payment.exact_cash')}
                                        variant={'secondary'}
                                        size={'xl'}
                                        shortcut={'exact_cash'}
                                        unavailable={frozen}
                                        onClick={() => {
                                            onPayment((current) => ({ ...current, tendered: total }));
                                            tenderedField.current?.focus();
                                        }}
                                    />
                                </div>
                                <p className={'bk-change'} data-short={tender.kind === 'short'} aria-live={'polite'}>
                                    <span className={'bk-pay__label'}>{tender.kind === 'short' ? t('ux.state.tender_low.title') : t('sales.payment.change')}</span>
                                    <span className={'bk-display'}>
                                        {tender.kind === 'short' ? t('ux.state.tender_low.body', { uang: formatMoney(tender.missing) }) : tender.kind === 'enough' ? formatMoney(tender.change) : '—'}
                                    </span>
                                </p>
                            </div>
                        ) : (
                            <div className={'bk-form'}>
                                <Notice tone={'info'} title={t('sales.payment.method_transfer')}>
                                    <p>{t('sales.payment.transfer_help')}</p>
                                </Notice>
                                <Field label={t('sales.payment.reference')} optional={true} error={referenceError}>
                                    <TextInput
                                        size={'lg'}
                                        type={'text'}
                                        autoComplete={'off'}
                                        maxLength={100}
                                        disabled={frozen}
                                        value={payment.reference}
                                        onKeyDown={(event) => (event.key === 'Enter' && !event.ctrlKey ? event.preventDefault() : undefined)}
                                        onChange={(event) => onPayment((current) => ({ ...current, reference: event.target.value }))}
                                    />
                                </Field>
                            </div>
                        )}
                        <Field label={t('sales.payment.customer_name')} optional={true} hint={t('sales.payment.customer_name_help')} error={customerError}>
                            <TextInput
                                size={'lg'}
                                type={'text'}
                                autoComplete={'off'}
                                maxLength={100}
                                disabled={frozen}
                                value={payment.customerName}
                                onKeyDown={(event) => (event.key === 'Enter' && !event.ctrlKey ? event.preventDefault() : undefined)}
                                onChange={(event) => onPayment((current) => ({ ...current, customerName: event.target.value }))}
                            />
                        </Field>
                        {!outstanding && !committed && (
                            <div className={'bk-final'}>
                                <Button label={finalLabel} size={'final'} block={true} shortcut={'confirm'} busy={busy} disabledReason={busy ? undefined : finalReason} unavailable={frozen && !busy && !throttled} onClick={onPay} />
                            </div>
                        )}
                    </div>
                )}
                {phase.kind !== 'rejected' || refusalState === undefined ? <CommandPanels control={control} {...(committed ? { committed: t('commands.resolution.committed') } : {})} /> : null}
                {phase.kind === 'not_committed' && <Button label={t('sales.payment.restart_from_cart')} size={'lg'} onClick={onBack} />}
            </div>
        </PageContainer>
    );
}

/** The names of the products that a refusal names, by the lines of the cart (RT-07 `lines`). */
function refusedNames(refused: Readonly<Record<string, unknown>>, lines: readonly Line[]): string[] {
    const ids = Object.values(refused).flatMap((entry) => (typeof entry === 'object' && entry !== null && 'productId' in entry && typeof entry.productId === 'number' ? [entry.productId] : []));

    return lines.filter((line) => ids.includes(line.product.id)).map((line) => line.product.name);
}
