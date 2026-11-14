import { describe, expect, it } from 'vitest';
import { BANK_TRANSFER, CASH, OWNER_SOURCE, refundPayload, refundRequest, sessionSource, type FundingSession, type SentLine } from '@/lib/sales/refunds';

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
