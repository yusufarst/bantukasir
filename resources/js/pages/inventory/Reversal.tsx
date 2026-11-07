import { router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { catalogFieldMessage } from '@/lib/catalog/products';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { reversalBlocked } from '@/lib/inventory/Reversal';
import {
    HISTORY_SHELL,
    issuePage,
    ISSUES_SHELL,
    movementPage,
    receivingPage,
    RECEIVINGS_SHELL,
    REVERSAL_COMMAND,
    signedQuantity,
    typeWord,
    type MovementType,
    type ReversalOpener,
} from '@/lib/inventory/stock';

// UXP-411 "Batalkan mutasi" (ROUTE_CONTRACTS RP-411, RC-10; COPY_DECK section 9.12; INTERACTION_INVENTORY
// UXI-277 to UXI-284; NAVIGATION_CONTRACTS UXP-411; UI_STATES section 6; INTERACTION_CONTRACTS section
// 1.5; BR-INV-34 to BR-INV-37; WF-23; TASK-026 scope item 4). The Owner reverses one OPENING, RECEIPT
// or MANUAL_ISSUE exactly, once: the movement, its exact inverse and the stock now and after above the
// draft — "Alasan" alone, no quantity (BR-INV-34) — which lives in this page's memory only, never in
// the browser's storage (NC-10). An ineligible source shows why (UXS-13): already reversed, an
// unresolved reversal of the user (NC-13), or the onboarding freeze for a RECEIPT or a MANUAL_ISSUE.
// "Tinjau" (Ctrl+Enter, never Enter) opens the review; its confirmation asks for the password first
// when the recent authentication has lapsed (UXS-11) and sends RC-10. STALE_VERSION shows the source
// as it is now, reversed meanwhile (UXS-17); INSUFFICIENT_STOCK (UXS-31) and REPLAY_REJECTED (UXS-40)
// say that nothing changed; an uncertain outcome freezes the request (UXS-14). Success leads to UXP-403
// of the REVERSAL with "Mutasi dibatalkan."; "Batal" and "Kembali ke …" lead to the page that opened
// it, asking first with a draft (UXS-45).

const REASON_MAX = 1000;

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const REFUSAL_STATES: Readonly<Record<string, StateId>> = {
    STALE_VERSION: 'UXS-17',
    ONBOARDING_FREEZE: 'UXS-27',
    POSTING_BLOCKED: 'UXS-28',
    INSUFFICIENT_STOCK: 'UXS-31-reversal',
    REPLAY_REJECTED: 'UXS-40-reversal',
};

interface Source {
    readonly movementId: number;
    readonly type: MovementType;
    readonly quantity: string;
    readonly inverse: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly recordedAt: string;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    /** The receiving document or the manual issue of the movement, for the way back. */
    readonly documentId: number | null;
    readonly stockNow: string;
    readonly stockAfter: string;
    readonly reversed: boolean;
}

interface ReversalProps {
    readonly source: Source;
    readonly postingFrozen: boolean;
    readonly opener: ReversalOpener | null;
}

export default function Reversal(props: ReversalProps) {
    const title = t('inventory.reversal.title');
    const shell = props.opener === 'receiving' ? RECEIVINGS_SHELL : props.opener === 'issue' ? ISSUES_SHELL : HISTORY_SHELL;

    return (
        <AppShell title={title} {...shell}>
            <ReversalForm title={title} {...props} />
        </AppShell>
    );
}

/** The page that opened this one and its title: the way back and "Batal" (UXI-283, UXI-284). */
function backOf(opener: ReversalOpener | null, source: Source): { readonly href: string; readonly page: string } {
    if (opener === 'receiving' && source.documentId !== null) {
        return { href: receivingPage(source.documentId), page: t('inventory.receipt_detail.title') };
    }

    if (opener === 'issue' && source.documentId !== null) {
        return { href: issuePage(source.documentId), page: t('inventory.issue_detail.title') };
    }

    return { href: movementPage(source.movementId), page: t('inventory.movement.title') };
}

/** A stock figure, which after a reversal of a receipt may fall below zero: the server refuses that (UXS-31). */
function stockText(value: string, source: Source): string {
    return value.startsWith('-') ? signedQuantity(value, source.quantityPrecision, source.baseUnit) : formatQuantity(value, source.quantityPrecision, source.baseUnit);
}

function ReversalForm({ title, source, postingFrozen, opener }: ReversalProps & { title: string }) {
    const control = useCommand(REVERSAL_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const ids = useId();
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
    // What the field shows is what is sent, while a frozen request is out, waits for the password or may be sent again (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';

    useUnsavedChanges(reason.trim() !== '' && !committed, t('ux.state.unsaved.body'));

    // Success leads to UXP-403 of the REVERSAL (NAVIGATION_CONTRACTS UXP-411; UI_STATES section 4).
    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('inventory.reversal.success'));
            router.visit(location);
        }
    }, [location]);

    // The review ends with its answer, and comes back after a "Batal" of the recent authentication.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting' && phase.kind !== 'recent_auth') {
            setReview(false);
        }
    }, [phase.kind]);

    // UXS-17: the source as it is now, reversed meanwhile (BR-INV-35).
    useEffect(() => {
        if (refusal?.code === 'STALE_VERSION') {
            router.reload({ only: ['source', 'postingFrozen'] });
        }
    }, [refusal]);

    const ineligible = reversalBlocked(source.type, source.reversed, blockedBy !== null && !busy, postingFrozen);
    const complete = reason.trim() !== '';
    const reviewReason = ineligible ?? (complete ? undefined : t('inventory.reversal.disabled'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        control.submit({ movementId: source.movementId, reason: reason.trim() });
    };

    const reasonId = `${ids}-reason`;
    const reasonError = (refusal === null ? undefined : catalogFieldMessage(refusal.fields['payload.reason'])) ?? (checked && !complete ? t('ux.validation.required') : undefined);
    const summary: FieldError[] = invalid && reasonError !== undefined ? [{ fieldId: reasonId, label: t('inventory.reversal.reason'), message: reasonError }] : [];
    const refusalState = refusal === null ? undefined : REFUSAL_STATES[refusal.code];
    const refusalBody = refusal?.code === 'POSTING_BLOCKED' ? t('ux.state.blocked.body', { produk: source.product.name }) : undefined;
    const items: KeyValue[] = [
        { key: 'product', label: t('inventory.movement.product'), value: source.product.name },
        { key: 'source', label: t('inventory.reversal.source'), value: t('inventory.movement.source_pair', { jenis: typeWord(source.type), waktu: formatDateTime(source.recordedAt) }) },
        { key: 'quantity', label: t('inventory.movement.quantity'), value: <span className={'bk-num'}>{signedQuantity(source.quantity, source.quantityPrecision, source.baseUnit)}</span> },
        { key: 'inverse', label: t('inventory.reversal.inverse'), value: <span className={'bk-num'}>{signedQuantity(source.inverse, source.quantityPrecision, source.baseUnit)}</span> },
        { key: 'now', label: t('inventory.reversal.stock_now'), value: <span className={'bk-num'}>{stockText(source.stockNow, source)}</span> },
        { key: 'after', label: t('inventory.reversal.stock_after'), value: <span className={'bk-num'}>{stockText(source.stockAfter, source)}</span> },
    ];

    return (
        <CommandLayout header={<PageHeader title={title} subtitle={t('inventory.movement.subtitle', { jenis: typeWord(source.type), produk: source.product.name })} back={{ href: back.href, page: back.page, onNavigate: guard }} />} width={'form'}>
            {invalid && <ErrorSummary errors={summary} />}
            {refusalState !== undefined && <StateNotice state={refusalState} {...(refusalBody === undefined ? {} : { body: refusalBody })} />}
            <Section title={t('inventory.reversal.source')} titleHidden={true}>
                <div className={'bk-stack'}>
                    <KeyValueList items={items} />
                    <p className={'bk-meta'}>{t('inventory.reversal.explanation')}</p>
                </div>
            </Section>
            <Card>
                <CommandForm label={title}>
                    <Field id={reasonId} label={t('inventory.reversal.reason')} required={true} error={reasonError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked || ineligible !== undefined ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                        <ButtonLink href={back.href} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog open={review && (phase.kind === 'idle' || busy || throttled)} onOpenChange={setReview} title={t('inventory.reversal.review_title')} confirmLabel={t('inventory.reversal.confirm')} busy={busy} onConfirm={confirm}>
                <KeyValueList items={items} />
            </ReviewDialog>
            {!invalid && refusalState === undefined && <CommandPanels control={control} {...(committed ? { committed: t('inventory.reversal.success') } : {})} />}
        </CommandLayout>
    );
}
