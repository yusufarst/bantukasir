import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

/** A card of DESIGN_SYSTEM 8.10: white surface, subtle edge, 10 px radius. */
export function Card({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('bk-card', className)}>{children}</div>;
}

/**
 * A section of DESIGN_SYSTEM 8.10, 20 px padding (16 compact). It always has
 * a heading: visible, or for assistive technology only when the page title
 * already names it (`titleHidden`). It is not a landmark: only the page's
 * banner, navigation, main and labelled regions such as the cart are (UXA-13).
 */
export function Section({
    title,
    level = 2,
    titleHidden = false,
    actions,
    children,
    card = true,
}: {
    title: string;
    level?: 2 | 3;
    titleHidden?: boolean;
    actions?: ReactNode;
    children: ReactNode;
    card?: boolean;
}) {
    const Heading = level === 2 ? 'h2' : 'h3';
    const heading = (
        <Heading className={titleHidden ? 'bk-sr-only' : level === 2 ? 'bk-h2' : 'bk-h3'}>
            {title}
        </Heading>
    );

    return (
        <section className={cn(card && 'bk-card', 'bk-section')}>
            {titleHidden && actions === undefined ? (
                heading
            ) : (
                <div className={'bk-section__head'}>
                    {heading}
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}
