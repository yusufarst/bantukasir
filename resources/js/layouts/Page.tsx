import type { MouseEvent, ReactNode } from 'react';
import { Icon } from '@/components/ui/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

// The layout primitives of DESIGN_SYSTEM section 7 without the shell, which
// TASK-007 builds: containers of the width classes and the page archetypes
// of section 7.3, each with its parts in the archetype's order.

/** Data pages up to 1440 px, forms 960 px, focused flows 640 px, sign-in 420 px (DESIGN_SYSTEM 7.1). */
export type ContainerWidth = 'data' | 'form' | 'focused' | 'auth';

export function PageContainer({ width = 'data', children, className }: { width?: ContainerWidth; children: ReactNode; className?: string }) {
    return (
        <div
            className={cn(
                'bk-container',
                width === 'form' && 'bk-container--form',
                width === 'focused' && 'bk-container--focused',
                width === 'auth' && 'bk-container--auth',
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * The page header: the back action of NAVIGATION_CONTRACTS where the page has
 * one, at every width ("Kembali ke …", DV-05), the one level-1 heading equal
 * to the page label (UXA-13), the introduction and the page's actions.
 */
export function PageHeader({
    title,
    subtitle,
    back,
    actions,
}: {
    title: string;
    subtitle?: ReactNode;
    /**
     * "Kembali ke :halaman" with the destination's title, or "Kembali ke
     * halaman awal" for the role's home (NC-01, NC-12). On a create, edit or
     * command page `onNavigate` applies the unsaved-changes guard (NC-03),
     * as its cancel does.
     */
    back?: { href: string; page?: string; onNavigate?: (event: MouseEvent<HTMLAnchorElement>) => void };
    actions?: ReactNode;
}) {
    const backLabel = back === undefined ? undefined : back.page === undefined ? t('ux.action.back_home') : t('ux.action.back_to', { halaman: back.page });

    return (
        <header>
            {back !== undefined && (
                <a className={'bk-back'} href={back.href} onClick={back.onNavigate}>
                    <Icon name={'arrow-left'} />
                    <span>{backLabel}</span>
                </a>
            )}
            <div className={'bk-pagehead'}>
                <div className={'bk-pagehead__text'}>
                    <h1 className={'bk-title'} tabIndex={-1}>
                        {title}
                    </h1>
                    {subtitle !== undefined && <p className={'bk-subtitle'}>{subtitle}</p>}
                </div>
                {actions !== undefined && <div className={'bk-pagehead__actions'}>{actions}</div>}
            </div>
        </header>
    );
}

/** Pre-authentication (KS-01): one centred card; no shell, no business data, no codename. */
export function PreAuthLayout({ children }: { children: ReactNode }) {
    return (
        <main className={'bk-auth'}>
            <div className={'bk-auth__card'}>{children}</div>
        </main>
    );
}

/** Home (KS-02): panels in contract order, each with its as-of line. */
export function HomeLayout({ header, panels }: { header: ReactNode; panels: ReactNode }) {
    return (
        <PageContainer>
            {header}
            <div className={'bk-stack'}>{panels}</div>
        </PageContainer>
    );
}

/**
 * List (KS-08): search and filters, the refresh line with its time, the table
 * that becomes cards below 640 px of container width, and "Muat lebih banyak".
 */
export function ListLayout({ header, toolbar, refresh, children, more }: { header: ReactNode; toolbar?: ReactNode; refresh?: ReactNode; children: ReactNode; more?: ReactNode }) {
    return (
        <PageContainer>
            {header}
            <div className={'bk-card bk-section'}>
                {toolbar !== undefined && <div className={'bk-toolbar'}>{toolbar}</div>}
                {refresh !== undefined && <div className={'bk-refresh'}>{refresh}</div>}
                {children}
                {more}
            </div>
        </PageContainer>
    );
}

/** Report (KS-10): the period choice, completeness, the figures, the chart with its table view. */
export function ReportLayout({ header, period, completeness, figures, chart }: { header: ReactNode; period: ReactNode; completeness?: ReactNode; figures: ReactNode; chart?: ReactNode }) {
    return (
        <PageContainer>
            {header}
            <div className={'bk-stack'}>
                {period}
                {completeness}
                {figures}
                {chart}
            </div>
        </PageContainer>
    );
}

/** Command page with review (KS-06, KS-07, KS-09): the form card; "Tinjau" opens the review dialog. */
export function CommandLayout({ header, children, width = 'form' }: { header: ReactNode; children: ReactNode; width?: Extract<ContainerWidth, 'form' | 'focused'> }) {
    return (
        <PageContainer width={width}>
            {header}
            <div className={'bk-stack'}>{children}</div>
        </PageContainer>
    );
}

/** Result (KS-05): the success notice naming what was saved, the next action first, then the document. */
export function ResultLayout({ header, notice, next, document }: { header: ReactNode; notice: ReactNode; next: ReactNode; document: ReactNode }) {
    return (
        <PageContainer>
            {header}
            <div className={'bk-stack'}>
                {notice}
                <div className={'bk-grid-2'}>
                    <div className={'bk-stack'}>{next}</div>
                    <div>{document}</div>
                </div>
            </div>
        </PageContainer>
    );
}
