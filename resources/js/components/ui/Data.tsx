import type { ReactNode } from 'react';
import { t } from '@/lib/i18n';
import { Button } from './Button';
import { Icon } from './Icon';
import type { IconName } from './icons';
import { STATE_CATALOGUE } from './stateCatalogue';
import { StateMark } from './States';
import type { Tone } from './Status';

/**
 * A value as a read delivers it. Only `loaded` shows a value: a value that is
 * loading, failed, stale or an UNKNOWN cost is never shown as zero, blank or
 * "Normal" (UI_STATES section 1, UXS-01, UXS-21, UXS-22, UXS-24).
 */
export type ReadValue =
    | { readonly status: 'loaded'; readonly text: string }
    | { readonly status: 'loading' }
    | { readonly status: 'failed' }
    /** The last value, shown with the marker that it may be out of date (UXS-21). */
    | { readonly status: 'stale'; readonly text: string }
    | { readonly status: 'unknown_cost' };

/** The text of a value, or its state in words. */
export function ValueText({ value }: { value: ReadValue }) {
    switch (value.status) {
        case 'loaded':
            return <span className={'bk-num'}>{value.text}</span>;
        case 'loading':
            return (
                <span data-state={'loading'}>
                    <span className={'bk-skel'} data-width={'40'} aria-hidden={true} />
                    <span className={'bk-sr-only'}>{t('ux.state.loading.body')}</span>
                </span>
            );
        case 'failed':
            return <span data-state={'failed'}>{t('ux.state.load_failed.title')}</span>;
        case 'stale':
            return (
                <span data-state={'stale'}>
                    <span className={'bk-num'}>{value.text}</span> <span className={'bk-meta'}>{t('ux.state.stale_read.title')}</span>
                </span>
            );
        case 'unknown_cost':
            return <StateMark kind={'cost_unknown'} />;
    }
}

/**
 * A figure of DESIGN_SYSTEM 8.12: label, value — never wrapped or abbreviated
 * (UXA-15) — help line and completeness mark (UXS-25).
 */
export function Figure({ label, value, hint, incomplete = false }: { label: string; value: ReadValue; hint?: ReactNode; incomplete?: boolean }) {
    return (
        <div className={'bk-metric'}>
            <dt className={'bk-metric__label'}>{label}</dt>
            <dd className={'bk-metric__value'} data-state={value.status === 'loaded' ? undefined : value.status}>
                <ValueText value={value} />
            </dd>
            {incomplete && (
                <dd className={'bk-metric__hint'}>
                    <span className={'bk-mark'} data-tone={STATE_CATALOGUE['UXS-25'].tone}>
                        <Icon name={'clock'} size={'sm'} />
                        <span>{t('ux.state.cost_incomplete.title')}</span>
                    </span>
                </dd>
            )}
            {hint !== undefined && <dd className={'bk-metric__hint'}>{hint}</dd>}
        </div>
    );
}

/** A row of figures that wraps by the space it has; two columns at compact width. */
export function Figures({ children }: { children: ReactNode }) {
    return <dl className={'bk-metrics'}>{children}</dl>;
}

export interface KeyValue {
    readonly key: string;
    readonly label: string;
    readonly value: ReadValue | ReactNode;
    /** The emphasised last row, such as a total or a variance. */
    readonly strong?: boolean;
}

function isReadValue(value: unknown): value is ReadValue {
    return typeof value === 'object' && value !== null && 'status' in value;
}

/** A key-value list of DESIGN_SYSTEM 8.12, for summaries and reviews. */
export function KeyValueList({ items }: { items: readonly KeyValue[] }) {
    return (
        <dl className={'bk-kv'}>
            {items.map((item) => {
                const value = isReadValue(item.value) ? <ValueText value={item.value} /> : item.value;

                return item.strong === true ? (
                    <div key={item.key} className={'bk-kv__strong'}>
                        <dt>{item.label}</dt>
                        <dd>{value}</dd>
                    </div>
                ) : (
                    <div key={item.key}>
                        <dt>{item.label}</dt>
                        <dd>{value}</dd>
                    </div>
                );
            })}
        </dl>
    );
}

/** A link row of at least 56 px (DESIGN_SYSTEM 8.12), as on Beranda's attention list. */
export function LinkRow({ href, title, meta, icon, tone, end }: { href: string; title: string; meta?: ReactNode; icon?: IconName; tone?: Tone; end?: ReactNode }) {
    return (
        <a className={'bk-row'} href={href} data-tone={tone}>
            {icon !== undefined && (
                <span className={'bk-row__icon'}>
                    <Icon name={icon} />
                </span>
            )}
            <span className={'bk-row__main'}>
                <span className={'bk-row__title'}>{title}</span>
                {meta !== undefined && <span className={'bk-row__meta'}>{meta}</span>}
            </span>
            <span className={'bk-row__end'}>{end ?? <Icon name={'chevron-right'} />}</span>
        </a>
    );
}

/**
 * The refresh line of a list (DESIGN_SYSTEM 7.3, BR-INV-41): the time of the
 * last refresh, "Diperbarui :waktu", the count of results and "Muat ulang".
 */
export function RefreshLine({ at, count, onReload }: { at: string; count?: string; onReload?: () => void }) {
    return (
        <div className={'bk-refresh'}>
            <span>{t('ux.list.updated_at', { waktu: at })}</span>
            {count !== undefined && <span>{t('ux.list.count', { jumlah: count })}</span>}
            {onReload !== undefined && <Button label={t('ux.action.reload')} variant={'quiet'} icon={'refresh'} onClick={onReload} />}
        </div>
    );
}

/** "Muat lebih banyak": the next page of a list's results (BR-CAT-25). */
export function LoadMore({ onLoad, busy = false }: { onLoad: () => void; busy?: boolean }) {
    return (
        <div className={'bk-actions'}>
            <Button label={t('ux.action.load_more')} variant={'secondary'} onClick={onLoad} busy={busy} />
        </div>
    );
}

/** The list that holds link rows. */
export function LinkRows({ label, children }: { label: string; children: ReactNode }) {
    return (
        <ul className={'bk-rows'} aria-label={label}>
            {children}
        </ul>
    );
}
