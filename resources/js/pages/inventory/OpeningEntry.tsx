import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { FilterChip } from '@/components/ui/Choices';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
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
import { formatCost, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    COUNT_REFERENCE_MAX,
    countProblem,
    eligibilityWord,
    isZeroCount,
    MAX_OPENING_ROWS,
    ONBOARDING_PAGE,
    ONBOARDING_SHELL,
    OPENING_COMMAND,
    OPENING_ENTRY_PAGE,
    openingEntryPage,
    openingProductOf,
    REASON_MAX,
    REPLACE_COMMAND,
    type OpeningProduct,
} from '@/lib/inventory/onboarding';
import { draftQuantity, REQUESTS_PAGE, sentQuantity } from '@/lib/inventory/stock';
import { CreateFlow } from '@/pages/catalog/ProductCreate';

// UXP-413 "Input stok awal" (ROUTE_CONTRACTS RP-413, RD-01 `opening`, RC-11, RC-48; COPY_DECK section 9.14;
// INTERACTION_INVENTORY UXI-292 to UXI-300; NAVIGATION_CONTRACTS UXP-413 and NC-11; UI_STATES section 6;
// BR-INV-24 to BR-INV-27, BR-INV-47, BR-INV-48; BR-COST-03, BR-COST-04; D-087; TASK-035 scope item 5), the
// Owner's alone (CAP-15), only while onboarding is open. The draft lives in this page's memory only, never in
// the browser's storage (SEC-CH-05), and no lock is held while the Owner counts (TR-TX-03). "Cari atau scan
// produk" is the shared Product Search in the context `opening` (UXI-292): a product without a movement is
// eligible; one counted — its OPENING or zero count effective — or with an earlier movement is shown so, and
// entering it is not offered; one whose OPENING was reversed leads to its replacement entry. A scan of a
// known code adds one base unit to the product's row, or a new row; each product has one row. An unknown
// code stays in the field (UXS-33) with "Tambah produk", which opens UXP-303 over this page and adds the new
// product (UXI-293, NC-11). Each row (UXI-294) takes its count in the product's precision — 0 records a zero
// count (BR-INV-27) — and a positive row its cost, or "Biaya belum diketahui", a zero with its evidence or
// reason (BR-COST-04), and its "Referensi hitung"; at most 100 rows, all saved together or none (BR-INV-25).
// "Tinjau" (Ctrl+Enter, never Enter) opens the review (UXI-295, UXI-296); its confirmation sends RC-11 with
// the onboarding version and each product's version as reviewed. A refusal returns to the draft with the rows
// concerned marked, an uncertain outcome freezes the request (UXI-298), and success leads to UXP-412.
//
// Opened for a product whose OPENING was reversed ("Catat ulang stok awal", UXI-291), the page is that one
// product's replacement entry (RC-48; D-087): its count, cost, count reference and reason, reviewed as "Tinjau
// stok awal pengganti" and confirmed after recent authentication (UXS-11). "Batal" (UXI-299) and "Kembali ke
// Stok awal" (UXI-300) lead to UXP-412, asking first with a draft (UXS-45).

/** The context of RD-01 for this page (INTERACTION_CONTRACTS section 3.1). */
const OPENING_CONTEXT = 'opening';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const EVIDENCE_MAX = 1000;

/** The largest whole rupiah of a cost (TECHNICAL_RULES section 11.1): twelve digits. */
const MONEY_DIGITS_MAX = 12;

/** The longest text of a search (ROUTE_CONTRACTS RD-01), in characters. */
const SEARCH_MAX = 128;

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const STALE_VERSION = 'STALE_VERSION';

const INACTIVE_STATE: StateId = 'UXS-32-product';

const STALE_STATE: StateId = 'UXS-17';

const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    STALE_VERSION: 'UXS-17',
    POSTING_BLOCKED: 'UXS-28',
    PRODUCT_INACTIVE: 'UXS-32-product',
};

/** The props that a changed onboarding or product reads again (UXS-17). */
const ENTRY_PROPS = ['onboardingVersion', 'product'];

interface OpeningEntryProps {
    readonly onboardingVersion: number;
    /** The product of "Catat ulang stok awal", as RD-01 in the context `opening` gives it, or null. */
    readonly product: ProductRow | null;
    readonly categories: readonly Category[];
}

export default function OpeningEntry({ onboardingVersion, product, categories }: OpeningEntryProps) {
    const title = t('inventory.opening_entry.title');
    const named = product === null ? null : openingProductOf(product);

    return (
        <AppShell title={title} {...ONBOARDING_SHELL}>
            {named !== null && named.eligibility === 'REPLACEABLE' && named.replacesLineId !== null ? (
                <ReplacementForm title={title} onboardingVersion={onboardingVersion} product={named} replacesLineId={named.replacesLineId} />
            ) : (
                <EntryForm title={title} onboardingVersion={onboardingVersion} categories={categories} stale={named !== null} />
            )}
        </AppShell>
    );
}

/** The cost of a positive row or a replacement, as typed. */
interface DraftCost {
    readonly unknown: boolean;
    readonly amount: string | null;
    readonly zeroEvidence: string;
}

/** A row of the draft, in page memory only. */
interface DraftRow extends DraftCost {
    readonly key: number;
    readonly product: OpeningProduct;
    readonly quantity: string;
    readonly countReference: string;
}

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly rows: readonly OpeningProduct[] }
    | { readonly kind: 'unknown'; readonly code: string }
    | { readonly kind: 'inactive'; readonly product: OpeningProduct }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string; readonly exact: boolean };

function isZeroCost(cost: DraftCost): boolean {
    return !cost.unknown && cost.amount !== null && /^0+$/.test(cost.amount);
}

/** Why a cost cannot be sent (BR-COST-03, BR-COST-04), or null; Rp0 is the Owner's with its evidence or reason. */
function costProblem(cost: DraftCost): string | null {
    if (cost.unknown) {
        return null;
    }

    if (cost.amount === null) {
        return t('ux.validation.required');
    }

    return cost.amount.length > MONEY_DIGITS_MAX ? t('ux.validation.money_max') : null;
}

/** A cost as RC-11 and RC-48 take it, as RC-07 does. */
function sentCost(cost: DraftCost): Readonly<Record<string, unknown>> {
    return cost.unknown ? { status: 'UNKNOWN' } : { status: 'KNOWN', amount: cost.amount, ...(isZeroCost(cost) ? { zeroEvidence: cost.zeroEvidence.trim() } : {}) };
}

/** The message of a field's first refusal of RC-11 or RC-48, or none for a key this page does not show. */
function refusalMessage(entries: unknown): string | undefined {
    const first: unknown = Array.isArray(entries) ? entries[0] : null;
    const key = typeof first === 'object' && first !== null && 'key' in first ? (first as { readonly key: unknown }).key : null;

    switch (key) {
        case 'inventory.opening_entry.not_eligible':
            return t('inventory.opening_entry.not_eligible');
        case 'inventory.opening_entry.already_zero':
            return t('inventory.opening_entry.already_zero');
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

/** A product of a refusal read again in the context `opening`, found by its key among the rows of one search. */
async function foundIn(productId: number, search: Readonly<Record<string, string>>): Promise<OpeningProduct | null> {
    const answer = await searchProducts({ context: OPENING_CONTEXT, ...search });
    const row = answer.kind === 'read' ? answer.body.rows.find((candidate) => candidate.id === productId) : undefined;

    return row === undefined ? null : openingProductOf(row);
}

/** A product that changed after the review, as it is now: by its SKU as reviewed, else by its name; null when neither finds it. */
async function rereadProduct(productId: number, reviewed: OpeningProduct): Promise<OpeningProduct | null> {
    const bySku = await foundIn(productId, { q: reviewed.sku, mode: 'exact' });

    return bySku ?? (await foundIn(productId, { q: Array.from(reviewed.name).slice(0, SEARCH_MAX).join('') }));
}

/** The products that a refusal names in its lines (RT-07). */
function refusedProducts(lines: Readonly<Record<string, unknown>>): readonly number[] {
    return Object.values(lines).flatMap((entry) => (typeof entry === 'object' && entry !== null && 'productId' in entry && typeof entry.productId === 'number' ? [entry.productId] : []));
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

/** A count as the review shows it: its quantity, or "Dihitung nol"; as typed while it is no quantity yet. */
function countText(quantity: string, product: OpeningProduct): string {
    const count = draftQuantity(quantity, product.quantityPrecision);

    if (count === null) {
        return quantity;
    }

    return count === '0' ? t('inventory.onboarding.zero_count') : formatQuantity(count, product.quantityPrecision, product.baseUnit);
}

/** A cost as the review shows it. */
function CostValue({ cost }: { cost: DraftCost }) {
    return cost.unknown || cost.amount === null ? <StateMark kind={'cost_unknown'} /> : <span className={'bk-num'}>{formatCost(cost.amount)}</span>;
}

/**
 * A row as the review shows it: its count, and for a positive row its cost and count reference. The
 * review's children are made with every render, the dialog closed too, so a count is formatted only
 * once it is one (pull request #31, finding P1).
 */
function reviewItemsOf(row: DraftRow): KeyValue[] {
    return [
        { key: 'quantity', label: t('inventory.line.quantity'), value: <span className={'bk-num'}>{countText(row.quantity, row.product)}</span> },
        ...(isZeroCount(row.quantity)
            ? []
            : [
                  { key: 'cost', label: t('costing.field.line_cost'), value: <CostValue cost={row} /> },
                  { key: 'count', label: t('inventory.adjustment.count_reference'), value: row.countReference.trim() },
              ]),
    ];
}

/** Why the review waits for an unresolved request of the family (NC-13), with the way to UXP-006. */
function unresolvedReason(): ReactNode {
    return (
        <>
            {t('inventory.opening_entry.disabled_unresolved')} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
}

/** The cost fields of a positive row or a replacement (UXI-294): the total, "Biaya belum diketahui", a zero's evidence. */
function CostFields({ cost, ids, frozen, costError, evidenceError, onChange }: { cost: DraftCost; ids: { cost: string; evidence: string }; frozen: boolean; costError: string | undefined; evidenceError: string | undefined; onChange: (patch: Partial<DraftCost>) => void }) {
    return (
        <>
            {!cost.unknown && (
                <Field id={ids.cost} label={t('costing.field.line_cost')} required={true} hint={t('costing.field.line_cost_help')} error={costError}>
                    <MoneyInput disabled={frozen} value={cost.amount} onValueChange={(amount) => onChange({ amount })} />
                </Field>
            )}
            <div className={'bk-actions'}>
                <FilterChip label={t('costing.field.cost_unknown')} pressed={cost.unknown} onPressedChange={(pressed) => (frozen ? undefined : onChange({ unknown: pressed }))} />
            </div>
            {cost.unknown && <p className={'bk-hint'}>{t('costing.field.cost_unknown_help')}</p>}
            {isZeroCost(cost) && (
                <Field id={ids.evidence} label={t('costing.field.zero_evidence')} required={true} error={evidenceError}>
                    <TextInput type={'text'} autoComplete={'off'} maxLength={EVIDENCE_MAX} disabled={frozen} value={cost.zeroEvidence} onChange={(event) => onChange({ zeroEvidence: event.target.value })} />
                </Field>
            )}
        </>
    );
}

function EntryForm({ title, onboardingVersion, categories, stale }: { title: string; onboardingVersion: number; categories: readonly Category[]; stale: boolean }) {
    const control = useCommand(OPENING_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const announce = useAnnounce();
    const ids = useId();
    const [rows, setRows] = useState<readonly DraftRow[]>([]);
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    const [over, setOver] = useState<{ readonly code: string } | null>(null);
    const [overDirty, setOverDirty] = useState(false);
    // The rows in the order they were sent, which a refusal's paths and products name.
    const [sent, setSent] = useState<readonly DraftRow[]>([]);
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
    // What the rows show is what is sent, while a frozen request is out or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';

    useUnsavedChanges((rows.length > 0 || overDirty) && !committed, t('ux.state.unsaved.body'));
    useScanCapture(searchField, !frozen && over === null);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('inventory.opening_entry.success'));
            router.visit(location);
        }
    }, [location]);

    // The review ends with its answer.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting') {
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

    // A frozen request ends every search still out, so that no answer changes the rows that were sent.
    useEffect(() => {
        if (frozen) {
            supersede();
            setResults((current) => (current.kind === 'searching' || current.kind === 'failed' ? { kind: 'none' } : current));
        }
    }, [frozen]);

    /**
     * Adds an eligible product as a new row, or with a scan one base unit to its row; a product that a new
     * row cannot take is shown with its reason (UXI-292). Nothing while the request is frozen.
     */
    const add = (product: OpeningProduct, scanned: boolean): void => {
        if (frozen) {
            return;
        }

        if (!product.active) {
            setResults({ kind: 'inactive', product });

            return;
        }

        if (product.eligibility !== 'ELIGIBLE') {
            setResults({ kind: 'rows', rows: [product] });

            return;
        }

        setResults({ kind: 'none' });
        setSearch('');
        setChecked(false);
        setReview(false);
        setRows((current) => {
            const known = current.find((row) => row.product.id === product.id);

            if (known === undefined) {
                const key = nextKey.current;

                nextKey.current += 1;

                return [...current, { key, product, quantity: '1', countReference: '', unknown: false, amount: null, zeroEvidence: '' }];
            }

            // This read of the product is the latest: its row takes it, and a scan counts one more.
            return current.map((row) => (row.key === known.key ? { ...row, product, quantity: scanned ? plusOne(row.quantity) : row.quantity } : row));
        });
        announce(t('ux.announce.added', { produk: product.name, jumlah: formatQuantity('1', 0, product.baseUnit) }), false);
        searchField.current?.focus();
    };

    const runSearch = (typed: string, exact: boolean): void => {
        if (frozen) {
            return;
        }

        const mine = supersede();

        setResults({ kind: 'searching' });
        void searchProducts({ context: OPENING_CONTEXT, q: typed, ...(exact ? { mode: 'exact' } : {}) }).then((answer) => {
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

            const found = answer.body.rows.flatMap((row) => {
                const product = openingProductOf(row);

                return product === null ? [] : [product];
            });
            const top = found[0];

            if (exact) {
                if (top === undefined) {
                    setResults({ kind: 'unknown', code: typed });
                    announce(t('ux.announce.unknown_code', { kode: typed }), false);
                } else {
                    add(top, true);
                }

                return;
            }

            setResults({ kind: 'rows', rows: found });
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

    const change = (key: number, patch: Partial<Omit<DraftRow, 'key' | 'product'>>): void => {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
    };

    const remove = (key: number): void => {
        setRows((current) => current.filter((row) => row.key !== key));
        searchField.current?.focus();
    };

    /** "Tambah produk" for an unknown code: UXP-303 over this page, the draft kept (UXI-293, NC-11). */
    const openCreate = (code: string): void => {
        supersede();
        setOver({ code });
    };

    const closeCreate = (): void => {
        setOver(null);
        setOverDirty(false);
        window.setTimeout(() => searchField.current?.focus(), 0);
    };

    /** The new product of UXP-303 joins the draft as a new row, at the version it was created with. */
    const created = (product: { readonly id: number; readonly sku: string }): void => {
        closeCreate();
        setResults({ kind: 'searching' });
        void searchProducts({ context: OPENING_CONTEXT, q: product.sku, mode: 'exact' }).then((answer) => {
            const row = answer.kind === 'read' ? answer.body.rows.find((candidate) => candidate.id === product.id) : undefined;
            const found = row === undefined ? null : openingProductOf(row);

            if (found === null) {
                setResults({ kind: 'failed', text: product.sku, exact: true });
            } else {
                add(found, false);
            }
        });
    };

    // A refusal of products that changed reads them again, so that the rows show them as they are now, and
    // a changed onboarding reads the page again, which leads to UXP-412 once onboarding is no longer open (UXS-17).
    useEffect(() => {
        if (refusal?.code !== STALE_VERSION) {
            return;
        }

        const named = refusedProducts(refusal.lines);

        if (named.length === 0) {
            router.reload({ only: ENTRY_PROPS });
        }

        for (const productId of named) {
            const row = sent.find((candidate) => candidate.product.id === productId);

            if (row === undefined) {
                continue;
            }

            void rereadProduct(productId, row.product).then((fresh) => {
                if (fresh !== null) {
                    setRows((current) => current.map((candidate) => (candidate.product.id === productId ? { ...candidate, product: fresh } : candidate)));
                }
            });
        }
    }, [refusal]);

    const problems = rows.map((row) => {
        const positive = !isZeroCount(row.quantity);

        return {
            eligibility: row.product.eligibility === 'ELIGIBLE' ? null : eligibilityWord(row.product.eligibility),
            quantity: countProblem(row.quantity, row.product.quantityPrecision, false),
            cost: positive ? costProblem(row) : null,
            evidence: positive && isZeroCost(row) && row.zeroEvidence.trim() === '' ? t('ux.validation.zero_evidence') : null,
            countReference: positive && row.countReference.trim() === '' ? t('ux.validation.required') : null,
        };
    });
    const complete =
        rows.length > 0 &&
        rows.length <= MAX_OPENING_ROWS &&
        problems.every((problem) => problem.eligibility === null && problem.quantity === null && problem.cost === null && problem.evidence === null && problem.countReference === null);
    const blockedReason = blockedBy !== null && !busy ? unresolvedReason() : undefined;
    const reviewReason = blockedReason ?? (complete ? undefined : t('inventory.opening_entry.disabled'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        // No search still out may add to the rows once they are sent.
        supersede();
        setSent(rows);
        control.submit(
            {
                // The count as the review showed it; a zero row records a zero count, with no cost (BR-INV-27).
                rows: rows.map((row) =>
                    isZeroCount(row.quantity)
                        ? { productId: row.product.id, quantity: sentQuantity(row.quantity, row.product.quantityPrecision), countReference: null }
                        : { productId: row.product.id, quantity: sentQuantity(row.quantity, row.product.quantityPrecision), countReference: row.countReference.trim(), cost: sentCost(row) },
                ),
            },
            { onboardingVersion, products: rows.map((row) => ({ productId: row.product.id, version: row.product.version })) },
        );
    };

    const fieldId = (key: number, field: string): string => `${ids}-row-${String(key)}-${field}`;
    const summary: FieldError[] = [];
    // A refusal's field of a row, by the row's index in the request that was sent.
    const refused = (key: number, field: string): string | undefined => {
        const index = sent.findIndex((row) => row.key === key);

        return refusal === null || index < 0 ? undefined : refusalMessage(refusal.fields[`payload.rows.${String(index)}.${field}`]);
    };
    const named = refusal === null ? [] : refusedProducts(refusal.lines);
    const namedProducts = sent.filter((row) => named.includes(row.product.id)).map((row) => row.product.name);

    const rowViews = rows.map((row, index) => {
        const problem = problems[index];
        const word = problem?.eligibility ?? null;
        const productError = refused(row.key, 'productId') ?? (word === null ? undefined : t(word));
        const quantityError = refused(row.key, 'quantity') ?? (checked ? (problem?.quantity ?? undefined) : undefined);
        const costError = refused(row.key, 'cost.amount') ?? (checked ? (problem?.cost ?? undefined) : undefined);
        const evidenceError = refused(row.key, 'cost.zeroEvidence') ?? (checked ? (problem?.evidence ?? undefined) : undefined);
        const countReferenceError = refused(row.key, 'countReference') ?? (checked ? (problem?.countReference ?? undefined) : undefined);

        for (const [field, label, message] of [
            ['product', t('inventory.line.product'), productError],
            ['quantity', t('inventory.line.quantity'), quantityError],
            ['cost', t('costing.field.line_cost'), costError],
            ['evidence', t('costing.field.zero_evidence'), evidenceError],
            ['count-reference', t('inventory.adjustment.count_reference'), countReferenceError],
        ] as const) {
            if (invalid && message !== undefined) {
                summary.push({ fieldId: fieldId(row.key, field), label: `${row.product.name}: ${label}`, message });
            }
        }

        const costIds = { cost: fieldId(row.key, 'cost'), evidence: fieldId(row.key, 'evidence') };

        return { row, positive: !isZeroCount(row.quantity), costIds, productError, quantityError, costError, evidenceError, countReferenceError, marked: named.includes(row.product.id) };
    });

    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const refusalBody =
        refusal?.code === 'PRODUCT_INACTIVE'
            ? t('ux.state.inactive.product.body', { produk: namedProducts.join(', ') })
            : refusal?.code === 'POSTING_BLOCKED'
              ? t('ux.state.blocked.body', { produk: namedProducts.join(', ') })
              : undefined;
    const back = { href: ONBOARDING_PAGE, page: t('inventory.onboarding.title'), onNavigate: guard };

    if (over !== null) {
        return (
            <CreateFlow
                title={t('catalog.product_create.title')}
                categories={categories}
                code={over.code}
                onDirty={setOverDirty}
                over={{ page: title, href: OPENING_ENTRY_PAGE, onBack: closeCreate, onCreated: created }}
            />
        );
    }

    const resultsView = (
        <>
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && !frozen && <LoadFailed onRetry={() => runSearch(results.text, results.exact)} />}
            {results.kind === 'unknown' && <UnknownCode code={results.code} action={<Button label={t('catalog.product_create.title')} variant={'secondary'} onClick={() => openCreate(results.code)} />} />}
            {results.kind === 'inactive' && <StateNotice state={INACTIVE_STATE} body={t('ux.state.inactive.product.body', { produk: results.product.name })} />}
            {results.kind === 'rows' && results.rows.length > 0 && (
                <ul className={'bk-stack'}>
                    {results.rows.map((product) => {
                        const word = eligibilityWord(product.eligibility);
                        // The accessible name holds the visible text, in its order: the name, then the SKU, or
                        // "Nonaktif" in its place, or why a new row cannot take it (WCAG 2.5.3).
                        const meta = !product.active ? t('ux.state.inactive.label') : word === null ? product.sku : t(word);

                        if (product.active && product.eligibility === 'REPLACEABLE') {
                            return (
                                <li key={product.id} className={'bk-actions'}>
                                    <span>
                                        <strong>{product.name}</strong> <span className={'bk-meta'}>{meta}</span>
                                    </span>
                                    <ButtonLink href={openingEntryPage(product.id)} label={t('inventory.opening_entry.replace')} variant={'secondary'} onClick={guard} />
                                </li>
                            );
                        }

                        return (
                            <li key={product.id}>
                                <Button
                                    label={product.name}
                                    rowName={meta}
                                    variant={'secondary'}
                                    block={true}
                                    enterActivates={true}
                                    unavailable={frozen}
                                    {...(product.active && word !== null ? { disabledReason: t(word) } : {})}
                                    onClick={() => add(product, false)}
                                >
                                    {' '}
                                    <span className={'bk-meta'}>{meta}</span>
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
            {results.kind === 'rows' && results.rows.length === 0 && <NoResults onClear={() => edited('')} />}
        </>
    );

    return (
        <CommandLayout header={<PageHeader title={title} back={back} />} width={'form'}>
            <Notice tone={'neutral'} urgency={'none'}>
                <p>{t('inventory.opening_entry.limit_note')}</p>
            </Notice>
            {stale && <StateNotice state={STALE_STATE} />}
            {invalid && <ErrorSummary errors={summary} />}
            {refusalState !== undefined && <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} />}
            <Card>
                <CommandForm label={title}>
                    <CodeField label={t('inventory.line.search')} hint={t('inventory.line.search_hint')} disabled={frozen} error={results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined} value={search} onValueChange={edited} onEnter={lookUp} inputRef={searchField} />
                    {resultsView}
                    {rowViews.length > 0 && <h2 className={'bk-sr-only'}>{t('inventory.line.product')}</h2>}
                    {rowViews.length > 0 && (
                        <ul className={'bk-stack'} aria-label={title}>
                            {rowViews.map(({ row, positive, costIds, productError, quantityError, costError, evidenceError, countReferenceError, marked }) => (
                                <li key={row.key}>
                                    <Section title={row.product.name} level={3} card={false}>
                                        <div className={'bk-stack'} data-marked={marked}>
                                            <p className={'bk-meta'}>{row.product.sku}</p>
                                            {productError !== undefined && (
                                                <p id={fieldId(row.key, 'product')} tabIndex={-1} className={'bk-error'}>
                                                    <Icon name={'danger'} size={'sm'} />
                                                    <span>{productError}</span>
                                                </p>
                                            )}
                                            <Field id={fieldId(row.key, 'quantity')} label={t('inventory.line.quantity')} required={true} hint={t('inventory.opening_entry.quantity_help')} error={quantityError}>
                                                <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} suffix={row.product.baseUnit} disabled={frozen} value={row.quantity} onChange={(event) => change(row.key, { quantity: event.target.value })} />
                                            </Field>
                                            {positive && (
                                                <CostFields
                                                    cost={row}
                                                    ids={costIds}
                                                    frozen={frozen}
                                                    costError={costError}
                                                    evidenceError={evidenceError}
                                                    onChange={(patch) => change(row.key, patch)}
                                                />
                                            )}
                                            {positive && (
                                                <Field id={fieldId(row.key, 'count-reference')} label={t('inventory.adjustment.count_reference')} required={true} hint={t('inventory.adjustment.count_reference_help')} error={countReferenceError}>
                                                    <TextInput type={'text'} autoComplete={'off'} maxLength={COUNT_REFERENCE_MAX} disabled={frozen} value={row.countReference} onChange={(event) => change(row.key, { countReference: event.target.value })} />
                                                </Field>
                                            )}
                                            <div className={'bk-actions'}>
                                                <Button label={t('ux.action.remove_line')} rowName={row.product.name} variant={'quiet'} icon={'trash'} unavailable={frozen} onClick={() => remove(row.key)} />
                                            </div>
                                        </div>
                                    </Section>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked || blockedReason !== undefined ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                        <ButtonLink href={ONBOARDING_PAGE} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog
                open={review && (phase.kind === 'idle' || busy || throttled)}
                onOpenChange={setReview}
                title={t('inventory.opening_entry.review_title')}
                confirmLabel={t('inventory.opening_entry.confirm')}
                busy={busy}
                onConfirm={confirm}
            >
                <ul className={'bk-stack'}>
                    {rows.map((row) => (
                        <li key={row.key}>
                            <strong>{row.product.name}</strong>
                            <KeyValueList items={reviewItemsOf(row)} />
                        </li>
                    ))}
                </ul>
                <p>{t('inventory.opening_entry.limit_note')}</p>
            </ReviewDialog>
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('inventory.opening_entry.success') } : {})} />}
        </CommandLayout>
    );
}

/**
 * The replacement entry of one product whose OPENING was reversed (RC-48; UXI-291, UXI-292, UXI-294,
 * UXI-296; BR-INV-48; D-087): its count above zero, its cost, its count reference and the Owner's reason,
 * reviewed and confirmed after recent authentication (UXS-11), with the version of the product and of
 * onboarding as they were read. A changed product or onboarding reads the page again (UXS-17).
 */
function ReplacementForm({ title, onboardingVersion, product, replacesLineId }: { title: string; onboardingVersion: number; product: OpeningProduct; replacesLineId: number }) {
    const control = useCommand(REPLACE_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const ids = useId();
    const [quantity, setQuantity] = useState('');
    const [cost, setCost] = useState<DraftCost>({ unknown: false, amount: null, zeroEvidence: '' });
    const [countReference, setCountReference] = useState('');
    const [reason, setReason] = useState('');
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the fields show is what is sent, while a frozen request is out, waits for the password or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const dirty = quantity.trim() !== '' || cost.unknown || cost.amount !== null || countReference.trim() !== '' || reason.trim() !== '';

    useUnsavedChanges(dirty && !committed, t('ux.state.unsaved.body'));

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('inventory.opening_entry.replace_success'));
            router.visit(location);
        }
    }, [location]);

    // The review ends with its answer, and comes back after a "Batal" of the recent authentication.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting' && phase.kind !== 'recent_auth') {
            setReview(false);
        }
    }, [phase.kind]);

    // A changed product, line or onboarding reads the page again: it shows the product as it is now, or
    // leads to UXP-412 once onboarding is no longer open (UXS-17).
    useEffect(() => {
        if (refusal?.code === STALE_VERSION) {
            router.reload({ only: ENTRY_PROPS });
        }
    }, [refusal]);

    const problems = {
        quantity: countProblem(quantity, product.quantityPrecision, true),
        cost: costProblem(cost),
        evidence: isZeroCost(cost) && cost.zeroEvidence.trim() === '' ? t('ux.validation.zero_evidence') : null,
        countReference: countReference.trim() === '' ? t('ux.validation.required') : null,
        reason: reason.trim() === '' ? t('ux.validation.required') : null,
    };
    const complete = Object.values(problems).every((problem) => problem === null);
    const blockedReason = blockedBy !== null && !busy ? unresolvedReason() : undefined;
    const reviewReason = blockedReason ?? (complete ? undefined : t('ux.disabled.form_incomplete'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        control.submit(
            { replacesLineId, quantity: sentQuantity(quantity, product.quantityPrecision), countReference: countReference.trim(), cost: sentCost(cost), reason: reason.trim() },
            { onboardingVersion, productVersion: product.version },
        );
    };

    const fieldOf = (field: string): string | undefined => (refusal === null ? undefined : refusalMessage(refusal.fields[`payload.${field}`]));
    const shown = (message: string | null): string | undefined => (checked ? (message ?? undefined) : undefined);
    const fieldIds = { line: `${ids}-line`, quantity: `${ids}-quantity`, cost: `${ids}-cost`, evidence: `${ids}-evidence`, countReference: `${ids}-count-reference`, reason: `${ids}-reason` };
    const lineError = fieldOf('replacesLineId');
    const quantityError = fieldOf('quantity') ?? shown(problems.quantity);
    const costError = fieldOf('cost.amount') ?? shown(problems.cost);
    const evidenceError = fieldOf('cost.zeroEvidence') ?? shown(problems.evidence);
    const countReferenceError = fieldOf('countReference') ?? shown(problems.countReference);
    const reasonError = fieldOf('reason') ?? shown(problems.reason);
    const summary: FieldError[] = [];

    for (const [id, label, message] of [
        [fieldIds.line, t('inventory.line.product'), lineError],
        [fieldIds.quantity, t('inventory.line.quantity'), quantityError],
        [fieldIds.cost, t('costing.field.line_cost'), costError],
        [fieldIds.evidence, t('costing.field.zero_evidence'), evidenceError],
        [fieldIds.countReference, t('inventory.adjustment.count_reference'), countReferenceError],
        [fieldIds.reason, t('inventory.opening_entry.replace_reason'), reasonError],
    ] as const) {
        if (invalid && message !== undefined) {
            summary.push({ fieldId: id, label, message });
        }
    }

    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const refusalBody =
        refusal?.code === 'PRODUCT_INACTIVE'
            ? t('ux.state.inactive.product.body', { produk: product.name })
            : refusal?.code === 'POSTING_BLOCKED'
              ? t('ux.state.blocked.body', { produk: product.name })
              : undefined;
    const reviewItems: KeyValue[] = [
        { key: 'product', label: t('inventory.line.product'), value: product.name },
        { key: 'quantity', label: t('inventory.line.quantity'), value: <span className={'bk-num'}>{countText(quantity, product)}</span> },
        { key: 'cost', label: t('costing.field.line_cost'), value: <CostValue cost={cost} /> },
        { key: 'count', label: t('inventory.adjustment.count_reference'), value: countReference.trim() },
        { key: 'reason', label: t('inventory.opening_entry.replace_reason'), value: reason.trim() },
    ];

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: ONBOARDING_PAGE, page: t('inventory.onboarding.title'), onNavigate: guard }} />} width={'form'}>
            {invalid && <ErrorSummary errors={summary} />}
            {refusalState !== undefined && <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} />}
            <Card>
                <CommandForm label={title}>
                    <Section title={product.name} level={2} card={false}>
                        <div className={'bk-stack'}>
                            <p className={'bk-meta'}>{product.sku}</p>
                            <p id={fieldIds.line} tabIndex={-1}>
                                {t('inventory.opening_entry.replace_eligible')}
                            </p>
                            {lineError !== undefined && (
                                <p className={'bk-error'}>
                                    <Icon name={'danger'} size={'sm'} />
                                    <span>{lineError}</span>
                                </p>
                            )}
                        </div>
                    </Section>
                    <Field id={fieldIds.quantity} label={t('inventory.line.quantity')} required={true} error={quantityError}>
                        <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} suffix={product.baseUnit} disabled={frozen} value={quantity} onChange={(event) => setQuantity(event.target.value)} />
                    </Field>
                    <CostFields cost={cost} ids={{ cost: fieldIds.cost, evidence: fieldIds.evidence }} frozen={frozen} costError={costError} evidenceError={evidenceError} onChange={(patch) => setCost((current) => ({ ...current, ...patch }))} />
                    <Field id={fieldIds.countReference} label={t('inventory.adjustment.count_reference')} required={true} hint={t('inventory.adjustment.count_reference_help')} error={countReferenceError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={COUNT_REFERENCE_MAX} disabled={frozen} value={countReference} onChange={(event) => setCountReference(event.target.value)} />
                    </Field>
                    <Field id={fieldIds.reason} label={t('inventory.opening_entry.replace_reason')} required={true} error={reasonError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked || blockedReason !== undefined ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                        <ButtonLink href={ONBOARDING_PAGE} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog
                open={review && (phase.kind === 'idle' || busy || throttled)}
                onOpenChange={setReview}
                title={t('inventory.opening_entry.replace_review_title')}
                confirmLabel={t('inventory.opening_entry.confirm')}
                busy={busy}
                onConfirm={confirm}
            >
                <KeyValueList items={reviewItems} />
            </ReviewDialog>
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('inventory.opening_entry.replace_success') } : {})} />}
        </CommandLayout>
    );
}
