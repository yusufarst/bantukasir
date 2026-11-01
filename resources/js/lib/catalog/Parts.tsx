import type { KeyboardEvent, ReactNode, Ref } from 'react';
import { Field, SelectInput, TextInput } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { blockingRequest } from '@/lib/commands/families';
import type { UnresolvedRequest } from '@/lib/commands/unresolved';
import { PRECISIONS, type Category } from '@/lib/catalog/products';
import { t } from '@/lib/i18n';

// What the catalogue pages share on screen (DESIGN_SYSTEM 8.3; COPY_DECK section 8.1;
// BR-CAT-44; TASK-017): a product's thumbnail or placeholder, the field that takes a
// scan, the lists of a product form, and the reason of a command that an unresolved
// request blocks (UXS-13, NC-13).

const UNRESOLVED_PAGE = '/requests';

/** The product's thumbnail through RD-07, or the placeholder of a product without a photo. */
export function ProductThumb({ name, src }: { name: string; src: string | null }) {
    return src === null ? (
        <span className={'bk-thumb'} role={'img'} aria-label={t('catalog.photo.placeholder_alt')}>
            <Icon name={'image'} />
        </span>
    ) : (
        <img className={'bk-thumb'} src={src} alt={t('catalog.photo.thumbnail_alt', { produk: name })} width={40} height={40} />
    );
}

/**
 * A field that accepts a scan (UXA-11): a scan is ordinary typing that ends with Enter,
 * which `onEnter` turns into a lookup, or nothing at all (UXA-10); it never submits.
 */
export function CodeField({
    id,
    label,
    hint,
    error,
    optional = false,
    value,
    onValueChange,
    onEnter,
    inputRef,
}: {
    id?: string;
    label: string;
    hint?: ReactNode;
    error?: ReactNode;
    optional?: boolean;
    value: string;
    onValueChange: (value: string) => void;
    onEnter?: () => void;
    inputRef?: Ref<HTMLInputElement>;
}) {
    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Enter' && !event.ctrlKey) {
            event.preventDefault();
            onEnter?.();
        }
    };

    return (
        <Field id={id} label={label} hint={hint} error={error} optional={optional}>
            <TextInput ref={inputRef} icon={'scan'} type={'text'} autoComplete={'off'} enterKeyHint={'search'} spellCheck={false} maxLength={128} value={value} onChange={(event) => onValueChange(event.target.value)} onKeyDown={onKeyDown} />
        </Field>
    );
}

/** The word of an inactive category in a list. */
export function categoryOption(category: Category): string {
    return category.active ? category.name : `${category.name} (${t('catalog.status.inactive')})`;
}

/** A form's choice of category (UXI-182, UXI-186): those the form offers, and none at first. */
export function CategoryField({ id, categories, value, error, onChange }: { id?: string; categories: readonly Category[]; value: string; error?: ReactNode; onChange: (value: string) => void }) {
    return (
        <Field id={id} label={t('catalog.field.category')} required={true} error={error}>
            <SelectInput value={value} onChange={(event) => onChange(event.target.value)}>
                <option value={''} />
                {categories.map((category) => (
                    <option key={category.id} value={String(category.id)}>
                        {categoryOption(category)}
                    </option>
                ))}
            </SelectInput>
        </Field>
    );
}

/** A form's precision (UXI-182, UXI-186), 0 to 3 decimals (BR-NUM-02). */
export function PrecisionField({ id, value, disabled = false, hint, error, onChange }: { id?: string; value: string; disabled?: boolean; hint: ReactNode; error?: ReactNode; onChange: (value: string) => void }) {
    return (
        <Field id={id} label={t('catalog.field.precision')} required={true} hint={hint} error={error}>
            <SelectInput value={value} disabled={disabled} onChange={(event) => onChange(event.target.value)}>
                {PRECISIONS.map((precision) => (
                    <option key={precision} value={String(precision)}>
                        {String(precision)}
                    </option>
                ))}
            </SelectInput>
        </Field>
    );
}

/** The reason of a control whose family holds an unresolved request, with the way to UXP-006 (NC-13 (a)). */
export function UnresolvedReason() {
    return (
        <>
            {t('ux.disabled.unresolved_family')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
}

/** The reason of a command of this family, when a request of the user blocks it (NC-13 (a)). */
export function blockedReason(unresolved: readonly UnresolvedRequest[], command: { readonly operation: string; readonly family: string }): ReactNode | undefined {
    return blockingRequest(unresolved, command.operation, command.family) === null ? undefined : <UnresolvedReason />;
}
