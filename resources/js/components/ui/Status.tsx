import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { Icon } from './Icon';
import type { IconName } from './icons';

/** The status tones of DESIGN_SYSTEM 3.2: they map to states, never to decoration. */
export type Tone = 'neutral' | 'success' | 'warning' | 'pending' | 'danger' | 'info';

/** The icon of a tone where the word needs one: warning and danger (DESIGN_SYSTEM 3.4, 8.5). */
export const TONE_ICON: Readonly<Record<Tone, IconName | null>> = {
    neutral: null,
    success: null,
    warning: 'warning',
    pending: 'clock',
    danger: 'danger',
    info: null,
};

/**
 * A status of DESIGN_SYSTEM 8.5: always a word, with a dot (neutral, success,
 * pending, info) or an icon (warning, danger); never colour alone (UXA-18).
 * The dot is decorative; the word carries the state.
 */
export function StatusWord({ tone = 'neutral', label, inactive = false }: { tone?: Tone; label: string; inactive?: boolean }) {
    const icon = tone === 'warning' || tone === 'danger' ? TONE_ICON[tone] : null;

    return (
        <span className={'bk-status'} data-tone={inactive ? 'inactive' : tone}>
            {icon === null ? <span className={'bk-status__dot'} aria-hidden={true} /> : <Icon name={icon} size={'sm'} />}
            <span>{label}</span>
        </span>
    );
}

/** A 24 px pill for a count or a mark (DESIGN_SYSTEM 8.5). */
export function Pill({ tone = 'neutral', label, icon, className }: { tone?: Tone; label: ReactNode; icon?: IconName; className?: string }) {
    return (
        <span className={cn('bk-pill', className)} data-tone={tone}>
            {icon !== undefined && <Icon name={icon} size={'sm'} />}
            <span>{label}</span>
        </span>
    );
}
