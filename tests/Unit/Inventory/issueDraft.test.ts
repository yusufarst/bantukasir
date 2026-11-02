import { describe, expect, it } from 'vitest';
import { issueReasonOf, issuesAddress, issueSteps, ISSUE_REASONS } from '@/lib/inventory/stock';

// The review of a draft of a manual issue on UXP-408 and the address of UXP-407 (TASK-023 scope
// item 4; BR-INV-07, BR-INV-19, BR-INV-20; COPY_DECK sections 9.8 and 9.9): the stock before, the
// change and the stock after of every line, the lines of one product taken in their order, from
// the stock the search gave; a product whose lines ask for more than it holds is marked, never
// shown below zero, since the server refuses it; the filters stand in the address (NC-10).

describe('the review of a manual issue', () => {
    it('takes the lines of one product in their order, each from the stock the line before it left', () => {
        expect(
            issueSteps([
                { productId: 1, quantity: '3', onHand: '10' },
                { productId: 2, quantity: '0.25', onHand: '2.5' },
                { productId: 1, quantity: '2', onHand: '10' },
            ]),
        ).toEqual([
            { before: '10', after: '7' },
            { before: '2.5', after: '2.25' },
            { before: '7', after: '5' },
        ]);
    });

    it('takes the stock to zero exactly, and marks a product whose lines ask for more than it holds', () => {
        expect(issueSteps([{ productId: 1, quantity: '1.5', onHand: '1.5' }])).toEqual([{ before: '1.5', after: '0' }]);
        expect(
            issueSteps([
                { productId: 1, quantity: '4', onHand: '5' },
                { productId: 1, quantity: '2', onHand: '5' },
                { productId: 1, quantity: '1', onHand: '5' },
            ]),
        ).toEqual([{ before: '5', after: '1' }, { before: '1', after: null }, { before: '0', after: null }]);
    });

    it('has no step for a quantity that is none yet or a product whose stock the search did not give', () => {
        expect(
            issueSteps([
                { productId: 1, quantity: null, onHand: '5' },
                { productId: 2, quantity: '1', onHand: null },
                { productId: 1, quantity: '1', onHand: '5' },
            ]),
        ).toEqual([null, null, { before: '5', after: '4' }]);
    });

    it('is exact at the largest quantities, in thousandths', () => {
        expect(issueSteps([{ productId: 1, quantity: '0.001', onHand: '99999999.999' }])).toEqual([{ before: '99999999.999', after: '99999999.998' }]);
    });
});

describe('the reasons and the address of the list', () => {
    it('holds the five reasons of BR-INV-19 in their order, and nothing else', () => {
        expect(ISSUE_REASONS.map((entry) => entry.reason)).toEqual(['DAMAGED', 'INTERNAL_USE', 'LOST', 'SAMPLE', 'OTHER']);
        expect(issueReasonOf('LOST')).toBe('LOST');
        expect(issueReasonOf('')).toBeNull();
        expect(issueReasonOf('lost')).toBeNull();
    });

    it('puts the filters and the cursor in the address, and only those set', () => {
        expect(issuesAddress({ from: null, to: null, reason: null })).toBe('/stock/issues');
        expect(issuesAddress({ from: '2026-11-03', to: '2026-11-03', reason: 'SAMPLE' }, { after: 'abc' })).toBe('/stock/issues?from=2026-11-03&to=2026-11-03&reason=SAMPLE&after=abc');
        expect(issuesAddress({ from: null, to: null, reason: 'OTHER' }, { before: 'xyz' })).toBe('/stock/issues?reason=OTHER&before=xyz');
    });
});
