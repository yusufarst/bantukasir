import { describe, expect, it } from 'vitest';
import { precisionOf, productsAddress, quantityFromEntry, searchParameters } from '@/lib/catalog/products';
import { shouldCapture } from '@/lib/catalog/scanCapture';

// What the catalogue pages compute without a page (AC-017-02, AC-017-04; TASK-017):
// a quantity as typed, the address that keeps a list's filters (NC-10), the parameters
// of RD-01, and scan capture, which pauses while a dialog is open (UXA-11).

const PLAIN = { key: '8', ctrlKey: false, metaKey: false, altKey: false };

describe('a quantity as typed', () => {
    it('reads the format of COPY_DECK section 1.3 in the canonical form', () => {
        expect(quantityFromEntry('0')).toBe('0');
        expect(quantityFromEntry(' 1,5 ')).toBe('1.5');
        expect(quantityFromEntry('1.250,5')).toBe('1250.5');
        expect(quantityFromEntry('1.250')).toBe('1250');
        expect(quantityFromEntry('1.234.567')).toBe('1234567');
    });

    it('leaves anything else as typed, for the server to refuse at the field', () => {
        expect(quantityFromEntry('1.5')).toBe('1.5');
        expect(quantityFromEntry('abc')).toBe('abc');
        expect(quantityFromEntry('-1')).toBe('-1');
    });
});

describe('the address of the list', () => {
    const none = { q: '', category: null, brand: '', status: null, page: 1 };

    it('holds only the filters that are set', () => {
        expect(productsAddress(none)).toBe('/products');
        expect(productsAddress({ q: 'pulpen & co', category: 7, brand: 'Merek A', status: 'inactive', page: 3 })).toBe('/products?q=pulpen+%26+co&category=7&brand=Merek+A&status=inactive&page=3');
    });

    it('gives RD-01 the filters and leaves the page to the read', () => {
        expect(searchParameters(none)).toEqual({});
        expect(searchParameters({ q: 'a', category: 7, brand: 'b', status: 'active', page: 4 })).toEqual({ q: 'a', category: '7', brand: 'b', status: 'active' });
    });
});

describe('a precision of the form', () => {
    it('is one of BR-NUM-02', () => {
        expect(precisionOf('0')).toBe(0);
        expect(precisionOf('3')).toBe(3);
        expect(precisionOf('4')).toBe(0);
    });
});

describe('scan capture', () => {
    it('takes a text key when no field holds the focus', () => {
        expect(shouldCapture(PLAIN, 'other', false)).toBe(true);
    });

    it('leaves another field its typing, and a menu its keys', () => {
        expect(shouldCapture(PLAIN, 'field', false)).toBe(false);
        expect(shouldCapture(PLAIN, 'menu', false)).toBe(false);
    });

    it('pauses while a dialog is open', () => {
        expect(shouldCapture(PLAIN, 'other', true)).toBe(false);
    });

    it('leaves Enter, Space, Tab and the shortcuts alone', () => {
        for (const key of ['Enter', ' ', 'Tab', 'F2', 'Escape']) {
            expect(shouldCapture({ ...PLAIN, key }, 'other', false), key).toBe(false);
        }

        expect(shouldCapture({ ...PLAIN, ctrlKey: true }, 'other', false)).toBe(false);
        expect(shouldCapture({ ...PLAIN, metaKey: true }, 'other', false)).toBe(false);
        expect(shouldCapture({ ...PLAIN, altKey: true }, 'other', false)).toBe(false);
    });
});
