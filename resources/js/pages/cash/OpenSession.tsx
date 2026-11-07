import { router, usePage } from '@inertiajs/react';
import { useEffect, useId, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { SegmentedControl } from '@/components/ui/Choices';
import { ReviewDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, SelectInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { cashFieldMessage, OPEN_SESSION_COMMAND, registerName, type AvailableRegister } from '@/lib/cash/registers';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { personaOf, type DestinationId, type RoleCode } from '@/lib/navigation';

// UXP-104 "Buka sesi kas" (ROUTE_CONTRACTS RP-104, RC-02; KEY_SCREENS KS-06 with its states
// `default`, `review` and `no-register`; COPY_DECK section 7.4; INTERACTION_INVENTORY UXI-094 to
// UXI-100; NAVIGATION_CONTRACTS UXP-104; UI_STATES section 6; BR-CASH-04 to BR-CASH-09; WF-04;
// TASK-027 scope item 4). A CASHIER, the Owner or a combined account opens a session as themselves:
// the register among the available ones — chosen already when only one is — and the opening cash
// they counted, Rp0 valid and a blank amount refused. "Tinjau" (Ctrl+Enter, never Enter) opens the
// review, whose "Buka sesi" sends RC-02 and whose "Kembali ubah" returns to the form with the choice
// and the amount kept. With no register available the page says so and offers no form (UXS-48). A
// register taken meanwhile is said at its field with the registers as they are now (UXS-05); a
// session of the user's own opened meanwhile loads the page again, which leads home; an uncertain
// outcome freezes the request (UXS-14). Success leads to the till — RN-01, the user's home, until
// TASK-029 delivers it — with "Sesi kas :nomor dibuka.". "Batal" leads to the role's non-till home:
// Stok for the combined account, RN-01 for the others until their homes exist (EXC-52), asking
// first once an amount was entered (UXS-45).

const DESTINATION: DestinationId = 'till';

const NO_REGISTER: StateId = 'UXS-48';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const REGISTERS_PROP = 'registers';

/** The most options a segmented control holds (DESIGN_SYSTEM 8.4); more registers are a list. */
const SEGMENTED_MAX = 3;

/** The non-till homes until each role's own exists: Stok for the combined account (TASK-022), RN-01 otherwise. */
const STOCK_HOME = '/stock';

const HOME = '/';

interface SharedProps {
    readonly auth: { readonly user: { readonly roles: readonly RoleCode[] } } | null;
    readonly [key: string]: unknown;
}

export default function OpenSession({ registers }: { registers: readonly AvailableRegister[] }) {
    const title = t('cash.open.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <OpenForm title={title} registers={registers} />
        </AppShell>
    );
}

function OpenForm({ title, registers }: { title: string; registers: readonly AvailableRegister[] }) {
    const control = useCommand(OPEN_SESSION_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const ids = useId();
    const roles = usePage<SharedProps>().props.auth?.user.roles ?? [];
    const cancel = personaOf(roles) === 'combined' ? STOCK_HOME : HOME;
    const only = registers.length === 1 ? registers[0] : undefined;
    const [chosen, setChosen] = useState<string | null>(only === undefined ? null : String(only.id));
    const [amount, setAmount] = useState<string | null>(null);
    const [review, setReview] = useState(false);
    const [checked, setChecked] = useState(false);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const number = phase.kind === 'committed' ? (phase.result?.number ?? '') : '';
    const throttled = phase.kind === 'throttled';
    // What the form shows is what is sent while a frozen request is out (INTERACTION_CONTRACTS section 1.3).
    const frozen = busy || committed || throttled || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const register = registers.find((candidate) => String(candidate.id) === chosen);

    useUnsavedChanges(amount !== null && !committed, t('ux.state.unsaved.body'));

    // Success leads to the user's till, its home until TASK-029 (NAVIGATION_CONTRACTS UXP-104).
    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('cash.open.success', { nomor: number }));
            router.visit(location);
        }
    }, [location, number]);

    // The review ends with its answer.
    useEffect(() => {
        if (phase.kind !== 'idle' && phase.kind !== 'submitting') {
            setReview(false);
        }
    }, [phase.kind]);

    // A register taken meanwhile: the registers as they are now. A session of the user's own opened
    // meanwhile, a refusal of the form: the page again, which leads home (RP-104).
    const registerError = refusal === null ? undefined : cashFieldMessage(refusal.fields['payload.registerId']);
    const amountRefused = refusal === null ? undefined : cashFieldMessage(refusal.fields['payload.openingCash']);
    const formRefused = invalid && registerError === undefined && amountRefused === undefined;

    useEffect(() => {
        if (registerError !== undefined || refusal?.code === 'NOT_FOUND') {
            router.reload({ only: [REGISTERS_PROP] });
        } else if (formRefused) {
            router.reload();
        }
    }, [registerError, refusal, formRefused]);

    useEffect(() => {
        if (registerError !== undefined && register === undefined) {
            setChosen(null);
        }
    }, [registerError, register]);

    const complete = register !== undefined && amount !== null;
    const unresolved: ReactNode =
        blockedBy !== null && !busy ? (
            <>
                {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
            </>
        ) : undefined;
    const reviewReason: ReactNode = unresolved ?? (complete ? undefined : t('cash.open.disabled'));

    const openReview = (): void => {
        setChecked(true);

        if (reviewReason === undefined) {
            control.startAgain();
            setReview(true);
        }
    };

    const confirm = (): void => {
        if (register !== undefined && amount !== null) {
            control.submit({ registerId: register.id, openingCash: amount });
        }
    };

    if (registers.length === 0 && phase.kind === 'idle') {
        return (
            <CommandLayout header={<PageHeader title={title} subtitle={t('cash.open.intro')} />} width={'focused'}>
                <StateNotice state={NO_REGISTER} />
                <div className={'bk-actions'}>
                    <ButtonLink href={cancel} label={t('ux.action.cancel')} variant={'secondary'} size={'lg'} />
                </div>
            </CommandLayout>
        );
    }

    const registerId = `${ids}-register`;
    const amountId = `${ids}-amount`;
    const shownRegisterError = registerError ?? (checked && register === undefined ? t('ux.validation.choose_one') : undefined);
    const amountError = amountRefused ?? (checked && amount === null ? t('ux.validation.money_blank') : undefined);
    const summary: FieldError[] = [];

    if (invalid && shownRegisterError !== undefined) {
        summary.push({ fieldId: registerId, label: t('cash.open.register'), message: shownRegisterError });
    }

    if (invalid && amountError !== undefined) {
        summary.push({ fieldId: amountId, label: t('cash.open.opening_cash'), message: amountError });
    }

    const options = registers.map((candidate) => ({ value: String(candidate.id), label: registerName(candidate), icon: 'drawer' as const, disabled: frozen }));

    return (
        <CommandLayout header={<PageHeader title={title} subtitle={t('cash.open.intro')} />} width={'focused'}>
            {invalid && summary.length > 0 && <ErrorSummary errors={summary} />}
            <Card>
                <CommandForm label={title}>
                    {registers.length <= SEGMENTED_MAX ? (
                        <div id={registerId} tabIndex={-1}>
                            <SegmentedControl legend={t('cash.open.register')} options={options} value={chosen} onValueChange={setChosen} size={'xl'} />
                            {shownRegisterError !== undefined && <p className={'bk-error'}>{shownRegisterError}</p>}
                        </div>
                    ) : (
                        <Field id={registerId} label={t('cash.open.register')} required={true} error={shownRegisterError}>
                            <SelectInput value={chosen ?? ''} disabled={frozen} onChange={(event) => setChosen(event.target.value === '' ? null : event.target.value)}>
                                <option value={''} />
                                {registers.map((candidate) => (
                                    <option key={candidate.id} value={String(candidate.id)}>
                                        {registerName(candidate)}
                                    </option>
                                ))}
                            </SelectInput>
                        </Field>
                    )}
                    <Field id={amountId} label={t('cash.open.opening_cash')} required={true} hint={t('cash.open.opening_cash_help')} error={amountError}>
                        <MoneyInput size={'xl'} value={amount} disabled={frozen} onValueChange={setAmount} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.review')} size={'lg'} shortcut={'confirm'} enterActivates={false} disabledReason={checked || unresolved !== undefined ? reviewReason : undefined} unavailable={frozen && !throttled} onClick={openReview} />
                        <ButtonLink href={cancel} label={t('ux.action.cancel')} variant={'secondary'} size={'lg'} onClick={guard} />
                    </div>
                </CommandForm>
            </Card>
            <ReviewDialog open={review && (phase.kind === 'idle' || busy || throttled)} onOpenChange={setReview} title={t('cash.open.review_title')} confirmLabel={t('cash.open.confirm')} busy={busy} onConfirm={confirm}>
                <p>{t('cash.open.review_body', { laci: register === undefined ? '' : registerName(register), uang: formatMoney(amount ?? '0') })}</p>
            </ReviewDialog>
            {!invalid && <CommandPanels control={control} {...(committed ? { committed: t('cash.open.success', { nomor: number }) } : {})} />}
        </CommandLayout>
    );
}
