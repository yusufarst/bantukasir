import { router } from '@inertiajs/react';
import { useEffect, useId, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { SegmentedControl, type SegmentedOption } from '@/components/ui/Choices';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand, type CommandControl } from '@/lib/commands/useCommand';
import { compareMoney } from '@/lib/exact';
import { formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import {
    BANK_TRANSFER,
    CASH,
    conditionWord,
    DRAWER_REFUND_COMMAND,
    draftLines,
    draftTotal,
    EMPTY_LINE,
    goodsSummary,
    lineAmount,
    lineProblem,
    lineQuantity,
    OTHER_REFUND_COMMAND,
    OWNER_SOURCE,
    paramText,
    payoutMethodWord,
    positive,
    refundFieldMessage,
    refundsMoney,
    returnsGoods,
    sessionSource,
    sessionWord,
    sourceSession,
    type Condition,
    type FundingSession,
    type LineDraft,
    type Method,
    type RefundLine,
} from '@/lib/sales/refunds';
import { salePage } from '@/lib/sales/sales';

// UXP-203 "Pengembalian uang dan retur" (ROUTE_CONTRACTS RP-203, RC-05, RC-06, RC-50; COPY_DECK sections 7.8,
// 12, 14 and 15; INTERACTION_INVENTORY UXI-122 to UXI-132; INTERACTION_CONTRACTS RC-05, RC-06 and section 2.6;
// NAVIGATION_CONTRACTS UXP-203 and NC-13; UI_STATES section 6; SALES_CASH BR-RFD-02 to BR-RFD-07, BR-RFD-15
// to BR-RFD-17; D-045, D-046, D-068, D-087; RACE-15; TASK-034 scope items 3 and 4). The Owner refunds money,
// takes goods back, or both, against the original Sale, which never changes. Each line of the Sale with
// something left shows what remains refundable and returnable (UXI-122): "Uang dikembalikan", "Jumlah barang
// diretur" and its "Kondisi", chosen explicitly. When money is refunded: "Cara pengembalian uang", Tunai or
// Transfer bank (UXI-123); for Tunai, "Sumber uang" — one OPEN session's drawer with its expected cash, or
// "Dana Pemilik" — exactly one, never a default and never split (UXI-124; BR-RFD-06, BR-RFD-16); a reference
// typed for a transfer or the Owner's own funds, which the server generates for a drawer (UXI-125; D-087);
// and always the mandatory "Alasan" (UXI-126). "Tinjau" (UXI-127; Ctrl+Enter, never Enter) opens the review
// of every line, the total, the method, the source, the goods and the reason, with, for a drawer, the note
// that the cash is taken only after the refund is recorded (UXI-128); "Konfirmasi pengembalian" sends RC-05
// for a drawer and RC-06 otherwise, after the password confirmation (UXS-11). A refusal returns to the form
// with its reason at the field concerned (UXS-35, UXS-36, UXS-30, UXS-27, UXS-28, UXS-05); a drawer refused or
// closed reloads the sessions, and the page never switches the source itself. An uncertain answer freezes the
// request on its panel, which says that no cash may leave the drawer and no new refund be made (UXS-14,
// UXI-130; RACE-15): "Kirim ulang" sends the same request, "Cek status" resolves it. Success leads to RP-204.
// "Batal" (UXI-131) and "Kembali ke Detail penjualan" (UXI-132) lead to UXP-202, asking first once something
// was entered (UXS-45).

const DESTINATION: DestinationId = 'sales';

const SESSIONS_PROP = 'sessions';

const LINES_PROP = 'lines';

/** The money source choice as a full-width list (NAVIGATION_CONTRACTS UXP-203). */
const LIST_LAYOUT = 'list';

/** The longest reason (INTERACTION_CONTRACTS RC-05, RC-06). */
const REASON_MAX = 1000;

/** The longest reference of a payout made (RC-06). */
const REFERENCE_MAX = 100;

/** The states of the refusals that this page shows itself (UI_STATES section 3). */
const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    REFUND_BOUND_EXCEEDED: 'UXS-35-money',
    RETURN_BOUND_EXCEEDED: 'UXS-35-goods',
    DRAWER_CASH_INSUFFICIENT: 'UXS-36',
    SESSION_NOT_OPEN: 'UXS-30-refund',
    ONBOARDING_FREEZE: 'UXS-27',
    POSTING_BLOCKED: 'UXS-28',
    PRODUCT_INACTIVE: 'UXS-32-product',
};

/** The phases in which what the page shows is what is sent, and no field may change (INTERACTION_CONTRACTS section 1.3). */
const FROZEN_PHASES: readonly string[] = ['submitting', 'recent_auth', 'uncertain', 'held', 'reload', 'throttled', 'committed'];

interface RefundProps {
    readonly sale: { readonly id: number; readonly number: string };
    readonly lines: readonly RefundLine[];
    readonly sessions: readonly FundingSession[];
}

export default function Refund(props: RefundProps) {
    const title = t('sales.refund.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <RefundForm title={title} {...props} />
        </AppShell>
    );
}

/**
 * The control whose request the page shows: one that is not idle — a request out, refused or adopted from
 * an unresolved one of its family (NC-13 (b)) — else the one that the chosen source would send.
 */
function activeControl(drawer: CommandControl, other: CommandControl, viaDrawer: boolean): CommandControl {
    if (drawer.phase.kind !== 'idle') {
        return drawer;
    }

    if (other.phase.kind !== 'idle') {
        return other;
    }

    return viaDrawer ? drawer : other;
}

/** A refusal's lines by the Sale line they name, from the payload's lines in the order they were sent. */
function refusedLines(lines: Readonly<Record<string, unknown>>, sent: readonly number[]): ReadonlyMap<number, Readonly<Record<string, unknown>>> {
    const refused = new Map<number, Readonly<Record<string, unknown>>>();

    for (const [index, value] of Object.entries(lines)) {
        if (typeof value !== 'object' || value === null) {
            continue;
        }

        const entry: Readonly<Record<string, unknown>> = Object.fromEntries(Object.entries(value));
        const named = entry.saleLineId;
        const position = /^[0-9]{1,3}$/.test(index) ? sent[parsedIndex(index)] : undefined;
        const saleLine = typeof named === 'number' ? named : position;

        if (saleLine !== undefined) {
            refused.set(saleLine, entry);
        }
    }

    return refused;
}

/** A line index of a refusal: its decimal digits, which are few (at most 200 lines). */
function parsedIndex(index: string): number {
    let value = 0;

    for (const digit of index) {
        value = value * 10 + '0123456789'.indexOf(digit);
    }

    return value;
}

function RefundForm({ title, sale, lines, sessions }: RefundProps & { title: string }) {
    const drawer = useCommand(DRAWER_REFUND_COMMAND);
    const other = useCommand(OTHER_REFUND_COMMAND);
    const guard = useLeaveGuard();
    const ids = useId();
    const [drafts, setDrafts] = useState<Readonly<Record<number, LineDraft>>>({});
    const [method, setMethod] = useState<Method | null>(null);
    const [source, setSource] = useState<string | null>(null);
    const [reference, setReference] = useState('');
    const [reason, setReason] = useState('');
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    const [sent, setSent] = useState<readonly number[]>([]);

    const total = draftTotal(drafts, lines);
    const money = compareMoney(total, '0') > 0;
    const goods = lines.some((line) => returnsGoods(drafts[line.saleLineId] ?? EMPTY_LINE, line));
    const chosenSession = method === CASH ? sourceSession(source, sessions) : null;
    const viaDrawer = money && chosenSession !== null;
    const control = activeControl(drawer, other, viaDrawer);
    const { phase } = control;
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const code = refusal?.code ?? null;
    const busy = phase.kind === 'submitting';
    const throttled = phase.kind === 'throttled';
    const frozen = FROZEN_PHASES.includes(drawer.phase.kind) || FROZEN_PHASES.includes(other.phase.kind);
    const location = phase.kind === 'committed' ? phase.result?.location : undefined;
    const back = salePage(sale.id);
    const trimmedReason = reason.trim();
    const trimmedReference = reference.trim();
    const typed = money || goods || trimmedReason !== '' || method !== null;
    const referenceNeeded = money && (method === BANK_TRANSFER || (method === CASH && source === OWNER_SOURCE));

    useUnsavedChanges(typed && phase.kind !== 'committed', t('ux.state.unsaved.body'));

    // Success leads to the correction recorded, RP-204 (NAVIGATION_CONTRACTS UXP-203), which for a drawer says
    // to take the cash now (D-068); so does a request this page opened on, once its status check finds it
    // committed: its cash is taken only then (RACE-15).
    useEffect(() => {
        if (location !== undefined) {
            router.visit(location);
        }
    }, [location]);

    // The review ends with its answer.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting') {
            setReview(false);
        }
    }, [phase.kind]);

    // A drawer that no longer holds the cash, or a session closed meanwhile: the sessions are read again, and
    // the closed one is no longer chosen; the page never chooses another source itself (BR-RFD-16). A bound
    // refused: the lines are read again with what now remains on them (BR-RFD-03).
    useEffect(() => {
        if (code === 'DRAWER_CASH_INSUFFICIENT' || code === 'SESSION_NOT_OPEN') {
            router.reload({ only: [SESSIONS_PROP] });
        }

        if (code === 'REFUND_BOUND_EXCEEDED' || code === 'RETURN_BOUND_EXCEEDED') {
            router.reload({ only: [LINES_PROP] });
        }

        if (code === 'SESSION_NOT_OPEN') {
            setSource(null);
        }
    }, [code]);

    const change = (line: RefundLine, next: Partial<LineDraft>): void => {
        setDrafts((current) => ({ ...current, [line.saleLineId]: { ...(current[line.saleLineId] ?? EMPTY_LINE), ...next } }));
    };

    // The messages at the fields: after a first "Tinjau", the page's own checks (COPY_DECK section 16.1); a
    // bound or a drawer refused, beside the page's notice of the refusal (UXS-35, UXS-36, UXS-30); and the
    // fields that the server found invalid (UXS-05), which alone the summary lists, as on every command page.
    const invalid = code === 'VALIDATION_FAILED';
    const fields: Readonly<Record<string, unknown>> = invalid && refusal !== null ? refusal.fields : {};
    const linesRefused = refusal === null ? new Map<number, Readonly<Record<string, unknown>>>() : refusedLines(refusal.lines, sent);
    const lineFieldId = (line: RefundLine, field: string): string => `${ids}-${field}-${String(line.saleLineId)}`;
    const lineErrors = lines.map((line) => {
        const draft = drafts[line.saleLineId] ?? EMPTY_LINE;
        const own: { readonly quantity?: string; readonly condition?: string } = checked ? lineProblem(draft, line) : {};
        const refused = linesRefused.get(line.saleLineId);
        const at = sent.indexOf(line.saleLineId);
        const path = `payload.lines.${String(at)}`;
        const server = {
            amount: at < 0 ? undefined : refundFieldMessage(fields[`${path}.refundAmount`]),
            quantity: at < 0 ? undefined : (refundFieldMessage(fields[`${path}.returnedSaleable`]) ?? refundFieldMessage(fields[`${path}.returnedNotSaleable`])),
        };
        const amountBound =
            code === 'REFUND_BOUND_EXCEEDED' && refused !== undefined
                ? t('sales.refund.remaining_amount', { uang: formatMoney(paramText(refused, 'remainingAmount') === '' ? line.remainingAmount : paramText(refused, 'remainingAmount')) })
                : undefined;
        const quantityBound =
            code === 'RETURN_BOUND_EXCEEDED' && refused !== undefined
                ? t('sales.refund.remaining_quantity', { jumlah: formatQuantity(paramText(refused, 'remainingQuantity') === '' ? line.remainingQuantity : paramText(refused, 'remainingQuantity'), line.quantityPrecision, line.unit) })
                : undefined;

        return {
            line,
            fieldIds: { amount: lineFieldId(line, 'amount'), quantity: lineFieldId(line, 'quantity') },
            server,
            shown: { amount: amountBound ?? server.amount, quantity: quantityBound ?? own.quantity ?? server.quantity, condition: own.condition },
        };
    });

    const methodInvalid = refundFieldMessage(fields['payload.payout.method']);
    const sourceInvalid = refundFieldMessage(fields['payload.payout.sessionId']);
    const referenceInvalid = refundFieldMessage(fields['payload.payout.reference']);
    const reasonInvalid = refundFieldMessage(fields['payload.reason']);
    const methodError = (checked && money && method === null ? t('ux.validation.choose_one') : undefined) ?? methodInvalid;
    const sourceRefused =
        code === 'DRAWER_CASH_INSUFFICIENT' && refusal !== null
            ? t('ux.state.drawer_short.body', { laci: paramText(refusal.params, 'laci'), uang: paramText(refusal.params, 'uang') })
            : code === 'SESSION_NOT_OPEN'
              ? t('ux.state.session_closed.refund.body')
              : undefined;
    const sourceError = sourceRefused ?? (checked && money && method === CASH && source === null ? t('ux.validation.choose_one') : undefined) ?? sourceInvalid;
    const referenceError = (checked && referenceNeeded && trimmedReference === '' ? t('ux.validation.required') : undefined) ?? referenceInvalid;
    const reasonError = (checked && trimmedReason === '' ? t('ux.validation.required') : undefined) ?? reasonInvalid;
    const linesValid = lines.every((line) => {
        const problem = lineProblem(drafts[line.saleLineId] ?? EMPTY_LINE, line);

        return problem.quantity === undefined && problem.condition === undefined;
    });
    const valid = (money || goods) && linesValid && !(money && method === null) && !(money && method === CASH && source === null) && !(referenceNeeded && trimmedReference === '') && trimmedReason !== '';
    const reviewReason: ReactNode = valid ? undefined : t('sales.refund.disabled');

    const methodId = `${ids}-method`;
    const sourceId = `${ids}-source`;
    const sourceHintId = `${ids}-source-hint`;
    const referenceId = `${ids}-reference`;
    const reasonId = `${ids}-reason`;
    const summary: FieldError[] = [
        ...lineErrors.flatMap(({ line, fieldIds, server }) => [
            ...(server.amount === undefined ? [] : [{ fieldId: fieldIds.amount, label: line.name, message: server.amount }]),
            ...(server.quantity === undefined ? [] : [{ fieldId: fieldIds.quantity, label: line.name, message: server.quantity }]),
        ]),
        ...(methodInvalid === undefined ? [] : [{ fieldId: methodId, label: t('sales.refund.method'), message: methodInvalid }]),
        ...(sourceInvalid === undefined ? [] : [{ fieldId: sourceId, label: t('sales.refund.source'), message: sourceInvalid }]),
        ...(referenceInvalid === undefined ? [] : [{ fieldId: referenceId, label: t('sales.refund.reference'), message: referenceInvalid }]),
        ...(reasonInvalid === undefined ? [] : [{ fieldId: reasonId, label: t('sales.refund.reason'), message: reasonInvalid }]),
    ];

    const openReview = (): void => {
        setChecked(true);

        if (valid) {
            drawer.startAgain();
            other.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        const payloadLines = draftLines(drafts, lines);
        const base = { saleId: sale.id, lines: payloadLines, reason: trimmedReason };

        setSent(payloadLines.map((line) => line.saleLineId));

        if (viaDrawer && chosenSession !== null) {
            drawer.submit({ ...base, payout: { sessionId: chosenSession.id } });
        } else if (money && method !== null) {
            other.submit({ ...base, payout: { method, reference: trimmedReference } });
        } else {
            other.submit(base);
        }
    };

    const header = <PageHeader title={title} subtitle={sale.number} back={{ href: back, page: t('sales.detail.title'), onNavigate: guard }} />;

    // Nothing remains to refund or to return on any line (BR-RFD-03).
    if (lines.length === 0) {
        return (
            <CommandLayout header={header}>
                <Notice tone={'neutral'} urgency={'none'} actions={<ButtonLink href={back} label={t('ux.action.back_to', { halaman: t('sales.detail.title') })} variant={'secondary'} />}>
                    <p>{t('sales.refund.disabled_fully_refunded')}</p>
                </Notice>
            </CommandLayout>
        );
    }

    const methods: readonly SegmentedOption<Method>[] = [
        { value: CASH, label: t('sales.payment.method_cash'), disabled: frozen },
        { value: BANK_TRANSFER, label: t('sales.payment.method_transfer'), disabled: frozen },
    ];
    const sources: readonly SegmentedOption<string>[] = [
        ...sessions.map((session) => ({ value: sessionSource(session.id), label: sessionWord(session), disabled: frozen })),
        { value: OWNER_SOURCE, label: t('sales.refund.source_owner'), disabled: frozen },
    ];
    const refusalState = code === null ? undefined : REFUSAL_STATES[code];

    return (
        <CommandLayout header={header}>
            <p>{t('sales.refund.intro', { nomor: sale.number })}</p>
            {invalid && <ErrorSummary errors={summary} />}
            {refusal !== null && refusalState !== undefined && <RefusalNotice state={refusalState} refusal={refusal} lines={lines} sent={sent} />}
            <CommandForm label={title}>
                <Section title={t('sales.detail.lines')}>
                    <div className={'bk-stack'}>
                        {lineErrors.map(({ line, fieldIds, shown }) => (
                            <LineFields key={line.saleLineId} line={line} draft={drafts[line.saleLineId] ?? EMPTY_LINE} frozen={frozen} ids={fieldIds} errors={shown} onChange={(next) => change(line, next)} />
                        ))}
                    </div>
                </Section>
                {money && (
                    <Section title={t('sales.refund.method')} titleHidden={true}>
                        <div className={'bk-stack'}>
                            <div id={methodId} tabIndex={-1}>
                                <SegmentedControl legend={t('sales.refund.method')} options={methods} value={method} onValueChange={setMethod} />
                                {methodError !== undefined && <p className={'bk-error'}>{methodError}</p>}
                            </div>
                            {method === CASH && (
                                <div id={sourceId} tabIndex={-1}>
                                    <SegmentedControl legend={t('sales.refund.source')} options={sources} value={source} onValueChange={setSource} layout={LIST_LAYOUT} describedBy={sourceHintId} />
                                    {sourceError !== undefined && <p className={'bk-error'}>{sourceError}</p>}
                                    <p className={'bk-hint'} id={sourceHintId}>
                                        {t('sales.refund.source_help')}
                                    </p>
                                </div>
                            )}
                            {referenceNeeded && (
                                <Field id={referenceId} label={t('sales.refund.reference')} required={true} error={referenceError}>
                                    <TextInput type={'text'} autoComplete={'off'} maxLength={REFERENCE_MAX} disabled={frozen} value={reference} onChange={(event) => setReference(event.target.value)} />
                                </Field>
                            )}
                        </div>
                    </Section>
                )}
                <Field id={reasonId} label={t('sales.refund.reason')} required={true} error={reasonError}>
                    <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
                </Field>
                <div className={'bk-actions'}>
                    <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                    <ButtonLink href={back} label={t('ux.action.cancel')} variant={'secondary'} size={'lg'} onClick={guard} />
                </div>
            </CommandForm>
            <ReviewDialog open={review && (phase.kind === 'idle' || busy || throttled)} onOpenChange={setReview} title={t('sales.refund.review_title')} confirmLabel={t('sales.refund.confirm')} busy={busy} onConfirm={confirm}>
                <ReviewContent drafts={drafts} lines={lines} total={total} method={money ? method : null} session={viaDrawer ? chosenSession : null} owner={money && method === CASH && source === OWNER_SOURCE} reason={trimmedReason} />
            </ReviewDialog>
            {!invalid && <CommandPanels control={control} />}
        </CommandLayout>
    );
}

/**
 * One line of the Sale (UXI-122): its product, what remains on it, "Uang dikembalikan" while money remains,
 * "Jumlah barang diretur" while goods remain, and "Kondisi" for the goods, never chosen by default.
 */
function LineFields({
    line,
    draft,
    frozen,
    ids,
    errors,
    onChange,
}: {
    line: RefundLine;
    draft: LineDraft;
    frozen: boolean;
    /** The ids of the line's controls, which the summary of errors links to (UXS-05). */
    ids: { readonly amount: string; readonly quantity: string };
    errors: { readonly amount: string | undefined; readonly quantity: string | undefined; readonly condition: string | undefined };
    onChange: (next: Partial<LineDraft>) => void;
}) {
    const moneyLeft = compareMoney(line.remainingAmount, '0') > 0;
    const goodsLeft = positive(line.remainingQuantity, line.quantityPrecision);
    const conditions: readonly SegmentedOption<Condition>[] = [
        { value: 'SALEABLE', label: conditionWord('SALEABLE'), disabled: frozen },
        { value: 'NOT_SALEABLE', label: conditionWord('NOT_SALEABLE'), disabled: frozen },
    ];

    return (
        <fieldset className={'bk-stack'}>
            <legend className={'bk-h3'}>{line.name}</legend>
            <p className={'bk-meta'}>{line.sku}</p>
            {moneyLeft && (
                <Field id={ids.amount} label={t('sales.refund.amount')} hint={t('sales.refund.remaining_amount', { uang: formatMoney(line.remainingAmount) })} error={errors.amount}>
                    <MoneyInput value={draft.amount} disabled={frozen} onValueChange={(amount) => onChange({ amount })} />
                </Field>
            )}
            {goodsLeft && (
                <>
                    <Field id={ids.quantity} label={t('sales.refund.return_quantity')} hint={t('sales.refund.remaining_quantity', { jumlah: formatQuantity(line.remainingQuantity, line.quantityPrecision, line.unit) })} error={errors.quantity}>
                        <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} suffix={line.unit} disabled={frozen} value={draft.quantity} onChange={(event) => onChange({ quantity: event.target.value })} />
                    </Field>
                    {returnsGoods(draft, line) && (
                        <div>
                            <SegmentedControl legend={t('sales.refund.condition')} options={conditions} value={draft.condition} onValueChange={(condition) => onChange({ condition })} />
                            {errors.condition !== undefined && <p className={'bk-error'}>{errors.condition}</p>}
                            <p className={'bk-hint'}>{t('sales.refund.condition_help')}</p>
                        </div>
                    )}
                </>
            )}
        </fieldset>
    );
}

/** A refusal that this page shows itself, with its safe values (UXS-35, UXS-36, UXS-30, UXS-27, UXS-28, UXS-32). */
function RefusalNotice({ state, refusal, lines, sent }: { state: StateId; refusal: { readonly code: string; readonly params: Readonly<Record<string, unknown>>; readonly lines: Readonly<Record<string, unknown>> }; lines: readonly RefundLine[]; sent: readonly number[] }) {
    const named = refusedLines(refusal.lines, sent);
    const first = lines.find((line) => named.has(line.saleLineId))?.name ?? '';
    const body = ((): string | undefined => {
        switch (refusal.code) {
            case 'REFUND_BOUND_EXCEEDED':
                return t('ux.state.refund_bound.money.body', { uang: paramText(refusal.params, 'uang') });
            case 'RETURN_BOUND_EXCEEDED':
                return t('ux.state.refund_bound.goods.body', { jumlah: paramText(refusal.params, 'jumlah') });
            case 'DRAWER_CASH_INSUFFICIENT':
                return t('ux.state.drawer_short.body', { laci: paramText(refusal.params, 'laci'), uang: paramText(refusal.params, 'uang') });
            case 'POSTING_BLOCKED':
                return t('ux.state.blocked.body', { produk: first });
            case 'PRODUCT_INACTIVE':
                return t('ux.state.inactive.product.body', { produk: first });
            default:
                return undefined;
        }
    })();

    return <StateNotice state={state} urgency={'alert'} {...(body === undefined ? {} : { body })} />;
}

/**
 * The review of UXI-128: every line with the money it refunds and the goods it returns, the total, the method,
 * the source, the goods and their condition, the reason; for a drawer, that its cash is taken only after the
 * refund is recorded (D-046).
 */
function ReviewContent({
    drafts,
    lines,
    total,
    method,
    session,
    owner,
    reason,
}: {
    drafts: Readonly<Record<number, LineDraft>>;
    lines: readonly RefundLine[];
    total: string;
    method: Method | null;
    session: FundingSession | null;
    owner: boolean;
    reason: string;
}) {
    const items: KeyValue[] = [];

    for (const line of lines) {
        const draft = drafts[line.saleLineId] ?? EMPTY_LINE;
        const quantity = lineQuantity(draft, line);
        const parts = [
            ...(refundsMoney(draft) ? [formatMoney(lineAmount(draft))] : []),
            ...(quantity !== null && returnsGoods(draft, line) && draft.condition !== null ? [`${formatQuantity(quantity, line.quantityPrecision, line.unit)} · ${conditionWord(draft.condition)}`] : []),
        ];

        if (parts.length > 0) {
            items.push({ key: String(line.saleLineId), label: line.name, value: parts.join(' · ') });
        }
    }

    const goods = goodsSummary(drafts, lines);

    return (
        <div className={'bk-stack'}>
            <KeyValueList items={items} />
            <p>{t('sales.refund.review_total', { uang: formatMoney(total) })}</p>
            {method !== null && <p>{t('sales.refund.review_method', { cara: payoutMethodWord(method) })}</p>}
            {session !== null && <p>{t('sales.refund.review_source', { sumber: sessionWord(session) })}</p>}
            {owner && <p>{t('sales.refund.review_source', { sumber: t('sales.refund.source_owner') })}</p>}
            {goods !== '' && <p>{t('sales.refund.review_goods', { ringkasan: goods })}</p>}
            <p>{t('sales.refund.review_reason', { alasan: reason })}</p>
            {session !== null && (
                <Notice tone={'warning'} urgency={'none'}>
                    <p>{t('sales.refund.review_drawer_note')}</p>
                </Notice>
            )}
        </div>
    );
}

