import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Dialog } from '@/components/ui/Dialog';
import { Field, TextInput } from '@/components/ui/Field';
import { Menu, type MenuEntry } from '@/components/ui/Menu';
import type { StateId } from '@/components/ui/stateCatalogue';
import { Committed, StateNotice } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { useWidthClass } from '@/layouts/widthClass';
import { holdLink, rolesText, statusText, takeSavedNote, USER_COMMANDS, userPage, usersListAddress, type RoleCode, type StatusCode } from '@/lib/access/users';
import { blockingRequest, committedText } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand } from '@/lib/commands/useCommand';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// UXP-604 "Detail pengguna" (ROUTE_CONTRACTS RP-604, RC-38 to RC-42; COPY_DECK section
// 11.4; INTERACTION_INVENTORY UXI-341 to UXI-349; NAVIGATION_CONTRACTS UXP-604; TASK-011
// scope item 4): one account, its roles, status and link, and what the Owner may do with
// it. The active Owner's own account has none of the controls (UXI-348, CAP-33) and says
// why; a former Owner may only be anonymized. Each command asks in a dialog that Enter
// never confirms and needs recent authentication; it updates the page in place with its
// message, and issuing a link leads to UXP-606. An unresolved request of the family blocks
// the others, but never Nonaktifkan or Cabut tautan (NC-13 (d)). TASK-030 adds the OPEN
// cash session, and TASK-042 the way back to UXP-612 (UXI-349).

const DESTINATION: DestinationId = 'settings';

const STALE_STATE: StateId = 'UXS-17';

const STALE_VERSION = 'STALE_VERSION';

const USER_PROP = 'user';

interface Account {
    readonly id: number;
    readonly displayName: string;
    readonly email: string | null;
    readonly roles: readonly RoleCode[];
    readonly status: StatusCode;
    readonly version: number;
    readonly passwordSet: boolean;
    readonly linkExpiresAt: string | null;
    readonly superAdmin: boolean;
    readonly self: boolean;
}

/** The commands of this page, each with its dialog. */
type ActionId = 'issue_reset' | 'issue_first' | 'revoke' | 'deactivate' | 'reactivate' | 'anonymize';

const ISSUE_RESET: ActionId = 'issue_reset';

const ISSUE_FIRST: ActionId = 'issue_first';

const REVOKE: ActionId = 'revoke';

const DEACTIVATE: ActionId = 'deactivate';

const REACTIVATE: ActionId = 'reactivate';

const ANONYMIZE: ActionId = 'anonymize';

const COMMAND_OF = {
    issue_reset: USER_COMMANDS.issueLink,
    issue_first: USER_COMMANDS.issueLink,
    revoke: USER_COMMANDS.revokeLink,
    deactivate: USER_COMMANDS.deactivate,
    reactivate: USER_COMMANDS.reactivate,
    anonymize: USER_COMMANDS.anonymize,
} as const satisfies Readonly<Record<ActionId, (typeof USER_COMMANDS)[keyof typeof USER_COMMANDS]>>;

export default function User({ user }: { user: Account }) {
    const title = t('access.user_detail.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <UserPage title={title} user={user} />
        </AppShell>
    );
}

function UserPage({ title, user }: { title: string; user: Account }) {
    const [note, setNote] = useState(takeSavedNote);
    const [stale, setStale] = useState(false);
    const [action, setAction] = useState<ActionId | null>(null);
    // A new key for each opening: its command starts again with a new intent.
    const [opening, setOpening] = useState(0);
    const [listPage] = useState(usersListAddress);
    const unresolved = useUnresolved();
    const active = user.status === 'ACTIVE';
    const deactivated = user.status === 'DEACTIVATED';
    const staff = !user.superAdmin;

    const open = (next: ActionId): void => {
        setNote(null);
        setStale(false);
        setOpening((current) => current + 1);
        setAction(next);
    };

    const reasonOf = (next: ActionId) =>
        blockingRequest(unresolved.union, COMMAND_OF[next].operation, COMMAND_OF[next].family) === null ? undefined : (
            <>
                {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
            </>
        );

    const items: KeyValue[] = [
        { key: 'name', label: t('access.account.name'), value: user.displayName },
        { key: 'email', label: t('access.account.email'), value: user.email ?? t('access.user.anonymized_email') },
        { key: 'roles', label: t('access.account.roles'), value: rolesText(user.roles) },
        { key: 'status', label: t('access.users.col_status'), value: statusText(user.status) },
        {
            key: 'link',
            label: t('access.user_detail.link_status'),
            value: user.linkExpiresAt === null ? t('access.link.status_none') : t('access.link.status_active', { waktu: formatDateTime(user.linkExpiresAt) }),
        },
    ];

    const passwordNotSet = !user.passwordSet && user.status !== 'ANONYMIZED';
    const editPage = `${userPage(user.id)}/edit`;

    // What this account allows, in the order of COPY_DECK section 11.4.
    const available: { readonly id: ActionId; readonly label: string; readonly danger: boolean }[] = [];

    if (staff && active && user.passwordSet) {
        available.push({ id: ISSUE_RESET, label: t('access.link.issue_reset'), danger: false });
    }

    if (staff && active && !user.passwordSet) {
        available.push({ id: ISSUE_FIRST, label: t('access.link.reissue_first'), danger: false });
    }

    if (staff && user.linkExpiresAt !== null) {
        available.push({ id: REVOKE, label: t('access.link.revoke'), danger: false });
    }

    if (staff && active) {
        available.push({ id: DEACTIVATE, label: t('ux.action.deactivate'), danger: true });
    }

    if (staff && deactivated) {
        available.push({ id: REACTIVATE, label: t('ux.action.reactivate'), danger: false });
    }

    if (deactivated) {
        available.push({ id: ANONYMIZE, label: t('access.user.anonymize'), danger: true });
    }

    // On a phone every action is in one menu (NAVIGATION_CONTRACTS UXP-604), and
    // otherwise "Cabut tautan" is the overflow (UXI-344). A menu item cannot show
    // why it waits (UXS-13), so an action that a request blocks stays a button.
    const compact = useWidthClass() === 'compact';
    const inMenu = available.filter((entry) => reasonOf(entry.id) === undefined && (compact || entry.id === REVOKE));
    const asButtons = available.filter((entry) => !inMenu.includes(entry));
    const menuItems: MenuEntry[] = inMenu.map((entry) => ({ kind: 'action', label: entry.label, danger: entry.danger, onSelect: () => open(entry.id) }));

    return (
        <PageContainer width={'form'}>
            <PageHeader
                title={title}
                back={{ href: listPage, page: t('access.users.title') }}
                actions={staff && user.status !== 'ANONYMIZED' ? <ButtonLink href={editPage} label={t('access.user_edit.title')} variant={'primary'} /> : undefined}
            />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                {stale && <StateNotice state={STALE_STATE} />}
                <Card>
                    <div className={'bk-stack'}>
                        <KeyValueList items={items} />
                        {passwordNotSet && <p className={'bk-meta'}>{t('access.user_detail.password_not_set')}</p>}
                        {user.self && <p className={'bk-meta'}>{t('access.user_detail.owner_note')}</p>}
                        <div className={'bk-actions'}>
                            {asButtons.map((entry) => (
                                <Button key={entry.id} label={entry.label} variant={entry.danger ? 'danger' : 'secondary'} enterActivates={false} disabledReason={reasonOf(entry.id)} onClick={() => open(entry.id)} />
                            ))}
                            {menuItems.length > 0 && <Menu label={t('ux.action.more_actions')} items={menuItems} />}
                        </div>
                    </div>
                </Card>
                {action !== null && (
                    <ActionRun
                        key={opening}
                        action={action}
                        user={user}
                        onClose={() => setAction(null)}
                        onDone={(text) => {
                            setAction(null);
                            setNote(text);
                            router.reload({ only: [USER_PROP] });
                        }}
                        onStale={() => {
                            setAction(null);
                            setStale(true);
                            router.reload({ only: [USER_PROP] });
                        }}
                    />
                )}
            </div>
        </PageContainer>
    );
}

/** The words of each dialog (COPY_DECK section 11.4). */
function wordsOf(action: ActionId, name: string): { title: string; body: string; confirm: string; done: string; danger: boolean } {
    const hidden = t('commands.resolution.committed_link_hidden');

    switch (action) {
        case 'issue_reset':
            return { title: t('access.link.issue_reset_confirm.title', { nama: name }), body: t('access.link.issue_confirm.body'), confirm: t('access.link.issue'), done: hidden, danger: false };
        case 'issue_first':
            return { title: t('access.link.reissue_first_confirm.title', { nama: name }), body: t('access.link.issue_confirm.body'), confirm: t('access.link.issue'), done: hidden, danger: false };
        case 'revoke':
            return { title: t('access.link.revoke_confirm.title', { nama: name }), body: t('access.link.revoke_confirm.body'), confirm: t('access.link.revoke'), done: t('access.link.revoked'), danger: false };
        case 'deactivate':
            return { title: t('access.user.deactivate_confirm.title', { nama: name }), body: t('access.user.deactivate_confirm.body'), confirm: t('ux.action.deactivate'), done: t('access.user.deactivated'), danger: true };
        case 'reactivate':
            return { title: t('access.user.reactivate_confirm.title', { nama: name }), body: t('access.user.reactivate_confirm.body'), confirm: t('ux.action.reactivate'), done: t('access.user.reactivated'), danger: false };
        case 'anonymize':
            return { title: t('access.user.anonymize_confirm.title', { nama: name }), body: t('access.user.anonymize_confirm.body'), confirm: t('access.user.anonymize'), done: t('access.user.anonymized'), danger: true };
    }
}

/**
 * One command of the page: its dialog until the Owner confirms, then its panels —
 * the recent-authentication dialog, UXS-14 and UXS-15 with their actions, and a
 * refusal's state. Its hook lives while the action runs, so that a new opening is
 * a new intent (ADR-007 section 2).
 */
function ActionRun({ action, user, onClose, onDone, onStale }: { action: ActionId; user: Account; onClose: () => void; onDone: (text: string) => void; onStale: () => void }) {
    const control = useCommand(COMMAND_OF[action]);
    const { phase } = control;
    const [reason, setReason] = useState('');
    const words = wordsOf(action, user.displayName);
    const asking = phase.kind === 'idle' || phase.kind === 'submitting';
    const stale = phase.kind === 'rejected' && phase.refusal.code === STALE_VERSION;

    useEffect(() => {
        if (stale) {
            onStale();
        }
    }, [stale]);

    const committed = phase.kind === 'committed';
    const link = committed ? phase.link : undefined;
    const location = committed ? phase.result?.location : undefined;

    useEffect(() => {
        if (!committed) {
            return;
        }

        if (link !== undefined && location !== undefined) {
            holdLink({ userId: user.id, url: link.url, expiresAt: link.expiresAt, kind: action === 'issue_first' ? 'FIRST_PASSWORD' : 'RESET' });
            router.visit(location);

            return;
        }

        // A request of the family that this run only adopted is another command's:
        // the page says what is true of it, never this action's success (R-01).
        onDone(control.ownRequest ? words.done : committedText(control.target?.operation ?? ''));
    }, [committed, link, location]);

    const confirm = (): void => {
        const expected = { userVersion: user.version };

        switch (action) {
            case 'issue_reset':
                control.submit({ userId: user.id, kind: 'RESET' }, expected);

                return;
            case 'issue_first':
                control.submit({ userId: user.id, kind: 'FIRST_PASSWORD' }, expected);

                return;
            case 'anonymize':
                control.submit({ userId: user.id, reason }, expected);

                return;
            default:
                control.submit({ userId: user.id }, expected);
        }
    };

    const busy = phase.kind === 'submitting';
    const needsReason = action === 'anonymize' && reason.trim() === '';

    return (
        <>
            <Dialog
                open={asking}
                onOpenChange={(next) => (next ? undefined : onClose())}
                title={words.title}
                description={<p>{words.body}</p>}
                dismissible={!busy}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} data-initial-focus={true} onClick={onClose} />
                        <Button label={words.confirm} variant={words.danger ? 'danger' : 'primary'} enterActivates={false} busy={busy} disabledReason={needsReason ? t('ux.disabled.form_incomplete') : undefined} onClick={confirm} />
                    </>
                }
            >
                {action === 'anonymize' && (
                    <Field label={t('access.user.anonymize_reason')} required={true}>
                        <TextInput value={reason} maxLength={1000} autoComplete={'off'} onChange={(event) => setReason(event.target.value)} />
                    </Field>
                )}
            </Dialog>
            {!asking && !stale && !committed && (
                <div className={'bk-stack'}>
                    <CommandPanels control={control} />
                    {(phase.kind === 'rejected' || phase.kind === 'not_committed' || phase.kind === 'throttled') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={onClose} />}
                </div>
            )}
        </>
    );
}
