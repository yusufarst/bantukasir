import { Icon } from '@/components/ui/Icon';
import { STAFF_ROLES, type StaffRoleName } from '@/lib/access/users';
import { t } from '@/lib/i18n';

// The role choice of UXP-603 and UXP-605 (UXI-336, UXI-350; COPY_DECK sections 3 and
// 11.3; D-032): Kasir, Admin Operasional, or both, each with its description; never
// Pemilik (CAP-33). Two native checkboxes in one labelled group, the whole row the
// target of each.

export function RoleChoices({ id, roles, error, onChange }: { id: string; roles: readonly StaffRoleName[]; error: string | undefined; onChange: (roles: readonly StaffRoleName[]) => void }) {
    const toggle = (name: StaffRoleName, checked: boolean): void => {
        onChange(STAFF_ROLES.filter((role) => (role.name === name ? checked : roles.includes(role.name))).map((role) => role.name));
    };

    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;

    return (
        <fieldset id={id} className={'bk-field bk-checks'} data-invalid={error !== undefined} tabIndex={-1} aria-describedby={error === undefined ? hintId : `${errorId} ${hintId}`}>
            <legend className={'bk-label'}>{t('access.user_form.roles')}</legend>
            {STAFF_ROLES.map((role) => (
                <label key={role.name} className={'bk-check'}>
                    <input type={'checkbox'} checked={roles.includes(role.name)} onChange={(event) => toggle(role.name, event.target.checked)} />
                    <span className={'bk-check__box'} aria-hidden={true}>
                        <Icon name={'check'} size={'sm'} />
                    </span>
                    <span className={'bk-check__text'}>
                        <span>{t(role.label)}</span>
                        <span className={'bk-meta'}>{t(role.description)}</span>
                    </span>
                </label>
            ))}
            {error !== undefined && (
                <p className={'bk-error'} id={errorId}>
                    <Icon name={'danger'} size={'sm'} />
                    <span>{error}</span>
                </p>
            )}
            <p className={'bk-hint'} id={hintId}>
                {t('access.role.choice_help')}
            </p>
        </fieldset>
    );
}
