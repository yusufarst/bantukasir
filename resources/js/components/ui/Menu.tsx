import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import type { MouseEvent, ReactNode } from 'react';
import { Button, type ButtonVariant } from './Button';
import { Icon } from './Icon';
import type { IconName } from './icons';

export type MenuEntry =
    | { readonly kind: 'action'; readonly label: string; readonly icon?: IconName; readonly danger?: boolean; readonly onSelect: () => void }
    | {
          readonly kind: 'link';
          readonly label: string;
          readonly icon?: IconName;
          readonly href: string;
          /** A guard before the page is left, such as the unsaved-changes dialog (UXS-45; TASK-007). */
          readonly onNavigate?: (event: MouseEvent<HTMLAnchorElement>) => void;
      }
    | { readonly kind: 'separator' };

/**
 * A menu of DESIGN_SYSTEM 8.9 — the account menu, an overflow of further
 * actions — built on the Radix dropdown menu of shadcn/ui: the button carries
 * aria-expanded; the items are menuitems; the arrow keys, Home and End move;
 * Esc closes and returns focus to the button. A row's overflow names its row
 * in the button's accessible name (COPY_DECK CW-11). It is not modal and renders in
 * place, so that the page keeps its landmarks while it is open. The shell's
 * account menu opens upwards from the foot of the side navigation and gives
 * its button the look of the account (TASK-007).
 */
export function Menu({
    label,
    rowName,
    items,
    icon,
    iconOnly = false,
    variant = 'secondary',
    align = 'start',
    side = 'bottom',
    triggerClassName,
    children,
}: {
    label: string;
    /** The row whose actions it holds: its name joins the accessible name (CW-11). */
    rowName?: string;
    items: readonly MenuEntry[];
    icon?: IconName;
    iconOnly?: boolean;
    variant?: ButtonVariant;
    align?: 'start' | 'end';
    side?: 'top' | 'bottom' | 'right';
    triggerClassName?: string;
    children?: ReactNode;
}) {
    return (
        <DropdownMenu.Root modal={false}>
            <DropdownMenu.Trigger asChild={true}>
                <Button label={label} rowName={rowName} icon={icon} iconOnly={iconOnly} variant={variant} className={triggerClassName}>
                    {children}
                </Button>
            </DropdownMenu.Trigger>
                <DropdownMenu.Content className={'bk-menu'} align={align} side={side} sideOffset={4} collisionPadding={16}>
                    {items.map((item, index) => {
                        if (item.kind === 'separator') {
                            return <DropdownMenu.Separator key={index} className={'bk-menu__sep'} />;
                        }

                        if (item.kind === 'link') {
                            return (
                                <DropdownMenu.Item key={index} asChild={true}>
                                    <a className={'bk-menu__item'} href={item.href} onClick={item.onNavigate}>
                                        {item.icon !== undefined && <Icon name={item.icon} />}
                                        <span>{item.label}</span>
                                    </a>
                                </DropdownMenu.Item>
                            );
                        }

                        return (
                            <DropdownMenu.Item key={index} className={'bk-menu__item'} data-tone={item.danger === true ? 'danger' : undefined} onSelect={item.onSelect}>
                                {item.icon !== undefined && <Icon name={item.icon} />}
                                <span>{item.label}</span>
                            </DropdownMenu.Item>
                        );
                    })}
                </DropdownMenu.Content>
        </DropdownMenu.Root>
    );
}
