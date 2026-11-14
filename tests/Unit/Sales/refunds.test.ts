import { describe, expect, it } from 'vitest';
import {
    BANK_TRANSFER,
    CASH,
    draftLines,
    draftTotal,
    EMPTY_LINE,
    OWNER_SOURCE,
    refundPayload,
    refundRequest,
    sessionSource,
    showsAmount,
    showsQuantity,
    type FundingSession,
    type LineDraft,
    type RefundLine,
    type SentLine,
} from '@/lib/sales/refunds';

// The payout that UXP-203 sends for the source the Owner chose (TASK-034 review R-01; SALES_CASH BR-RFD-05,
// BR-RFD-06, BR-RFD-16; D-045, D-087; INTERACTION_CONTRACTS RC-05, RC-06): a listed OPEN session's drawer
// by RC-05, naming that session alone; "Dana Pemilik" and a transfer by RC-06, with the payout made; a return
// without money by RC-06, without a payout. A CASH refund whose source is none, or a session that the page
// no longer lists, sends nothing — never the Owner's own funds or another session.

const LACI_1: FundingSession = { id: 7, number: '20261114-000001', registerCode: 'LACI-1', registerLabel: 'Laci 1', responsible: 'Rina', expectedCash: '50000' };

const LACI_2: FundingSession = { id: 8, number: '20261114-000002', registerCode: 'LACI-2', registerLabel: 'Laci 2', responsible: 'Dewi', expectedCash: '0' };

const LINES: readonly SentLine[] = [{ saleLineId: 9, refundAmount: '20000', returnedSaleable: '0', returnedNotSaleable: '0' }];

const BASE = { saleId: 3, lines: LINES, reason: 'Pelanggan membatalkan sebagian pembelian.' };

describe('the payout of the refund page', () => {
    it('is the listed drawer for RC-05, and the Owner\'s funds, a transfer or none for RC-06', () => {
        const drawer = refundRequest(true, CASH, sessionSource(8), [LACI_1, LACI_2]);

        expect(drawer).toEqual({ kind: 'drawer', session: LACI_2 });
        expect(drawer === null ? null : refundPayload(drawer, BASE, 'DANA-1')).toEqual({ ...BASE, payout: { sessionId: 8 } });
        expect(refundPayload({ kind: 'owner' }, BASE, 'DANA-1')).toEqual({ ...BASE, payout: { method: CASH, reference: 'DANA-1' } });
        expect(refundRequest(true, CASH, OWNER_SOURCE, [LACI_1])).toEqual({ kind: 'owner' });
        expect(refundRequest(true, BANK_TRANSFER, sessionSource(7), [LACI_1])).toEqual({ kind: 'transfer' });
        expect(refundPayload({ kind: 'transfer' }, BASE, 'TRF-1')).toEqual({ ...BASE, payout: { method: BANK_TRANSFER, reference: 'TRF-1' } });
        expect(refundRequest(false, null, null, [])).toEqual({ kind: 'return' });
        expect(refundPayload({ kind: 'return' }, BASE, 'TRF-1')).toEqual(BASE);
    });

    it('is none while no method or source is chosen, and for a session no longer listed, never another source (review R-01)', () => {
        expect(refundRequest(true, null, OWNER_SOURCE, [LACI_1])).toBeNull();
        expect(refundRequest(true, CASH, null, [LACI_1])).toBeNull();
        // The chosen session closed, so that the sessions read again no longer list it.
        expect(refundRequest(true, CASH, sessionSource(7), [LACI_2])).toBeNull();
        expect(refundRequest(true, CASH, sessionSource(7), [])).toBeNull();
    });
});

// A line's fields after a bound refused (UI_STATES UXS-35; NAVIGATION_CONTRACTS UXP-203; the automated
// reviewer's P2 finding on pull request #46): the lines read again may leave a line with nothing more to
// refund, or to return, while the draft still holds what the Owner typed there, which its total and payload
// still send. The field stays while the draft holds a value there or its reason stands, so that the Owner can
// change it; without either, a field whose bound is nothing is not shown.

const LINE: RefundLine = { saleLineId: 9, lineNo: 1, name: 'Pulpen Hitam', sku: 'PLP-01', unit: 'pcs', quantityPrecision: 0, quantity: '2', lineTotal: '20000', remainingAmount: '20000', remainingQuantity: '2' };

describe('the fields of a line of the refund page', () => {
    it('show what remains, and no field whose bound is nothing on an empty draft', () => {
        expect(showsAmount(EMPTY_LINE, LINE, false)).toBe(true);
        expect(showsQuantity(EMPTY_LINE, LINE, false)).toBe(true);
        expect(showsAmount(EMPTY_LINE, { ...LINE, remainingAmount: '0' }, false)).toBe(false);
        expect(showsQuantity(EMPTY_LINE, { ...LINE, remainingQuantity: '0' }, false)).toBe(false);
    });

    it('keep an amount that no longer fits at its field until the Owner empties it and reviews again (UXS-35)', () => {
        // The rest of the line's money refunded in another tab: read again after REFUND_BOUND_EXCEEDED, the
        // line has goods left only, and the draft still sends the amount typed.
        const read: RefundLine = { ...LINE, remainingAmount: '0' };
        const typed: LineDraft = { amount: '20000', quantity: '', condition: null };

        expect(draftTotal({ 9: typed }, [read])).toBe('20000');
        expect(draftLines({ 9: typed }, [read])).toEqual([{ saleLineId: 9, refundAmount: '20000', returnedSaleable: '0', returnedNotSaleable: '0' }]);
        expect(showsAmount(typed, read, true)).toBe(true);
        expect(showsAmount(typed, read, false)).toBe(true);

        // Emptied while its reason stands, the field stays where the Owner types; with no reason at it — once
        // reviewed again, or where the refusal names no reason there — it goes as soon as it is empty, and
        // nothing of it is sent (review R-10).
        const emptied: LineDraft = { ...typed, amount: null };

        expect(showsAmount(emptied, read, true)).toBe(true);
        expect(showsAmount(emptied, read, false)).toBe(false);
        expect(draftTotal({ 9: emptied }, [read])).toBe('0');
        expect(draftLines({ 9: emptied }, [read])).toEqual([]);
    });

    it('keep a quantity that no longer fits at its field, with its condition, as the amount (UXS-35)', () => {
        // The rest of the line's goods returned meanwhile: read again after RETURN_BOUND_EXCEEDED.
        const read: RefundLine = { ...LINE, remainingQuantity: '0' };
        const typed: LineDraft = { amount: null, quantity: '1', condition: 'SALEABLE' };

        expect(draftLines({ 9: typed }, [read])).toEqual([{ saleLineId: 9, refundAmount: '0', returnedSaleable: '1', returnedNotSaleable: '0' }]);
        expect(showsQuantity(typed, read, true)).toBe(true);
        expect(showsQuantity(typed, read, false)).toBe(true);

        const emptied: LineDraft = { ...typed, quantity: '' };

        expect(showsQuantity(emptied, read, true)).toBe(true);
        expect(showsQuantity(emptied, read, false)).toBe(false);
        expect(draftLines({ 9: emptied }, [read])).toEqual([]);
    });
});
