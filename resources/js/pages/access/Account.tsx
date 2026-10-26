import { Head } from '@inertiajs/react';
import { ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Committed, StateNotice } from '@/components/ui/States';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { AUTH_STATES } from '@/lib/authAnswer';
import { t } from '@/lib/i18n';
import { useOnline } from '@/lib/online';
import type { TranslationKey } from '@/types/translation-keys';

// RP-003, UXP-003 "Akun saya" (INTERACTION_INVENTORY UXI-045; COPY_DECK section
// 6.4; TASK-006): the user's own display name, email and roles, from the DTO of
// App\DTO\Access\OwnAccountData. Staff are told that the Owner changes their
// name and email (SEC-ACC-10); the Owner's own "Ubah nama dan email" (UXI-046)
// comes with its page in TASK-011. Reached from the account menu, so it has no
// back action (NAVIGATION_CONTRACTS UXP-003); TASK-007 frames it in the shell.

type RoleCode = 'SUPER_ADMIN' | 'OPERATIONS_ADMIN' | 'CASHIER';

interface OwnAccount {
    readonly displayName: string;
    readonly email: string;
    readonly roles: readonly RoleCode[];
    readonly owner: boolean;
}

const ROLE_LABELS: Readonly<Record<RoleCode, TranslationKey>> = {
    SUPER_ADMIN: 'access.role.super_admin',
    OPERATIONS_ADMIN: 'access.role.operations_admin',
    CASHIER: 'access.role.cashier',
};

export default function Account({ account, passwordChanged }: { account: OwnAccount; passwordChanged: boolean }) {
    const online = useOnline();
    const roles = t('access.role.list', { peran: account.roles.map((role) => t(ROLE_LABELS[role])).join(', ') });
    const items: KeyValue[] = [
        { key: 'name', label: t('access.account.name'), value: account.displayName },
        { key: 'email', label: t('access.account.email'), value: account.email },
        { key: 'roles', label: t('access.account.roles'), value: roles },
    ];

    return (
        <main className={'bk-page'}>
            <Head title={t('ux.shell.document_title_public', { halaman: t('access.account.title') })} />
            <PageContainer width={'form'}>
                <div className={'bk-stack'}>
                    <PageHeader title={t('access.account.title')} />
                    {!online && <StateNotice state={AUTH_STATES.offline} />}
                    {passwordChanged && <Committed message={t('access.change_password.success')} />}
                    <Card>
                        <div className={'bk-stack'}>
                            <KeyValueList items={items} />
                            {!account.owner && <p className={'bk-meta'}>{t('access.account.staff_note')}</p>}
                            <div className={'bk-actions'}>
                                <ButtonLink href={'/account/password'} label={t('access.change_password.title')} variant={'primary'} />
                            </div>
                        </div>
                    </Card>
                </div>
            </PageContainer>
        </main>
    );
}
