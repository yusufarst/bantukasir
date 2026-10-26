import { useId, useRef, type ButtonHTMLAttributes, type KeyboardEvent, type MouseEvent, type ReactNode, type Ref } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { Icon } from './Icon';
import type { IconName } from './icons';
import { SHORTCUTS, withShortcut, type ShortcutId } from './shortcuts';
import { useShortcut } from './useShortcut';

export type ButtonVariant = 'primary' | 'secondary' | 'quiet' | 'danger';

/** md 44 px, lg 48 px, xl 56 px; final is the final payment action, 64 px (DESIGN_SYSTEM 8.1, 8.2). */
export type ButtonSize = 'md' | 'lg' | 'xl' | 'final';

type NativeProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children' | 'disabled' | 'aria-label' | 'aria-disabled' | 'aria-describedby' | 'className'>;

export interface ButtonProps extends NativeProps {
    /** The visible label, from a translation key. */
    label: string;
    /** The row a row action acts on: its name joins the accessible name, "Hapus baris Pulpen gel hitam" (COPY_DECK CW-11). */
    rowName?: string;
    variant?: ButtonVariant;
    size?: ButtonSize;
    block?: boolean;
    icon?: IconName;
    /** An icon-only button: the label is its accessible name (DESIGN_SYSTEM section 6). */
    iconOnly?: boolean;
    /** The shortcut of the registry; it joins the accessible name and, with a fine pointer, shows as a keycap. */
    shortcut?: ShortcutId;
    /** False where the layer already handles the key, as Esc closes a dialog: the shortcut stays in the name only. */
    bindShortcut?: boolean;
    /**
     * Enter never activates a command, a final action, a review dialog's
     * confirm button or a print button (UXA-10): set false for those. Pointer,
     * touch, Space and Ctrl+Enter still act. The final action (size "final") never
     * takes Enter, whatever this says.
     */
    enterActivates?: boolean;
    /** UXS-06: the request is being sent; the button shows "Sedang diproses" and blocks repeats. */
    busy?: boolean;
    /** UXS-13: the control keeps its place, looks disabled and shows why, tied to it. */
    disabledReason?: ReactNode;
    /** Disabled while its reason is shown elsewhere and named by describedBy. */
    unavailable?: boolean;
    /** The id of an existing element that explains the control. */
    describedBy?: string;
    children?: ReactNode;
    className?: string;
    ref?: Ref<HTMLButtonElement>;
}

/**
 * The button of DESIGN_SYSTEM 8.1: primary (accent fill), secondary, quiet and
 * danger, 44, 48 and 56 px high, and the 64 px final action of 8.2. A button
 * that cannot be used stays focusable with aria-disabled, so that its reason
 * is reached and announced (8.7).
 */
export function Button({
    label,
    rowName,
    variant = 'primary',
    size = 'md',
    block = false,
    icon,
    iconOnly = false,
    shortcut,
    bindShortcut = true,
    enterActivates = true,
    busy = false,
    disabledReason,
    unavailable = false,
    describedBy,
    children,
    className,
    onClick,
    onKeyDown,
    type = 'button',
    ref,
    ...rest
}: ButtonProps) {
    const reasonId = useId();
    const own = useRef<HTMLButtonElement | null>(null);
    const blocked = busy || unavailable || disabledReason !== undefined;
    const visibleLabel = busy ? t('ux.state.submitting.title') : label;
    // While sending, the name follows the label and the shortcut is hidden (8.1).
    const named = rowName === undefined ? label : `${label} ${rowName}`;
    const name = busy ? visibleLabel : withShortcut(named, shortcut);
    const described = [describedBy, disabledReason !== undefined ? reasonId : undefined].filter((id) => id !== undefined).join(' ');

    useShortcut(
        shortcut,
        own,
        () => {
            if (blocked || own.current === null) {
                return false;
            }

            own.current.click();

            return true;
        },
        !blocked && bindShortcut,
    );

    const handleClick = (event: MouseEvent<HTMLButtonElement>): void => {
        if (blocked) {
            event.preventDefault();
            event.stopPropagation();

            return;
        }

        onClick?.(event);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>): void => {
        if (event.key === 'Enter' && !event.ctrlKey && (!enterActivates || size === 'final' || blocked)) {
            event.preventDefault();
        }

        onKeyDown?.(event);
    };

    const setRef = (element: HTMLButtonElement | null): void => {
        own.current = element;

        if (typeof ref === 'function') {
            ref(element);
        } else if (ref !== null && ref !== undefined) {
            ref.current = element;
        }
    };

    const button = (
        <button
            {...rest}
            ref={setRef}
            type={type}
            className={cn(
                'bk-btn',
                `bk-btn--${variant}`,
                size === 'lg' && 'bk-btn--lg',
                size === 'xl' && 'bk-btn--xl',
                size === 'final' && 'bk-btn--final',
                block && 'bk-btn--block',
                iconOnly && 'bk-btn--icon',
                className,
            )}
            aria-label={name === visibleLabel && !iconOnly ? undefined : name}
            aria-disabled={blocked ? true : undefined}
            aria-busy={busy ? true : undefined}
            aria-describedby={described === '' ? undefined : described}
            aria-keyshortcuts={shortcut !== undefined && !busy ? SHORTCUTS[shortcut].aria : undefined}
            data-shortcut={shortcut}
            data-variant={variant}
            data-size={size}
            onClick={handleClick}
            onKeyDown={handleKeyDown}
        >
            {busy ? <span className={'bk-spinner'} aria-hidden={true} /> : icon !== undefined && <Icon name={icon} size={size === 'final' || size === 'xl' ? 'lg' : 'md'} />}
            {iconOnly ? null : <span>{visibleLabel}</span>}
            {children}
            {shortcut !== undefined && !busy && (
                <span className={'bk-kbd'} data-key={shortcut} aria-hidden={true} />
            )}
        </button>
    );

    if (disabledReason === undefined) {
        return button;
    }

    return (
        <span className={'bk-reasoned'} data-block={block}>
            {button}
            <span className={'bk-reason'} id={reasonId}>
                <Icon name={'info'} size={'sm'} />
                <span>{disabledReason}</span>
            </span>
        </span>
    );
}

/** A link with the look of a button, for an action that only opens a page (COPY_DECK CW-04). */
export function ButtonLink({
    label,
    href,
    variant = 'secondary',
    size = 'md',
    icon,
    className,
    onClick,
}: {
    label: string;
    href: string;
    variant?: ButtonVariant;
    size?: Exclude<ButtonSize, 'final'>;
    icon?: IconName;
    className?: string;
    /** A guard before the page is left, such as the unsaved-changes dialog (UXS-45). */
    onClick?: (event: MouseEvent<HTMLAnchorElement>) => void;
}) {
    return (
        <a
            href={href}
            onClick={onClick}
            className={cn('bk-btn', `bk-btn--${variant}`, size === 'lg' && 'bk-btn--lg', size === 'xl' && 'bk-btn--xl', className)}
            data-variant={variant}
        >
            {icon !== undefined && <Icon name={icon} />}
            <span>{label}</span>
        </a>
    );
}
