import { useId, type ReactNode, type Ref } from 'react';
import { Icon } from './Icon';
import type { IconName } from './icons';
import type { Tone } from './Status';

const NOTICE_ICON: Readonly<Record<Tone, IconName>> = {
    neutral: 'info',
    success: 'check',
    warning: 'warning',
    pending: 'clock',
    danger: 'danger',
    info: 'info',
};

/**
 * How a notice reaches assistive technology (UXA-17): `status` is polite,
 * `alert` assertive — a refusal or an uncertain outcome — and `none` is a
 * notice that was already on the page when it loaded.
 */
export type Urgency = 'status' | 'alert' | 'none';

/**
 * A notice or recovery panel of DESIGN_SYSTEM 8.6: tone, icon, title, body and
 * actions. There are no toasts (DV-07): an outcome is shown in place or on
 * the destination.
 */
export function Notice({
    tone = 'neutral',
    title,
    children,
    actions,
    urgency = 'none',
    icon,
    ref,
    focusable = false,
}: {
    tone?: Tone;
    title?: ReactNode;
    children?: ReactNode;
    actions?: ReactNode;
    urgency?: Urgency;
    icon?: IconName;
    ref?: Ref<HTMLDivElement>;
    /** A notice that receives focus, such as the summary of a form's errors (UXS-05). */
    focusable?: boolean;
}) {
    const titleId = useId();

    return (
        <div
            ref={ref}
            className={'bk-notice'}
            data-tone={tone}
            role={urgency === 'none' ? undefined : urgency}
            aria-labelledby={title === undefined ? undefined : titleId}
            tabIndex={focusable ? -1 : undefined}
        >
            <Icon name={icon ?? NOTICE_ICON[tone]} />
            {title === undefined ? (
                <div className={'bk-notice__body'}>{children}</div>
            ) : (
                <>
                    <p className={'bk-notice__title'} id={titleId}>
                        {title}
                    </p>
                    {children !== undefined && <div className={'bk-notice__body'}>{children}</div>}
                </>
            )}
            {actions !== undefined && <div className={'bk-notice__actions'}>{actions}</div>}
        </div>
    );
}
