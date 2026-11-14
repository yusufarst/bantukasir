import { router } from '@inertiajs/react';
import { useCallback, useEffect, useId, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, Loading, StateNotice, type FieldError } from '@/components/ui/States';
import { Steps } from '@/components/ui/Steps';
import { AppShell, useLeaveGuard, useUnsavedChanges, type ShellLayout } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { cashFieldMessage } from '@/lib/cash/registers';
import { CHECKOUT_FAMILY, CLOSE_SESSION_COMMAND, closedMeanwhile, refundsText, sessionPage, TILL_PAGE, UNRESOLVED_PAGE, type ClosePreview, type SessionFigures } from '@/lib/cash/sessions';
import { PreviewRefunds, VarianceWord } from '@/lib/cash/Parts';
import { blockingRequest } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand } from '@/lib/commands/useCommand';
import { formatCount, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-105 "Tutup sesi kas" (ROUTE_CONTRACTS RP-105, RC-03, RC-50; KEY_SCREENS KS-07 with its states
// `default`, `count`, `review`, `recount` and `unresolved-checkout`; COPY_DECK sections 7.5, 12 and 15;
// INTERACTION_INVENTORY UXI-101 to UXI-111; INTERACTION_CONTRACTS RC-03 and section 2.5;
// NAVIGATION_CONTRACTS UXP-105 and NC-13; UI_STATES section 6; BR-CASH-12 to BR-CASH-17; WF-08;
// TASK-030 scope item 3). The responsible user closes their own OPEN session in two steps before the
// review: the preview of the server — the figures with expected cash, never a cost — then the count
// of the drawer, "Uang fisik di laci", Rp0 valid and a blank amount refused. "Lanjut hitung" (UXI-103)
// is disabled with its reason while the user has an unresolved checkout, in this browser or on the
// server (BR-CASH-16), with "Cek permintaan yang belum pasti" (UXI-102). "Tinjau" (Ctrl+Enter, never
// Enter) opens the review of physical cash, expected cash and the variance in words; "Tutup sesi"
// sends RC-03 with the activity version of the preview. Nothing is locked while the cash is counted.
// RECOUNT_REQUIRED returns to the preview, reloaded, with UXS-19 — or its variant when the preview now
// lists an unresolved checkout; "Lanjut hitung" waits for the new figures, and a reload that fails says
// so with "Coba lagi" (UXS-22). The count is kept within its step and its review only: back at the
// preview it is discarded, so that a new preview is counted again. A session closed meanwhile is UXS-30
// with the way to its report; an uncertain outcome freezes the close on its panel (UXS-14), never
// counted again. Success leads to the session's report, RP-206. "Batal" and "Kembali ke Kasir" lead to
// the till, asking first once a count was entered (UXS-45).

const DESTINATION: DestinationId = 'till';

/** On the till's pages the side navigation is the icon rail (UXA-03; KS-07). */
const TILL_LAYOUT: ShellLayout = 'rail';

const PREVIEW_PROP = 'preview';

const RECOUNT_REQUIRED = 'RECOUNT_REQUIRED';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

/** The message key of RECOUNT_REQUIRED when the server holds an unresolved checkout (COPY_DECK section 13.1). */
const UNRESOLVED_RECOUNT_KEY = 'ux.state.recount.unresolved_checkouts';

const RECOUNT: StateId = 'UXS-19';

const RECOUNT_UNRESOLVED: StateId = 'UXS-19-unresolved';

const CLOSED_MEANWHILE: StateId = 'UXS-30-close';

const LOAD_FAILED: StateId = 'UXS-22';

/** The amount the review names while none is entered, which the review never shows: it opens only with an amount. */
const NO_AMOUNT = '0';

type Step = 'preview' | 'count';

/** The preview as the page holds it: read, read again after RECOUNT_REQUIRED, or failed to read again. */
type PreviewRead = 'read' | 'reading' | 'failed';

interface CloseSessionProps {
    readonly session: { readonly id: number; readonly number: string; readonly registerCode: string; readonly registerLabel: string };
    /** The close preview, a deferred prop: absent while it loads, null once the session is no longer OPEN. */
    readonly preview?: ClosePreview | null;
}

export default function CloseSession({ session, preview }: CloseSessionProps) {
    const title = t('cash.close.title');

    return (
        <AppShell title={title} current={DESTINATION} layout={TILL_LAYOUT}>
            <CloseFlow title={title} session={session} preview={preview} />
        </AppShell>
    );
}

function CloseFlow({ title, session, preview }: CloseSessionProps & { title: string }) {
    const control = useCommand(CLOSE_SESSION_COMMAND);
    const { phase } = control;
    const guard = useLeaveGuard();
    const unresolved = useUnresolved();
    const ids = useId();
    const [step, setStep] = useState<Step>('preview');
    const [amount, setAmount] = useState<string | null>(null);
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    const [previewRead, setPreviewRead] = useState<PreviewRead>('read');

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the page shows is what is sent while a frozen close is out (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const recount = refusal?.code === RECOUNT_REQUIRED;
    // A preview of another session — this one closed and a new one opened elsewhere before a reload
    // of the preview — is never counted under this session's header: this one is no longer OPEN.
    const closed = closedMeanwhile(session.id, preview, refusal?.code ?? null);
    const amountRefused = refusal?.code === VALIDATION_FAILED ? cashFieldMessage(refusal.fields['payload.physicalCash']) : undefined;
    // BR-CASH-16: an unresolved checkout of this browser or of the server keeps the count closed.
    const checkoutUnresolved = blockingRequest(unresolved.union, CHECKOUT_FAMILY.operation, CHECKOUT_FAMILY.family) !== null || (preview?.unresolvedCheckouts ?? 0) > 0;

    useUnsavedChanges(amount !== null && !committed, t('ux.state.unsaved.body'));

    // Success leads to the session's report (NAVIGATION_CONTRACTS UXP-105).
    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('cash.close.success', { nomor: session.number }));
            router.visit(location);
        }
    }, [location, session.number]);

    // The review ends with its answer.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting') {
            setReview(false);
        }
    }, [phase.kind]);

    // The preview read again by name: "Lanjut hitung" waits for it, and a failed read is said in place
    // with "Coba lagi" (UXS-22), never the earlier figures to count against.
    const readPreview = useCallback((): void => {
        const failed = (): boolean => {
            setPreviewRead('failed');

            return false;
        };

        setPreviewRead('reading');
        router.reload({ only: [PREVIEW_PROP], onSuccess: () => setPreviewRead('read'), onHttpException: failed, onNetworkError: failed });
    }, []);

    // RECOUNT_REQUIRED: back to the preview, reloaded with the new figures or the unresolved checkout
    // (UXS-19; INTERACTION_CONTRACTS RC-03 "Client"); the count is discarded, so that the new preview is
    // counted again (NAVIGATION_CONTRACTS UXP-105; BR-CASH-14).
    useEffect(() => {
        if (recount) {
            setStep('preview');
            setAmount(null);
            setChecked(false);
            readPreview();
        } else if (amountRefused !== undefined) {
            setStep('count');
        }
    }, [recount, amountRefused, readPreview]);

    const checkoutReason: ReactNode = checkoutUnresolved ? (
        <>
            {t('ux.disabled.unresolved_checkouts')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;
    const amountError = amountRefused ?? (checked && amount === null ? t('ux.validation.money_blank') : undefined);
    const reviewReason: ReactNode = amount === null ? t('cash.close.disabled') : undefined;
    const amountId = `${ids}-physical`;
    const summary: FieldError[] = amountRefused === undefined ? [] : [{ fieldId: amountId, label: t('cash.close.physical_cash'), message: amountRefused }];

    const toCount = (): void => {
        if (checkoutReason === undefined && previewRead === 'read' && preview !== undefined && preview !== null) {
            setStep('count');
        }
    };

    // UXI-106: back at the preview the count is discarded (NAVIGATION_CONTRACTS UXP-105).
    const toPreview = (): void => {
        setStep('preview');
        setAmount(null);
        setChecked(false);
    };

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        if (amount !== null && preview !== undefined && preview !== null) {
            control.submit({ sessionId: preview.sessionId, physicalCash: amount }, { activityVersion: preview.activityVersion });
        }
    };

    const header = (
        <PageHeader
            title={title}
            subtitle={t('sales.till.session', { nomor: session.number, laci: session.registerLabel })}
            back={{ href: TILL_PAGE, page: t('sales.till.title'), onNavigate: guard }}
        />
    );

    // A session closed meanwhile, by the user elsewhere or by the Owner: the count is not kept (UXS-30).
    if (closed && !committed) {
        return (
            <CommandLayout header={header} width={'focused'}>
                <StateNotice state={CLOSED_MEANWHILE} actions={<ButtonLink href={sessionPage(session.id)} label={t('ux.state.session_closed.open_report')} variant={'secondary'} />} />
            </CommandLayout>
        );
    }

    const steps = [t('cash.close.step_preview'), t('cash.close.step_count')];
    const unresolvedRecount = refusal !== null && refusal.code === RECOUNT_REQUIRED && (refusal.messageKey === UNRESOLVED_RECOUNT_KEY || checkoutUnresolved);

    return (
        <CommandLayout header={header} width={'focused'}>
            <Steps label={title} steps={steps} current={step === 'preview' ? 0 : 1} />
            {amountRefused !== undefined && <ErrorSummary errors={summary} />}
            {step === 'preview' ? (
                <div className={'bk-stack'}>
                    {recount && <StateNotice state={unresolvedRecount ? RECOUNT_UNRESOLVED : RECOUNT} />}
                    {checkoutUnresolved && (
                        <Notice tone={'pending'} urgency={'status'} actions={<ButtonLink href={UNRESOLVED_PAGE} label={t('cash.close.resolve_unresolved')} variant={'primary'} />}>
                            <p>{t('cash.close.unresolved_note')}</p>
                        </Notice>
                    )}
                    <Section title={t('cash.close.step_preview')}>
                        {previewRead === 'failed' ? (
                            <StateNotice state={LOAD_FAILED} actions={<Button label={t('ux.action.retry')} variant={'secondary'} onClick={readPreview} />} />
                        ) : preview === undefined || previewRead === 'reading' ? (
                            <Loading lines={3} />
                        ) : (
                            <div className={'bk-stack'}>
                                <PreviewFigures figures={preview?.figures ?? null} />
                                <PreviewRefunds refunds={preview?.drawerRefunds ?? []} />
                            </div>
                        )}
                    </Section>
                    <div className={'bk-actions'}>
                        <Button
                            label={t('cash.close.continue_count')}
                            size={'lg'}
                            shortcut={'confirm'}
                            enterActivates={false}
                            disabledReason={checkoutReason}
                            unavailable={frozen || preview === undefined || previewRead !== 'read'}
                            onClick={toCount}
                        />
                        <ButtonLink href={TILL_PAGE} label={t('ux.action.cancel')} variant={'secondary'} size={'lg'} onClick={guard} />
                    </div>
                </div>
            ) : (
                <Section title={t('cash.close.step_count')}>
                    <CommandForm label={t('cash.close.step_count')}>
                        <Field id={amountId} label={t('cash.close.physical_cash')} required={true} hint={t('cash.close.physical_cash_help')} error={amountError}>
                            <MoneyInput size={'xl'} value={amount} disabled={frozen} onValueChange={setAmount} />
                        </Field>
                        <div className={'bk-actions'}>
                            <Button
                                label={t('ux.action.review')}
                                size={'lg'}
                                shortcut={'confirm'}
                                enterActivates={false}
                                disabledReason={checked ? reviewReason : undefined}
                                unavailable={frozen && !throttled}
                                onClick={openReview}
                            />
                            <Button label={t('cash.close.back_to_preview')} variant={'secondary'} size={'lg'} unavailable={frozen} onClick={toPreview} />
                            <ButtonLink href={TILL_PAGE} label={t('ux.action.cancel')} variant={'quiet'} size={'lg'} onClick={guard} />
                        </div>
                    </CommandForm>
                </Section>
            )}
            {preview !== undefined && preview !== null && (
                <ReviewDialog
                    open={review && (phase.kind === 'idle' || busy || throttled)}
                    onOpenChange={setReview}
                    title={t('cash.close.review_title')}
                    confirmLabel={t('cash.close.confirm')}
                    busy={busy}
                    onConfirm={confirm}
                >
                    <ReviewFigures expected={preview.figures.expectedCash} physical={amount ?? NO_AMOUNT} />
                    <p>{t('cash.close.review_note')}</p>
                </ReviewDialog>
            )}
            {!recount && !closed && <CommandPanels control={control} {...(committed ? { committed: t('cash.close.success', { nomor: session.number }) } : {})} />}
        </CommandLayout>
    );
}

/** The preview's figures (UXI-101; COPY_DECK section 7.5): the drawer-funded refunds taken from expected cash. */
function PreviewFigures({ figures }: { figures: SessionFigures | null }) {
    if (figures === null) {
        return null;
    }

    const items: KeyValue[] = [
        { key: 'opening', label: t('cash.figure.opening_cash'), value: formatMoney(figures.openingCash) },
        { key: 'count', label: t('cash.figure.sale_count'), value: formatCount(figures.saleCount) },
        { key: 'cash', label: t('cash.figure.cash_payments'), value: formatMoney(figures.cashPayments) },
        { key: 'transfer', label: t('cash.figure.transfer_payments'), value: formatMoney(figures.transferPayments) },
        { key: 'refunds', label: t('cash.figure.drawer_refunds'), value: refundsText(figures.drawerRefunds) },
        { key: 'expected', label: t('cash.figure.expected_cash'), value: formatMoney(figures.expectedCash), strong: true },
    ];

    return <KeyValueList items={items} />;
}

/** The review's figures (UXI-107): physical cash, expected cash and the variance in words (COPY_DECK section 1.4). */
function ReviewFigures({ expected, physical }: { expected: string; physical: string }) {
    const items: KeyValue[] = [
        { key: 'physical', label: t('cash.figure.physical_cash'), value: formatMoney(physical) },
        { key: 'expected', label: t('cash.figure.expected_cash'), value: formatMoney(expected) },
        { key: 'variance', label: t('cash.figure.variance'), value: <VarianceWord physical={physical} expected={expected} />, strong: true },
    ];

    return <KeyValueList items={items} />;
}
