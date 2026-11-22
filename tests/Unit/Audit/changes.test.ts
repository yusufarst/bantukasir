import { beforeAll, describe, expect, it } from 'vitest';
import { changeText, changeWord, type ChangeValue } from '@/lib/audit/activity';
import { installDictionary, namedKeyText } from '@/lib/i18n';
import { mismatchWord } from '@/lib/inventory/incidents';

// "Perubahan" of UXP-612 (AC-051-02; COPY_DECK section 11.15, ACV-03 and ACV-04; TASK-051) and the detail code of
// UXP-415 (TASK-051 F-1, D-138): each kind of value as the page presents it; a key the server names shows its text,
// and a key the dictionary lacks or one that takes placeholders shows nothing; a value that cannot be read shows
// nothing rather than breaking the page; a block's detail code shows its approved label, never the code.

beforeAll(() => {
    installDictionary({
        'access.role.list': ':peran',
        'access.role.cashier': 'Kasir',
        'access.role.operations_admin': 'Admin Operasional',
        'audit.change.field.total': 'Total',
        'audit.change.from_to': ':lama → :baru',
        'audit.change.value.cost_zero': ':uang (:catatan)',
        'audit.change.value.yes': 'Ya',
        'audit.change.value.mismatch_balance': 'Saldo stok',
        'audit.change.value.mismatch_valuation': 'Nilai persediaan',
        'audit.change.value.mismatch_issue_projection': 'Biaya barang keluar',
        'audit.change.value.mismatch_return_projection': 'Biaya retur',
        'audit.change.value.mismatch_history': 'Riwayat mutasi tidak bisa dihitung ulang',
        'costing.status.unknown': 'Belum diketahui',
        'ux.format.variance': ':uang (:keterangan)',
        'ux.format.variance_short': 'kurang',
        'ux.format.variance_over': 'lebih',
        'ux.format.variance_even': 'sesuai',
        'ux.format.month.november': 'November',
    });
});

function value(kind: string, text: string | null, extra: Partial<ChangeValue> = {}): ChangeValue {
    return { kind, text, keys: [], precision: null, unit: null, ...extra };
}

describe('a value under "Perubahan"', () => {
    it('is money, a variance with its word, a cost rounded once, or a known zero cost with its reason', () => {
        expect(changeText(value('money', '1234567'))).toBe('Rp1.234.567');
        expect(changeText(value('variance', '-2000'))).toBe('−Rp2.000 (kurang)');
        expect(changeText(value('variance', '0'))).toBe('Rp0 (sesuai)');
        expect(changeText(value('cost', '3500.5'))).toBe('Rp3.501');
        expect(changeText(value('cost_zero', 'Hadiah pemasok'))).toBe('Rp0 (Hadiah pemasok)');
    });

    it('is a quantity in its unit and precision, a signed one with + before an increase, a date and a count', () => {
        expect(changeText(value('quantity', '1250.5', { precision: 1, unit: 'kg' }))).toBe('1.250,5 kg');
        expect(changeText(value('quantity', '2', { precision: 0, unit: null }))).toBe('2');
        expect(changeText(value('signed', '1.5', { precision: 1, unit: 'm' }))).toBe('+1,5 m');
        expect(changeText(value('signed', '-2', { precision: 0, unit: 'pcs' }))).toBe('−2 pcs');
        expect(changeText(value('signed', '0', { precision: 0, unit: 'pcs' }))).toBe('0 pcs');
        expect(changeText(value('date', '2026-11-15'))).toBe('15 November 2026');
        expect(changeText(value('count', '1500'))).toBe('1.500');
        expect(changeText(value('text', 'Pulpen Hitam'))).toBe('Pulpen Hitam');
    });

    it('is the labels of codes joined as roles are, or the word of an UNKNOWN cost', () => {
        expect(changeText(value('labels', null, { keys: ['access.role.operations_admin', 'access.role.cashier'] }))).toBe('Admin Operasional, Kasir');
        expect(changeText(value('labels', null, { keys: ['costing.status.unknown'] }))).toBe('Belum diketahui');
        expect(changeText(value('labels', null, { keys: ['audit.change.value.yes'] }))).toBe('Ya');
    });

    it('shows nothing for a value it cannot read, a kind it does not know, or no value', () => {
        expect(changeText(value('money', '1.5'))).toBe('');
        expect(changeText(value('count', 'abc'))).toBe('');
        expect(changeText(value('evidenceId', '7'))).toBe('');
        expect(changeText(null)).toBe('');
    });
});

describe('a key that the server names', () => {
    it('shows its text, and nothing for a key the dictionary lacks or one that takes placeholders', () => {
        expect(changeWord('audit.change.field.total')).toBe('Total');
        expect(namedKeyText('audit.change.field.evidenceId')).toBe('');
        expect(namedKeyText('evidenceId')).toBe('');
        expect(namedKeyText('audit.change.from_to')).toBe('');
    });
});

describe('the detail code of an incident (UXP-415)', () => {
    it('shows its approved label for each of the five codes, and never the stored code', () => {
        // Review R-01: every code of ProductReconciliation, each with its label of COPY_DECK section 11.15.2.
        expect(mismatchWord('BALANCE_MISMATCH')).toBe('Saldo stok');
        expect(mismatchWord('VALUATION_MISMATCH')).toBe('Nilai persediaan');
        expect(mismatchWord('ISSUE_PROJECTION_MISMATCH')).toBe('Biaya barang keluar');
        expect(mismatchWord('RETURN_PROJECTION_MISMATCH')).toBe('Biaya retur');
        expect(mismatchWord('HISTORY_INVALID')).toBe('Riwayat mutasi tidak bisa dihitung ulang');
        expect(mismatchWord('SOMETHING_NEW')).toBe('');
    });
});
