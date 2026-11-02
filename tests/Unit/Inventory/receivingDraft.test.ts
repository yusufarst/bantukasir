import { describe, expect, it } from 'vitest';
import { quantityFromEntry } from '@/lib/catalog/products';
import { formatQuantity } from '@/lib/format';
import { draftQuantity } from '@/lib/inventory/stock';

// A draft line's quantity on UXP-405 (TASK-022 scope item 1; pull request #31, finding P1): the
// review's children are made with every render, the dialog closed too, so the quantity as typed
// reached the formatter while the user edited it, and an emptied or half-typed field ended the
// page and its draft, which lives in page memory only (BR-INV-17). The review now formats only
// `draftQuantity`, which is a quantity or null.

/** Entries a user passes through while editing a line, and pastes, that are no quantity yet. */
const NOT_YET = ['', ' ', '0,', '1,', '12,', ',5', '1.', 'abc', '-1', '1,2,3', '1,2345', '123456789', '9'.repeat(45)];

describe("a draft line's quantity", () => {
    it('was handed to the formatter as typed, which refuses what is no quantity yet (the defect)', () => {
        for (const entry of ['', '0,', '1,', '01', '2,0']) {
            expect(() => formatQuantity(quantityFromEntry(entry), 0, 'pcs')).toThrow();
        }
    });

    it('is none while the entry is no quantity of the precision', () => {
        for (const entry of NOT_YET) {
            expect(draftQuantity(entry, 3)).toBeNull();
        }

        expect(draftQuantity('1,5', 0)).toBeNull();
        expect(draftQuantity('1,25', 1)).toBeNull();
    });

    it('is the canonical form of an entry that is one, in its shortest digits', () => {
        expect(draftQuantity('1', 0)).toBe('1');
        expect(draftQuantity(' 2 ', 0)).toBe('2');
        expect(draftQuantity('01', 0)).toBe('1');
        expect(draftQuantity('007', 0)).toBe('7');
        expect(draftQuantity('2,0', 0)).toBe('2');
        expect(draftQuantity('2,50', 1)).toBe('2.5');
        expect(draftQuantity('0,500', 3)).toBe('0.5');
        expect(draftQuantity('1.250,5', 1)).toBe('1250.5');
        expect(draftQuantity('99.999.999,999', 3)).toBe('99999999.999');
        expect(draftQuantity('0', 0)).toBe('0');
    });

    it('never makes the formatter refuse, whatever was typed', () => {
        for (const entry of [...NOT_YET, '1', '01', '2,0', '2,50', '0,500', '1.250,5', '99.999.999,999', '0']) {
            for (const precision of [0, 1, 2, 3]) {
                const quantity = draftQuantity(entry, precision);

                expect(() => (quantity === null ? null : formatQuantity(quantity, precision, 'pcs'))).not.toThrow();
            }
        }

        expect(formatQuantity(draftQuantity('2,0', 0) ?? '', 0, 'pcs')).toBe('2 pcs');
        expect(formatQuantity(draftQuantity('1.250,5', 1) ?? '', 1, 'kg')).toBe('1.250,5 kg');
    });
});
