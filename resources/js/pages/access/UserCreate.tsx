import { router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import { ErrorSummary, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { RoleChoices } from '@/lib/access/RoleChoices';
import { fieldMessage, holdLink, leaveSavedNote, USER_COMMANDS, usersListAddress, type StaffRoleName } from '@/lib/access/users';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-603 "Tambah pengguna" (ROUTE_CONTRACTS RP-603, RC-35; COPY_DECK section 11.3;
// INTERACTION_INVENTORY UXI-336 to UXI-339; NAVIGATION_CONTRACTS UXP-603; SEC-ACC-02,
// SEC-ACC-03, SEC-ACC-05; TASK-011 scope item 4): a staff account with its roles —
// Kasir, Admin Operasional or both, never Pemilik. "Simpan dan buat tautan" is enabled
// by a valid form, never by Enter, and needs recent authentication. The answer carries
// the link once: it is held in page memory and shown by UXP-606. A request whose answer
// was lost and that committed has no link any more: the page says so and leads to the
// account, where a new link can be issued, and the form is gone, since its draft is what
// committed. An adopted request that committed is not this draft: the draft goes on and
// the final action sends it as a new request.

const DESTINATION: DestinationId = 'settings';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

export default function UserCreate() {
    const title = t('access.user_create.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <CreateForm title={title} />
        </AppShell>
    );
}

function CreateForm({ title }: { title: string }) {
    const control = useCommand(USER_COMMANDS.create);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [roles, setRoles] = useState<readonly StaffRoleName[]>([]);
    const ids = useId();

    const dirty = name !== '' || email !== '' || roles.length > 0;
    // A request of the family that this page only adopted is another command's.
    const committed = phase.kind === 'committed' && control.ownRequest;
    const adoptedDone = phase.kind === 'committed' && !control.ownRequest;
    const link = phase.kind === 'committed' ? phase.link : undefined;
    const result = phase.kind === 'committed' && control.ownRequest ? phase.result : null;

    useUnsavedChanges(dirty && !committed);

    useEffect(() => {
        if (link !== undefined && result !== null && typeof result.id === 'number' && result.location !== undefined) {
            holdLink({ userId: result.id, url: link.url, expiresAt: link.expiresAt, kind: 'FIRST_PASSWORD' });
            leaveSavedNote(t('access.user_create.success', { nama: name.trim() }));
            router.visit(result.location);
        }
    }, [link, result, name]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const valid = name.trim() !== '' && email.trim() !== '' && roles.length > 0;
    const reason = !valid ? (
        t('ux.disabled.form_incomplete')
    ) : blockedBy !== null && !busy ? (
        <>
            {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;

    const listPage = usersListAddress();
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

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: listPage, page: t('access.users.title'), onNavigate: guard }} />}>
            {invalid && <ErrorSummary errors={summary} />}
            {!committed && (
                <Card>
                    <CommandForm>
                        <Field id={nameId} label={t('access.user_form.name')} required={true} error={nameError}>
                            <TextInput value={name} maxLength={100} autoComplete={'off'} onChange={(event) => setName(event.target.value)} />
                        </Field>
                        <Field id={emailId} label={t('access.user_form.email')} required={true} hint={t('access.email_help')} error={emailError}>
                            <TextInput type={'email'} value={email} maxLength={254} autoComplete={'off'} onChange={(event) => setEmail(event.target.value)} />
                        </Field>
                        <RoleChoices id={rolesId} roles={roles} error={rolesError} onChange={setRoles} />
                        <p className={'bk-hint'}>{t('access.user_create.submit_help')}</p>
                        <div className={'bk-actions'}>
                            <Button
                                label={t('access.user_create.submit')}
                                size={'lg'}
                                enterActivates={false}
                                busy={busy}
                                disabledReason={reason}
                                onClick={() => control.submit({ displayName: name, email, roles }, {})}
                            />
                            <ButtonLink href={listPage} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                            {(phase.kind === 'rejected' || phase.kind === 'not_committed' || adoptedDone) && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                        </div>
                    </CommandForm>
                </Card>
            )}
            {!invalid && link === undefined && <CommandPanels control={control} />}
            {committed && link === undefined && result?.location !== undefined && <ButtonLink href={result.location} label={t('access.user_detail.title')} variant={'secondary'} />}
        </CommandLayout>
    );
}
