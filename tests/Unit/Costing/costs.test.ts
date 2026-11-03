import { describe, expect, it } from 'vitest';
import { COST_CORRECTION_COMMAND, correctionPage, correctionPayload, correctionTitle, costHistoryPage, isZeroAmount, newCostProblem, worklistAddress } from '@/lib/costing/costs';

// The cost pages' shared parts (TASK-025; INTERACTION_CONTRACTS RC-15 and sections 1.4 and 1.5;
// ROUTE_CONTRACTS RP-503 to RP-505; COPY_DECK sections 4.1 and 10.5; BR-COST-03, BR-COST-04).

describe('RC-15 and the addresses of the cost pages', () => {
    it('names the family of a cost correction and its recent authentication', () => {
        expect(COST_CORRECTION_COMMAND).toEqual({ operation: 'cost_correction.confirm', family: 'cost_correction', recentAuth: true });
    });

    it('opens RP-504 and RP-505 with the page that opened them', () => {
        expect(correctionPage(12, 'worklist')).toBe('/costs/12/correct?from_page=worklist');
        expect(correctionPage(7, 'movement')).toBe('/costs/7/correct?from_page=movement');
        expect(costHistoryPage(3, 'product')).toBe('/products/3/cost-history?from_page=product');
    });

    it('keeps the filter and the page of UXP-503 in its address, the first page without a cursor', () => {
        expect(worklistAddress(null)).toBe('/reports/costs-to-complete');
        expect(worklistAddress(9)).toBe('/reports/costs-to-complete?product=9');
        expect(worklistAddress(9, { after: 'abc' })).toBe('/reports/costs-to-complete?product=9&after=abc');
        expect(worklistAddress(null, { before: 'xyz' })).toBe('/reports/costs-to-complete?before=xyz');
    });

    it('titles UXP-504 "Lengkapi biaya" while the cost is UNKNOWN and "Koreksi biaya" otherwise', () => {
        expect(correctionTitle({ status: 'UNKNOWN', amount: null })).toBe('costing.correction.title_complete');
        expect(correctionTitle({ status: 'KNOWN', amount: '5000' })).toBe('costing.correction.title_correct');
    });
});

describe('the draft of a cost correction', () => {
    it('requires a new total of at most twelve whole digits', () => {
        expect(newCostProblem(null)).toBe('ux.validation.required');
        expect(newCostProblem('')).toBe('ux.validation.required');
        expect(newCostProblem('5500')).toBeNull();
        expect(newCostProblem('0')).toBeNull();
        expect(newCostProblem('999999999999')).toBeNull();
        expect(newCostProblem('1000000000000')).toBe('ux.validation.money_max');
        expect(newCostProblem('0000000000005')).toBeNull();
    });

    it('tells an explicit zero from an amount, and UNKNOWN is no amount at all', () => {
        expect(isZeroAmount('0')).toBe(true);
        expect(isZeroAmount('000')).toBe(true);
        expect(isZeroAmount('0.000000')).toBe(true);
        expect(isZeroAmount('10')).toBe(false);
        expect(isZeroAmount(null)).toBe(false);
    });

    it('sends the new total as KNOWN, an explicit zero with its evidence or reason, and the reason trimmed (BR-COST-04)', () => {
        expect(correctionPayload(4, '5500', 'ignored', '  Nota pemasok  ')).toEqual({ evidenceId: 4, newCost: { amount: '5500' }, reason: 'Nota pemasok' });
        expect(correctionPayload(4, '000', ' Sampel gratis ', 'Hadiah pemasok')).toEqual({ evidenceId: 4, newCost: { amount: '0', zeroEvidence: 'Sampel gratis' }, reason: 'Hadiah pemasok' });
        expect(correctionPayload(4, '0012', '', 'Salah ketik')).toEqual({ evidenceId: 4, newCost: { amount: '12' }, reason: 'Salah ketik' });
    });
});
