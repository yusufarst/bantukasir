import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { FilterChip, SegmentedControl } from '@/components/ui/Choices';
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
import { catalogFieldMessage, quantityFromEntry, searchProducts, type ProductRow } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatCost, formatCount, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { postingBlockedReason } from '@/lib/inventory/Parts';
import {
    ADJUSTMENT_COMMAND,
    adjustmentSteps,
    backFrom,
    draftQuantity,
    HISTORY_PAGE,
    HISTORY_SHELL,
    historyAddress,
    MAX_ADJUSTMENT_LINES,
    QUANTITY_DIGITS_MAX,
    reviewedVersions,
    sentQuantity,
    signedQuantity,
    STOCK_PAGE,
    STOCK_SHELL,
    withSnapshot,
    type AdjustmentOpener,
} from '@/lib/inventory/stock';

// UXP-410 "Sesuaikan stok" (ROUTE_CONTRACTS RP-410, RD-01 `adjustment`, RC-09; COPY_DECK section
// 9.11; INTERACTION_INVENTORY UXI-268 to UXI-276; NAVIGATION_CONTRACTS UXP-410; UI_STATES section 6;
// INTERACTION_CONTRACTS section 1.5; BR-INV-10, BR-INV-31 to BR-INV-33; BR-COST-03, BR-COST-04;
// WF-22; TASK-024 scope item 3). The Owner brings recorded stock in line with a physical count. The
// draft lives in this page's memory only, never in the browser's storage (SEC-CH-05); the product
// the address names, from "Sesuaikan stok" on UXP-401 or UXP-402, is its first line. "Cari atau scan
// produk" is the shared Product Search in the context `adjustment`: a scan of a known code adds one
// base unit to the latest line of its product, or a new line; choosing a result adds a new line
// (UXI-268). An unknown code stays in the field (UXS-33); an inactive product cannot be chosen
// (UXS-32). Each line is "Tambah" or "Kurangi" with its quantity in the product's precision; an
// increase also takes its cost — the total for the line — or "Biaya belum diketahui", a zero with
// its evidence or reason (UXI-269; BR-INV-32, BR-COST-04). "Alasan", "Referensi hitung" and
// "Referensi" are all mandatory (UXI-270; BR-INV-31). "Tinjau" (Ctrl+Enter, never Enter) opens the
// review with the stock before, the change and the stock after of every line; its confirmation
// asks for the password first when the recent authentication has lapsed (UXS-11) and sends RC-09
// with the version of each product as reviewed. A refusal returns to the draft with the lines
// concerned marked — insufficient stock with the stock available (UXS-31) — and an uncertain
// outcome freezes the request (UXS-14). Success leads to UXP-402 filtered to the adjusted products.
// "Batal" and "Kembali ke …" lead to the page that opened it, asking first with a draft (UXS-45).

/** The context of RD-01 for this page (INTERACTION_CONTRACTS section 3.1). */
const ADJUSTMENT_CONTEXT = 'adjustment';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const REASON_MAX = 1000;

const REFERENCE_MAX = 100;

const EVIDENCE_MAX = 1000;

/** The largest whole rupiah of a cost (TECHNICAL_RULES section 11.1): twelve digits. */
const MONEY_DIGITS_MAX = 12;

/** The longest text of a search (ROUTE_CONTRACTS RD-01), in characters. */
const SEARCH_MAX = 128;

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';

const INACTIVE_STATE: StateId = 'UXS-32-product';

const FROZEN_STATE: StateId = 'UXS-27';

const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    STALE_VERSION: 'UXS-17',
    ONBOARDING_FREEZE: 'UXS-27',
    POSTING_BLOCKED: 'UXS-28',
    PRODUCT_INACTIVE: 'UXS-32-product',
    INSUFFICIENT_STOCK: 'UXS-31',
};

type Direction = 'INCREASE' | 'DECREASE';

/** A product of the draft, as RD-01 in the context `adjustment` gives it. */
interface DraftProduct {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly version: number;
    readonly active: boolean;
    /** The stock on hand when the product was read, for the review; null when the search gave none. */
    readonly onHand: string | null;
}

/** A line of the draft, in page memory only. */
interface DraftLine {
    readonly key: number;
    readonly product: DraftProduct;
    readonly direction: Direction | null;
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

interface AdjustmentCreateProps {
    readonly postingFrozen: boolean;
    /** The first line's product, as RD-01 in the context `adjustment` gives it, or null. */
    readonly product: ProductRow | null;
    readonly opener: AdjustmentOpener | null;
}

export default function AdjustmentCreate(props: AdjustmentCreateProps) {
    const title = t('inventory.adjustment.title');

    return (
        <AppShell title={title} {...(props.opener === 'history' ? HISTORY_SHELL : STOCK_SHELL)}>
            <AdjustmentForm title={title} {...props} />
        </AppShell>
    );
}

/** A row of RD-01 in the context `adjustment`, with the version that RC-09 expects; null when it has none. */
function draftProductOf(row: ProductRow): DraftProduct | null {
    const version: unknown = (row as ProductRow & { readonly version?: unknown }).version;

    if (typeof version !== 'number' || !Number.isSafeInteger(version)) {
        return null;
    }

    return { id: row.id, name: row.name, sku: row.sku, baseUnit: row.baseUnit, quantityPrecision: row.quantityPrecision, version, active: row.active, onHand: row.stock?.onHand ?? null };
}

/** The way back and "Batal" of UXI-275 and UXI-276: the page that opened this one, with its filters, and its title. */
function backOf(opener: AdjustmentOpener | null, product: number | null): { readonly href: string; readonly page: string } {
    if (opener === 'history') {
        const fallback = product === null ? HISTORY_PAGE : historyAddress({ products: [product], type: null, from: null, to: null });

        return { href: backFrom(document.referrer, window.location.origin, (path) => path === HISTORY_PAGE, fallback), page: t('inventory.history.title') };
    }

    return { href: backFrom(document.referrer, window.location.origin, (path) => path === STOCK_PAGE, STOCK_PAGE), page: t('inventory.stock.title') };
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

function isIncrease(line: DraftLine): boolean {
    return line.direction === 'INCREASE';
}

function isZero(line: DraftLine): boolean {
    return isIncrease(line) && !line.unknown && line.amount !== null && /^0+$/.test(line.amount);
}

/** Why an increase's cost cannot be sent (UXI-269; BR-COST-03, BR-COST-04), or null; a decrease has none. */
function costProblem(line: DraftLine): string | null {
    if (!isIncrease(line) || line.unknown) {
        return null;
    }

    if (line.amount === null) {
        return t('ux.validation.required');
    }

    return line.amount.length > MONEY_DIGITS_MAX ? t('ux.validation.money_max') : null;
}

/** The message of a field's first refusal of RC-09, or none for a key this page does not show. */
function refusalMessage(entries: unknown): string | undefined {
    const first: unknown = Array.isArray(entries) ? entries[0] : null;
    const key = typeof first === 'object' && first !== null && 'key' in first ? (first as { readonly key: unknown }).key : null;

    switch (key) {
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

/** A product of a refusal read again in the context `adjustment`, found by its key among the rows of one search. */
async function foundIn(productId: number, search: Readonly<Record<string, string>>): Promise<DraftProduct | null> {
    const answer = await searchProducts({ context: ADJUSTMENT_CONTEXT, ...search });
    const row = answer.kind === 'read' ? answer.body.rows.find((candidate) => candidate.id === productId) : undefined;

    return row === undefined ? null : draftProductOf(row);
}

/** A product that changed after the review, as it is now: by its SKU as reviewed, else by its name; null when neither finds it. */
async function rereadProduct(productId: number, reviewed: DraftProduct): Promise<DraftProduct | null> {
    const bySku = await foundIn(productId, { q: reviewed.sku, mode: 'exact' });

    return bySku ?? (await foundIn(productId, { q: Array.from(reviewed.name).slice(0, SEARCH_MAX).join('') }));
}

/** The products that a refusal names in its lines (RT-07). */
function refusedProducts(lines: Readonly<Record<string, unknown>>): readonly number[] {
    return Object.values(lines).flatMap((entry) => (typeof entry === 'object' && entry !== null && 'productId' in entry && typeof entry.productId === 'number' ? [entry.productId] : []));
}

/** The stock available that INSUFFICIENT_STOCK gives for a line, or null (INTERACTION_CONTRACTS section 1.6). */
function availableOf(entry: unknown): string | null {
    return typeof entry === 'object' && entry !== null && 'available' in entry && typeof entry.available === 'string' ? entry.available : null;
}

function AdjustmentForm({ title, postingFrozen, product, opener }: AdjustmentCreateProps & { title: string }) {
    const control = useCommand(ADJUSTMENT_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const announce = useAnnounce();
    const ids = useId();
    const first = product === null ? null : draftProductOf(product);
    const [lines, setLines] = useState<readonly DraftLine[]>(() => (first !== null && first.active ? [{ key: 1, product: first, direction: null, quantity: '', unknown: false, amount: null, zeroEvidence: '' }] : []));
    const [reason, setReason] = useState('');
    const [countReference, setCountReference] = useState('');
    const [reference, setReference] = useState('');
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Results>(() => (first !== null && !first.active ? { kind: 'inactive', product: first } : { kind: 'none' }));
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    // The lines in the order they were sent, which a refusal's paths and products name.
    const [sent, setSent] = useState<readonly DraftLine[]>([]);
    const [back] = useState(() => backOf(opener, first?.id ?? null));
    const nextKey = useRef(2);
    const latest = useRef(0);
    const timer = useRef<number | null>(null);
    const searchField = useRef<HTMLInputElement | null>(null);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the lines show is what is sent, while a frozen request is out, waits for the password or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    // The first line that the address gave is the page's start, not a change of the user's.
    const draftDirty = lines.some((line) => line.key !== 1 || line.direction !== null || line.quantity !== '' || line.unknown || line.amount !== null) || reason.trim() !== '' || countReference.trim() !== '' || reference.trim() !== '';

    useUnsavedChanges(draftDirty && !committed, t('ux.state.unsaved.body'));
    useScanCapture(searchField, !frozen);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('inventory.adjustment.success'));
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

    /** Ends every search still out, and its loading or failed state, which nothing would end then; results already shown stay. */
    const endSearches = (): void => {
        supersede();
        setResults((current) => (current.kind === 'searching' || current.kind === 'failed' ? { kind: 'none' } : current));
    };

    // A frozen request ends every search still out, so that no answer changes the lines that were sent.
    useEffect(() => {
        if (frozen) {
            endSearches();
        }
    }, [frozen]);

    /** Adds the product as a new line, or with a scan one base unit to its latest line (UXI-268); nothing while the request is frozen. */
    const add = (chosen: DraftProduct, scanned: boolean): void => {
        if (frozen) {
            return;
        }

        if (!chosen.active) {
            setResults({ kind: 'inactive', product: chosen });

            return;
        }

        setResults({ kind: 'none' });
        setSearch('');
        setChecked(false);
        // The draft changed: a review shown before it is reviewed again.
        setReview(false);
        setLines((before) => {
            // This read of the product is the latest: every line of it takes it (pull request #34's review).
            const current = withSnapshot(before, chosen);
            const latestLine = scanned ? [...current].reverse().find((line) => line.product.id === chosen.id) : undefined;

            if (latestLine === undefined) {
                const key = nextKey.current;

                nextKey.current += 1;

                return [...current, { key, product: chosen, direction: null, quantity: '1', unknown: false, amount: null, zeroEvidence: '' }];
            }

            return current.map((line) => (line.key === latestLine.key ? { ...line, quantity: plusOne(line.quantity) } : line));
        });
        announce(t('ux.announce.added', { produk: chosen.name, jumlah: formatQuantity('1', 0, chosen.baseUnit) }), false);
        searchField.current?.focus();
    };

    const runSearch = (typed: string, exact: boolean): void => {
        if (frozen) {
            return;
        }

        const mine = supersede();

        setResults({ kind: 'searching' });
        void searchProducts({ context: ADJUSTMENT_CONTEXT, q: typed, ...(exact ? { mode: 'exact' } : {}) }).then((answer) => {
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
                const found = draftProductOf(row);

                return found === null ? [] : [found];
            });
            const top = rows[0];

            if (exact) {
                if (top === undefined) {
                    setResults({ kind: 'unknown', code: typed });
                    announce(t('ux.announce.unknown_code', { kode: typed }), false);
                } else {
                    add(top, true);
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

    const change = (key: number, patch: Partial<Omit<DraftLine, 'key' | 'product'>>): void => {
        setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)));
    };

    const remove = (key: number): void => {
        setLines((current) => current.filter((line) => line.key !== key));
        searchField.current?.focus();
    };

    // A refusal of products that changed, or whose stock was short, reads them again, so that the review shows them as they are now (UXS-17, UXS-31).
    useEffect(() => {
        if (refusal === null || (refusal.code !== 'STALE_VERSION' && refusal.code !== INSUFFICIENT_STOCK)) {
            return;
        }

        for (const productId of new Set(refusedProducts(refusal.lines))) {
            const line = sent.find((candidate) => candidate.product.id === productId);

            if (line === undefined) {
                continue;
            }

            void rereadProduct(productId, line.product).then((fresh) => {
                if (fresh !== null) {
                    setLines((current) => current.map((candidate) => (candidate.product.id === productId ? { ...candidate, product: fresh } : candidate)));
                }
            });
        }
    }, [refusal]);

    const problems = lines.map((line) => ({
        direction: line.direction === null ? t('ux.validation.choose_one') : null,
        quantity: quantityProblem(line.quantity, line.product.quantityPrecision),
        cost: costProblem(line),
        evidence: isZero(line) && line.zeroEvidence.trim() === '' ? t('ux.validation.zero_evidence') : null,
    }));
    const fieldsComplete = reason.trim() !== '' && countReference.trim() !== '' && reference.trim() !== '';
    const complete = lines.length > 0 && lines.length <= MAX_ADJUSTMENT_LINES && fieldsComplete && problems.every((problem) => problem.direction === null && problem.quantity === null && problem.cost === null && problem.evidence === null);
    const blockedReason = postingBlockedReason(postingFrozen, blockedBy !== null && !busy);
    const reviewReason = blockedReason ?? (complete ? undefined : t('inventory.adjustment.disabled'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        const products = reviewedVersions(lines);

        // Lines of one product with different versions are never sent: the draft is reviewed again.
        if (products === null) {
            setReview(false);

            return;
        }

        // No search still out may add to the lines once they are sent.
        endSearches();
        setSent(lines);
        control.submit(
            {
                reason: reason.trim(),
                countReference: countReference.trim(),
                reference: reference.trim(),
                // The quantity as the review showed it.
                lines: lines.map((line) => ({
                    productId: line.product.id,
                    direction: line.direction,
                    quantity: sentQuantity(line.quantity, line.product.quantityPrecision),
                    ...(isIncrease(line) ? { cost: line.unknown ? { status: 'UNKNOWN' } : { status: 'KNOWN', amount: line.amount, ...(isZero(line) ? { zeroEvidence: line.zeroEvidence.trim() } : {}) } } : {}),
                })),
            },
            { products },
        );
    };

    const fieldId = (key: number, field: string): string => `${ids}-line-${String(key)}-${field}`;
    const summary: FieldError[] = [];
    // A refusal's field of a line, by the line's index in the request that was sent.
    const sentIndex = (key: number): number => sent.findIndex((line) => line.key === key);
    const refused = (key: number, field: string): string | undefined => {
        const index = sentIndex(key);

        return refusal === null || index < 0 ? undefined : refusalMessage(refusal.fields[`payload.lines.${String(index)}.${field}`]);
    };
    const named = refusal === null ? [] : refusedProducts(refusal.lines);
    const namedProducts = sent.filter((line, index) => named.includes(line.product.id) && sent.findIndex((other) => other.product.id === line.product.id) === index).map((line) => line.product.name);

    const lineViews = lines.map((line, index) => {
        const problem = problems[index];
        const sentAt = sentIndex(line.key);
        const available = refusal?.code === INSUFFICIENT_STOCK && sentAt >= 0 ? availableOf(refusal.lines[String(sentAt)]) : null;
        const directionError = refused(line.key, 'direction') ?? (checked ? (problem?.direction ?? undefined) : undefined);
        const quantityError = refused(line.key, 'quantity') ?? (checked ? (problem?.quantity ?? undefined) : undefined);
        const costError = refused(line.key, 'cost.amount') ?? (checked ? (problem?.cost ?? undefined) : undefined);
        const evidenceError = refused(line.key, 'cost.zeroEvidence') ?? (checked ? (problem?.evidence ?? undefined) : undefined);

        for (const [field, label, message] of [
            ['direction', t('inventory.line.change'), directionError],
            ['quantity', t('inventory.line.quantity'), quantityError],
            ['cost', t('costing.field.line_cost'), costError],
            ['evidence', t('costing.field.zero_evidence'), evidenceError],
        ] as const) {
            if (invalid && message !== undefined) {
                summary.push({ fieldId: fieldId(line.key, field), label: `${line.product.name}: ${label}`, message });
            }
        }

        return { line, directionError, quantityError, costError, evidenceError, available, marked: named.includes(line.product.id) };
    });

    const reasonId = `${ids}-reason`;
    const countReferenceId = `${ids}-count-reference`;
    const referenceId = `${ids}-reference`;
    const required = (value: string): string | undefined => (checked && value.trim() === '' ? t('ux.validation.required') : undefined);
    const reasonError = (refusal === null ? undefined : refusalMessage(refusal.fields['payload.reason'])) ?? required(reason);
    const countReferenceError = (refusal === null ? undefined : refusalMessage(refusal.fields['payload.countReference'])) ?? required(countReference);
    const referenceError = (refusal === null ? undefined : refusalMessage(refusal.fields['payload.reference'])) ?? required(reference);

    for (const [id, label, message] of [
        [reasonId, t('inventory.adjustment.reason'), reasonError],
        [countReferenceId, t('inventory.adjustment.count_reference'), countReferenceError],
        [referenceId, t('inventory.adjustment.reference'), referenceError],
    ] as const) {
        if (invalid && message !== undefined) {
            summary.push({ fieldId: id, label, message });
        }
    }

    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const refusalBody =
        refusal?.code === 'PRODUCT_INACTIVE'
            ? t('ux.state.inactive.product.body', { produk: namedProducts.join(', ') })
            : refusal?.code === 'POSTING_BLOCKED'
              ? t('ux.state.blocked.body', { produk: namedProducts.join(', ') })
              : undefined;
    const cancel = <ButtonLink href={back.href} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />;
    const directions = [
        { value: 'INCREASE' as const, label: t('inventory.adjustment.add'), disabled: frozen },
        { value: 'DECREASE' as const, label: t('inventory.adjustment.subtract'), disabled: frozen },
    ];

    // The review, line by line: a line's quantity is formatted only once it is one, so that no edit can end the page.
    const steps = adjustmentSteps(lines.map((line) => ({ productId: line.product.id, increase: isIncrease(line), quantity: draftQuantity(line.quantity, line.product.quantityPrecision), onHand: line.product.onHand })));
    const reviewItems: KeyValue[] = [
        { key: 'reason', label: t('inventory.adjustment.reason'), value: reason.trim() },
        { key: 'count', label: t('inventory.adjustment.count_reference'), value: countReference.trim() },
        { key: 'reference', label: t('inventory.adjustment.reference'), value: reference.trim() },
    ];
    const reviewLines = lines.map((line, index) => {
        const step = steps[index] ?? null;
        const quantity = draftQuantity(line.quantity, line.product.quantityPrecision);
        const unit = (value: string): string => formatQuantity(value, line.product.quantityPrecision, line.product.baseUnit);
        const after = step === null ? '' : step.after === null ? t('ux.state.insufficient_stock.line', { jumlah: unit(step.before) }) : <span className={'bk-num'}>{unit(step.after)}</span>;
        const items: KeyValue[] = [
            { key: 'before', label: t('inventory.line.stock_before'), value: step === null ? '' : <span className={'bk-num'}>{unit(step.before)}</span> },
            { key: 'change', label: t('inventory.line.change'), value: <span className={'bk-num'}>{quantity === null ? line.quantity : signedQuantity(isIncrease(line) ? quantity : `-${quantity}`, line.product.quantityPrecision, line.product.baseUnit)}</span> },
            { key: 'after', label: t('inventory.line.stock_after'), value: after },
            ...(isIncrease(line)
                ? [{ key: 'cost', label: t('costing.field.line_cost'), value: line.unknown || line.amount === null ? <StateMark kind={'cost_unknown'} /> : <span className={'bk-num'}>{formatCost(line.amount)}</span> }]
                : []),
        ];

        return { line, short: step !== null && step.after === null, items };
    });

    const resultsView = (
        <>
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && !frozen && <LoadFailed onRetry={() => runSearch(results.text, results.exact)} />}
            {results.kind === 'unknown' && <UnknownCode code={results.code} />}
            {results.kind === 'inactive' && <StateNotice state={INACTIVE_STATE} body={t('ux.state.inactive.product.body', { produk: results.product.name })} />}
            {results.kind === 'rows' && results.rows.length > 0 && (
                <ul className={'bk-stack'}>
                    {results.rows.map((row) => {
                        // The accessible name holds the visible text, in its order and with its
                        // words apart: the name, then the SKU, or "Nonaktif" in its place (WCAG 2.5.3).
                        const meta = row.active ? row.sku : t('ux.state.inactive.label');

                        return (
                            <li key={row.id}>
                                <Button label={row.name} rowName={meta} variant={'secondary'} block={true} enterActivates={true} unavailable={frozen} onClick={() => add(row, false)}>
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
        <CommandLayout header={<PageHeader title={title} back={{ href: back.href, page: back.page, onNavigate: guard }} />} width={'form'}>
            <Notice tone={'neutral'} urgency={'none'}>
                <p>{t('inventory.adjustment.intro')}</p>
            </Notice>
            {postingFrozen && <StateNotice state={FROZEN_STATE} />}
            {invalid && <ErrorSummary errors={summary} />}
            {refusalState !== undefined && <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} />}
            <Card>
                <CommandForm label={title}>
                    <CodeField label={t('inventory.line.search')} hint={t('inventory.line.search_hint')} disabled={frozen} error={results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined} value={search} onValueChange={edited} onEnter={lookUp} inputRef={searchField} />
                    {resultsView}
                    {lineViews.length > 0 && <h2 className={'bk-sr-only'}>{t('inventory.line.product')}</h2>}
                    {lineViews.length > 0 && (
                        <ul className={'bk-stack'} aria-label={title}>
                            {lineViews.map(({ line, directionError, quantityError, costError, evidenceError, available, marked }) => (
                                <li key={line.key}>
                                    <Section title={line.product.name} level={3} card={false}>
                                        <div className={'bk-stack'} data-marked={marked}>
                                            <p className={'bk-meta'}>{line.product.sku}</p>
                                            <div id={fieldId(line.key, 'direction')} tabIndex={-1} data-invalid={directionError !== undefined}>
                                                <SegmentedControl legend={t('inventory.line.change')} options={directions} value={line.direction} onValueChange={(direction) => (frozen ? undefined : change(line.key, { direction }))} />
                                                {directionError !== undefined && (
                                                    <p className={'bk-error'}>
                                                        <Icon name={'danger'} size={'sm'} />
                                                        <span>{directionError}</span>
                                                    </p>
                                                )}
                                            </div>
                                            <Field
                                                id={fieldId(line.key, 'quantity')}
                                                label={t('inventory.line.quantity')}
                                                required={true}
                                                error={quantityError ?? (available === null ? undefined : t('ux.state.insufficient_stock.line', { jumlah: formatQuantity(available, line.product.quantityPrecision, line.product.baseUnit) }))}
                                            >
                                                <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} suffix={line.product.baseUnit} disabled={frozen} value={line.quantity} onChange={(event) => change(line.key, { quantity: event.target.value })} />
                                            </Field>
                                            {isIncrease(line) && !line.unknown && (
                                                <Field id={fieldId(line.key, 'cost')} label={t('costing.field.line_cost')} required={true} hint={t('costing.field.line_cost_help')} error={costError}>
                                                    <MoneyInput disabled={frozen} value={line.amount} onValueChange={(amount) => change(line.key, { amount })} />
                                                </Field>
                                            )}
                                            {isIncrease(line) && (
                                                <div className={'bk-actions'}>
                                                    <FilterChip label={t('costing.field.cost_unknown')} pressed={line.unknown} onPressedChange={(pressed) => (frozen ? undefined : change(line.key, { unknown: pressed }))} />
                                                </div>
                                            )}
                                            {isIncrease(line) && line.unknown && <p className={'bk-hint'}>{t('costing.field.cost_unknown_help')}</p>}
                                            {isZero(line) && (
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
                    <Field id={reasonId} label={t('inventory.adjustment.reason')} required={true} error={reasonError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                    <Field id={countReferenceId} label={t('inventory.adjustment.count_reference')} required={true} hint={t('inventory.adjustment.count_reference_help')} error={countReferenceError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REFERENCE_MAX} disabled={frozen} value={countReference} onChange={(event) => setCountReference(event.target.value)} />
                    </Field>
                    <Field id={referenceId} label={t('inventory.adjustment.reference')} required={true} hint={t('inventory.adjustment.reference_help')} error={referenceError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REFERENCE_MAX} disabled={frozen} value={reference} onChange={(event) => setReference(event.target.value)} />
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
                title={t('inventory.adjustment.review_title')}
                confirmLabel={t('inventory.adjustment.confirm')}
                busy={busy}
                onConfirm={confirm}
            >
                <KeyValueList items={reviewItems} />
                <ul className={'bk-stack'}>
                    {reviewLines.map(({ line, short, items }) => (
                        <li key={line.key} data-short={short}>
                            <strong>{line.product.name}</strong>
                            <KeyValueList items={items} />
                        </li>
                    ))}
                </ul>
            </ReviewDialog>
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('inventory.adjustment.success') } : {})} />}
        </CommandLayout>
    );
}
