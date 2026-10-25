import * as RadixDialog from '@radix-ui/react-dialog';
import { useRef, type ReactNode } from 'react';
import { t } from '@/lib/i18n';
import { Button } from './Button';
import { Icon } from './Icon';
import { LAYER_ATTRIBUTE } from './shortcuts';

/** The element that takes focus when a dialog opens: its first safe control. */
const INITIAL_FOCUS = '[data-initial-focus]';

/**
 * The button of the menu that holds `element`: the element whose aria-controls
 * names that menu, which the Radix menu button carries while its menu is open.
 */
function menuButtonOf(element: HTMLElement): HTMLElement | null {
    const menu = element.closest<HTMLElement>('[role="menu"]');

    if (menu === null || menu.id === '') {
        return null;
    }

    return Array.from(document.querySelectorAll<HTMLElement>('[aria-controls]')).find((candidate) => candidate.getAttribute('aria-controls') === menu.id) ?? null;
}

/**
 * The control that had the focus when a layer opened, which gets it back when
 * it closes (UXA-08). A layer opened from a menu item remembers that menu's
 * button too: the menu closes and its item leaves the page while the layer
 * opens, so when the remembered item is no longer connected the focus goes
 * back to the button that opened the menu (review finding R-22, D-103).
 */
function useOpener(): { remember: () => void; restore: (event: Event) => void } {
    const opener = useRef<HTMLElement | null>(null);
    const menuButton = useRef<HTMLElement | null>(null);

    return {
        remember: () => {
            opener.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            menuButton.current = opener.current === null ? null : menuButtonOf(opener.current);
        },
        restore: (event) => {
            const target = [opener.current, menuButton.current].find((candidate) => candidate !== null && candidate.isConnected);

            if (target !== undefined && target !== null) {
                event.preventDefault();
                target.focus();
            }
        },
    };
}

function focusInitial(event: Event, content: HTMLElement | null): void {
    const target = content?.querySelector<HTMLElement>(INITIAL_FOCUS);

    if (target !== null && target !== undefined) {
        event.preventDefault();
        target.focus();
    }
}

/**
 * A dialog of DESIGN_SYSTEM 8.8, built on the Radix dialog of shadcn/ui:
 * centred, 12 px radius, a scrim; only for the uses of UXA-05 (a review, a
 * confirmation, recent authentication, the unsaved-changes guard and the
 * sign-out and sign-in-ending warnings). Focus starts on the first safe
 * control — the one marked data-initial-focus, never the final action — stays
 * inside, Esc closes like the cancel, and focus returns to the opener. The
 * page behind is inert and its shortcuts wait (shortcuts.ts).
 */
export function Dialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    actions,
    dismissible = true,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    children?: ReactNode;
    actions: ReactNode;
    /** While a request is sending, Esc and the scrim do nothing (UXS-06). */
    dismissible?: boolean;
}) {
    const content = useRef<HTMLDivElement | null>(null);
    const opener = useOpener();

    return (
        <RadixDialog.Root open={open} onOpenChange={(next) => (dismissible || next ? onOpenChange(next) : undefined)}>
            <RadixDialog.Portal>
                <RadixDialog.Overlay className={'bk-scrim'} />
                <RadixDialog.Content
                    ref={content}
                    className={'bk-dialog'}
                    {...{ [LAYER_ATTRIBUTE]: '' }}
                    {...(description === undefined ? { 'aria-describedby': undefined } : {})}
                    onOpenAutoFocus={(event) => {
                        opener.remember();
                        focusInitial(event, content.current);
                    }}
                    onCloseAutoFocus={opener.restore}
                    onEscapeKeyDown={(event) => (dismissible ? undefined : event.preventDefault())}
                    onPointerDownOutside={(event) => (dismissible ? undefined : event.preventDefault())}
                    onInteractOutside={(event) => (dismissible ? undefined : event.preventDefault())}
                >
                    <RadixDialog.Title className={'bk-dialog__title'}>{title}</RadixDialog.Title>
                    <div className={'bk-dialog__body'}>
                        {description !== undefined && <RadixDialog.Description asChild={true}><div>{description}</div></RadixDialog.Description>}
                        {children}
                    </div>
                    <div className={'bk-dialog__actions'}>{actions}</div>
                </RadixDialog.Content>
            </RadixDialog.Portal>
        </RadixDialog.Root>
    );
}

/**
 * The review step of a command page (DESIGN_SYSTEM 7.3, BR-CMD-04): "Kembali
 * ubah" takes the first focus and closes with Esc; the confirm button names
 * the command, is activated by pointer, Space or a new Ctrl+Enter, and never
 * by Enter (UXA-10).
 */
export function ReviewDialog({
    open,
    onOpenChange,
    title,
    children,
    confirmLabel,
    onConfirm,
    busy = false,
    confirmVariant = 'primary',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    children: ReactNode;
    confirmLabel: string;
    onConfirm: () => void;
    busy?: boolean;
    confirmVariant?: 'primary' | 'danger';
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            dismissible={!busy}
            actions={
                <>
                    <Button
                        label={t('ux.action.edit_again')}
                        variant={'secondary'}
                        size={'lg'}
                        shortcut={'back'}
                        bindShortcut={false}
                        unavailable={busy}
                        data-initial-focus={true}
                        onClick={() => onOpenChange(false)}
                    />
                    <Button label={confirmLabel} variant={confirmVariant} size={'lg'} shortcut={'confirm'} enterActivates={false} busy={busy} onClick={onConfirm} />
                </>
            }
        >
            {children}
        </Dialog>
    );
}

/**
 * UXS-45: leaving a page with entered data that was not sent. The dialog
 * names what will be lost — the page's own text for receiving and the CSV
 * import (`body`) — and "Tetap di halaman ini" is the default and the first
 * focus; Esc stays too.
 */
export function UnsavedDialog({ open, onStay, onLeave, body }: { open: boolean; onStay: () => void; onLeave: () => void; body?: string }) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? undefined : onStay())}
            title={t('ux.state.unsaved.title')}
            description={body ?? t('ux.state.unsaved.body')}
            actions={
                <>
                    <Button label={t('ux.action.leave')} variant={'secondary'} size={'lg'} onClick={onLeave} />
                    <Button label={t('ux.action.stay')} size={'lg'} data-initial-focus={true} onClick={onStay} />
                </>
            }
        />
    );
}

/**
 * The drawer of DESIGN_SYSTEM 8.9: modal from the left, the page inert; it
 * closes with Esc, its 44 px close button "Tutup menu" or the scrim, and
 * returns focus to the button that opened it.
 */
export function Drawer({ open, onOpenChange, title, children }: { open: boolean; onOpenChange: (open: boolean) => void; title: ReactNode; children: ReactNode }) {
    const opener = useOpener();

    return (
        <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
            <RadixDialog.Portal>
                <RadixDialog.Overlay className={'bk-drawer-scrim'} />
                <RadixDialog.Content
                    className={'bk-drawer'}
                    {...{ [LAYER_ATTRIBUTE]: '' }}
                    aria-describedby={undefined}
                    onOpenAutoFocus={opener.remember}
                    onCloseAutoFocus={opener.restore}
                >
                    <div className={'bk-drawer__head'}>
                        <RadixDialog.Title className={'bk-h3'}>{title}</RadixDialog.Title>
                        <RadixDialog.Close asChild={true}>
                            <button type={'button'} className={'bk-btn bk-btn--quiet bk-btn--icon'} aria-label={t('ux.nav.menu_close')}>
                                <Icon name={'close'} />
                            </button>
                        </RadixDialog.Close>
                    </div>
                    <div className={'bk-drawer__body'}>{children}</div>
                </RadixDialog.Content>
            </RadixDialog.Portal>
        </RadixDialog.Root>
    );
}
