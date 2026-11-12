import type { IconName } from '@/components/ui/icons';
import type { TranslationKey } from '@/types/translation-keys';

// The navigation of the application shell (INFORMATION_ARCHITECTURE section 4;
// INTERACTION_INVENTORY UXI-001 to UXI-021; DESIGN_SYSTEM 7.2; TASK-007): the
// primary destinations of each role in their order, and the sub-navigation of
// the Produk, Stok and Laporan destinations. Navigation and controls appear
// only for what the role holds (SEC-AZ-07) — the server refuses everything
// else regardless — and a destination appears only once it exists
// (EXECUTION_CONTEXT EXC-52): each page Task registers its destination below,
// with its route and the capabilities of its row of INTERACTION_INVENTORY.
// TASK-007 builds the frame, the Tasks of TRACEABILITY section 9.2 add UXI-001
// to UXI-021.

export type RoleCode = 'SUPER_ADMIN' | 'OPERATIONS_ADMIN' | 'CASHIER';

/** The navigation's four kinds of account: the three roles and the combined account (D-032). */
export type Persona = 'owner' | 'ops' | 'cashier' | 'combined';

export type DestinationId = 'home' | 'till' | 'sales' | 'sessions' | 'stock' | 'products' | 'reports' | 'settings';

export type AreaId = 'products' | 'stock' | 'reports';

export type SubDestinationId = 'products' | 'categories' | 'csv' | 'stock' | 'history' | 'receipts' | 'issues' | 'onboarding' | 'incidents' | 'daily' | 'sessions' | 'finance' | 'worklist';

/** A registered destination: its title's key, its route, its icon and the capabilities that show it (any of them). */
export interface DestinationEntry {
    readonly label: TranslationKey;
    readonly href: string;
    readonly icon: IconName;
    readonly capabilities: readonly string[];
}

/** A registered item of a sub-navigation. */
export interface SubDestinationEntry {
    readonly label: TranslationKey;
    readonly href: string;
    readonly capabilities: readonly string[];
}

/**
 * The primary destinations of each kind of account, in order
 * (INFORMATION_ARCHITECTURE section 4): Owner 7, Operations 2, cashier 3, and
 * the combined account exactly the union of the cashier's and Operations'
 * with no Owner destination. There is no role switcher (D-075).
 */
export const PRIMARY_ORDER: Readonly<Record<Persona, readonly DestinationId[]>> = {
    owner: ['home', 'till', 'sales', 'stock', 'products', 'reports', 'settings'],
    ops: ['stock', 'products'],
    cashier: ['till', 'sales', 'sessions'],
    combined: ['till', 'sales', 'sessions', 'stock', 'products'],
};

/**
 * The sub-navigation of each area, per kind of account (INFORMATION_ARCHITECTURE
 * section 4): Produk is the same for every role that reaches it; Stok has two
 * items more for the Owner; Laporan is the Owner's alone.
 */
export const SUB_ORDER: Readonly<Record<AreaId, Readonly<Partial<Record<Persona, readonly SubDestinationId[]>>>>> = {
    products: {
        owner: ['products', 'categories', 'csv'],
        ops: ['products', 'categories', 'csv'],
        combined: ['products', 'categories', 'csv'],
    },
    stock: {
        owner: ['stock', 'history', 'receipts', 'issues', 'onboarding', 'incidents'],
        ops: ['stock', 'history', 'receipts', 'issues'],
        combined: ['stock', 'history', 'receipts', 'issues'],
    },
    reports: {
        owner: ['daily', 'sessions', 'finance', 'worklist'],
    },
};

/**
 * The registered destinations. A page Task adds its destination here with its
 * route when it delivers the page (TRACEABILITY section 9.2): UXI-008
 * "Pengaturan", the Owner's (CAP-25, CAP-32), from TASK-010; UXI-005 "Produk"
 * (CAP-02, CAP-04), from TASK-017; UXI-006 "Stok" (CAP-14), with its badge,
 * from TASK-022; UXI-002 "Kasir" (CAP-21), from TASK-027, which leads to the
 * till at `/till` since TASK-029, whose server sends a user without an OPEN
 * session to Buka sesi kas; UXI-004 "Sesi kas" (CAP-21) of a cashier and the
 * combined account, from TASK-030 — the Owner reaches it through Laporan.
 */
export const DESTINATIONS: Readonly<Partial<Record<DestinationId, DestinationEntry>>> = {
    till: { label: 'sales.till.title', href: '/till', icon: 'till', capabilities: ['CAP-21'] },
    sessions: { label: 'cash.sessions.title', href: '/cash-sessions', icon: 'drawer', capabilities: ['CAP-21'] },
    stock: { label: 'inventory.stock.title', href: '/stock', icon: 'boxes', capabilities: ['CAP-14'] },
    products: { label: 'catalog.products.title', href: '/products', icon: 'tag', capabilities: ['CAP-02', 'CAP-04'] },
    settings: { label: 'config.settings.title', href: '/settings', icon: 'sliders', capabilities: ['CAP-25', 'CAP-32'] },
};

/**
 * The registered items of each sub-navigation: UXI-009 and UXI-010, from TASK-017; UXI-011,
 * from TASK-019; UXI-012 to UXI-014, from TASK-022; UXI-015 "Barang keluar", the Owner's explicit
 * entry of D-018, from TASK-023; UXI-021 "Biaya perlu dilengkapi" (CAP-20), from TASK-025, and
 * UXI-019 "Sesi kas" (CAP-22), from TASK-030, shown once the Laporan destination exists (TASK-041).
 */
export const SUB_DESTINATIONS: Readonly<Record<AreaId, Readonly<Partial<Record<SubDestinationId, SubDestinationEntry>>>>> = {
    products: {
        products: { label: 'catalog.products.title', href: '/products', capabilities: ['CAP-02', 'CAP-04'] },
        categories: { label: 'catalog.categories.title', href: '/categories', capabilities: ['CAP-04'] },
        csv: { label: 'catalog.csv_import.title', href: '/products/import', capabilities: ['CAP-10'] },
    },
    stock: {
        stock: { label: 'inventory.stock.title', href: '/stock', capabilities: ['CAP-14'] },
        history: { label: 'inventory.history.title', href: '/stock/history', capabilities: ['CAP-14'] },
        receipts: { label: 'inventory.receipts.title', href: '/stock/receivings', capabilities: ['CAP-11'] },
        issues: { label: 'inventory.issues.title', href: '/stock/issues', capabilities: ['CAP-13'] },
    },
    reports: {
        sessions: { label: 'cash.sessions.title', href: '/cash-sessions', capabilities: ['CAP-22'] },
        worklist: { label: 'costing.worklist.title', href: '/reports/costs-to-complete', capabilities: ['CAP-20'] },
    },
};

/** The kind of account of these roles; null without a role. SUPER_ADMIN is held alone (BR-USR-02). */
export function personaOf(roles: readonly RoleCode[]): Persona | null {
    if (roles.includes('SUPER_ADMIN')) {
        return 'owner';
    }

    const cashier = roles.includes('CASHIER');
    const ops = roles.includes('OPERATIONS_ADMIN');

    return cashier && ops ? 'combined' : cashier ? 'cashier' : ops ? 'ops' : null;
}

/**
 * The visible items of an ordered list: the registered ones whose
 * capabilities the account holds, in the order given.
 */
function visible<Id extends string, Entry extends { readonly capabilities: readonly string[] }>(
    order: readonly Id[],
    registry: Readonly<Partial<Record<Id, Entry>>>,
    capabilities: readonly string[],
): { readonly id: Id; readonly entry: Entry }[] {
    const items: { readonly id: Id; readonly entry: Entry }[] = [];

    for (const id of order) {
        const entry = registry[id];

        if (entry !== undefined && entry.capabilities.some((capability) => capabilities.includes(capability))) {
            items.push({ id, entry });
        }
    }

    return items;
}

/** The primary navigation of an account: its kind's destinations that exist and that it holds, in order. */
export function navigationFor<Entry extends { readonly capabilities: readonly string[] }>(
    roles: readonly RoleCode[],
    capabilities: readonly string[],
    registry: Readonly<Partial<Record<DestinationId, Entry>>>,
): { readonly id: DestinationId; readonly entry: Entry }[] {
    const persona = personaOf(roles);

    return persona === null ? [] : visible(PRIMARY_ORDER[persona], registry, capabilities);
}

/** The sub-navigation of an area for an account, in order. */
export function subNavigationFor<Entry extends { readonly capabilities: readonly string[] }>(
    area: AreaId,
    roles: readonly RoleCode[],
    capabilities: readonly string[],
    registry: Readonly<Partial<Record<SubDestinationId, Entry>>>,
): { readonly id: SubDestinationId; readonly entry: Entry }[] {
    const persona = personaOf(roles);
    const order = persona === null ? undefined : SUB_ORDER[area][persona];

    return order === undefined ? [] : visible(order, registry, capabilities);
}
