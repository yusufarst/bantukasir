import { describe, expect, it } from 'vitest';
import { ADJUSTMENT_COMMAND, adjustmentPage, adjustmentSteps, historyAddress, productHistory, reviewedVersions, withSnapshot } from '@/lib/inventory/stock';

// The review of a draft of an adjustment on UXP-410, its entries and its result (TASK-024 scope
// item 3; BR-INV-31 to BR-INV-33; COPY_DECK section 9.11; NAVIGATION_CONTRACTS UXP-410): the stock
// before, the change and the stock after of every line, the lines of one product taken in their
// order, an increase adding and a decrease taking; a decrease is marked once the product's
// decreases ask for more than the stock before the adjustment, as the server refuses it, whatever
// increases the adjustment holds (BR-INV-33); the addresses of UXP-410 and of UXP-402 filtered to
// the adjusted products.

describe('the review of an adjustment', () => {
    it('takes the lines of one product in their order, increases adding and decreases taking', () => {
        expect(
            adjustmentSteps([
                { productId: 1, increase: true, quantity: '3', onHand: '10' },
                { productId: 2, increase: false, quantity: '0.25', onHand: '2.5' },
                { productId: 1, increase: false, quantity: '5', onHand: '10' },
            ]),
        ).toEqual([
            { before: '10', after: '13' },
            { before: '2.5', after: '2.25' },
            { before: '13', after: '8' },
        ]);
    });

    it('marks a decrease once the decreases of its product ask for more than the stock before, an increase notwithstanding', () => {
        expect(
            adjustmentSteps([
                { productId: 1, increase: true, quantity: '4', onHand: '5' },
                { productId: 1, increase: false, quantity: '3', onHand: '5' },
                { productId: 1, increase: false, quantity: '3', onHand: '5' },
            ]),
        ).toEqual([
            { before: '5', after: '9' },
            { before: '9', after: '6' },
            { before: '6', after: null },
        ]);
        expect(adjustmentSteps([{ productId: 1, increase: false, quantity: '1.5', onHand: '1.5' }])).toEqual([{ before: '1.5', after: '0' }]);
    });

    it('has no step for a quantity that is none yet or a product whose stock the search did not give', () => {
        expect(
            adjustmentSteps([
                { productId: 1, increase: true, quantity: null, onHand: '5' },
                { productId: 2, increase: false, quantity: '1', onHand: null },
            ]),
        ).toEqual([null, null]);
    });
});

// Pull request #34's review: a product changed in another tab between two lines of it — its base
// unit, while it has no movement — must not leave the first line showing and reviewing the old
// snapshot while the version of the second is sent for both.
describe('one snapshot of a product in a draft', () => {
    const before = { id: 1, baseUnit: 'pcs', version: 1 };
    const after = { id: 1, baseUnit: 'kg', version: 2 };
    const other = { id: 2, baseUnit: 'pcs', version: 5 };

    it('gives a product read again to every line of it, and leaves the other products alone', () => {
        const lines = [
            { key: 1, quantity: '2', product: before },
            { key: 2, quantity: '1', product: other },
        ];

        expect(withSnapshot(lines, after)).toEqual([
            { key: 1, quantity: '2', product: after },
            { key: 2, quantity: '1', product: other },
        ]);
    });

    it('sends each product once with the version its lines were reviewed in', () => {
        expect(
            reviewedVersions([
                { product: after },
                { product: other },
                { product: after },
            ]),
        ).toEqual([
            { productId: 1, version: 2 },
            { productId: 2, version: 5 },
        ]);
    });

    it('sends nothing when the lines of one product hold different versions, rather than the last overwriting the first', () => {
        expect(reviewedVersions([{ product: before }, { product: other }, { product: after }])).toBeNull();
    });
});

describe('the addresses of an adjustment', () => {
    it('opens UXP-410 with the product and the page that opened it', () => {
        expect(adjustmentPage(12, 'stock')).toBe('/stock/adjustments/new?product=12&from_page=stock');
        expect(adjustmentPage(7, 'history')).toBe('/stock/adjustments/new?product=7&from_page=history');
    });

    it('filters UXP-402 to one product or to several', () => {
        expect(productHistory(3, 'stock')).toBe('/stock/history?product=3&from_page=stock');
        expect(historyAddress({ products: [3, 9], type: null, from: null, to: null })).toBe('/stock/history?product=3%2C9');
        expect(historyAddress({ products: [], type: 'ADJUSTMENT', from: null, to: null })).toBe('/stock/history?type=ADJUSTMENT');
    });

    it('needs the recent authentication of its family', () => {
        expect(ADJUSTMENT_COMMAND).toEqual({ operation: 'adjustment.confirm', family: 'stock_posting', recentAuth: true });
    });
});
