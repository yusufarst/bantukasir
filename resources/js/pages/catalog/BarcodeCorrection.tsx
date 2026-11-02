import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState, type MouseEvent, type RefObject } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, LoadMore, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, LoadFailed, Loading, NoResults, StateNotice, UnknownCode, type FieldError } from '@/components/ui/States';
import { AppShell, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { CodeField, UnresolvedReason, visitInDocument } from '@/lib/catalog/Parts';
import { CATALOG_COMMANDS, catalogFieldMessage, lookUpToken, productPage, PRODUCTS_SHELL, searchProducts, type ProductRow, type TokenLookup } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';

// UXP-305 "Koreksi salah pasang barcode" (ROUTE_CONTRACTS RP-305, RD-05, RD-01, RC-30; COPY_DECK
// section 8.6; INTERACTION_INVENTORY UXI-190 to UXI-198; NAVIGATION_CONTRACTS UXP-305; UI_STATES
// section 6; WF-17; BR-CAT-50, BR-CAT-51; D-051, D-052; TASK-020 scope item 3). The Owner scans or
// types the token, and RD-05 shows where it stands: a token that cannot be corrected is UXS-38,
// one that is not known UXS-33. Only a correctable token offers the correct product, by the
// shared Product Search in the context `correction`. "Tinjau" opens the review, whose final
// action needs recent authentication (UXS-11), and which sends what RD-05 showed as the expected
// mapping. Enter ends a code and runs its lookup only, and never reviews or confirms (UXA-10,
// BR-CAT-32). A mapping that changed meanwhile, or a token no longer correctable, reads the token
// again in place (UXS-17, UXS-38). Success leads to UXP-302 of the correct product with its
// message; leaving with entered data asks first (UXS-45). While a correction is unresolved the
// page shows its panel and its fields stand still (UXS-14, UXS-15).

const STALE_STATE: StateId = 'UXS-17';

const DUPLICATE_STATE: StateId = 'UXS-37-barcode';

const INELIGIBLE_STATE: StateId = 'UXS-38';

const STALE_VERSION = 'STALE_VERSION';

const DUPLICATE_BARCODE = 'DUPLICATE_BARCODE';

const BARCODE_NOT_ELIGIBLE = 'BARCODE_NOT_ELIGIBLE';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const ACTIVE = 'ACTIVE';

/** The context of RD-01 for this page (INTERACTION_CONTRACTS section 3.1). */
const CORRECTION_CONTEXT = 'correction';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

const REASON_MAX = 1000;

interface CorrectedProduct {
    readonly id: number;
    readonly name: string;
}

type Lookup =
    | { readonly kind: 'none' }
    | { readonly kind: 'looking' }
    | { readonly kind: 'found'; readonly answer: TokenLookup }
    /** VALIDATION_FAILED, with the field entries of RT-07. */
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed' };

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly text: string; readonly rows: readonly ProductRow[]; readonly hasMore: boolean; readonly page: number; readonly unknownCode: string | null }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string };

export default function BarcodeCorrection({ product }: { product: CorrectedProduct }) {
    const title = t('catalog.barcode_correction.title');

    return (
        <AppShell title={title} {...PRODUCTS_SHELL}>
            <CorrectionForm title={title} product={product} />
        </AppShell>
    );
}

/**
 * "Produk yang benar" (UXI-191): the shared Product Search, active products only, without the
 * product the token is on, which it cannot move to. Typing searches once the keys pause, and the
 * Enter that ends a code looks it up exactly and chooses its product (BR-CAT-32). A newer read
 * supersedes an older one (TR-PAGE-04). A choice stands in the field; a change of the text ends it.
 */
function ProductPicker({
    id,
    excluded,
    chosen,
    disabled,
    error,
    inputRef,
    onChoose,
}: {
    id: string;
    excluded: number | null;
    chosen: ProductRow | null;
    disabled: boolean;
    error: string | undefined;
    inputRef: RefObject<HTMLInputElement | null>;
    onChoose: (product: ProductRow | null) => void;
}) {
    const [text, setText] = useState(chosen?.name ?? '');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const [loadingMore, setLoadingMore] = useState(false);
    const latest = useRef(0);
    const timer = useRef<number | null>(null);

    const stopTyping = (): void => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }
    };

    useEffect(() => stopTyping, []);

    const search = (typed: string): void => {
        latest.current += 1;
        const mine = latest.current;

        setResults({ kind: 'searching' });
        void searchProducts({ context: CORRECTION_CONTEXT, q: typed }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'read') {
                setResults({ kind: 'rows', text: typed, rows: answer.body.rows, hasMore: answer.body.hasMore, page: 1, unknownCode: null });
            } else if (answer.kind === 'invalid') {
                setResults({ kind: 'invalid', fields: answer.fields });
            } else {
                setResults({ kind: 'failed', text: typed });
            }
        });
    };

    const edited = (next: string): void => {
        stopTyping();
        latest.current += 1;
        setText(next);
        onChoose(null);

        const typed = next.trim();

        if (typed === '') {
            setResults({ kind: 'none' });
        } else {
            timer.current = window.setTimeout(() => {
                timer.current = null;
                search(typed);
            }, TYPING_MS);
        }
    };

    const choose = (product: ProductRow): void => {
        stopTyping();
        latest.current += 1;
        setText(product.name);
        setResults({ kind: 'none' });
        onChoose(product);
        inputRef.current?.focus();
    };

    const lookUp = (): void => {
        const code = text.trim();

        if (code === '' || chosen !== null) {
            return;
        }

        stopTyping();
        latest.current += 1;
        const mine = latest.current;

        setResults({ kind: 'searching' });
        void searchProducts({ context: CORRECTION_CONTEXT, q: code, mode: 'exact' }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'read') {
                const found = answer.body.rows.find((row) => row.id !== excluded);

                if (found === undefined) {
                    setResults({ kind: 'rows', text: code, rows: [], hasMore: false, page: 1, unknownCode: code });
                } else {
                    choose(found);
                }
            } else if (answer.kind === 'invalid') {
                setResults({ kind: 'invalid', fields: answer.fields });
            } else {
                setResults({ kind: 'failed', text: code });
            }
        });
    };

    const loadMore = (current: Extract<Results, { kind: 'rows' }>): void => {
        latest.current += 1;
        const mine = latest.current;

        setLoadingMore(true);
        void searchProducts({ context: CORRECTION_CONTEXT, q: current.text, page: String(current.page + 1) }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            setLoadingMore(false);

            if (answer.kind === 'read') {
                setResults({ ...current, rows: [...current.rows, ...answer.body.rows], hasMore: answer.body.hasMore, page: current.page + 1 });
            } else {
                setResults({ kind: 'failed', text: current.text });
            }
        });
    };

    const clear = (): void => edited('');

    const invalidText = results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined;
    const rows = results.kind === 'rows' ? results.rows.filter((row) => row.id !== excluded) : [];

    return (
        <div className={'bk-stack'}>
            <CodeField id={id} label={t('catalog.barcode_correction.correct_product')} required={true} disabled={disabled} error={error ?? invalidText} hint={chosen?.sku} value={text} onValueChange={edited} onEnter={lookUp} inputRef={inputRef} />
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && <LoadFailed onRetry={() => search(results.text)} />}
            {results.kind === 'rows' && results.unknownCode !== null && <UnknownCode code={results.unknownCode} />}
            {results.kind === 'rows' && results.unknownCode === null && rows.length === 0 && <NoResults onClear={clear} />}
            {rows.length > 0 && (
                <ul className={'bk-stack'} aria-label={t('catalog.barcode_correction.correct_product')}>
                    {rows.map((row) => (
                        <li key={row.id}>
                            <Button label={row.name} rowName={row.sku} variant={'secondary'} block={true} enterActivates={true} onClick={() => choose(row)}>
                                <span className={'bk-meta'}>{row.sku}</span>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            {results.kind === 'rows' && results.hasMore && <LoadMore onLoad={() => loadMore(results)} busy={loadingMore} />}
        </div>
    );
}

function CorrectionForm({ title, product }: { title: string; product: CorrectedProduct }) {
    const control = useCommand(CATALOG_COMMANDS.correctBarcode);
    const { phase, blockedBy } = control;
    const ids = useId();
    const [token, setToken] = useState('');
    const [lookup, setLookup] = useState<Lookup>({ kind: 'none' });
    const [chosen, setChosen] = useState<ProductRow | null>(null);
    const [reason, setReason] = useState('');
    const [review, setReview] = useState(false);
    const latestLookup = useRef(0);
    const tokenField = useRef<HTMLInputElement | null>(null);
    const productField = useRef<HTMLInputElement | null>(null);
    const detail = productPage(product.id);
    const toDetail = (event: MouseEvent<HTMLAnchorElement>): void => visitInDocument(event, detail);

    const found = lookup.kind === 'found' ? lookup.answer : null;
    const mapping = found?.mapping ?? null;
    const target = mapping !== null && chosen !== null && !(mapping.state === ACTIVE && mapping.productId === chosen.id) ? chosen : null;
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const duplicate = refusal?.code === DUPLICATE_BARCODE;
    const ineligible = refusal?.code === BARCODE_NOT_ELIGIBLE;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the fields show is what is sent, while a frozen request is out or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const typed = token !== '' || chosen !== null || reason !== '';

    useUnsavedChanges(typed && !committed);
    useScanCapture(mapping === null ? tokenField : productField, !frozen);

    /** RD-05 for this code; `again` keeps the page in place while it reads the token again after a refusal. */
    const runLookup = (code: string, again = false): void => {
        const text = code.trim();

        if (text === '') {
            return;
        }

        latestLookup.current += 1;
        const mine = latestLookup.current;

        if (!again) {
            setLookup({ kind: 'looking' });
        }

        void lookUpToken(text).then((answer) => {
            // A late answer never replaces a newer one (BR-CAT-30, TR-PAGE-04).
            if (mine !== latestLookup.current) {
                return;
            }

            if (answer.kind === 'read') {
                setLookup({ kind: 'found', answer: answer.body });
            } else if (answer.kind === 'invalid') {
                setLookup({ kind: 'invalid', fields: answer.fields });
            } else {
                setLookup({ kind: 'failed' });
            }
        });
    };

    /** Another code makes the answer of its lookup, and the product chosen for it, out of date. */
    const editToken = (next: string): void => {
        latestLookup.current += 1;
        setToken(next);
        setLookup({ kind: 'none' });
        setChosen(null);
    };

    useEffect(() => {
        if (stale || duplicate || ineligible) {
            runLookup(token, true);
        }
    }, [stale, duplicate, ineligible]);

    // The review ends with its answer, and comes back after a "Batal" of the recent authentication.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting' && phase.kind !== 'recent_auth') {
            setReview(false);
        }
    }, [phase.kind]);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('catalog.barcode_correction.success', { kode: found?.token ?? token.trim(), produk: chosen?.name ?? '' }));
            router.visit(location);
        }
    }, [location]);

    const openReview = (): void => {
        control.startAgain();
        setReview(true);
    };

    const confirm = (): void => {
        if (found !== null && mapping !== null && target !== null) {
            control.submit({ token: found.token, targetProductId: target.id, reason: reason.trim() }, { mapping: { productId: mapping.productId, state: mapping.state } });
        }
    };

    const fieldId = (field: string): string => `${ids}-${field}`;
    const summary: FieldError[] = [];
    const add = (field: string, label: string): string | undefined => {
        const text = refusal === null ? undefined : catalogFieldMessage(refusal.fields[`payload.${field}`]);

        if (invalid && text !== undefined) {
            summary.push({ fieldId: fieldId(field), label, message: text });
        }

        return invalid ? text : undefined;
    };
    const tokenError = add('token', t('catalog.field.barcode'));
    const targetError = add('targetProductId', t('catalog.barcode_correction.correct_product'));
    const reasonError = add('reason', t('catalog.barcode_correction.reason'));

    const complete = mapping !== null && target !== null && reason.trim() !== '';
    const reviewReason = !complete ? t('catalog.barcode_correction.disabled') : blockedBy !== null && !busy ? <UnresolvedReason /> : undefined;
    const reviewBody =
        mapping === null || target === null || found === null
            ? ''
            : mapping.state === ACTIVE
              ? t('catalog.barcode_correction.review_body_move', { kode: found.token, lama: mapping.productName, baru: target.name })
              : t('catalog.barcode_correction.review_body_reattach', { kode: found.token, lama: mapping.productName, baru: target.name });
    const reviewItems: KeyValue[] = [{ key: 'reason', label: t('catalog.barcode_correction.reason'), value: reason.trim() }];

    return (
        <CommandLayout header={<PageHeader title={title} subtitle={t('catalog.product_detail.subtitle', { produk: product.name })} back={{ href: detail, page: t('catalog.product_detail.title'), onNavigate: toDetail }} />}>
            {invalid && <ErrorSummary errors={summary} />}
            {stale && <StateNotice state={STALE_STATE} />}
            {duplicate && <StateNotice state={DUPLICATE_STATE} />}
            {ineligible && mapping !== null && <StateNotice state={INELIGIBLE_STATE} body={catalogFieldMessage(refusal?.fields['payload.token'])} />}
            <Card>
                <CommandForm>
                    <p>{t('catalog.barcode_correction.intro')}</p>
                    <CodeField
                        id={fieldId('token')}
                        label={t('catalog.field.barcode')}
                        required={true}
                        disabled={frozen}
                        error={tokenError ?? (lookup.kind === 'invalid' ? catalogFieldMessage(lookup.fields.token) : undefined)}
                        value={token}
                        onValueChange={editToken}
                        onEnter={() => runLookup(token)}
                        onBlur={() => {
                            if (lookup.kind === 'none') {
                                runLookup(token);
                            }
                        }}
                        inputRef={tokenField}
                    />
                    {lookup.kind === 'looking' && <Loading lines={1} />}
                    {lookup.kind === 'failed' && <LoadFailed onRetry={() => runLookup(token)} />}
                    {found !== null && found.kind === null && <UnknownCode code={found.token} />}
                    {found !== null && found.kind !== null && mapping === null && <StateNotice state={INELIGIBLE_STATE} body={t('ux.state.barcode_ineligible.not_correctable')} />}
                    {mapping !== null && (
                        <>
                            <p className={'bk-meta'} role={'status'}>
                                {mapping.state === ACTIVE ? t('catalog.barcode_correction.current_mapping', { produk: mapping.productName }) : t('catalog.barcode_correction.retired_mapping', { produk: mapping.productName })}
                            </p>
                            <ProductPicker key={found?.token} id={fieldId('targetProductId')} excluded={mapping.state === ACTIVE ? mapping.productId : null} chosen={chosen} disabled={frozen} error={targetError} inputRef={productField} onChoose={setChosen} />
                        </>
                    )}
                    <Field id={fieldId('reason')} label={t('catalog.barcode_correction.reason')} required={true} error={reasonError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={reviewReason} unavailable={frozen && !throttled} onClick={openReview} />
                        <ButtonLink href={detail} label={t('ux.action.cancel')} variant={'secondary'} onClick={toDetail} />
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog open={review && (phase.kind === 'idle' || busy || throttled)} onOpenChange={setReview} title={t('catalog.barcode_correction.review_title')} confirmLabel={t('catalog.barcode_correction.confirm')} busy={busy} onConfirm={confirm}>
                <p>{reviewBody}</p>
                <KeyValueList items={reviewItems} />
            </ReviewDialog>
            {!invalid && <CommandPanels control={control} {...(committed ? { committed: t('catalog.barcode_correction.success', { kode: found?.token ?? token.trim(), produk: chosen?.name ?? '' }) } : {})} />}
        </CommandLayout>
    );
}
