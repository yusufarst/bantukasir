import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { EmptyState, NoResults } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { rememberUsersFilters, rolesText, statusText, userPage, USERS_PAGE, type RoleCode, type StatusCode } from '@/lib/access/users';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import type { TranslationKey } from '@/types/translation-keys';

// UXP-602 "Pengguna" (ROUTE_CONTRACTS RP-602; COPY_DECK section 11.2;
// INTERACTION_INVENTORY UXI-332 to UXI-335; NAVIGATION_CONTRACTS UXP-602; TASK-011
// scope item 4): every account with its roles and status, its row leading to UXP-604;
// the filters are the page's address (`status`, `role`), which its child pages' way back
// returns to (NC-10); "Tambah pengguna" leads to UXP-603. TASK-027 adds the mark of an OPEN
// cash session. An account's row becomes a card on a phone (DataTable).

const DESTINATION: DestinationId = 'settings';

const SETTINGS = '/settings';

const CREATE = `${USERS_PAGE}/new`;

interface Row {
    readonly id: number;
    readonly displayName: string;
    readonly email: string | null;
    readonly roles: readonly RoleCode[];
    readonly status: StatusCode;
}

interface Filters {
    readonly status: string | null;
    readonly role: string | null;
}

const STATUS_FILTERS: readonly { readonly value: string; readonly label: TranslationKey }[] = [
    { value: 'active', label: 'access.user.status.active' },
    { value: 'deactivated', label: 'access.user.status.deactivated' },
    { value: 'anonymized', label: 'access.user.status.anonymized' },
];

const ROLE_FILTERS: readonly { readonly value: string; readonly label: TranslationKey }[] = [
    { value: 'owner', label: 'access.role.super_admin' },
    { value: 'cashier', label: 'access.role.cashier' },
    { value: 'operations_admin', label: 'access.role.operations_admin' },
];

function visit(filters: Filters): void {
    const query: Record<string, string> = {};

    if (filters.status !== null) {
        query.status = filters.status;
    }

    if (filters.role !== null) {
        query.role = filters.role;
    }

    router.get(USERS_PAGE, query, { preserveScroll: true });
}

export default function Users({ users, filters }: { users: readonly Row[]; filters: Filters }) {
    const title = t('access.users.title');
    const filtered = filters.status !== null || filters.role !== null;
    const columns: readonly Column<Row>[] = [
        { id: 'name', header: t('access.users.col_name'), isHead: true, cell: (row) => <a className={'bk-table-link'} href={userPage(row.id)}>{row.displayName}</a> },
        { id: 'email', header: t('access.users.col_email'), cell: (row) => row.email ?? t('access.user.anonymized_email') },
        { id: 'roles', header: t('access.users.col_roles'), cell: (row) => rolesText(row.roles) },
        { id: 'status', header: t('access.users.col_status'), cell: (row) => statusText(row.status) },
    ];
    const noStaff = !filtered && users.every((row) => row.roles.includes('SUPER_ADMIN'));

    // The way back of its child pages returns to these filters (NC-10).
    useEffect(() => {
        rememberUsersFilters(window.location.search);
    }, [filters.status, filters.role]);

    return (
        <AppShell title={title} current={DESTINATION}>
            <ListLayout
                header={
                    <PageHeader title={title} back={{ href: SETTINGS, page: t('config.settings.title') }} actions={<ButtonLink href={CREATE} label={t('access.user_create.title')} variant={'primary'} />} />
                }
                toolbar={
                    <>
                        <FilterChips label={t('access.users.filter_status')}>
                            {STATUS_FILTERS.map((filter) => (
                                <FilterChip key={filter.value} label={t(filter.label)} pressed={filters.status === filter.value} onPressedChange={(pressed) => visit({ ...filters, status: pressed ? filter.value : null })} />
                            ))}
                        </FilterChips>
                        <FilterChips label={t('access.users.filter_role')}>
                            {ROLE_FILTERS.map((filter) => (
                                <FilterChip key={filter.value} label={t(filter.label)} pressed={filters.role === filter.value} onPressedChange={(pressed) => visit({ ...filters, role: pressed ? filter.value : null })} />
                            ))}
                        </FilterChips>
                    </>
                }
            >
                {users.length > 0 ? (
                    <DataTable caption={title} columns={columns} rows={users} rowKey={(row) => String(row.id)} />
                ) : (
                    <NoResults onClear={() => visit({ status: null, role: null })} />
                )}
                {noStaff && <EmptyState body={t('access.users.empty')} />}
            </ListLayout>
        </AppShell>
    );
}
