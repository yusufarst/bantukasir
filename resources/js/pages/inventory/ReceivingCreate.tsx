import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { FilterChip } from '@/components/ui/Choices';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, LoadFailed, Loading, NoResults, StateMark, StateNotice, UnknownCode, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, quantityFromEntry, searchProducts, type Category, type ProductRow } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatCost, formatCount, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { postingBlockedReason } from '@/lib/inventory/Parts';
import { MAX_RECEIVING_LINES, NEW_RECEIVING_PAGE, QUANTITY_DIGITS_MAX, RECEIVING_COMMAND, RECEIVINGS_PAGE, RECEIVINGS_SHELL, backFrom, draftQuantity } from '@/lib/inventory/stock';
import { CreateFlow } from '@/pages/catalog/ProductCreate';

// UXP-405 "Catat barang masuk" (ROUTE_CONTRACTS RP-405, RD-01 `receiving`, RC-07; KS-09; COPY_DECK
// section 9.6; INTERACTION_INVENTORY UXI-234 to UXI-243 and UXI-245; NAVIGATION_CONTRACTS UXP-405
// and NC-11; UI_STATES section 6; BR-INV-10, BR-INV-12 to BR-INV-17; BR-COST-03, BR-COST-04;
// D-033, D-069 (a); TASK-022 scope item 1). The draft lives in this page's memory only, never in
// the browser's storage (BR-INV-17, SEC-CH-05): a reload, a closed tab or the end of the sign-in
// session loses it, as the draft note says. "Cari atau scan produk" is the shared Product Search
// in the context `receiving`: a scan of a known code adds one base unit to the latest line of its
// product, or a new line; choosing a result always adds a new line, so a product may have several
// lines with different costs (UXI-234; D-067). An unknown code stays in the field (UXS-33) with
// "Tambah produk", which opens UXP-303 over this page and adds the new product to the draft
// (UXI-235, NC-11). An inactive product cannot be chosen (UXS-32). Each line takes its quantity in
// the product's precision and its cost — the total for the line — or "Biaya belum diketahui",
// never stored as zero; Rp0 is the Owner's alone, with its evidence or reason, and for staff it is
// disabled with its reason (UXI-236; BR-COST-04). "Tinjau" (Ctrl+Enter, never Enter) opens the
// review with every line and the unknown costs marked; its confirmation sends RC-07 with the
// version of each product as reviewed. A refusal returns to the draft with the lines concerned
// marked (UXS-05, UXS-17, UXS-27, UXS-28, UXS-32); an uncertain outcome freezes the request
// (UXS-14). Success leads to UXP-406 of the document. Leaving with a draft asks first (UXS-45).

/** The context of RD-01 for this page (INTERACTION_CONTRACTS section 3.1). */
const RECEIVING_CONTEXT = 'receiving';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const REFERENCE_MAX = 100;

const EVIDENCE_MAX = 1000;

/** The largest whole rupiah of a cost (TECHNICAL_RULES section 11.1): twelve digits. */
const MONEY_DIGITS_MAX = 12;

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const INACTIVE_STATE: StateId = 'UXS-32-product';

const FROZEN_STATE: StateId = 'UXS-27';

const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    STALE_VERSION: 'UXS-17',
    ONBOARDING_FREEZE: 'UXS-27',
    POSTING_BLOCKED: 'UXS-28',
    PRODUCT_INACTIVE: 'UXS-32-product',
};

/** A product of the draft, as RD-01 in the context `receiving` gives it. */
interface DraftProduct {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly version: number;
    readonly active: boolean;
}

/** A line of the draft, in page memory only. */
interface DraftLine {
    readonly key: number;
    readonly product: DraftProduct;
    readonly quantity: string;
    readonly unknown: boolean;
    readonly amount: string | null;
    readonly zeroEvidence: string;
}

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly rows: readonly DraftProduct[] }
    | { readonly kind: 'unknown'; readonly code: string }
    | { readonly kind: 'inactive'; readonly product: DraftProduct }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string; readonly exact: boolean };

interface ReceivingCreateProps {
    readonly postingFrozen: boolean;
    readonly zeroAllowed: boolean;
    readonly categories: readonly Category[];
}

export default function ReceivingCreate(props: ReceivingCreateProps) {
    const title = t('inventory.receipt_new.title');

    return (
        <AppShell title={title} {...RECEIVINGS_SHELL}>
            <ReceivingForm title={title} {...props} />
        </AppShell>
    );
}

/** A row of RD-01 in the context `receiving`, with the version that RC-07 expects; null when it has none. */
function draftProductOf(row: ProductRow): DraftProduct | null {
    const version: unknown = (row as ProductRow & { readonly version?: unknown }).version;

    if (typeof version !== 'number' || !Number.isSafeInteger(version)) {
        return null;
    }

    return { id: row.id, name: row.name, sku: row.sku, baseUnit: row.baseUnit, quantityPrecision: row.quantityPrecision, version, active: row.active };
}

/** A quantity as typed with one base unit more: "2" is "3" and "1,5" is "2,5", in exact digits. */
function plusOne(text: string): string {
    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(quantityFromEntry(text));

    if (match === null) {
        return '1';
    }

    const whole = (BigInt(match[1] ?? '0') + 1n).toString();

    return match[2] === undefined ? whole : `${whole},${match[2]}`;
}

/** Why a quantity cannot be sent, in the product's precision (COPY_DECK section 16.1), or null. */
function quantityProblem(text: string, precision: number): string | null {
    const entry = text.trim();

    if (entry === '') {
        return t('ux.validation.required');
    }

    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(quantityFromEntry(entry));

    if (match === null || /^0+$/.test(`${match[1] ?? ''}${match[2] ?? ''}`)) {
        return t('ux.validation.quantity_positive');
    }

    const decimals = (match[2] ?? '').replace(/0+$/, '').length;

    if (decimals > precision) {
        return precision === 0 ? t('ux.validation.quantity_whole') : t('ux.validation.quantity_decimals', { desimal: formatCount(precision) });
    }

    return (match[1] ?? '').replace(/^0+/, '').length > QUANTITY_DIGITS_MAX ? t('ux.validation.quantity_max') : null;
}

/** Why a cost cannot be sent (UXI-236; BR-COST-03, BR-COST-04), or null. */
function costProblem(line: DraftLine, zeroAllowed: boolean): string | null {
    if (line.unknown) {
        return null;
    }

    if (line.amount === null) {
        return t('ux.validation.required');
    }

    if (line.amount.length > MONEY_DIGITS_MAX) {
        return t('ux.validation.money_max');
    }

    return /^0+$/.test(line.amount) && !zeroAllowed ? t('costing.field.zero_disabled') : null;
}

function isZero(line: DraftLine): boolean {
    return !line.unknown && line.amount !== null && /^0+$/.test(line.amount);
}

/** The message of a field's first refusal of RC-07, or none for a key this page does not show. */
function refusalMessage(entries: unknown): string | undefined {
    const first: unknown = Array.isArray(entries) ? entries[0] : null;
    const key = typeof first === 'object' && first !== null && 'key' in first ? (first as { readonly key: unknown }).key : null;

    switch (key) {
        case 'costing.field.zero_disabled':
            return t('costing.field.zero_disabled');
        case 'ux.validation.zero_evidence':
            return t('ux.validation.zero_evidence');
        case 'ux.validation.lines_min':
            return t('ux.validation.lines_min');
        case 'ux.validation.quantity_positive':
            return t('ux.validation.quantity_positive');
        default:
            return catalogFieldMessage(entries);
    }
}

/** The longest text of a search (ROUTE_CONTRACTS RD-01), in characters. */
const SEARCH_MAX = 128;

/** A product of a refusal read again in the context `receiving`, found by its key among the rows of one search. */
async function foundIn(productId: number, search: Readonly<Record<string, string>>): Promise<DraftProduct | null> {
    const answer = await searchProducts({ context: RECEIVING_CONTEXT, ...search });
    const row = answer.kind === 'read' ? answer.body.rows.find((candidate) => candidate.id === productId) : undefined;

    return row === undefined ? null : draftProductOf(row);
}

/** A product that changed after the review, as it is now: by its SKU as reviewed, else by its name; null when neither finds it. */
async function rereadProduct(productId: number, reviewed: DraftProduct): Promise<DraftProduct | null> {
    const bySku = await foundIn(productId, { q: reviewed.sku, mode: 'exact' });

    return bySku ?? (await foundIn(productId, { q: Array.from(reviewed.name).slice(0, SEARCH_MAX).join('') }));
}

/** The products that a refusal names in its lines (RT-07; TASK-021). */
function refusedProducts(lines: Readonly<Record<string, unknown>>): readonly number[] {
    return Object.values(lines).flatMap((entry) => (typeof entry === 'object' && entry !== null && 'productId' in entry && typeof entry.productId === 'number' ? [entry.productId] : []));
}

function ReceivingForm({ title, postingFrozen, zeroAllowed, categories }: ReceivingCreateProps & { title: string }) {
    const control = useCommand(RECEIVING_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const announce = useAnnounce();
    const ids = useId();
    const [lines, setLines] = useState<readonly DraftLine[]>([]);
    const [reference, setReference] = useState('');
    const [documentDate, setDocumentDate] = useState('');
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    const [over, setOver] = useState<{ readonly code: string } | null>(null);
    const [overDirty, setOverDirty] = useState(false);
    // The lines in the order they were sent, which a refusal's paths and products name.
    const [sent, setSent] = useState<readonly DraftLine[]>([]);
    const [back] = useState(() => backFrom(document.referrer, window.location.origin, (path) => path === RECEIVINGS_PAGE, RECEIVINGS_PAGE));
    const nextKey = useRef(1);
    const latest = useRef(0);
    const timer = useRef<number | null>(null);
    const searchField = useRef<HTMLInputElement | null>(null);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the lines show is what is sent, while a frozen request is out or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const draftDirty = lines.length > 0 || reference.trim() !== '' || documentDate !== '';

    useUnsavedChanges((draftDirty || overDirty) && !committed, t('ux.state.unsaved.body_receiving'));
    useScanCapture(searchField, !frozen && over === null);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('inventory.receipt_new.success'));
            router.visit(location);
        }
    }, [location]);

    // The review ends with its answer, and comes back after a "Batal" of the recent authentication.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting' && phase.kind !== 'recent_auth') {
            setReview(false);
        }
    }, [phase.kind]);

    /** Ends the typing's wait and every read still out: their answers are applied to nothing. */
    const supersede = (): number => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        latest.current += 1;

        return latest.current;
    };

    useEffect(() => () => void supersede(), []);

    const newLine = (product: DraftProduct, quantity: string): DraftLine => {
        const key = nextKey.current;

        nextKey.current += 1;

        return { key, product, quantity, unknown: false, amount: null, zeroEvidence: '' };
    };

    /** Adds the product as a new line, or with a scan one base unit to its latest line (UXI-234). */
    const add = (product: DraftProduct, scanned: boolean): void => {
        if (!product.active) {
            setResults({ kind: 'inactive', product });

            return;
        }

        setResults({ kind: 'none' });
        setSearch('');
        setChecked(false);
        setLines((current) => {
            const latestLine = scanned ? [...current].reverse().find((line) => line.product.id === product.id) : undefined;

            if (latestLine === undefined) {
                return [...current, newLine(product, '1')];
            }

            return current.map((line) => (line.key === latestLine.key ? { ...line, quantity: plusOne(line.quantity) } : line));
        });
        announce(t('ux.announce.added', { produk: product.name, jumlah: formatQuantity('1', 0, product.baseUnit) }), false);
        searchField.current?.focus();
    };

    const runSearch = (typed: string, exact: boolean): void => {
        const mine = supersede();

        setResults({ kind: 'searching' });
        void searchProducts({ context: RECEIVING_CONTEXT, q: typed, ...(exact ? { mode: 'exact' } : {}) }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'invalid') {
                setResults({ kind: 'invalid', fields: answer.fields });

                return;
            }

            if (answer.kind === 'failed') {
                setResults({ kind: 'failed', text: typed, exact });

                return;
            }

            const rows = answer.body.rows.flatMap((row) => {
                const product = draftProductOf(row);

                return product === null ? [] : [product];
            });
            const first = rows[0];

            if (exact) {
                if (first === undefined) {
                    setResults({ kind: 'unknown', code: typed });
                    announce(t('ux.announce.unknown_code', { kode: typed }), false);
                } else {
                    add(first, true);
                }

                return;
            }

            setResults({ kind: 'rows', rows });
        });
    };

    const edited = (next: string): void => {
        supersede();
        setSearch(next);

        const typed = next.trim();

        if (typed === '') {
            setResults({ kind: 'none' });

            return;
        }

        timer.current = window.setTimeout(() => {
            timer.current = null;
            runSearch(typed, false);
        }, TYPING_MS);
    };

    const lookUp = (): void => {
        const code = search.trim();

        if (code !== '') {
            runSearch(code, true);
        }
    };

    const change = (key: number, patch: Partial<DraftLine>): void => {
        setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)));
    };

    const remove = (key: number): void => {
        setLines((current) => current.filter((line) => line.key !== key));
        searchField.current?.focus();
    };

    /** "Tambah produk" for an unknown code: UXP-303 over this page, the draft kept (UXI-235, NC-11). */
    const openCreate = (code: string): void => {
        supersede();
        setOver({ code });
    };

    const closeCreate = (): void => {
        setOver(null);
        setOverDirty(false);
        window.setTimeout(() => searchField.current?.focus(), 0);
    };

    /** The new product of UXP-303 joins the draft as a new line, at the version it was created with. */
    const created = (product: { readonly id: number; readonly sku: string }): void => {
        closeCreate();
        setResults({ kind: 'searching' });
        void searchProducts({ context: RECEIVING_CONTEXT, q: product.sku, mode: 'exact' }).then((answer) => {
            const row = answer.kind === 'read' ? answer.body.rows.find((candidate) => candidate.id === product.id) : undefined;
            const found = row === undefined ? null : draftProductOf(row);

            if (found === null) {
                setResults({ kind: 'failed', text: product.sku, exact: true });
            } else {
                add(found, false);
            }
        });
    };

    // A refusal of products that changed reads them again, so that the review shows them as they are now (UXS-17).
    useEffect(() => {
        if (refusal?.code !== 'STALE_VERSION') {
            return;
        }

        const stale = refusedProducts(refusal.lines);

        for (const productId of stale) {
            const line = sent.find((candidate) => candidate.product.id === productId);

            if (line === undefined) {
                continue;
            }

            // By its SKU as reviewed, then, should the SKU itself have changed, by its name (review finding R-05).
            void rereadProduct(productId, line.product).then((fresh) => {
                if (fresh !== null) {
                    setLines((current) => current.map((candidate) => (candidate.product.id === productId ? { ...candidate, product: fresh } : candidate)));
                }
            });
        }
    }, [refusal]);

    const problems = lines.map((line) => ({
        quantity: quantityProblem(line.quantity, line.product.quantityPrecision),
        cost: costProblem(line, zeroAllowed),
        evidence: isZero(line) && zeroAllowed && line.zeroEvidence.trim() === '' ? t('ux.validation.zero_evidence') : null,
    }));
    const complete = lines.length > 0 && lines.length <= MAX_RECEIVING_LINES && problems.every((problem) => problem.quantity === null && problem.cost === null && problem.evidence === null);
    const blockedReason = postingBlockedReason(postingFrozen, blockedBy !== null && !busy);
    const reviewReason = blockedReason ?? (complete ? undefined : t('inventory.receipt_new.disabled'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        const products = new Map<number, number>();

        for (const line of lines) {
            products.set(line.product.id, line.product.version);
        }

        setSent(lines);
        control.submit(
            {
                reference: reference.trim() === '' ? null : reference.trim(),
                documentDate: documentDate === '' ? null : documentDate,
                lines: lines.map((line) => ({
                    productId: line.product.id,
                    quantity: quantityFromEntry(line.quantity),
                    cost: line.unknown ? { status: 'UNKNOWN' } : { status: 'KNOWN', amount: line.amount, ...(isZero(line) ? { zeroEvidence: line.zeroEvidence.trim() } : {}) },
                })),
            },
            { products: [...products.entries()].map(([productId, version]) => ({ productId, version })) },
        );
    };

    const fieldId = (key: number, field: string): string => `${ids}-line-${String(key)}-${field}`;
    const summary: FieldError[] = [];
    // A refusal's field of a line, by the line's index in the request that was sent.
    const refused = (key: number, field: string): string | undefined => {
        const index = sent.findIndex((line) => line.key === key);

        return refusal === null || index < 0 ? undefined : refusalMessage(refusal.fields[`payload.lines.${String(index)}.${field}`]);
    };
    const named = refusal === null ? [] : refusedProducts(refusal.lines);
    const namedProducts = sent.filter((line, index) => named.includes(line.product.id) && sent.findIndex((other) => other.product.id === line.product.id) === index).map((line) => line.product.name);

    const lineViews = lines.map((line, index) => {
        const problem = problems[index];
        const quantityError = refused(line.key, 'quantity') ?? (checked ? (problem?.quantity ?? undefined) : undefined);
        const costError = refused(line.key, 'cost.amount') ?? (checked || isZero(line) ? (problem?.cost ?? undefined) : undefined);
        const evidenceError = refused(line.key, 'cost.zeroEvidence') ?? (checked ? (problem?.evidence ?? undefined) : undefined);

        for (const [field, label, message] of [
            ['quantity', t('inventory.line.quantity'), quantityError],
            ['cost', t('costing.field.line_cost'), costError],
            ['evidence', t('costing.field.zero_evidence'), evidenceError],
        ] as const) {
            if (invalid && message !== undefined) {
                summary.push({ fieldId: fieldId(line.key, field), label: `${line.product.name}: ${label}`, message });
            }
        }

        return { line, quantityError, costError, evidenceError, marked: named.includes(line.product.id) };
    });

    // The document's own fields, refused at their field and in the summary (review finding R-04).
    const referenceId = `${ids}-reference`;
    const documentDateId = `${ids}-document-date`;
    const referenceError = refusal === null ? undefined : refusalMessage(refusal.fields['payload.reference']);
    const documentDateError = refusal === null ? undefined : refusalMessage(refusal.fields['payload.documentDate']);

    for (const [id, label, message] of [
        [referenceId, t('inventory.receipts.col_reference'), referenceError],
        [documentDateId, t('inventory.receipt_new.document_date'), documentDateError],
    ] as const) {
        if (invalid && message !== undefined) {
            summary.push({ fieldId: id, label, message });
        }
    }

    const unknownLines = lines.filter((line) => line.unknown).length;
    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const refusalBody =
        refusal?.code === 'PRODUCT_INACTIVE'
            ? t('ux.state.inactive.product.body', { produk: namedProducts.join(', ') })
            : refusal?.code === 'POSTING_BLOCKED'
              ? `${t('ux.state.blocked.body', { produk: namedProducts.join(', ') })} ${zeroAllowed ? '' : t('ux.state.blocked.staff')}`.trim()
              : undefined;
    const cancel = <ButtonLink href={back} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />;

    if (over !== null) {
        return (
            <CreateFlow
                title={t('catalog.product_create.title')}
                categories={categories}
                code={over.code}
                onDirty={setOverDirty}
                over={{ page: title, href: NEW_RECEIVING_PAGE, onBack: closeCreate, onCreated: created }}
            />
        );
    }

    const resultsView = (
        <>
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && <LoadFailed onRetry={() => runSearch(results.text, results.exact)} />}
            {results.kind === 'unknown' && <UnknownCode code={results.code} action={<Button label={t('catalog.product_create.title')} variant={'secondary'} onClick={() => openCreate(results.code)} />} />}
            {results.kind === 'inactive' && <StateNotice state={INACTIVE_STATE} body={t('ux.state.inactive.product.body', { produk: results.product.name })} />}
            {results.kind === 'rows' && results.rows.length > 0 && (
                <ul className={'bk-stack'}>
                    {results.rows.map((row) => (
                        <li key={row.id}>
                            <Button label={row.name} rowName={row.sku} variant={'secondary'} block={true} enterActivates={true} onClick={() => add(row, false)}>
                                <span className={'bk-meta'}>{row.active ? row.sku : t('ux.state.inactive.label')}</span>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            {results.kind === 'rows' && results.rows.length === 0 && <NoResults onClear={() => edited('')} />}
        </>
    );

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: back, page: t('inventory.receipts.title'), onNavigate: guard }} />} width={'form'}>
            <Notice tone={'neutral'} urgency={'none'}>
                <p>{t('inventory.receipt_new.draft_note')}</p>
            </Notice>
            {postingFrozen && <StateNotice state={FROZEN_STATE} />}
            {invalid && <ErrorSummary errors={summary} />}
            {refusalState !== undefined && <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} />}
            <Card>
                <CommandForm label={title}>
                    <CodeField label={t('inventory.line.search')} hint={t('inventory.line.search_hint')} disabled={frozen} error={results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined} value={search} onValueChange={edited} onEnter={lookUp} inputRef={searchField} />
                    {resultsView}
                    {lineViews.length > 0 && (
                        <ul className={'bk-stack'} aria-label={t('inventory.receipt_new.title')}>
                            {lineViews.map(({ line, quantityError, costError, evidenceError, marked }) => (
                                <li key={line.key}>
                                    <Section title={line.product.name} level={3} card={false}>
                                        <div className={'bk-stack'} data-marked={marked}>
                                            <p className={'bk-meta'}>{line.product.sku}</p>
                                            <Field id={fieldId(line.key, 'quantity')} label={t('inventory.line.quantity')} required={true} error={quantityError}>
                                                <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} suffix={line.product.baseUnit} disabled={frozen} value={line.quantity} onChange={(event) => change(line.key, { quantity: event.target.value })} />
                                            </Field>
                                            {!line.unknown && (
                                                <Field id={fieldId(line.key, 'cost')} label={t('costing.field.line_cost')} required={true} hint={t('costing.field.line_cost_help')} error={costError}>
                                                    <MoneyInput disabled={frozen} value={line.amount} onValueChange={(amount) => change(line.key, { amount })} />
                                                </Field>
                                            )}
                                            <div className={'bk-actions'}>
                                                <FilterChip label={t('costing.field.cost_unknown')} pressed={line.unknown} onPressedChange={(pressed) => (frozen ? undefined : change(line.key, { unknown: pressed }))} />
                                            </div>
                                            {line.unknown && <p className={'bk-hint'}>{t('costing.field.cost_unknown_help')}</p>}
                                            {isZero(line) && zeroAllowed && (
                                                <Field id={fieldId(line.key, 'evidence')} label={t('costing.field.zero_evidence')} required={true} error={evidenceError}>
                                                    <TextInput type={'text'} autoComplete={'off'} maxLength={EVIDENCE_MAX} disabled={frozen} value={line.zeroEvidence} onChange={(event) => change(line.key, { zeroEvidence: event.target.value })} />
                                                </Field>
                                            )}
                                            <div className={'bk-actions'}>
                                                <Button label={t('ux.action.remove_line')} rowName={line.product.name} variant={'quiet'} icon={'trash'} unavailable={frozen} onClick={() => remove(line.key)} />
                                            </div>
                                        </div>
                                    </Section>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Field id={referenceId} label={t('inventory.receipts.col_reference')} optional={true} error={referenceError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REFERENCE_MAX} disabled={frozen} value={reference} onChange={(event) => setReference(event.target.value)} />
                    </Field>
                    <Field id={documentDateId} label={t('inventory.receipt_new.document_date')} optional={true} hint={t('inventory.receipt_new.document_date_help')} error={documentDateError}>
                        <TextInput type={'date'} disabled={frozen} value={documentDate} onChange={(event) => setDocumentDate(event.target.value)} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked || blockedReason !== undefined ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                        {cancel}
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog
                open={review && (phase.kind === 'idle' || busy || throttled)}
                onOpenChange={setReview}
                title={t('inventory.receipt_new.review_title')}
                confirmLabel={t('inventory.receipt_new.confirm')}
                busy={busy}
                onConfirm={confirm}
            >
                <p>{t('inventory.receipt_new.review_lines', { jumlah: formatCount(lines.length) })}</p>
                {unknownLines > 0 && <p>{t('inventory.receipt_new.review_unknown', { jumlah: formatCount(unknownLines) })}</p>}
                <ul className={'bk-stack'}>
                    {lines.map((line) => {
                        // The review's children are made with every render, the dialog closed too, so a line's quantity is
                        // formatted only once it is one; the review opens only then (pull request #31, finding P1).
                        const quantity = draftQuantity(line.quantity, line.product.quantityPrecision);

                        return (
                            <li key={line.key} data-unknown={line.unknown}>
                                <strong>{line.product.name}</strong> · <span className={'bk-num'}>{quantity === null ? line.quantity : formatQuantity(quantity, line.product.quantityPrecision, line.product.baseUnit)}</span> · {line.unknown || line.amount === null ? <StateMark kind={'cost_unknown'} /> : <span className={'bk-num'}>{formatCost(line.amount)}</span>}
                            </li>
                        );
                    })}
                </ul>
                <p>{t('inventory.receipt_new.review_note')}</p>
            </ReviewDialog>
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('inventory.receipt_new.success') } : {})} />}
        </CommandLayout>
    );
}
