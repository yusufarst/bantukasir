import { router } from '@inertiajs/react';
import { useEffect, useId, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { cashFieldMessage, REGISTER_COMMANDS, REGISTERS_PAGE, type RegisterRow } from '@/lib/cash/registers';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-610 "Tambah laci kas" or "Ubah laci kas" (ROUTE_CONTRACTS RP-610, RC-33, RC-34; COPY_DECK
// section 11.10; INTERACTION_INVENTORY UXI-370 to UXI-373; NAVIGATION_CONTRACTS UXP-610; UI_STATES
// section 6; INTERACTION_CONTRACTS section 1.5; BR-CASH-02; TASK-027 scope item 4): Kode, Label and
// Aktif. "Simpan" is enabled once the code and the label are filled — and, for a change, once a
// field changed — otherwise it says why (UXS-13); Enter never activates it (UXA-10), and it asks
// for the password first when the recent authentication has lapsed (UXS-11). A register with an
// OPEN session cannot be changed: the form says so and offers nothing to send (UXS-13); a session
// opened meanwhile, a code that another register holds, or a version changed meanwhile are said at
// the form (UXS-05, UXS-17). Success leads to UXP-609 with "Laci kas disimpan."; "Batal" and
// "Kembali ke Laci kas" lead there too, asking first with entered data (UXS-45).

const DESTINATION: DestinationId = 'settings';

const STALE_STATE: StateId = 'UXS-17';

const INVALID_STATE: StateId = 'UXS-05';

const STALE_VERSION = 'STALE_VERSION';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const REGISTER_PROP = 'register';

/** UXP-006 "Permintaan belum pasti" (NC-13 (a)). */
const REQUESTS_PAGE = '/requests';

const CODE_MAX = 32;

const LABEL_MAX = 100;

export default function RegisterForm({ register }: { register: RegisterRow | null }) {
    const title = register === null ? t('cash.register_form.title_new') : t('cash.register_form.title_edit');

    return (
        <AppShell title={title} current={DESTINATION}>
            <Form title={title} register={register} />
        </AppShell>
    );
}

function Form({ title, register }: { title: string; register: RegisterRow | null }) {
    const control = useCommand(register === null ? REGISTER_COMMANDS.create : REGISTER_COMMANDS.update);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const ids = useId();
    const [code, setCode] = useState(register?.code ?? '');
    const [label, setLabel] = useState(register?.label ?? '');
    const [active, setActive] = useState(register?.active ?? true);
    const [checked, setChecked] = useState(false);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed' && control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const frozen = busy || committed || phase.kind === 'recent_auth' || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const inUse = register !== null && register.openBy !== null;
    // What is sent is trimmed, so outer spaces alone change nothing (UXS-13; review finding R-04).
    const changed = register === null ? code.trim() !== '' || label.trim() !== '' || !active : code.trim() !== register.code || label.trim() !== register.label || active !== register.active;

    useUnsavedChanges(changed && !committed, t('ux.state.unsaved.body'));

    // Success leads to UXP-609 with its message (NAVIGATION_CONTRACTS UXP-610).
    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('cash.register_form.success'));
            router.visit(location);
        }
    }, [location]);

    // UXS-17, or a session opened on the register meanwhile: the register as it is now.
    const inUseNow = refusal !== null && cashFieldMessage(refusal.fields['payload.registerId']) !== undefined;

    useEffect(() => {
        if (stale || inUseNow) {
            router.reload({ only: [REGISTER_PROP] });
        }
    }, [stale, inUseNow]);

    const complete = code.trim() !== '' && label.trim() !== '';
    // NC-13 (a): an unresolved request of the family, with the way to resolve it.
    const blockedReason = (
        <>
            {t('ux.disabled.unresolved_family')} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
    const reason: ReactNode = inUse
        ? t('cash.registers.edit_disabled')
        : !complete
          ? t('cash.register_form.disabled')
          : register !== null && !changed
            ? t('ux.disabled.no_change')
            : blockedBy !== null && !busy
              ? blockedReason
              : undefined;

    const submit = (): void => {
        setChecked(true);

        if (reason !== undefined) {
            return;
        }

        if (register === null) {
            control.submit({ code: code.trim(), label: label.trim(), active });
        } else {
            control.submit({ registerId: register.id, code: code.trim(), label: label.trim(), active }, { registerVersion: register.version });
        }
    };

    const codeId = `${ids}-code`;
    const labelId = `${ids}-label`;
    const codeError = (refusal === null ? undefined : cashFieldMessage(refusal.fields['payload.code'])) ?? (checked && code.trim() === '' ? t('ux.validation.required') : undefined);
    const labelError = (refusal === null ? undefined : cashFieldMessage(refusal.fields['payload.label'])) ?? (checked && label.trim() === '' ? t('ux.validation.required') : undefined);
    const summary: FieldError[] = [];

    if (invalid && codeError !== undefined) {
        summary.push({ fieldId: codeId, label: t('cash.register_form.code'), message: codeError });
    }

    if (invalid && labelError !== undefined) {
        summary.push({ fieldId: labelId, label: t('cash.register_form.label'), message: labelError });
    }

    const current: KeyValue[] =
        register === null
            ? []
            : [
                  { key: 'code', label: t('cash.register_form.code'), value: register.code },
                  { key: 'label', label: t('cash.register_form.label'), value: register.label },
                  { key: 'active', label: t('cash.registers.col_status'), value: register.active ? t('catalog.status.active') : t('catalog.status.inactive') },
              ];

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: REGISTERS_PAGE, page: t('cash.registers.title'), onNavigate: guard }} />}>
            {invalid && summary.length > 0 && <ErrorSummary errors={summary} />}
            {invalid && inUseNow && <StateNotice state={INVALID_STATE} body={t('ux.validation.register_in_use')} />}
            {stale && (
                <>
                    <StateNotice state={STALE_STATE} />
                    <KeyValueList items={current} />
                </>
            )}
            <Card>
                <CommandForm label={title}>
                    <Field id={codeId} label={t('cash.register_form.code')} required={true} error={codeError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={CODE_MAX} disabled={frozen || inUse} value={code} onChange={(event) => setCode(event.target.value)} />
                    </Field>
                    <Field id={labelId} label={t('cash.register_form.label')} required={true} error={labelError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={LABEL_MAX} disabled={frozen || inUse} value={label} onChange={(event) => setLabel(event.target.value)} />
                    </Field>
                    <div className={'bk-field bk-checks'}>
                        <label className={'bk-check'}>
                            <input type={'checkbox'} checked={active} disabled={frozen || inUse} onChange={(event) => setActive(event.target.checked)} />
                            <span className={'bk-check__box'} aria-hidden={true}>
                                <Icon name={'check'} size={'sm'} />
                            </span>
                            <span className={'bk-check__text'}>
                                <span>{t('cash.register_form.active')}</span>
                            </span>
                        </label>
                        <p className={'bk-hint'}>{t('cash.register_form.help')}</p>
                    </div>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.save')} size={'lg'} enterActivates={false} busy={busy} disabledReason={checked || inUse || (register !== null && !changed) ? reason : undefined} unavailable={frozen && !busy} onClick={submit} />
                        <ButtonLink href={REGISTERS_PAGE} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                        {((phase.kind === 'rejected' && !stale) || phase.kind === 'not_committed') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                    </div>
                </CommandForm>
            </Card>
            {!invalid && !stale && <CommandPanels control={control} {...(committed ? { committed: t('cash.register_form.success') } : {})} />}
        </CommandLayout>
    );
}
