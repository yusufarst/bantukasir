import { beforeEach, describe, expect, it } from 'vitest';
import { errorsFirst, importErrors, importErrorText, namesMissingCategory, previewAnswerOf, rowErrorText, rowsOfRefusal, type PreviewRow } from '@/lib/catalog/csvImport';
import { searchParameters, productsAddress } from '@/lib/catalog/products';
import { installDictionary } from '@/lib/i18n';

// What the page of the CSV import makes of its answers (TASK-019, L1; COPY_DECK section 16.3;
// INTERACTION_CONTRACTS sections 1.6 and 6): the requests' answers, the words of each error, the
// rows with errors first, and RC-31's location in the list's address.

const preview = { importId: 'a', fileSha256: 'b', dataRowCount: 0, readyRows: 0, errorRows: 0, rows: [] };

function row(number: number, errors: PreviewRow['errors'] = []): PreviewRow {
    return { row: number, sku: '', name: '', category: '', sellingPrice: '', errors };
}

describe('the answer of RR-01', () => {
    it('reads each status', () => {
        expect(previewAnswerOf(null)).toEqual({ kind: 'failed' });
        expect(previewAnswerOf({ status: 200, body: preview, retryAfter: null })).toEqual({ kind: 'preview', preview });
        expect(previewAnswerOf({ status: 413, body: null, retryAfter: null })).toEqual({ kind: 'refused', errors: [{ key: 'ux.validation.upload.too_large', params: { ukuran: '2 MB' } }] });
        expect(previewAnswerOf({ status: 422, body: { fields: { file: [{ key: 'ux.validation.upload.csv_type', params: {} }] } }, retryAfter: null })).toEqual({ kind: 'refused', errors: [{ key: 'ux.validation.upload.csv_type', params: {} }] });
        expect(previewAnswerOf({ status: 422, body: { fields: {} }, retryAfter: null })).toEqual({ kind: 'failed' });
        expect(previewAnswerOf({ status: 429, body: null, retryAfter: '60' })).toEqual({ kind: 'throttled', seconds: 60n });
        expect(previewAnswerOf({ status: 419, body: null, retryAfter: null })).toEqual({ kind: 'reload' });
        expect(previewAnswerOf({ status: 403, body: null, retryAfter: null })).toEqual({ kind: 'forbidden' });
        expect(previewAnswerOf({ status: 500, body: {}, retryAfter: null })).toEqual({ kind: 'failed' });
        expect(previewAnswerOf({ status: 200, body: null, retryAfter: null })).toEqual({ kind: 'failed' });
    });

    it('keeps only well-formed entries of a field', () => {
        expect(importErrors(null)).toEqual([]);
        expect(importErrors([{ key: 'k', params: { kolom: 'SKU' } }, { key: 'l' }, { nope: 1 }, 'x'])).toEqual([{ key: 'k', params: { kolom: 'SKU' } }, { key: 'l', params: {} }]);
    });
});

describe('the words of an error', () => {
    beforeEach(() => {
        installDictionary({
            'catalog.csv_import.error.row': 'Baris :nomor: :masalah',
            'catalog.csv_import.error.required': 'Kolom :kolom wajib diisi.',
            'catalog.csv_import.error.formula': 'Sel yang diawali = tidak diterima.',
        });
    });

    it('names its column and its row', () => {
        const error = { key: 'catalog.csv_import.error.required', params: { kolom: 'SKU' } };

        expect(importErrorText(error)).toBe('Kolom SKU wajib diisi.');
        expect(rowErrorText(7, error)).toBe('Baris 7: Kolom SKU wajib diisi.');
        expect(rowErrorText(8, { key: 'catalog.csv_import.error.formula', params: {} })).toBe('Baris 8: Sel yang diawali = tidak diterima.');
    });

    it('says nothing for a key that the page does not show', () => {
        expect(importErrorText({ key: 'lain', params: {} })).toBeUndefined();
    });
});

describe('the rows', () => {
    const wrong = { key: 'catalog.csv_import.error.category_missing', params: {} };

    it('puts the rows with errors first, each part in the order of the file', () => {
        expect(errorsFirst([row(2), row(3, [wrong]), row(4), row(5, [wrong])]).map((entry) => entry.row)).toEqual([3, 5, 2, 4]);
    });

    it('reads the rows of a refusal in the order of the file', () => {
        expect(rowsOfRefusal({ 10: { errors: [wrong] }, 2: { errors: [wrong] }, 3: { errors: [] }, x: { errors: [wrong] }, 4: 'bukan' })).toEqual([
            { row: '2', errors: [wrong] },
            { row: '10', errors: [wrong] },
        ]);
    });

    it('knows when a row names a category that no active category matches (UXI-209)', () => {
        expect(namesMissingCategory([wrong])).toBe(true);
        expect(namesMissingCategory([{ key: 'catalog.csv_import.error.category_ambiguous', params: {} }])).toBe(false);
    });
});

describe('RC-31 location in the list', () => {
    const filters = { q: '', category: null, brand: '', status: null, page: 1 };

    it('stands in the address and the read', () => {
        expect(productsAddress({ ...filters, import: 7 })).toBe('/products?import=7');
        expect(searchParameters({ ...filters, import: 7 })).toEqual({ import: '7' });
        expect(productsAddress({ ...filters, import: null })).toBe('/products');
        expect(searchParameters(filters)).toEqual({});
    });
});
