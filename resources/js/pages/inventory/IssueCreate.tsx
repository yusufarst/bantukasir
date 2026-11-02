import { router, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, SelectInput, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, LoadFailed, Loading, NoResults, StateNotice, UnknownCode, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, quantityFromEntry, searchProducts, type ProductRow } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatCount, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { postingBlockedReason } from '@/lib/inventory/Parts';
import { backFrom, draftQuantity, ISSUE_COMMAND, ISSUE_REASONS, issueReasonOf, ISSUES_PAGE, ISSUES_SHELL, issueSteps, MAX_ISSUE_LINES, QUANTITY_DIGITS_MAX, reasonWord, signedQuantity, type IssueReason } from '@/lib/inventory/stock';

// UXP-408 "Catat barang keluar" (ROUTE_CONTRACTS RP-408, RD-01 `issue`, RC-08; COPY_DECK section
// 9.9; INTERACTION_INVENTORY UXI-256 to UXI-264; NAVIGATION_CONTRACTS UXP-408; UI_STATES section 6;
// BR-INV-10, BR-INV-18 to BR-INV-22; TASK-023 scope item 4). Goods that leave stock for a reason
// other than a sale; the draft lives in this page's memory only, never in the browser's storage
// (SEC-CH-05). "Cari atau scan produk" is the shared Product Search in the context `issue`: a scan
// of a known code adds one base unit to the latest line of its product, or a new line; choosing a
// result adds a new line (UXI-256). An unknown code stays in the field (UXS-33); an inactive
// product cannot be chosen (UXS-32). Each line takes its quantity in the product's precision
// (UXI-257); "Alasan", one of the five of BR-INV-19, is mandatory, "Catatan" optional (UXI-258).
// "Tinjau" (Ctrl+Enter, never Enter) opens the review with the stock before, the change and the
// stock after of every line, the lines of one product taken together (BR-INV-07, BR-INV-19); its
// confirmation sends RC-08 with the version of each product as reviewed. A refusal returns to the
// draft with the lines concerned marked — insufficient stock with the stock available, a warning
// never an override (UXS-31, BR-INV-20) — and an uncertain outcome freezes the request (UXS-14).
// Success leads to UXP-409 of the issue. Leaving with a draft asks first (UXS-45). No cost is
// shown or sent: the allocation is computed at commit (ADR-008 section 2).

/** The context of RD-01 for this page (INTERACTION_CONTRACTS section 3.1). */
const ISSUE_CONTEXT = 'issue';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const NOTE_MAX = 1000;

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

/** A product of the draft, as RD-01 in the context `issue` gives it. */
interface DraftProduct {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly version: number;
    readonly active: boolean;
    /** The stock on hand when the product was read, for the review (BR-INV-19); null when the search gave none. */
    readonly onHand: string | null;
}

/** A line of the draft, in page memory only. */
interface DraftLine {
    readonly key: number;
    readonly product: DraftProduct;
    readonly quantity: string;
}

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly rows: readonly DraftProduct[] }
    | { readonly kind: 'unknown'; readonly code: string }
    | { readonly kind: 'inactive'; readonly product: DraftProduct }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string; readonly exact: boolean };

interface IssueCreateProps {
    readonly postingFrozen: boolean;
}

/** The signed-in user that every page shares (TASK-007). */
interface SharedProps {
    readonly auth: { readonly user: { readonly roles: readonly string[] } };
    readonly [key: string]: unknown;
}

export default function IssueCreate(props: IssueCreateProps) {
    const title = t('inventory.issue_new.title');

    return (
        <AppShell title={title} {...ISSUES_SHELL}>
            <IssueForm title={title} {...props} />
        </AppShell>
    );
}

/** A row of RD-01 in the context `issue`, with the version that RC-08 expects; null when it has none. */
function draftProductOf(row: ProductRow): DraftProduct | null {
    const version: unknown = (row as ProductRow & { readonly version?: unknown }).version;

    if (typeof version !== 'number' || !Number.isSafeInteger(version)) {
        return null;
    }

    return { id: row.id, name: row.name, sku: row.sku, baseUnit: row.baseUnit, quantityPrecision: row.quantityPrecision, version, active: row.active, onHand: row.stock?.onHand ?? null };
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

/** The message of a field's first refusal of RC-08, or none for a key this page does not show. */
function refusalMessage(entries: unknown): string | undefined {
    const first: unknown = Array.isArray(entries) ? entries[0] : null;
    const key = typeof first === 'object' && first !== null && 'key' in first ? (first as { readonly key: unknown }).key : null;

    return key === 'ux.validation.lines_min' ? t('ux.validation.lines_min') : catalogFieldMessage(entries);
}

/** A product of a refusal read again in the context `issue`, found by its key among the rows of one search. */
async function foundIn(productId: number, search: Readonly<Record<string, string>>): Promise<DraftProduct | null> {
    const answer = await searchProducts({ context: ISSUE_CONTEXT, ...search });
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

function IssueForm({ title, postingFrozen }: IssueCreateProps & { title: string }) {
    const control = useCommand(ISSUE_COMMAND);
    const { phase, blockedBy } = control;
    // UXS-28 tells staff to tell the Owner, and not the Owner.
    const owner = usePage<SharedProps>().props.auth.user.roles.includes('SUPER_ADMIN');
    const guard = useLeaveGuard();
    const announce = useAnnounce();
    const ids = useId();
    const [lines, setLines] = useState<readonly DraftLine[]>([]);
    const [reason, setReason] = useState<IssueReason | null>(null);
    const [note, setNote] = useState('');
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    // The lines in the order they were sent, which a refusal's paths and products name.
    const [sent, setSent] = useState<readonly DraftLine[]>([]);
    const [back] = useState(() => backFrom(document.referrer, window.location.origin, (path) => path === ISSUES_PAGE, ISSUES_PAGE));
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
    const draftDirty = lines.length > 0 || reason !== null || note.trim() !== '';

    useUnsavedChanges(draftDirty && !committed, t('ux.state.unsaved.body'));
    useScanCapture(searchField, !frozen);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('inventory.issue_new.success'));
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

    /** Adds the product as a new line, or with a scan one base unit to its latest line (UXI-256). */
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
                const key = nextKey.current;

                nextKey.current += 1;

                return [...current, { key, product, quantity: '1' }];
            }

            return current.map((line) => (line.key === latestLine.key ? { ...line, quantity: plusOne(line.quantity) } : line));
        });
        announce(t('ux.announce.added', { produk: product.name, jumlah: formatQuantity('1', 0, product.baseUnit) }), false);
        searchField.current?.focus();
    };

    const runSearch = (typed: string, exact: boolean): void => {
        const mine = supersede();

        setResults({ kind: 'searching' });
        void searchProducts({ context: ISSUE_CONTEXT, q: typed, ...(exact ? { mode: 'exact' } : {}) }).then((answer) => {
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

    const change = (key: number, quantity: string): void => {
        setLines((current) => current.map((line) => (line.key === key ? { ...line, quantity } : line)));
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

    const problems = lines.map((line) => quantityProblem(line.quantity, line.product.quantityPrecision));
    const complete = lines.length > 0 && lines.length <= MAX_ISSUE_LINES && reason !== null && problems.every((problem) => problem === null);
    const blockedReason = postingBlockedReason(postingFrozen, blockedBy !== null && !busy);
    const reviewReason = blockedReason ?? (complete ? undefined : t('inventory.issue_new.disabled'));

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
                reasonCode: reason,
                note: note.trim() === '' ? null : note.trim(),
                lines: lines.map((line) => ({ productId: line.product.id, quantity: quantityFromEntry(line.quantity) })),
            },
            { products: [...products.entries()].map(([productId, version]) => ({ productId, version })) },
        );
    };

    const fieldId = (key: number): string => `${ids}-line-${String(key)}-quantity`;
    const reasonId = `${ids}-reason`;
    const noteId = `${ids}-note`;
    const summary: FieldError[] = [];
    // A refusal's field or line, by the line's index in the request that was sent.
    const sentIndex = (key: number): number => sent.findIndex((line) => line.key === key);
    const refused = (key: number): string | undefined => {
        const index = sentIndex(key);

        return refusal === null || index < 0 ? undefined : refusalMessage(refusal.fields[`payload.lines.${String(index)}.quantity`]);
    };
    const named = refusal === null ? [] : refusedProducts(refusal.lines);
    const namedProducts = sent.filter((line, index) => named.includes(line.product.id) && sent.findIndex((other) => other.product.id === line.product.id) === index).map((line) => line.product.name);

    const lineViews = lines.map((line, index) => {
        const sentAt = sentIndex(line.key);
        const available = refusal?.code === INSUFFICIENT_STOCK && sentAt >= 0 ? availableOf(refusal.lines[String(sentAt)]) : null;
        const quantityError = refused(line.key) ?? (checked ? (problems[index] ?? undefined) : undefined);

        if (invalid && quantityError !== undefined) {
            summary.push({ fieldId: fieldId(line.key), label: `${line.product.name}: ${t('inventory.line.quantity')}`, message: quantityError });
        }

        return { line, quantityError, available, marked: named.includes(line.product.id) };
    });

    const reasonError = (refusal === null ? undefined : refusalMessage(refusal.fields['payload.reasonCode'])) ?? (checked && reason === null ? t('ux.validation.required') : undefined);
    const noteError = refusal === null ? undefined : refusalMessage(refusal.fields['payload.note']);

    for (const [id, label, message] of [
        [reasonId, t('inventory.issues.col_reason'), reasonError],
        [noteId, t('inventory.issue_new.note'), noteError],
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
              ? `${t('ux.state.blocked.body', { produk: namedProducts.join(', ') })} ${owner ? '' : t('ux.state.blocked.staff')}`.trim()
              : undefined;
    const cancel = <ButtonLink href={back} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />;

    // The review, line by line: a line's quantity is formatted only once it is one, so that no edit can end the page (pull request #31, finding P1).
    const steps = issueSteps(lines.map((line) => ({ productId: line.product.id, quantity: draftQuantity(line.quantity, line.product.quantityPrecision), onHand: line.product.onHand })));
    const reviewItems: KeyValue[] = [
        { key: 'reason', label: t('inventory.issues.col_reason'), value: reason === null ? '' : reasonWord(reason) },
        ...(note.trim() === '' ? [] : [{ key: 'note', label: t('inventory.issue_new.note'), value: note.trim() }]),
    ];
    const reviewLines = lines.map((line, index) => {
        const step = steps[index] ?? null;
        const quantity = draftQuantity(line.quantity, line.product.quantityPrecision);
        const unit = (value: string): string => formatQuantity(value, line.product.quantityPrecision, line.product.baseUnit);
        const after = step === null ? '' : step.after === null ? t('ux.state.insufficient_stock.line', { jumlah: unit(step.before) }) : <span className={'bk-num'}>{unit(step.after)}</span>;
        const items: KeyValue[] = [
            { key: 'before', label: t('inventory.line.stock_before'), value: step === null ? '' : <span className={'bk-num'}>{unit(step.before)}</span> },
            { key: 'change', label: t('inventory.line.change'), value: <span className={'bk-num'}>{quantity === null ? line.quantity : signedQuantity(`-${quantity}`, line.product.quantityPrecision, line.product.baseUnit)}</span> },
            { key: 'after', label: t('inventory.line.stock_after'), value: after },
        ];

        return { line, short: step !== null && step.after === null, items };
    });

    const resultsView = (
        <>
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && <LoadFailed onRetry={() => runSearch(results.text, results.exact)} />}
            {results.kind === 'unknown' && <UnknownCode code={results.code} />}
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
        <CommandLayout header={<PageHeader title={title} back={{ href: back, page: t('inventory.issues.title'), onNavigate: guard }} />} width={'form'}>
            <Notice tone={'neutral'} urgency={'none'}>
                <p>{t('inventory.issue_new.intro')}</p>
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
                            {lineViews.map(({ line, quantityError, available, marked }) => (
                                <li key={line.key}>
                                    <Section title={line.product.name} level={3} card={false}>
                                        <div className={'bk-stack'} data-marked={marked}>
                                            <p className={'bk-meta'}>{line.product.sku}</p>
                                            <Field
                                                id={fieldId(line.key)}
                                                label={t('inventory.line.quantity')}
                                                required={true}
                                                error={quantityError ?? (available === null ? undefined : t('ux.state.insufficient_stock.line', { jumlah: formatQuantity(available, line.product.quantityPrecision, line.product.baseUnit) }))}
                                            >
                                                <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} suffix={line.product.baseUnit} disabled={frozen} value={line.quantity} onChange={(event) => change(line.key, event.target.value)} />
                                            </Field>
                                            <div className={'bk-actions'}>
                                                <Button label={t('ux.action.remove_line')} rowName={line.product.name} variant={'quiet'} icon={'trash'} unavailable={frozen} onClick={() => remove(line.key)} />
                                            </div>
                                        </div>
                                    </Section>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Field id={reasonId} label={t('inventory.issues.col_reason')} required={true} error={reasonError}>
                        <SelectInput disabled={frozen} value={reason ?? ''} onChange={(event) => setReason(issueReasonOf(event.target.value))}>
                            <option value={''} />
                            {ISSUE_REASONS.map((entry) => (
                                <option key={entry.reason} value={entry.reason}>
                                    {t(entry.label)}
                                </option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field id={noteId} label={t('inventory.issue_new.note')} optional={true} error={noteError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={NOTE_MAX} disabled={frozen} value={note} onChange={(event) => setNote(event.target.value)} />
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
                title={t('inventory.issue_new.review_title')}
                confirmLabel={t('inventory.issue_new.confirm')}
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
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('inventory.issue_new.success') } : {})} />}
        </CommandLayout>
    );
}
