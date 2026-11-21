import { describe, expect, it } from 'vitest';
import { attentionItems, splitStep, type AttentionPanel } from '@/lib/reporting/home';

// The attention items of Beranda (AC-041-03; INTERACTION_INVENTORY UXI-061 to UXI-065; INTERACTION_CONTRACTS
// section 9; COPY_DECK section 6.8; KEY_SCREENS KS-02 `quiet`; TASK-041 section 7, L5 KS-02 states): each item
// appears exactly when its evidence says so — a count above zero, a state that holds — in the order of COPY_DECK,
// leading to its page; none for a zero, a false or an item the viewer may not see, so the panel then says that
// nothing needs attention.

const NOTHING: AttentionPanel = { restock: { out: 0, low: 0 }, costs: 0, incidents: 0, status: false, onboarding: false };

describe('the attention items of Beranda', () => {
    it('are none when no evidence calls for one, or when the viewer may see none (KS-02 quiet)', () => {
        expect(attentionItems(NOTHING)).toEqual([]);
        expect(attentionItems({ restock: null, costs: null, incidents: null, status: null, onboarding: null })).toEqual([]);
    });

    it('are each shown with its count and page when its evidence says so, in the order of COPY_DECK section 6.8', () => {
        expect(attentionItems({ restock: { out: 2, low: 5 }, costs: 3, incidents: 1, status: true, onboarding: true })).toEqual([
            { id: 'restock', count: 7, stale: false, href: '/stock?state=OUT', icon: 'boxes', tone: 'warning' },
            { id: 'costs', count: 3, href: '/reports/costs-to-complete', icon: 'tag', tone: 'pending' },
            { id: 'incidents', count: 1, href: '/stock/incidents', icon: 'danger', tone: 'danger' },
            { id: 'status', count: null, href: '/settings/system-status', icon: 'warning', tone: 'warning' },
            { id: 'onboarding', count: null, href: '/stock/onboarding', icon: 'info', tone: 'info' },
        ]);
    });

    it('lead to Stok filtered to Habis while any product is Habis, and otherwise to Menipis', () => {
        expect(attentionItems({ ...NOTHING, restock: { out: 0, low: 4 } }).map((item) => [item.id, item.count, item.href])).toEqual([['restock', 4, '/stock?state=LOW']]);
        expect(attentionItems({ ...NOTHING, restock: { out: 1, low: 0 } }).map((item) => [item.id, item.count, item.href])).toEqual([['restock', 1, '/stock?state=OUT']]);
    });

    it('counts the restock item by the live read of the stock badge once the shell has one, refreshed as the badge (BR-INV-41; review R-01)', () => {
        // The badge's later read wins over the panel's: a change of stock shows without reloading the page.
        expect(attentionItems({ ...NOTHING, restock: { out: 1, low: 0 } }, { count: 9, failed: false }).map((item) => [item.id, item.count, item.stale, item.href])).toEqual([['restock', 9, false, '/stock?state=OUT']]);
        expect(attentionItems(NOTHING, { count: 3, failed: false }).map((item) => [item.id, item.count, item.href])).toEqual([['restock', 3, '/stock?state=LOW']]);
        // The badge read zero: no item, whatever the panel read before.
        expect(attentionItems({ ...NOTHING, restock: { out: 2, low: 2 } }, { count: 0, failed: false })).toEqual([]);
        // Before the shell's first read, the panel's count of the same definition.
        expect(attentionItems({ ...NOTHING, restock: { out: 2, low: 2 } }, { count: null, failed: true }).map((item) => [item.count, item.stale])).toEqual([[4, false]]);
        // A refresh that failed keeps the last count, marked stale (UXS-21), never a zero.
        expect(attentionItems(NOTHING, { count: 5, failed: true }).map((item) => [item.count, item.stale])).toEqual([[5, true]]);
        // An account without the stock pages sees no restock item, whatever the shell holds.
        expect(attentionItems({ ...NOTHING, restock: null }, { count: 5, failed: false })).toEqual([]);
    });

    it('reads the split of Habis and Menipis again with each later read of the badge, so the link follows a Menipis product turned Habis (pull request finding)', () => {
        const before: AttentionPanel = { ...NOTHING, restock: { out: 0, low: 1 } };
        const after: AttentionPanel = { ...NOTHING, restock: { out: 1, low: 0 } };
        const first = '2026-11-21T03:00:00.000000Z';
        const later = '2026-11-21T03:00:30.000000Z';

        // The evidence arrives beside the badge's read: it becomes the basis, nothing is read again.
        const arrived = splitStep(null, before, first);
        expect(arrived).toEqual({ basis: { evidence: before, asOf: first }, reread: false });
        expect(splitStep(arrived.basis, before, first)).toEqual({ basis: arrived.basis, reread: false });
        // The badge reads again — the same count of one, the product now Habis: the panel is read again.
        const refreshed = splitStep(arrived.basis, before, later);
        expect(refreshed).toEqual({ basis: { evidence: before, asOf: later }, reread: true });
        // Its new evidence becomes the basis without another read, and the link opens Habis.
        expect(splitStep(refreshed.basis, after, later)).toEqual({ basis: { evidence: after, asOf: later }, reread: false });
        expect(attentionItems(after, { count: 1, failed: false }).map((item) => item.href)).toEqual(['/stock?state=OUT']);
        // Evidence read before the badge's first read: that first read becomes the basis, read together at the load.
        const early = splitStep(null, before, null);
        expect(early).toEqual({ basis: { evidence: before, asOf: null }, reread: false });
        expect(splitStep(early.basis, before, first)).toEqual({ basis: { evidence: before, asOf: first }, reread: false });
        // No evidence, a failed read of the panel or no restock item for this viewer: no basis, no read again.
        expect(splitStep(arrived.basis, undefined, later)).toEqual({ basis: null, reread: false });
        expect(splitStep(arrived.basis, null, later)).toEqual({ basis: null, reread: false });
        expect(splitStep(arrived.basis, { ...NOTHING, restock: null }, later)).toEqual({ basis: null, reread: false });
        // A read again that failed marks the item stale, its link possibly outdated (UXS-21).
        expect(attentionItems(before, { count: 1, failed: false, splitStale: true }).map((item) => [item.count, item.stale, item.href])).toEqual([[1, true, '/stock?state=LOW']]);
    });

    it('shows each item alone when only its evidence holds', () => {
        expect(attentionItems({ ...NOTHING, costs: 12 }).map((item) => item.id)).toEqual(['costs']);
        expect(attentionItems({ ...NOTHING, incidents: 2 }).map((item) => item.id)).toEqual(['incidents']);
        expect(attentionItems({ ...NOTHING, status: true }).map((item) => item.id)).toEqual(['status']);
        expect(attentionItems({ ...NOTHING, onboarding: true }).map((item) => item.id)).toEqual(['onboarding']);
    });
});
