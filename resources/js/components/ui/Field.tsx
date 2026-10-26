import { createContext, useContext, useId, useState, type FormEvent, type InputHTMLAttributes, type KeyboardEvent, type ReactNode, type Ref } from 'react';
import { cn } from '@/lib/cn';
import { RUPIAH_SYMBOL } from '@/lib/format';
import { t } from '@/lib/i18n';
import { Icon } from './Icon';
import type { IconName } from './icons';
import { ENTER_BLOCKED_ATTRIBUTE } from './shortcuts';

interface FieldContext {
    readonly id: string;
    readonly describedBy: string | undefined;
    readonly invalid: boolean;
    readonly required: boolean;
}

const FieldIds = createContext<FieldContext | null>(null);

/**
 * A field of DESIGN_SYSTEM 8.3: the label above, the control, the hint below
 * and the error tied to the control; an optional field is marked "(opsional)"
 * (COPY_DECK section 5). Hints never replace the label (CW-12).
 */
export function Field({
    id: givenId,
    label,
    hint,
    error,
    optional = false,
    required = false,
    children,
    className,
}: {
    /** The control's id, when another element links to it (UXS-05). */
    id?: string;
    label: string;
    hint?: ReactNode;
    error?: ReactNode;
    optional?: boolean;
    required?: boolean;
    children: ReactNode;
    className?: string;
}) {
    const generated = useId();
    const id = givenId ?? generated;
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;
    const describedBy = [error !== undefined ? errorId : undefined, hint !== undefined ? hintId : undefined].filter((value) => value !== undefined).join(' ');

    return (
        <div className={cn('bk-field', className)} data-invalid={error !== undefined}>
            <label className={'bk-label'} htmlFor={id}>
                {label}
                {optional && <span className={'bk-label__opt'}> {t('ux.action.optional')}</span>}
            </label>
            <FieldIds.Provider value={{ id, describedBy: describedBy === '' ? undefined : describedBy, invalid: error !== undefined, required }}>{children}</FieldIds.Provider>
            {error !== undefined && (
                <p className={'bk-error'} id={errorId}>
                    <Icon name={'danger'} size={'sm'} />
                    <span>{error}</span>
                </p>
            )}
            {hint !== undefined && (
                <p className={'bk-hint'} id={hintId}>
                    {hint}
                </p>
            )}
        </div>
    );
}

export type InputSize = 'md' | 'lg' | 'xl';

type NativeInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'id' | 'size' | 'className' | 'aria-describedby' | 'aria-invalid' | 'required'>;

export interface TextInputProps extends NativeInputProps {
    size?: InputSize;
    /** A unit or currency before the value, such as "Rp", or after it. */
    prefix?: string;
    suffix?: string;
    icon?: IconName;
    numeric?: boolean;
    ref?: Ref<HTMLInputElement>;
}

/** The 44 px control of a Field with its 3.55:1 boundary (DESIGN_SYSTEM 8.3, DV-03). */
export function TextInput({ size = 'md', prefix, suffix, icon, numeric = false, disabled, ref, ...rest }: TextInputProps) {
    const field = useContext(FieldIds);

    if (field === null) {
        throw new Error('A TextInput is placed inside a Field, which gives it its label (UXA-13).');
    }

    return (
        <div
            className={cn('bk-input', size === 'lg' && 'bk-input--lg', size === 'xl' && 'bk-input--xl', numeric && 'bk-input--num')}
            data-invalid={field.invalid}
            data-disabled={disabled === true}
        >
            {icon !== undefined && (
                <span className={'bk-input__icon'}>
                    <Icon name={icon} />
                </span>
            )}
            {prefix !== undefined && (
                <span className={'bk-input__affix'} aria-hidden={true}>
                    {prefix}
                </span>
            )}
            <input
                {...rest}
                ref={ref}
                id={field.id}
                className={'bk-input__control'}
                disabled={disabled}
                required={field.required}
                aria-required={field.required ? true : undefined}
                aria-invalid={field.invalid ? true : undefined}
                aria-describedby={field.describedBy}
            />
            {suffix !== undefined && (
                <span className={'bk-input__affix bk-input__affix--end'} aria-hidden={true}>
                    {suffix}
                </span>
            )}
        </div>
    );
}

/** A password is never altered by the keyboard, shown or not (TR-VAL-05). */
const NO_ALTERATION = 'off';

/**
 * A password field (TASK-006): a TextInput of type password with the toggle
 * "Tampilkan kata sandi" (UXI-040; COPY_DECK section 5), a 44 px button inside
 * the control's boundary whose state is pressed while the password is shown.
 * Paste and password managers are allowed (SEC-AUTH-05): the autocomplete
 * value names the field's purpose, and nothing blocks pasting.
 */
export function PasswordInput({
    size = 'md',
    value,
    onValueChange,
    autoComplete,
    ref,
    ...rest
}: Omit<TextInputProps, 'type' | 'value' | 'onChange' | 'prefix' | 'suffix' | 'icon' | 'numeric' | 'autoComplete'> & {
    value: string;
    onValueChange: (value: string) => void;
    autoComplete: 'current-password' | 'new-password';
}) {
    const field = useContext(FieldIds);
    const [shown, setShown] = useState(false);

    if (field === null) {
        throw new Error('A PasswordInput is placed inside a Field, which gives it its label (UXA-13).');
    }

    const label = shown ? t('ux.action.hide_password') : t('ux.action.show_password');

    return (
        <div className={cn('bk-input', size === 'lg' && 'bk-input--lg', size === 'xl' && 'bk-input--xl')} data-invalid={field.invalid} data-disabled={rest.disabled === true}>
            <input
                {...rest}
                ref={ref}
                id={field.id}
                className={'bk-input__control'}
                type={shown ? 'text' : 'password'}
                value={value}
                onChange={(event) => onValueChange(event.target.value)}
                autoComplete={autoComplete}
                autoCapitalize={NO_ALTERATION}
                autoCorrect={NO_ALTERATION}
                spellCheck={false}
                required={field.required}
                aria-required={field.required ? true : undefined}
                aria-invalid={field.invalid ? true : undefined}
                aria-describedby={field.describedBy}
            />
            <button type={'button'} className={'bk-input__btn'} aria-pressed={shown} aria-label={label} title={label} onClick={() => setShown(!shown)}>
                <Icon name={shown ? 'eye-off' : 'eye'} />
            </button>
        </div>
    );
}

/** Groups whole rupiah with dots as they are typed: "1234567" → "1.234.567" (COPY_DECK 1.3). */
export function groupDigits(text: string): string {
    const digits = text.replace(/[^0-9]/g, '').replace(/^0+(?=[0-9])/, '');
    let grouped = '';

    for (let index = 0; index < digits.length; index += 1) {
        const fromEnd = digits.length - index;

        grouped += digits.charAt(index);

        if (fromEnd > 1 && (fromEnd - 1) % 3 === 0) {
            grouped += '.';
        }
    }

    return grouped;
}

/** The canonical money string of a grouped entry, or null when nothing was typed. */
export function moneyFromEntry(text: string): string | null {
    const digits = groupDigits(text).replace(/\./g, '');

    return digits === '' ? null : digits;
}

/**
 * A rupiah field: the numeric keyboard (UXA-14), whole rupiah, thousands
 * grouped as typed (DESIGN_SYSTEM 8.3). The value is a canonical string of
 * whole rupiah, never a JavaScript number (ADR-006).
 */
export function MoneyInput({
    value,
    onValueChange,
    size = 'md',
    ...rest
}: Omit<TextInputProps, 'value' | 'onChange' | 'prefix' | 'numeric' | 'inputMode' | 'type'> & {
    value: string | null;
    onValueChange: (value: string | null) => void;
}) {
    return (
        <TextInput
            {...rest}
            size={size}
            type={'text'}
            inputMode={'numeric'}
            autoComplete={'off'}
            prefix={RUPIAH_SYMBOL}
            numeric={true}
            value={value === null ? '' : groupDigits(value)}
            onChange={(event) => onValueChange(moneyFromEntry(event.target.value))}
        />
    );
}

/**
 * A form that sends a command: Enter in a field never submits it (UXA-10),
 * with or without Control, so that no implicit submission sends it. The
 * command is sent only by its review and confirm buttons; Ctrl+Enter still
 * reaches the confirm binding of the page from a field, because the form
 * carries ENTER_BLOCKED_ATTRIBUTE (shortcuts.ts). So an input of a command
 * form must never act on Ctrl+Enter itself.
 */
export function CommandForm({ children, onSubmit, className, label }: { children: ReactNode; onSubmit?: () => void; className?: string; label?: string }) {
    const blockEnter = (event: KeyboardEvent<HTMLFormElement>): void => {
        const target = event.target;

        if (event.key === 'Enter' && target instanceof HTMLInputElement) {
            event.preventDefault();
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        onSubmit?.();
    };

    return (
        <form className={cn('bk-form', className)} noValidate={true} onKeyDown={blockEnter} onSubmit={submit} aria-label={label} {...{ [ENTER_BLOCKED_ATTRIBUTE]: '' }}>
            {children}
        </form>
    );
}
