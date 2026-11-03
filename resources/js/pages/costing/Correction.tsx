import { router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateMark, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { catalogFieldMessage, PRODUCTS_SHELL } from '@/lib/catalog/products';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import {
    COST_CORRECTION_COMMAND,
    correctionPayload,
    correctionTitle,
    costHistoryPage,
    isZeroAmount,
    newCostProblem,
    WORKLIST_PAGE,
    WORKLIST_SHELL,
    type CorrectionEntry,
    type CorrectionOpener,
    type CostValue,
} from '@/lib/costing/costs';
import { CorrectionHistory, unresolvedCorrectionReason } from '@/lib/costing/Parts';
import { formatCost, formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { backFrom, HISTORY_SHELL, movementPage, openerOf, RECEIVINGS_PAGE, RECEIVINGS_SHELL, typeWord, type MovementType } from '@/lib/inventory/stock';

// UXP-504 "Lengkapi biaya" or "Koreksi biaya" (ROUTE_CONTRACTS RP-504, RC-15; COPY_DECK section 10.5;
// INTERACTION_INVENTORY UXI-317 to UXI-323; NAVIGATION_CONTRACTS UXP-504; UI_STATES section 6;
// INTERACTION_CONTRACTS section 1.5; BR-COST-02 to BR-COST-04, BR-COST-20 to BR-COST-27; D-034, D-063;
// WF-26; TASK-025 scope item 6). The Owner completes an UNKNOWN cost or corrects a known one: the cost
// source with its value now and its history above the draft — "Biaya baru", the total for the
// source's quantity, an explicit zero with its evidence or reason, and "Alasan" — which lives in this
// page's memory only, never in the browser's storage (NC-10). "Tinjau" (Ctrl+Enter, never Enter)
// opens the review, the old value and the new one; its confirmation asks for the password first
// when the recent authentication has lapsed (UXS-11) and sends RC-15 with the number of the latest
// correction as reviewed. STALE_VERSION shows the source as it is now (UXS-17); REPLAY_REJECTED
// says that nothing changed and that reporting is marked incomplete (UXS-40); an uncertain outcome
// freezes the request (UXS-14), SERVICE_BUSY included (D-063). Success leads back to the page that
// opened it with "Biaya disimpan. Laporan sudah dihitung ulang."; "Batal" and "Kembali ke …" lead
// there too, asking first with a draft (UXS-45).

const REASON_MAX = 1000;

const EVIDENCE_MAX = 1000;

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    STALE_VERSION: 'UXS-17',
    POSTING_BLOCKED: 'UXS-28',
    REPLAY_REJECTED: 'UXS-40-cost',
};

interface Source {
    readonly evidenceId: number;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    readonly movementId: number;
    readonly sourceType: MovementType;
    readonly recordedAt: string;
    readonly quantity: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly cost: CostValue;
    readonly zeroNote: string | null;
    readonly confirmedCost: CostValue;
    readonly correctionNo: number;
    readonly marked: boolean;
}

interface CorrectionProps {
    readonly source: Source;
    readonly corrections: readonly CorrectionEntry[];
    readonly opener: CorrectionOpener | null;
}

export default function Correction(props: CorrectionProps) {
    const title = t(correctionTitle(props.source.cost));
    const shell = props.opener === 'receiving' ? RECEIVINGS_SHELL : props.opener === 'movement' ? HISTORY_SHELL : props.opener === 'history' ? PRODUCTS_SHELL : WORKLIST_SHELL;

    return (
        <AppShell title={title} {...shell}>
            <CorrectionForm title={title} {...props} />
        </AppShell>
    );
}

/** The page that opened this one, with its filters, and its title: the way back, "Batal" and the success's destination. */
function backOf(opener: CorrectionOpener | null, source: Source): { readonly href: string; readonly page: string } {
    switch (opener) {
        case 'movement':
            return { href: movementPage(source.movementId), page: t('inventory.movement.title') };
        case 'receiving': {
            const path = openerOf(document.referrer, window.location.origin)?.pathname ?? '';

            return /^\/stock\/receivings\/[0-9]+$/.test(path) ? { href: path, page: t('inventory.receipt_detail.title') } : { href: RECEIVINGS_PAGE, page: t('inventory.receipts.title') };
        }
        case 'history': {
            const page = `/products/${String(source.product.id)}/cost-history`;

            return { href: backFrom(document.referrer, window.location.origin, (path) => path === page, costHistoryPage(source.product.id, 'product')), page: t('costing.history.title') };
        }
        default:
            return { href: backFrom(document.referrer, window.location.origin, (path) => path === WORKLIST_PAGE, WORKLIST_PAGE), page: t('costing.worklist.title') };
    }
}

/** A value as the review and the current value show it: an amount, or the words of UXS-24, never Rp0 (BR-COST-03). */
function valueText(cost: CostValue): string {
    return cost.amount === null ? t('costing.status.unknown') : formatCost(cost.amount);
}

function CorrectionForm({ title, source, corrections, opener }: CorrectionProps & { title: string }) {
    const control = useCommand(COST_CORRECTION_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const ids = useId();
    const [amount, setAmount] = useState<string | null>(null);
    const [zeroEvidence, setZeroEvidence] = useState('');
    const [reason, setReason] = useState('');
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    const [back] = useState(() => backOf(opener, source));

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the fields show is what is sent, while a frozen request is out, waits for the password or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const zero = isZeroAmount(amount);
    const draftDirty = amount !== null || zeroEvidence.trim() !== '' || reason.trim() !== '';

    useUnsavedChanges(draftDirty && !committed, t('ux.state.unsaved.body'));

    // Success leads back to the page that opened this one, else to the cost history of the product (UI_STATES section 4).
    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('costing.correction.success'));
            router.visit(opener === null ? location : back.href);
        }
    }, [location]);

    // The review ends with its answer, and comes back after a "Batal" of the recent authentication.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting' && phase.kind !== 'recent_auth') {
            setReview(false);
        }
    }, [phase.kind]);

    // UXS-17: the source as it is now, with the number of its latest correction, which the next request expects.
    useEffect(() => {
        if (refusal?.code === 'STALE_VERSION') {
            router.reload({ only: ['source', 'corrections'] });
        }
    }, [refusal]);

    const amountProblem = newCostProblem(amount);
    const evidenceProblem = zero && zeroEvidence.trim() === '' ? 'ux.validation.zero_evidence' : null;
    const complete = amountProblem === null && evidenceProblem === null && reason.trim() !== '';
    const unresolved = blockedBy !== null && !busy ? unresolvedCorrectionReason() : undefined;
    const reviewReason = unresolved ?? (complete ? undefined : t('costing.correction.disabled'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        if (amount === null) {
            setReview(false);

            return;
        }

        control.submit(correctionPayload(source.evidenceId, amount, zeroEvidence, reason), { correctionNo: source.correctionNo });
    };

    const amountId = `${ids}-amount`;
    const evidenceId = `${ids}-evidence`;
    const reasonId = `${ids}-reason`;
    const refused = (path: string): string | undefined => (refusal === null ? undefined : catalogFieldMessage(refusal.fields[path]));
    const amountError = refused('payload.newCost.amount') ?? (checked && amountProblem !== null ? t(amountProblem) : undefined);
    const evidenceError = refused('payload.newCost.zeroEvidence') ?? (checked && evidenceProblem !== null ? t(evidenceProblem) : undefined);
    const reasonError = refused('payload.reason') ?? (checked && reason.trim() === '' ? t('ux.validation.required') : undefined);
    const summary: FieldError[] = [];

    for (const [id, label, message] of [
        [amountId, t('costing.correction.new_cost'), amountError],
        [evidenceId, t('costing.field.zero_evidence'), evidenceError],
        [reasonId, t('costing.correction.reason'), reasonError],
    ] as const) {
        if (invalid && message !== undefined) {
            summary.push({ fieldId: id, label, message });
        }
    }

    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const refusalBody = refusal?.code === 'POSTING_BLOCKED' ? t('ux.state.blocked.body', { produk: source.product.name }) : undefined;
    const sourceItems: KeyValue[] = [
        { key: 'product', label: t('inventory.line.product'), value: source.product.name },
        { key: 'source', label: t('costing.worklist.col_source'), value: t('costing.worklist.source_entry', { jenis: typeWord(source.sourceType), waktu: formatDateTime(source.recordedAt) }) },
        { key: 'quantity', label: t('inventory.line.quantity'), value: <span className={'bk-num'}>{formatQuantity(source.quantity, source.quantityPrecision, source.baseUnit)}</span> },
        ...(source.zeroNote === null ? [] : [{ key: 'zero', label: t('costing.field.zero_evidence'), value: source.zeroNote }]),
    ];
    const newText = amount === null ? '' : formatCost(amount);

    return (
        <CommandLayout header={<PageHeader title={title} subtitle={t('catalog.product_detail.subtitle', { produk: source.product.name })} back={{ href: back.href, page: back.page, onNavigate: guard }} />} width={'form'}>
            {invalid && <ErrorSummary errors={summary} />}
            {refusalState !== undefined && <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} />}
            <Section title={t('costing.correction.current', { biaya: valueText(source.cost) })}>
                <div className={'bk-stack'}>
                    {source.cost.amount === null && <StateMark kind={'cost_unknown'} />}
                    <KeyValueList items={sourceItems} />
                    <CorrectionHistory title={t('costing.correction.history')} entries={corrections} />
                </div>
            </Section>
            <Card>
                <CommandForm label={title}>
                    <Field id={amountId} label={t('costing.correction.new_cost')} required={true} hint={t('costing.correction.new_cost_help')} error={amountError}>
                        <MoneyInput disabled={frozen} value={amount} onValueChange={setAmount} />
                    </Field>
                    {zero && (
                        <Field id={evidenceId} label={t('costing.field.zero_evidence')} required={true} hint={t('costing.status.zero')} error={evidenceError}>
                            <TextInput type={'text'} autoComplete={'off'} maxLength={EVIDENCE_MAX} disabled={frozen} value={zeroEvidence} onChange={(event) => setZeroEvidence(event.target.value)} />
                        </Field>
                    )}
                    <Field id={reasonId} label={t('costing.correction.reason')} required={true} error={reasonError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked || unresolved !== undefined ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                        <ButtonLink href={back.href} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog open={review && (phase.kind === 'idle' || busy || throttled)} onOpenChange={setReview} title={t('costing.correction.review_title')} confirmLabel={t('costing.correction.confirm')} busy={busy} onConfirm={confirm}>
                <p>{t('costing.correction.review_body', { lama: valueText(source.cost), baru: newText })}</p>
            </ReviewDialog>
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('costing.correction.success') } : {})} />}
        </CommandLayout>
    );
}
