import { describe, expect, it } from 'vitest';
import { installDictionary, replacePlaceholders, t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';
import placeholders from './cases/placeholders.json';

// The typed helper t() of ADR-005 item 4 (AC-003-06): it reads the
// dictionary that the first page of a full page load carries, replaces
// placeholders as Laravel's translator does, and throws on a key the
// dictionary lacks instead of showing the key.
describe('t()', () => {
    it('throws before the dictionary is installed, and on a dictionary that is not texts', () => {
        expect(() => t('ux.format.month.october')).toThrow(/missing from the dictionary/);
        expect(() => installDictionary(undefined)).toThrow(/missing from the page/);
        expect(() => installDictionary(['Oktober'])).toThrow(/missing from the page/);
        expect(() => installDictionary({ 'ux.format.month.october': 10 })).toThrow(/is not a text/);
    });

    it('reads the installed dictionary and replaces placeholders', () => {
        installDictionary({ 'ux.format.month.october': 'Oktober', 'ux.format.variance': ':uang (:keterangan)' });

        expect(t('ux.format.month.october')).toBe('Oktober');
        expect(t('ux.format.variance', { uang: 'Rp0', keterangan: 'sesuai' })).toBe('Rp0 (sesuai)');
    });

    it('throws on a key the dictionary lacks instead of showing the key', () => {
        installDictionary({ 'ux.format.month.october': 'Oktober' });

        expect(() => t('ux.format.month.smarch' as TranslationKey)).toThrow('The translation key ux.format.month.smarch is missing from the dictionary');
    });
});

// The type inferred from the JSON table gives each case the other cases'
// names as optional; the values themselves are texts.
function texts(record: Readonly<Record<string, string | undefined>>): Record<string, string> {
    return Object.fromEntries(Object.entries(record).filter((pair): pair is [string, string] => pair[1] !== undefined));
}

describe('placeholders, as Laravel replaces them', () => {
    it.each(placeholders.cases.map((entry) => [entry.case, entry] as const))('%s', (_name, entry) => {
        expect(replacePlaceholders(entry.text, texts(entry.replacements))).toBe(entry.expected);
    });
});
