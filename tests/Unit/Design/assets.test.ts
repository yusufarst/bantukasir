import { createHash } from 'node:crypto';
import { readdirSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { ICON_PATHS } from '@/components/ui/icons';
import { SHORTCUTS, withShortcut } from '@/components/ui/shortcuts';
import { STATE_CATALOGUE } from '@/components/ui/stateCatalogue';
import { ACCENT_TOKENS, DEFAULT_ACCENT, isAccentToken } from '@/lib/accent';
import { groupDigits, moneyFromEntry } from '@/components/ui/Field';

// L1 checks of the design system's assets and registries (TASK-005): the
// self-hosted fonts and their licence (AC-005-06), the icons of the approved
// previews, the stylesheets under the content security policy (AC-005-05),
// the shortcut registry (DESIGN_SYSTEM section 11, COPY_DECK 5.1) and the
// catalogue of the states (AC-005-03).

const sha256 = (path: string): string => createHash('sha256').update(readFileSync(path)).digest('hex');
const stylesheets = readdirSync('resources/css').map((name) => `resources/css/${name}`);

describe('fonts (AC-005-06, SEC-HDR-03)', () => {
    it('serves the Latin and Latin Extended faces of @fontsource-variable/inter 5.3.0, unchanged, with the OFL', () => {
        for (const file of ['inter-latin-opsz-normal.woff2', 'inter-latin-ext-opsz-normal.woff2']) {
            expect(sha256(`public/fonts/inter/${file}`), file).toBe(sha256(`node_modules/@fontsource-variable/inter/files/${file}`));
        }

        expect(sha256('public/fonts/inter/OFL.txt')).toBe(sha256('node_modules/@fontsource-variable/inter/LICENSE'));
        expect(readFileSync('public/fonts/inter/OFL.txt', 'utf8')).toContain('SIL Open Font License, Version 1.1');
        expect(JSON.parse(readFileSync('node_modules/@fontsource-variable/inter/package.json', 'utf8'))).toMatchObject({ version: '5.3.0', license: 'OFL-1.1' });
    });

    it('loads every font from the application\'s own origin', () => {
        const css = readFileSync('resources/css/fonts.css', 'utf8');
        const sources = [...css.matchAll(/url\(([^)]+)\)/g)].map((match) => (match[1] ?? '').replace(/['"]/g, ''));

        expect(sources.length).toBeGreaterThan(0);
        expect(sources.every((source) => source.startsWith('/fonts/inter/'))).toBe(true);
    });
});

describe('stylesheets under the content security policy', () => {
    it('import nothing from another origin and read no text from an attribute', () => {
        for (const path of stylesheets) {
            const css = readFileSync(path, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

            expect(css, path).not.toMatch(/url\(\s*['"]?(https?:)?\/\//);
            expect(css, path).not.toMatch(/@import\s+url\(/);
            expect(css, path).not.toMatch(/attr\(/);
        }
    });

    it('draws each keycap of the registry with the key of COPY_DECK section 5.1', () => {
        const css = readFileSync('resources/css/components.css', 'utf8');
        const drawn = Object.fromEntries([...css.matchAll(/\.bk-kbd\[data-key='([a-z_]+)'\]::after \{\s*content: '([^']+)';/g)].map((match) => [match[1], match[2]]));

        expect(drawn).toEqual(Object.fromEntries(Object.entries(SHORTCUTS).map(([id, shortcut]) => [id, shortcut.keycap])));
    });
});

describe('icons (DESIGN_SYSTEM section 6)', () => {
    it('are the drawings of the approved previews, path for path', () => {
        const source = readFileSync('docs/07-ux-design/previews/assets/bk-icons.js', 'utf8');
        const approved = Object.fromEntries([...source.matchAll(/^\s+'([a-z-]+)': '([^']+)',?$/gm)].map((match) => [match[1], match[2]]));

        expect(Object.keys(approved).length).toBe(35);
        expect({ ...ICON_PATHS }).toEqual(approved);
    });
});

describe('the shortcut registry (DESIGN_SYSTEM section 11, D-075)', () => {
    it('holds the provisional shortcuts, written as COPY_DECK section 5.1 writes them', () => {
        const deck = readFileSync('docs/07-ux-design/COPY_DECK.md', 'utf8');

        expect(Object.values(SHORTCUTS).map((shortcut) => shortcut.keycap)).toEqual(['F2', 'F8', 'Alt+1', 'Alt+2', 'Alt+U', 'Ctrl+Enter', 'Esc']);

        for (const shortcut of Object.values(SHORTCUTS)) {
            expect(deck).toContain(`(${shortcut.keycap})`);
        }
    });

    it('adds the shortcut to the accessible name after the label, in parentheses (CW-11)', () => {
        expect(withShortcut('Bayar', 'pay')).toBe('Bayar (F8)');
        expect(withShortcut('Tinjau', 'confirm')).toBe('Tinjau (Ctrl+Enter)');
        expect(withShortcut('Kembali ubah', 'back')).toBe('Kembali ubah (Esc)');
        expect(withShortcut('Batal', undefined)).toBe('Batal');
    });

    it('matches each key exactly: Enter alone is no shortcut, and Ctrl+Enter is', () => {
        const key = (init: Partial<KeyboardEvent>): KeyboardEvent => ({ ctrlKey: false, altKey: false, metaKey: false, shiftKey: false, key: '', code: '', ...init }) as KeyboardEvent;
        const matching = (event: KeyboardEvent): string[] => Object.entries(SHORTCUTS).filter(([, shortcut]) => shortcut.matches(event)).map(([id]) => id);

        expect(matching(key({ key: 'Enter' }))).toEqual([]);
        expect(matching(key({ key: 'Enter', ctrlKey: true }))).toEqual(['confirm']);
        expect(matching(key({ key: 'F8' }))).toEqual(['pay']);
        expect(matching(key({ key: 'F8', shiftKey: true }))).toEqual([]);
        expect(matching(key({ key: '¡', code: 'Digit1', altKey: true }))).toEqual(['method_cash']);
        expect(matching(key({ key: 'U', code: 'KeyU', altKey: true }))).toEqual(['exact_cash']);
        expect(matching(key({ key: 'Escape' }))).toEqual(['back']);
    });
});

describe('the catalogue of the states (UI_STATES section 2, COPY_DECK section 12)', () => {
    const generated = readFileSync('resources/js/types/translation-keys.ts', 'utf8');
    const placeholders = new Map([...generated.matchAll(/^\s+'([a-z0-9_.]+)': ([^;]+);$/gm)].map((match) => [match[1] ?? '', match[2] ?? '']));
    const stateKeys = [...placeholders.keys()].filter((key) => key.startsWith('ux.state.'));
    const used = new Set(Object.values(STATE_CATALOGUE).flatMap((entry) => [entry.title, ...(entry.body === undefined ? [] : [entry.body]), ...(entry.texts ?? [])]));

    it('presents every state UXS-01 to UXS-50', () => {
        const numbers = new Set(Object.keys(STATE_CATALOGUE).map((id) => id.slice(0, 6)));

        expect([...numbers].sort()).toEqual(Array.from({ length: 50 }, (_, index) => `UXS-${String(index + 1).padStart(2, '0')}`));
    });

    it('names each key of ux.state in lang/id, and no other', () => {
        expect([...used].sort()).toEqual([...stateKeys].sort());
    });

    it('takes a body of the catalogue only when it has no placeholder; the page fills the others', () => {
        for (const entry of Object.values(STATE_CATALOGUE)) {
            expect(placeholders.get(entry.title), entry.title).toBe('never');

            if (entry.body !== undefined) {
                expect(placeholders.get(entry.body), entry.body).toBe('never');
            }
        }
    });
});

describe('the accent set and amount entry', () => {
    it('accepts only the five accents of SCHEMA accent_token, green by default', () => {
        expect(ACCENT_TOKENS).toEqual(['green', 'teal', 'blue', 'purple', 'graphite']);
        expect(DEFAULT_ACCENT).toBe('green');
        expect(isAccentToken('teal')).toBe(true);
        expect(isAccentToken('lime')).toBe(false);
        expect(isAccentToken(undefined)).toBe(false);
    });

    it('groups whole rupiah with dots as typed and keeps the canonical string', () => {
        expect(groupDigits('1234567')).toBe('1.234.567');
        expect(groupDigits('1.234.5678')).toBe('12.345.678');
        expect(groupDigits('Rp 0050.000')).toBe('50.000');
        expect(groupDigits('0')).toBe('0');
        expect(groupDigits('')).toBe('');
        expect(moneyFromEntry('1.234.567')).toBe('1234567');
        expect(moneyFromEntry('abc')).toBeNull();
    });
});
