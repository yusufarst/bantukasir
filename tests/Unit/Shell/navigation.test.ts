import { describe, expect, it } from 'vitest';
import { DESTINATIONS, PRIMARY_ORDER, SUB_DESTINATIONS, SUB_ORDER, navigationFor, personaOf, subNavigationFor, type DestinationId, type SubDestinationId } from '@/lib/navigation';

// The navigation of the shell (AC-007-06; INFORMATION_ARCHITECTURE section 4;
// INTERACTION_INVENTORY UXI-001 to UXI-021; SECURITY_MODEL SEC-AZ-07;
// EXECUTION_CONTEXT EXC-52; TASK-007): the destinations of each role in their
// order, the combined account exactly the union of the cashier's and
// Operations' with no Owner destination, the sub-navigation of each area, an
// item only where its destination is registered and the capabilities of its
// row are held — and no destination registered before its page Task.

const ALL = ['home', 'till', 'sales', 'sessions', 'stock', 'products', 'reports', 'settings'] as const satisfies readonly DestinationId[];

/** A registry with every destination, each shown by the capabilities of its row of INTERACTION_INVENTORY. */
const EVERY: Readonly<Record<DestinationId, { capabilities: readonly string[] }>> = {
    home: { capabilities: ['CAP-22'] },
    till: { capabilities: ['CAP-21'] },
    sales: { capabilities: ['CAP-21', 'CAP-22'] },
    sessions: { capabilities: ['CAP-21'] },
    stock: { capabilities: ['CAP-14'] },
    products: { capabilities: ['CAP-02', 'CAP-04'] },
    reports: { capabilities: ['CAP-22', 'CAP-20'] },
    settings: { capabilities: ['CAP-25', 'CAP-32'] },
};

const FLAGS = {
    owner: Array.from({ length: 34 }, (_, index) => `CAP-${String(index + 1).padStart(2, '0')}`).filter((capability) => capability !== 'CAP-33'),
    ops: ['CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06', 'CAP-08', 'CAP-09', 'CAP-10', 'CAP-11', 'CAP-13', 'CAP-14', 'CAP-34'],
    cashier: ['CAP-01', 'CAP-03', 'CAP-21', 'CAP-34'],
    combined: ['CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06', 'CAP-08', 'CAP-09', 'CAP-10', 'CAP-11', 'CAP-13', 'CAP-14', 'CAP-21', 'CAP-34'],
} as const;

describe('the primary navigation of each role', () => {
    it('is the order of INFORMATION_ARCHITECTURE section 4', () => {
        expect(navigationFor(['SUPER_ADMIN'], FLAGS.owner, EVERY).map((item) => item.id)).toEqual(['home', 'till', 'sales', 'stock', 'products', 'reports', 'settings']);
        expect(navigationFor(['OPERATIONS_ADMIN'], FLAGS.ops, EVERY).map((item) => item.id)).toEqual(['stock', 'products']);
        expect(navigationFor(['CASHIER'], FLAGS.cashier, EVERY).map((item) => item.id)).toEqual(['till', 'sales', 'sessions']);
        expect(navigationFor(['CASHIER', 'OPERATIONS_ADMIN'], FLAGS.combined, EVERY).map((item) => item.id)).toEqual(['till', 'sales', 'sessions', 'stock', 'products']);
    });

    it('gives the combined account the union of the cashier and Operations and no Owner destination', () => {
        const union = new Set([...PRIMARY_ORDER.cashier, ...PRIMARY_ORDER.ops]);

        expect(new Set(PRIMARY_ORDER.combined)).toEqual(union);
        expect(PRIMARY_ORDER.combined.filter((id) => !PRIMARY_ORDER.cashier.includes(id) && !PRIMARY_ORDER.ops.includes(id))).toEqual([]);
        expect(PRIMARY_ORDER.combined).not.toContain('home');
        expect(PRIMARY_ORDER.combined).not.toContain('reports');
        expect(PRIMARY_ORDER.combined).not.toContain('settings');
    });

    it('keeps the counts of G1-01 and shows Sesi kas to the Owner only under Laporan', () => {
        expect([PRIMARY_ORDER.owner.length, PRIMARY_ORDER.ops.length, PRIMARY_ORDER.cashier.length, PRIMARY_ORDER.combined.length]).toEqual([7, 2, 3, 5]);
        expect(PRIMARY_ORDER.owner).not.toContain('sessions');
        expect(SUB_ORDER.reports.owner).toContain('sessions');
    });

    it('shows an item only where its destination exists and its capabilities are held', () => {
        const some: Partial<Record<DestinationId, { capabilities: readonly string[] }>> = { stock: EVERY.stock, till: EVERY.till };

        expect(navigationFor(['OPERATIONS_ADMIN'], FLAGS.ops, some).map((item) => item.id)).toEqual(['stock']);
        expect(navigationFor(['CASHIER'], FLAGS.cashier, some).map((item) => item.id)).toEqual(['till']);
        // A destination of the role whose capability the account no longer holds.
        expect(navigationFor(['CASHIER'], ['CAP-01'], EVERY)).toEqual([]);
        // No role, nothing.
        expect(navigationFor([], FLAGS.owner, EVERY)).toEqual([]);
        expect(personaOf([])).toBeNull();
    });

    it('registers no destination before the Task that delivers its page (EXC-52)', () => {
        // UXI-008 "Pengaturan" is TASK-010's, with the capabilities of its row.
        expect(ALL.filter((id) => DESTINATIONS[id] !== undefined)).toEqual(['settings']);
        expect(DESTINATIONS.settings).toEqual({ label: 'config.settings.title', href: '/settings', icon: 'sliders', capabilities: ['CAP-25', 'CAP-32'] });
        expect(Object.values(SUB_DESTINATIONS).every((area) => Object.keys(area).length === 0)).toBe(true);
        expect(navigationFor(['SUPER_ADMIN'], FLAGS.owner, DESTINATIONS).map((item) => item.id)).toEqual(['settings']);
        expect(navigationFor(['CASHIER', 'OPERATIONS_ADMIN'], FLAGS.combined, DESTINATIONS)).toEqual([]);
    });
});

describe('the sub-navigation of each area', () => {
    const shown = { capabilities: ['CAP-01'] };
    const everySub: Readonly<Record<SubDestinationId, { capabilities: readonly string[] }>> = {
        products: shown,
        categories: shown,
        csv: shown,
        stock: shown,
        history: shown,
        receipts: shown,
        issues: shown,
        onboarding: shown,
        incidents: shown,
        daily: shown,
        sessions: shown,
        finance: shown,
        worklist: shown,
    };

    it('is the order of INFORMATION_ARCHITECTURE section 4 for each kind of account', () => {
        expect(subNavigationFor('stock', ['SUPER_ADMIN'], ['CAP-01'], everySub).map((item) => item.id)).toEqual(['stock', 'history', 'receipts', 'issues', 'onboarding', 'incidents']);
        expect(subNavigationFor('stock', ['OPERATIONS_ADMIN'], ['CAP-01'], everySub).map((item) => item.id)).toEqual(['stock', 'history', 'receipts', 'issues']);
        expect(subNavigationFor('stock', ['CASHIER', 'OPERATIONS_ADMIN'], ['CAP-01'], everySub).map((item) => item.id)).toEqual(['stock', 'history', 'receipts', 'issues']);
        expect(subNavigationFor('products', ['OPERATIONS_ADMIN'], ['CAP-01'], everySub).map((item) => item.id)).toEqual(['products', 'categories', 'csv']);
        expect(subNavigationFor('reports', ['SUPER_ADMIN'], ['CAP-01'], everySub).map((item) => item.id)).toEqual(['daily', 'sessions', 'finance', 'worklist']);
        expect(subNavigationFor('reports', ['CASHIER', 'OPERATIONS_ADMIN'], ['CAP-01'], everySub)).toEqual([]);
        expect(subNavigationFor('stock', ['CASHIER'], ['CAP-01'], everySub)).toEqual([]);
    });
});

describe('the kind of account', () => {
    it('reads SUPER_ADMIN alone and the cashier with Operations as combined', () => {
        expect(personaOf(['SUPER_ADMIN'])).toBe('owner');
        expect(personaOf(['OPERATIONS_ADMIN'])).toBe('ops');
        expect(personaOf(['CASHIER'])).toBe('cashier');
        expect(personaOf(['OPERATIONS_ADMIN', 'CASHIER'])).toBe('combined');
        expect(personaOf(['SUPER_ADMIN', 'CASHIER'])).toBe('owner');
    });
});
