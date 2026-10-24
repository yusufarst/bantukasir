import { cn } from '@/lib/cn';
import { ICON_PATHS, ICON_STROKE_OVERRIDE, ICON_VIEWBOX, type IconName } from './icons';

export type IconSize = 'sm' | 'md' | 'lg';

/**
 * An outline icon (DESIGN_SYSTEM section 6), 16, 20 or 24 px. An icon always
 * accompanies a word, or a control's accessible name: it is hidden from
 * assistive technology.
 */
export function Icon({ name, size = 'md', className }: { name: IconName; size?: IconSize; className?: string }) {
    return (
        <svg
            className={cn('bk-icon', size === 'lg' && 'bk-icon--lg', size === 'sm' && 'bk-icon--sm', className)}
            viewBox={ICON_VIEWBOX}
            aria-hidden={true}
            focusable={false}
            data-icon={name}
        >
            <path d={ICON_PATHS[name]} strokeWidth={ICON_STROKE_OVERRIDE[name]} />
        </svg>
    );
}
