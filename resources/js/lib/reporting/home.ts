import type { IconName } from '@/components/ui/icons';
import type { Tone } from '@/components/ui/Status';
import { formatCount } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';

// Beranda, UXP-007 (ROUTE_CONTRACTS RP-007; KEY_SCREENS KS-02; COPY_DECK section 6.8; INTERACTION_INVENTORY
// UXI-059 to UXI-065; NAVIGATION_CONTRACTS UXP-007; TASK-041): the panels as the server reads them
// (App\Queries\Reporting\OwnerHome), each a deferred prop that is undefined until it arrives and null when its
// read failed, and the attention items that its evidence shows, each with the page it leads to. The client
// computes no figure and no count: it shows the server's.

/** RP-007. */
export const HOME_PAGE = '/home';

/** The deferred props of the panels, each read again by name with "Coba lagi" (Reporting\HomeController). */
export const TODAY_PROP = 'today';

export const OPEN_SESSIONS_PROP = 'openSessions';

export const ATTENTION_PROP = 'attention';

/** Where Beranda stands in the shell: the Owner's first destination (UXI-001). */
export const HOME_SHELL = { current: 'home' } as const satisfies { current: DestinationId };

/** Today's four figures of KS-02, with the snapshot's as-of time. */
export interface TodayPanel {
    readonly asOf: string;
    readonly netSales: string;
    readonly saleCount: number;
    readonly paymentsCash: string;
    readonly paymentsTransfer: string;
}

/** An OPEN cash session of UXI-060. */
export interface OpenSessionRow {
    readonly id: number;
    readonly registerLabel: string;
    readonly responsible: string;
    readonly openedAt: string;
}

/** The evidence of each attention item; null for an item whose capability the viewer does not hold. */
export interface AttentionPanel {
    readonly restock: { readonly out: number; readonly low: number } | null;
    readonly costs: number | null;
    readonly incidents: number | null;
    readonly status: boolean | null;
    readonly onboarding: boolean | null;
}

/** A shown attention item: the count it names, none for a state, where it leads, its icon and its tone. */
export interface AttentionItem {
    readonly id: 'restock' | 'costs' | 'incidents' | 'status' | 'onboarding';
    readonly count: number | null;
    readonly href: string;
    readonly icon: IconName;
    readonly tone: Tone;
}

/** RP-401 with its state filter; RP-503, RP-414, RP-611 and RP-412 (INTERACTION_CONTRACTS section 9). */
const STOCK_PAGE = '/stock';

const WORKLIST_PAGE = '/reports/costs-to-complete';

const INCIDENTS_PAGE = '/stock/incidents';

const STATUS_PAGE = '/settings/system-status';

const ONBOARDING_PAGE = '/stock/onboarding';

/**
 * The attention items that the evidence shows, in the order of COPY_DECK section 6.8, each exactly when its evidence
 * says so (AC-041-03): products Habis or Menipis (UXI-061), leading to Stok filtered to Habis while any product is
 * Habis and otherwise to Menipis, because RP-401's `state` takes one state; cost sources to complete (UXI-062);
 * open reconciliation incidents (UXI-063); a status item failed or unverified (UXI-064); the onboarding freeze
 * (UXI-065). None is shown for a count of zero, a false or an item the viewer may not see.
 */
export function attentionItems(attention: AttentionPanel): AttentionItem[] {
    const items: AttentionItem[] = [];
    const restock = attention.restock;

    if (restock !== null && restock.out + restock.low > 0) {
        items.push({ id: 'restock', count: restock.out + restock.low, href: `${STOCK_PAGE}?state=${restock.out > 0 ? 'OUT' : 'LOW'}`, icon: 'boxes', tone: 'warning' });
    }

    if (attention.costs !== null && attention.costs > 0) {
        items.push({ id: 'costs', count: attention.costs, href: WORKLIST_PAGE, icon: 'tag', tone: 'pending' });
    }

    if (attention.incidents !== null && attention.incidents > 0) {
        items.push({ id: 'incidents', count: attention.incidents, href: INCIDENTS_PAGE, icon: 'danger', tone: 'danger' });
    }

    if (attention.status === true) {
        items.push({ id: 'status', count: null, href: STATUS_PAGE, icon: 'warning', tone: 'warning' });
    }

    if (attention.onboarding === true) {
        items.push({ id: 'onboarding', count: null, href: ONBOARDING_PAGE, icon: 'info', tone: 'info' });
    }

    return items;
}

/** The text of an attention item, which names the condition it reports and its count (COPY_DECK section 6.8, CW-04). */
export function attentionTitle(item: AttentionItem): string {
    const count = formatCount(item.count ?? 0);

    switch (item.id) {
        case 'restock':
            return t('reporting.home.attention_restock', { jumlah: count });
        case 'costs':
            return t('reporting.home.attention_cost', { jumlah: count });
        case 'incidents':
            return t('reporting.home.attention_incidents', { jumlah: count });
        case 'status':
            return t('reporting.home.attention_status');
        case 'onboarding':
            return t('reporting.home.attention_onboarding');
    }
}
