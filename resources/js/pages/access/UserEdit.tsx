import { router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { RoleChoices } from '@/lib/access/RoleChoices';
import { fieldMessage, leaveSavedNote, rolesText, STAFF_ROLES, USER_COMMANDS, userPage, type RoleCode, type StaffRoleName } from '@/lib/access/users';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-605 "Ubah pengguna" (ROUTE_CONTRACTS RP-605, RC-36; COPY_DECK section 11.5;
// INTERACTION_INVENTORY UXI-350 to UXI-353; NAVIGATION_CONTRACTS UXP-605; SEC-ACC-04;
// TASK-011 scope item 4): a staff member's display name, email and roles. "Simpan
// perubahan" is enabled once a field changed (UXS-13), never by Enter, and needs recent
// authentication. A version changed meanwhile shows the current values (UXS-17), and the
// next save is expected against them. Success leads to UXP-604 with its message; leaving
// with changes asks first (UXS-45).

const DESTINATION: DestinationId = 'settings';

const STALE_STATE: StateId = 'UXS-17';

const STALE_VERSION = 'STALE_VERSION';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const USER_PROP = 'user';

interface EditedUser {
    readonly id: number;
    readonly displayName: string;
    readonly email: string;
    readonly roles: readonly RoleCode[];
    readonly version: number;
}

function roleNames(roles: readonly RoleCode[]): readonly StaffRoleName[] {
    return STAFF_ROLES.filter((role) => roles.includes(role.code)).map((role) => role.name);
}

export default function UserEdit({ user }: { user: EditedUser }) {
    const title = t('access.user_edit.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <EditForm title={title} user={user} />
        </AppShell>
    );
}

function EditForm({ title, user }: { title: string; user: EditedUser }) {
    const control = useCommand(USER_COMMANDS.update);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const [name, setName] = useState(user.displayName);
    const [email, setEmail] = useState(user.email);
    const [roles, setRoles] = useState<readonly StaffRoleName[]>(roleNames(user.roles));
    const ids = useId();
    const detail = userPage(user.id);

    const current = roleNames(user.roles);
    const rolesChanged = roles.length !== current.length || roles.some((role) => !current.includes(role));
    const changed = name !== user.displayName || email !== user.email || rolesChanged;
    const committed = phase.kind === 'committed';
    const location = committed ? phase.result?.location : undefined;

    useUnsavedChanges(changed && !committed);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('access.user_edit.success'));
            router.visit(location);
        }
    }, [location]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const invalid = refusal?.code === VALIDATION_FAILED;

    useEffect(() => {
        if (stale) {
            router.reload({ only: [USER_PROP] });
        }
    }, [stale]);

    const busy = phase.kind === 'submitting';
    const reason = !changed ? (
        t('ux.disabled.no_change')
    ) : roles.length === 0 || name.trim() === '' || email.trim() === '' ? (
        t('ux.disabled.form_incomplete')
    ) : blockedBy !== null && !busy ? (
        <>
            {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;

    const nameId = `${ids}-name`;
    const emailId = `${ids}-email`;
    const rolesId = `${ids}-roles`;
    const nameError = refusal === null ? undefined : fieldMessage(refusal.fields['payload.displayName']);
    const emailError = refusal === null ? undefined : fieldMessage(refusal.fields['payload.email']);
    const rolesError = refusal === null ? undefined : fieldMessage(refusal.fields['payload.roles']);
    const summary: FieldError[] = [];

    if (nameError !== undefined) {
        summary.push({ fieldId: nameId, label: t('access.user_form.name'), message: nameError });
    }

    if (emailError !== undefined) {
        summary.push({ fieldId: emailId, label: t('access.user_form.email'), message: emailError });
    }

    if (rolesError !== undefined) {
        summary.push({ fieldId: rolesId, label: t('access.user_form.roles'), message: rolesError });
    }

    const currentValues: KeyValue[] = [
        { key: 'name', label: t('access.user_form.name'), value: user.displayName },
        { key: 'email', label: t('access.user_form.email'), value: user.email },
        { key: 'roles', label: t('access.user_form.roles'), value: rolesText(user.roles) },
    ];

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: detail, page: t('access.user_detail.title'), onNavigate: guard }} />}>
            {invalid && <ErrorSummary errors={summary} />}
            {stale && (
                <>
                    <StateNotice state={STALE_STATE} />
                    <KeyValueList items={currentValues} />
                </>
            )}
            <Card>
                <CommandForm>
                    <Field id={nameId} label={t('access.user_form.name')} required={true} error={nameError}>
                        <TextInput value={name} maxLength={100} autoComplete={'off'} onChange={(event) => setName(event.target.value)} />
                    </Field>
                    <Field id={emailId} label={t('access.user_form.email')} required={true} hint={t('access.user_edit.email_change_help')} error={emailError}>
                        <TextInput type={'email'} value={email} maxLength={254} autoComplete={'off'} onChange={(event) => setEmail(event.target.value)} />
                    </Field>
                    <RoleChoices id={rolesId} roles={roles} error={rolesError} onChange={setRoles} />
                    <p className={'bk-hint'}>{t('access.user_edit.role_change_help')}</p>
                    <div className={'bk-actions'}>
                        <Button
                            label={t('ux.action.save_changes')}
                            size={'lg'}
                            enterActivates={false}
                            busy={busy}
                            disabledReason={reason}
                            onClick={() => control.submit({ userId: user.id, displayName: name, email, roles }, { userVersion: user.version })}
                        />
                        <ButtonLink href={detail} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                        {/* After UXS-17 the current values stay above the form until the next save. */}
                        {((phase.kind === 'rejected' && !stale) || phase.kind === 'not_committed') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                    </div>
                </CommandForm>
            </Card>
            {!invalid && <CommandPanels control={control} committed={t('access.user_edit.success')} />}
        </CommandLayout>
    );
}
