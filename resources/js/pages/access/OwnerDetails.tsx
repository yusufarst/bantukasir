import { router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList } from '@/components/ui/Data';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { fieldMessage, leaveSavedNote, USER_COMMANDS } from '@/lib/access/users';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';

// UXP-005 "Ubah nama dan email" (ROUTE_CONTRACTS RP-005, RC-37; COPY_DECK section
// 6.6; INTERACTION_INVENTORY UXI-051 to UXI-054; NAVIGATION_CONTRACTS UXP-005;
// SEC-ACC-10; TASK-011 scope item 2): the Owner's own display name and email. "Simpan
// perubahan" is enabled once a field changed (UXS-13), never by Enter (UXA-10), and
// needs recent authentication (UXS-11). A refusal shows at its field and in the
// summary (UXS-05); a version changed meanwhile shows the current values (UXS-17).
// Success leads to UXP-003 with its message; leaving with changes asks first (UXS-45).

const ACCOUNT = '/account';

const STALE_STATE: StateId = 'UXS-17';

const STALE_VERSION = 'STALE_VERSION';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const ACCOUNT_PROP = 'account';

interface Details {
    readonly displayName: string;
    readonly email: string;
    readonly version: number;
}

export default function OwnerDetails({ account }: { account: Details }) {
    const title = t('access.owner_details.title');

    return (
        <AppShell title={title}>
            <DetailsForm title={title} account={account} />
        </AppShell>
    );
}

function DetailsForm({ title, account }: { title: string; account: Details }) {
    const control = useCommand(USER_COMMANDS.ownerDetails);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const [name, setName] = useState(account.displayName);
    const [email, setEmail] = useState(account.email);
    const ids = useId();

    const changed = name !== account.displayName || email !== account.email;
    const committed = phase.kind === 'committed';
    const location = committed ? phase.result?.location : undefined;

    useUnsavedChanges(changed && !committed);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('access.owner_details.success'));
            router.visit(location);
        }
    }, [location]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const invalid = refusal?.code === VALIDATION_FAILED;

    useEffect(() => {
        if (stale) {
            router.reload({ only: [ACCOUNT_PROP] });
        }
    }, [stale]);

    const busy = phase.kind === 'submitting';
    const reason = !changed ? (
        t('ux.disabled.no_change')
    ) : blockedBy !== null && !busy ? (
        <>
            {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;

    const nameId = `${ids}-name`;
    const emailId = `${ids}-email`;
    const nameError = refusal === null ? undefined : fieldMessage(refusal.fields['payload.displayName']);
    const emailError = refusal === null ? undefined : fieldMessage(refusal.fields['payload.email']);
    const summary: FieldError[] = [];

    if (nameError !== undefined) {
        summary.push({ fieldId: nameId, label: t('access.owner_details.name'), message: nameError });
    }

    if (emailError !== undefined) {
        summary.push({ fieldId: emailId, label: t('access.owner_details.email'), message: emailError });
    }

    const submit = (): void => {
        control.submit({ displayName: name, email }, { userVersion: account.version });
    };

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: ACCOUNT, page: t('access.account.title'), onNavigate: guard }} />}>
            {invalid && <ErrorSummary errors={summary} />}
            {stale && (
                <>
                    <StateNotice state={STALE_STATE} />
                    <KeyValueList
                        items={[
                            { key: 'name', label: t('access.owner_details.name'), value: account.displayName },
                            { key: 'email', label: t('access.owner_details.email'), value: account.email },
                        ]}
                    />
                </>
            )}
            <Card>
                <CommandForm>
                    <Field id={nameId} label={t('access.owner_details.name')} required={true} error={nameError}>
                        <TextInput value={name} maxLength={100} autoComplete={'off'} onChange={(event) => setName(event.target.value)} />
                    </Field>
                    <Field id={emailId} label={t('access.owner_details.email')} required={true} hint={t('access.email_help')} error={emailError}>
                        <TextInput type={'email'} value={email} maxLength={254} autoComplete={'off'} onChange={(event) => setEmail(event.target.value)} />
                    </Field>
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.save_changes')} size={'lg'} enterActivates={false} busy={busy} disabledReason={reason} onClick={submit} />
                        <ButtonLink href={ACCOUNT} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                        {/* After UXS-17 the current values stay above the form until the next save. */}
                        {((phase.kind === 'rejected' && !stale) || phase.kind === 'not_committed') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                    </div>
                </CommandForm>
            </Card>
            {!invalid && <CommandPanels control={control} committed={t('access.owner_details.success')} />}
        </CommandLayout>
    );
}
