import { describe, expect, it } from 'vitest';
import {
    acceptNewPrices,
    addProduct,
    applyRefusal,
    BANK_TRANSFER,
    canDecrease,
    CASH,
    cartProductOf,
    cartTotal,
    checkoutRequest,
    clearCart,
    draftOf,
    EMPTY_DRAFT,
    lineTotalOf,
    linesValid,
    priceReviewPending,
    readQuantity,
    removeLine,
    setQuantity,
    stepQuantity,
    tenderOf,
    type CartProduct,
    type TillDraft,
} from '@/lib/sales/cart';

// The till's cart and payment draft (AC-029-01, AC-029-03, AC-029-05; KEY_SCREENS KS-03, KS-04;
// INTERACTION_INVENTORY UXI-067, UXI-070 to UXI-074, UXI-078 to UXI-083; INTERACTION_CONTRACTS RC-01
// and section 1.6; BR-SALE-05, BR-SALE-06, BR-SALE-11, BR-CAT-29, BR-NUM-02; D-067, D-075; TASK-029):
// one base unit per add, one line per product, fractions only typed, the "−" floor, the total exactly
// from canonical strings, a refusal's marks at its lines by the products sent, the price review, the
// tender and change, and the request with the prices reviewed.

const PEN: CartProduct = { id: 11, name: 'Pulpen gel hitam', sku: 'PG-1', unit: 'pcs', precision: 0, price: '4500', revisionId: 101, thumbnail: null };

const PAPER: CartProduct = { id: 12, name: 'Kertas kado per meter', sku: 'KK-1', unit: 'm', precision: 1, price: '7000', revisionId: 201, thumbnail: null };

const THIRD: CartProduct = { id: 13, name: 'Kalkulator', sku: 'KL-1', unit: 'pcs', precision: 0, price: '3333', revisionId: 301, thumbnail: null };

function cartOf(...products: CartProduct[]): TillDraft {
    return products.reduce(addProduct, EMPTY_DRAFT);
}

describe('the cart of the till', () => {
    it('adds one base unit per add, to the product\'s own line, never a second line (BR-CAT-29, D-067)', () => {
        const draft = cartOf(PEN, PAPER, PEN, PEN);

        expect(draft.lines.map((line) => [line.product.id, line.quantity])).toEqual([
            [11, '3'],
            [12, '1'],
        ]);
        expect(cartTotal(draft.lines)).toBe('20500');
    });

    it('takes a fraction only from a typed quantity, within the product\'s precision (BR-NUM-02)', () => {
        const draft = setQuantity(cartOf(PAPER), 12, '2,5');

        expect(lineTotalOf(draft.lines[0]!)).toBe('17500');
        expect(readQuantity('2,5', 1)).toEqual({ quantity: '2.5' });
        expect(readQuantity('2,55', 1)).toEqual({ problem: { kind: 'decimals', precision: 1 } });
        expect(readQuantity('1,5', 0)).toEqual({ problem: { kind: 'whole' } });
        expect(readQuantity('0', 0)).toEqual({ problem: { kind: 'positive' } });
        expect(readQuantity(' ', 0)).toEqual({ problem: { kind: 'required' } });
        expect(readQuantity('abc', 0)).toEqual({ problem: { kind: 'positive' } });
        expect(readQuantity('123456789', 0)).toEqual({ problem: { kind: 'max' } });
        expect(readQuantity('1.250,5', 1)).toEqual({ quantity: '1250.5' });

        // An invalid line counts for nothing and keeps "Bayar" back (UXI-075).
        const invalid = setQuantity(cartOf(PEN, PAPER), 12, '2,55');

        expect(lineTotalOf(invalid.lines[1]!)).toBeNull();
        expect(cartTotal(invalid.lines)).toBe('4500');
        expect(linesValid(invalid.lines)).toBe(false);
        expect(linesValid(cartOf(PEN, PAPER).lines)).toBe(true);
    });

    it('rounds each line half-up once and sums the line totals (BR-SALE-06)', () => {
        const draft = setQuantity(cartOf(THIRD, PAPER), 13, '1');

        // 1,5 m at Rp3.333 would be Rp4.999,5: the till shows the server's rounding.
        expect(lineTotalOf({ ...draft.lines[0]!, product: { ...THIRD, unit: 'm', precision: 1 }, quantity: '1,5' })).toBe('5000');
        expect(cartTotal(draft.lines)).toBe('10333');
    });

    it('steps by one base unit, never below the smallest valid quantity (UXI-071)', () => {
        let draft = setQuantity(cartOf(PEN, PAPER), 12, '1,5');

        draft = stepQuantity(draft, 11, 1);
        expect(draft.lines[0]!.quantity).toBe('2');
        draft = stepQuantity(draft, 11, -1);
        draft = stepQuantity(draft, 11, -1);
        expect(draft.lines[0]!.quantity).toBe('1');
        expect(canDecrease(draft.lines[0]!)).toBe(false);

        draft = stepQuantity(draft, 12, -1);
        expect(draft.lines[1]!.quantity).toBe('0,5');
        draft = stepQuantity(draft, 12, -1);
        expect(draft.lines[1]!.quantity).toBe('0,1');
        expect(canDecrease(draft.lines[1]!)).toBe(false);
        expect(stepQuantity(draft, 12, -1).lines[1]!.quantity).toBe('0,1');
        expect(stepQuantity(draft, 12, 1).lines[1]!.quantity).toBe('1,1');
    });

    it('removes a line and empties the cart; a change of the lines empties the payment entries (UXP-102)', () => {
        const paying: TillDraft = { ...cartOf(PEN, PAPER), payment: { method: BANK_TRANSFER, tendered: null, reference: 'TRF-1', customerName: 'Bu Sari' } };

        expect(removeLine(paying, 11).lines.map((line) => line.product.id)).toEqual([12]);
        expect(removeLine(paying, 11).payment).toEqual(EMPTY_DRAFT.payment);
        expect(clearCart(paying)).toEqual(EMPTY_DRAFT);
        expect(EMPTY_DRAFT.payment.method).toBe(CASH);
    });

    it('marks a newer price for review and changes the total only through "Setuju harga baru" (UXS-18, BR-SALE-05)', () => {
        const draft = cartOf(PEN, PAPER);
        const refused = applyRefusal(draft, 'PRICE_CHANGED', { '1': { productId: 12, price: { amount: '7500', revisionId: 202 } } }, [11, 12]);
        const paper = refused.lines[1]!;

        expect([paper.previousPrice, paper.product.price, paper.product.revisionId]).toEqual(['7000', '7500', 202]);
        expect(priceReviewPending(refused.lines)).toBe(true);
        expect(cartTotal(refused.lines)).toBe('12000');
        expect(priceReviewPending(acceptNewPrices(refused).lines)).toBe(false);

        // The same amount at another revision needs no review; a scan that reads a newer price marks it too.
        expect(priceReviewPending(applyRefusal(draft, 'PRICE_CHANGED', { '0': { productId: 11, price: { amount: '4500', revisionId: 102 } } }, [11, 12]).lines)).toBe(false);
        const scanned = addProduct(draft, { ...PEN, price: '5000', revisionId: 103 });

        expect([scanned.lines[0]!.quantity, scanned.lines[0]!.previousPrice, scanned.lines[0]!.product.price]).toEqual(['2', '4500', '5000']);
    });

    it('marks the stock available, an inactive and a blocked product at their lines, by the products sent (NC-09)', () => {
        const draft = cartOf(PEN, PAPER, THIRD);
        const sent = [11, 12, 13];

        expect(applyRefusal(draft, 'INSUFFICIENT_STOCK', { '2': { productId: 13, available: '2' } }, sent).lines.map((line) => line.available)).toEqual([null, null, '2']);
        expect(applyRefusal(draft, 'PRODUCT_INACTIVE', { '0': { productId: 11 } }, sent).lines.map((line) => line.refusal)).toEqual(['inactive', null, null]);
        expect(applyRefusal(draft, 'POSTING_BLOCKED', { '1': { productId: 12 } }, sent).lines.map((line) => line.refusal)).toEqual([null, 'blocked', null]);
        // A line index that names no line sent changes nothing.
        expect(applyRefusal(draft, 'INSUFFICIENT_STOCK', { '7': { productId: 13, available: '2' } }, sent)).toEqual(draft);
        // A quantity typed again ends the mark of its line.
        expect(setQuantity(applyRefusal(draft, 'INSUFFICIENT_STOCK', { '2': { productId: 13, available: '2' } }, sent), 13, '2').lines[2]!.available).toBeNull();
    });
});

describe('the payment of the till', () => {
    it('shows the change only from enough cash, and the missing amount otherwise (BR-SALE-11, BR-PAY-02)', () => {
        expect(tenderOf('26500', null)).toEqual({ kind: 'none' });
        expect(tenderOf('26500', '20000')).toEqual({ kind: 'short', missing: '6500' });
        expect(tenderOf('26500', '26500')).toEqual({ kind: 'enough', change: '0' });
        expect(tenderOf('26500', '30000')).toEqual({ kind: 'enough', change: '3500' });
        expect(tenderOf('26500', '1000000000000')).toEqual({ kind: 'none' });
    });

    it('sends each line with its product and canonical quantity, the prices reviewed, and only the method\'s own fields (RC-01)', () => {
        const draft = setQuantity(cartOf(PEN, PAPER), 12, '2,5');
        const cash = checkoutRequest({ ...draft, payment: { method: CASH, tendered: '30000', reference: 'ignored', customerName: '  Bu Sari ' } }, 7);

        expect(cash).toEqual({
            payload: {
                sessionId: 7,
                lines: [
                    { productId: 11, quantity: '1' },
                    { productId: 12, quantity: '2.5' },
                ],
                payment: { method: CASH, tendered: '30000', customerName: 'Bu Sari' },
            },
            expected: { lines: [{ priceRevisionId: 101 }, { priceRevisionId: 201 }] },
            products: [11, 12],
        });

        const transfer = checkoutRequest({ ...draft, payment: { method: BANK_TRANSFER, tendered: '30000', reference: ' ', customerName: '' } }, 7);

        expect(transfer.payload.payment).toEqual({ method: BANK_TRANSFER, reference: null, customerName: null });
    });

    it('keeps a draft of the history entry and starts empty from anything else', () => {
        const draft = cartOf(PEN);

        expect(draftOf(draft)).toEqual(draft);
        expect(draftOf(null)).toEqual(EMPTY_DRAFT);
        expect(draftOf({ lines: 'x', payment: {} })).toEqual(EMPTY_DRAFT);
        expect(draftOf({ lines: [], payment: { method: 'STATIC_QRIS', tendered: 5 } })).toEqual(EMPTY_DRAFT);
    });

    it('takes a product of RD-01 with its price revision', () => {
        const row = { id: 11, name: 'Pulpen gel hitam', sku: 'PG-1', baseUnit: 'pcs', quantityPrecision: 0, price: { amount: '4500', revisionId: 101 }, active: true, thumbnail: null, activeBarcode: true };

        expect(cartProductOf(row)).toEqual(PEN);
    });
});
