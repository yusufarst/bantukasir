import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useId, useState, type ReactNode } from 'react';
import { useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, Loading, StateNotice, type FieldError } from '@/components/ui/States';
import { Steps } from '@/components/ui/Steps';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { cashFieldMessage } from '@/lib/cash/registers';
import { ASSISTED_CLOSE_COMMAND, CHECKOUT_FAMILY, closedMeanwhile, refundsText, registerText, sessionPage, sessionsShell, type AssistedClosePreview, type SessionFigures, type UnresolvedCheckout } from '@/lib/cash/sessions';
import { VarianceWord } from '@/lib/cash/Parts';
import { familyLabel } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { CheckNote, useRowCheck } from '@/lib/commands/RowCheck';
import { useCommand } from '@/lib/commands/useCommand';
import { formatCount, formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';

// UXP-207 "Tutup sesi oleh Pemilik" (ROUTE_CONTRACTS RP-207, RC-04, RC-50; COPY_DECK sections 7.5, 7.12,
// 12, 14 and 15; INTERACTION_INVENTORY UXI-149 to UXI-158; INTERACTION_CONTRACTS RC-04 and section 2.5;
// NAVIGATION_CONTRACTS UXP-207 and NC-13; UI_STATES section 6; BR-CASH-12 to BR-CASH-18; WF-09; D-087;
// TASK-032 scope item 3). The Owner closes another user's OPEN session in the steps of UXP-105: the
// preview of the server — the responsible user and the register, the figures with expected cash, never a
// cost — then the count, "Uang fisik di laci" with Rp0 valid, and the mandatory "Alasan". The preview
// lists the responsible user's checkouts that the server holds unresolved, each with the Owner's "Cek
// status" (CAP-34), and "Lanjut hitung" (UXI-150) is disabled with its reason while one is listed
// (BR-CASH-16). "Tinjau" (Ctrl+Enter, never Enter) opens the review, which says that the responsible
// user stays and the Owner is recorded as the one who closes; "Tutup sesi" sends RC-04 with the activity
// version of the preview, after the password confirmation of recent authentication (UXS-11).
// RECOUNT_REQUIRED returns to the preview, reloaded, with UXS-19 or its variant; a session closed
// meanwhile is UXS-30 with the way to its report; an uncertain outcome freezes the close on its panel
// (UXS-14). Success leads to the session's report, RP-206. "Batal" and "Kembali ke Detail sesi kas" lead
// to RP-206, asking first once a count or a reason was entered (UXS-45).

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

/** The longest reason of RC-04 (INTERACTION_CONTRACTS RC-04). */
const REASON_MAX = 1000;

type Step = 'preview' | 'count';

/** The preview as the page holds it: read, read again, or failed to read again. */
type PreviewRead = 'read' | 'reading' | 'failed';

interface AssistedCloseProps {
    readonly session: { readonly id: number; readonly number: string; readonly registerCode: string; readonly registerLabel: string; readonly responsible: string };
    /** The close preview, a deferred prop: absent while it loads, null once the session is no longer another user's OPEN one. */
    readonly preview?: AssistedClosePreview | null;
}

interface SharedProps {
    readonly recoveryEpoch: string | null;
    readonly [key: string]: unknown;
}

export default function AssistedClose({ session, preview }: AssistedCloseProps) {
    const title = t('cash.assisted_close.title');

    return (
        <AppShell title={title} {...sessionsShell(true)}>
            <CloseFlow title={title} session={session} preview={preview} />
        </AppShell>
    );
}

function CloseFlow({ title, session, preview }: AssistedCloseProps & { title: string }) {
    const control = useCommand(ASSISTED_CLOSE_COMMAND);
    const { phase } = control;
    const guard = useLeaveGuard();
    const ids = useId();
    const [step, setStep] = useState<Step>('preview');
    const [amount, setAmount] = useState<string | null>(null);
    const [reason, setReason] = useState('');
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);
    const [previewRead, setPreviewRead] = useState<PreviewRead>('read');
    const [concluded, setConcluded] = useState<string | null>(null);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const throttled = phase.kind === 'throttled';
    // What the page shows is what is sent while a frozen close is out (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const recount = refusal?.code === RECOUNT_REQUIRED;
    const closed = closedMeanwhile(session.id, preview, refusal?.code ?? null);
    const amountRefused = refusal?.code === VALIDATION_FAILED ? cashFieldMessage(refusal.fields['payload.physicalCash']) : undefined;
    const reasonRefused = refusal?.code === VALIDATION_FAILED ? cashFieldMessage(refusal.fields['payload.reason']) : undefined;
    // BR-CASH-16: a checkout of the responsible user that the server holds unresolved keeps the count closed.
    const unresolvedCheckouts = preview?.unresolvedCheckouts ?? [];
    const checkoutUnresolved = unresolvedCheckouts.length > 0;
    const trimmed = reason.trim();

    useUnsavedChanges((amount !== null || trimmed !== '') && !committed, t('ux.state.unsaved.body'));

    // Success leads to the session's report (NAVIGATION_CONTRACTS UXP-207).
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

    // RECOUNT_REQUIRED: back to the preview, reloaded with the new figures or the unresolved checkout;
    // the count is discarded, so that the new preview is counted again (BR-CASH-14). The reason is kept.
    useEffect(() => {
        if (recount) {
            setStep('preview');
            setAmount(null);
            setChecked(false);
            readPreview();
        } else if (amountRefused !== undefined || reasonRefused !== undefined) {
            setStep('count');
        }
    }, [recount, amountRefused, reasonRefused, readPreview]);

    // The Owner's status check of a listed checkout settled it: the preview is read again (D-089).
    const resolved = useCallback((): void => readPreview(), [readPreview]);

    const checkoutReason: ReactNode = checkoutUnresolved ? t('ux.disabled.unresolved_checkouts') : undefined;
    const amountError = amountRefused ?? (checked && amount === null ? t('ux.validation.money_blank') : undefined);
    const reasonError = reasonRefused ?? (checked && trimmed === '' ? t('ux.validation.required') : undefined);
    const reviewReason: ReactNode = amount === null || trimmed === '' ? t('cash.assisted_close.disabled') : undefined;
    const amountId = `${ids}-physical`;
    const reasonId = `${ids}-reason`;
    const summary: FieldError[] = [
        ...(amountRefused === undefined ? [] : [{ fieldId: amountId, label: t('cash.close.physical_cash'), message: amountRefused }]),
        ...(reasonRefused === undefined ? [] : [{ fieldId: reasonId, label: t('cash.assisted_close.reason'), message: reasonRefused }]),
    ];

    const toCount = (): void => {
        if (checkoutReason === undefined && previewRead === 'read' && preview !== undefined && preview !== null) {
            setConcluded(null);
            setStep('count');
        }
    };

    // UXI-153: back at the preview the count is discarded; the reason is kept.
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
        if (amount !== null && trimmed !== '' && preview !== undefined && preview !== null) {
            control.submit({ sessionId: preview.sessionId, physicalCash: amount, reason: trimmed }, { activityVersion: preview.activityVersion });
        }
    };

    const detail = sessionPage(session.id);
    const header = <PageHeader title={title} subtitle={t('cash.session_detail.subtitle', { nomor: session.number, laci: session.registerLabel })} back={{ href: detail, page: t('cash.session_detail.title'), onNavigate: guard }} />;

    // A session closed meanwhile, by its responsible user or by the Owner elsewhere: the count is not kept (UXS-30).
    if (closed && !committed) {
        return (
            <CommandLayout header={header} width={'focused'}>
                <StateNotice state={CLOSED_MEANWHILE} actions={<ButtonLink href={detail} label={t('ux.state.session_closed.open_report')} variant={'secondary'} />} />
            </CommandLayout>
        );
    }

    const steps = [t('cash.close.step_preview'), t('cash.close.step_count')];
    const unresolvedRecount = refusal !== null && refusal.code === RECOUNT_REQUIRED && (refusal.messageKey === UNRESOLVED_RECOUNT_KEY || checkoutUnresolved);
    const identity: KeyValue[] = [
        { key: 'responsible', label: t('cash.assisted_close.responsible'), value: session.responsible },
        { key: 'register', label: t('cash.open.register'), value: registerText(session.registerCode, session.registerLabel) },
    ];

    return (
        <CommandLayout header={header} width={'focused'}>
            <Steps label={title} steps={steps} current={step === 'preview' ? 0 : 1} />
            {summary.length > 0 && <ErrorSummary errors={summary} />}
            {step === 'preview' ? (
                <div className={'bk-stack'}>
                    {recount && <StateNotice state={unresolvedRecount ? RECOUNT_UNRESOLVED : RECOUNT} />}
                    {concluded !== null && (
                        <Notice tone={'neutral'} urgency={'status'}>
                            <p>{concluded}</p>
                        </Notice>
                    )}
                    {checkoutUnresolved && previewRead !== 'failed' && (
                        <Notice tone={'pending'} urgency={'status'}>
                            <div className={'bk-stack'}>
                                <p>{t('cash.close.unresolved_note')}</p>
                                <ul className={'bk-stack'} role={'list'}>
                                    {unresolvedCheckouts.map((checkout) => (
                                        <li key={checkout.sourceIntentId}>
                                            <UnresolvedRow checkout={checkout} onConcluded={setConcluded} onResolved={resolved} />
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Notice>
                    )}
                    <Section title={t('cash.close.step_preview')}>
                        <div className={'bk-stack'}>
                            <KeyValueList items={identity} />
                            {previewRead === 'failed' ? (
                                <StateNotice state={LOAD_FAILED} actions={<Button label={t('ux.action.retry')} variant={'secondary'} onClick={readPreview} />} />
                            ) : preview === undefined || previewRead === 'reading' ? (
                                <Loading lines={3} />
                            ) : (
                                <PreviewFigures figures={preview?.figures ?? null} />
                            )}
                        </div>
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
                        <ButtonLink href={detail} label={t('ux.action.cancel')} variant={'secondary'} size={'lg'} onClick={guard} />
                    </div>
                </div>
            ) : (
                <Section title={t('cash.close.step_count')}>
                    <CommandForm label={t('cash.close.step_count')}>
                        <Field id={amountId} label={t('cash.close.physical_cash')} required={true} hint={t('cash.close.physical_cash_help')} error={amountError}>
                            <MoneyInput size={'xl'} value={amount} disabled={frozen} onValueChange={setAmount} />
                        </Field>
                        <Field id={reasonId} label={t('cash.assisted_close.reason')} required={true} error={reasonError}>
                            <TextInput type={'text'} autoComplete={'off'} maxLength={REASON_MAX} disabled={frozen} value={reason} onChange={(event) => setReason(event.target.value)} />
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
                            <ButtonLink href={detail} label={t('ux.action.cancel')} variant={'quiet'} size={'lg'} onClick={guard} />
                        </div>
                    </CommandForm>
                </Section>
            )}
            {preview !== undefined && preview !== null && (
                <ReviewDialog
                    open={review && (phase.kind === 'idle' || busy || throttled)}
                    onOpenChange={setReview}
                    title={t('cash.assisted_close.review_title')}
                    confirmLabel={t('cash.close.confirm')}
                    busy={busy}
                    onConfirm={confirm}
                >
                    <ReviewFigures expected={preview.figures.expectedCash} physical={amount ?? NO_AMOUNT} />
                    <p>{t('cash.assisted_close.review_body', { nama: session.responsible })}</p>
                    <p>{t('cash.close.review_note')}</p>
                </ReviewDialog>
            )}
            {!recount && !closed && <CommandPanels control={control} {...(committed ? { committed: t('cash.close.success', { nomor: session.number }) } : {})} />}
        </CommandLayout>
    );
}

/**
 * One checkout of the responsible user that the server holds unresolved (UXI-149; COPY_DECK section 7.12):
 * its family and time, and the Owner's "Cek status" as the Owner (CAP-34), whose conclusive answer settles
 * it (D-089); an original still running answers IN_PROGRESS until its hard end (TR-TIME-03).
 */
function UnresolvedRow({ checkout, onConcluded, onResolved }: { checkout: UnresolvedCheckout; onConcluded: (text: string) => void; onResolved: () => void }) {
    const epoch = usePage<SharedProps>().props.recoveryEpoch;
    const announce = useAnnounce();
    const concluded = useCallback(
        (text: string): void => {
            onConcluded(text);
            announce(text, false);
        },
        [onConcluded, announce],
    );
    const { view, check } = useRowCheck({ operation: CHECKOUT_FAMILY.operation, sourceIntentId: checkout.sourceIntentId, recoveryEpoch: null }, epoch, concluded, onResolved);
    const row = t('commands.unresolved.row', { jenis: familyLabel(CHECKOUT_FAMILY.family), waktu: formatDateTime(checkout.receivedAt) });

    return (
        <div className={'bk-stack'}>
            <p>{row}</p>
            {view.kind === 'epoch_stale' && <p>{t('ux.state.held.epoch_stale.body')}</p>}
            <div className={'bk-actions'}>
                <Button label={t('ux.action.check_status')} rowName={row} variant={'secondary'} enterActivates={false} busy={view.kind === 'checking'} onClick={check} />
            </div>
            <CheckNote view={view} />
        </div>
    );
}

/** The preview's figures (UXI-149; COPY_DECK section 7.5): the drawer-funded refunds taken from expected cash. */
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

/** The review's figures (UXI-154): physical cash, expected cash and the variance in words (COPY_DECK section 1.4). */
function ReviewFigures({ expected, physical }: { expected: string; physical: string }) {
    const items: KeyValue[] = [
        { key: 'physical', label: t('cash.figure.physical_cash'), value: formatMoney(physical) },
        { key: 'expected', label: t('cash.figure.expected_cash'), value: formatMoney(expected) },
        { key: 'variance', label: t('cash.figure.variance'), value: <VarianceWord physical={physical} expected={expected} />, strong: true },
    ];

    return <KeyValueList items={items} />;
}
