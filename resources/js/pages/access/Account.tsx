import { useState } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Committed } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// RP-003, UXP-003 "Akun saya" (INTERACTION_INVENTORY UXI-045; COPY_DECK section
// 6.4; TASK-006): the user's own display name, email and roles, from the DTO of
// App\DTO\Access\OwnAccountData. Staff are told that the Owner changes their
// name and email (SEC-ACC-10); the Owner has "Ubah nama dan email" (UXI-046,
// TASK-011) and the description of the role. Reached from the account menu, so it has no
// back action (NAVIGATION_CONTRACTS UXP-003). It is framed by the shell
// (UXP-000, TASK-007), which shows the no-connection state (UXS-20); until the
// roles' own homes exist it is every role's home (RN-01).

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
    const roles = t('access.role.list', { peran: account.roles.map((role) => t(ROLE_LABELS[role])).join(', ') });
    const items: KeyValue[] = [
        { key: 'name', label: t('access.account.name'), value: account.displayName },
        { key: 'email', label: t('access.account.email'), value: account.email },
        { key: 'roles', label: t('access.account.roles'), value: roles },
    ];
    const title = t('access.account.title');
    const [saved] = useState(takeSavedNote);

    return (
        <AppShell title={title}>
            <PageContainer width={'form'}>
                <div className={'bk-stack'}>
                    <PageHeader title={title} />
                    {passwordChanged && <Committed message={t('access.change_password.success')} />}
                    {saved !== null && <Committed message={saved} />}
                    <Card>
                        <div className={'bk-stack'}>
                            <KeyValueList items={items} />
                            {account.owner && <p className={'bk-meta'}>{t('access.role.super_admin.description')}</p>}
                            {!account.owner && <p className={'bk-meta'}>{t('access.account.staff_note')}</p>}
                            <div className={'bk-actions'}>
                                <ButtonLink href={'/account/password'} label={t('access.change_password.title')} variant={'primary'} />
                                {account.owner && <ButtonLink href={'/account/details'} label={t('access.owner_details.title')} variant={'secondary'} />}
                            </div>
                        </div>
                    </Card>
                </div>
            </PageContainer>
        </AppShell>
    );
}
