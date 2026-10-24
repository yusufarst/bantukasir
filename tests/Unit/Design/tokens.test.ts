import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// AC-005-01: the token values equal DESIGN_SYSTEM sections 3 to 5 and the
// approved bk-tokens.css, proven by a snapshot comparison: every block of
// custom properties of resources/css/tokens.css — its selector or media query
// and each property with its value — equals the approved file, and the values
// that the tables of DESIGN_SYSTEM state are the values of the tokens.

const APPROVED = 'docs/07-ux-design/previews/assets/bk-tokens.css';
const OURS = 'resources/css/tokens.css';

/** The blocks of a stylesheet of custom properties: "media selector" → property → value, comments removed. */
function blocks(path: string): Map<string, Map<string, string>> {
    const css = readFileSync(path, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
    const result = new Map<string, Map<string, string>>();
    const open: string[] = [];
    let buffer = '';

    for (const character of css) {
        if (character === '{') {
            open.push(buffer.replace(/\s+/g, ' ').trim());
            buffer = '';
        } else if (character === '}') {
            const selector = open.pop() ?? '';

            if (buffer.trim() !== '') {
                const declarations = new Map<string, string>();

                for (const declaration of buffer.split(';')) {
                    const [name, ...value] = declaration.split(':');

                    if (name !== undefined && name.trim().startsWith('--')) {
                        declarations.set(name.trim(), value.join(':').trim());
                    }
                }

                result.set([...open, selector].join(' '), declarations);
            }

            buffer = '';
        } else {
            buffer += character;
        }
    }

    return result;
}

function flatten(map: Map<string, Map<string, string>>): Record<string, Record<string, string>> {
    return Object.fromEntries([...map].map(([selector, declarations]) => [selector, Object.fromEntries(declarations)]));
}

const ours = blocks(OURS);
const approved = blocks(APPROVED);
const root = ours.get(':root') ?? new Map<string, string>();
const green = ours.get(':root, [data-accent="green"]') ?? new Map<string, string>();
const design = readFileSync('docs/07-ux-design/DESIGN_SYSTEM.md', 'utf8');

describe('the tokens equal the approved bk-tokens.css (AC-005-01)', () => {
    it('has every block, property and value of the approved file, and nothing else', () => {
        expect(ours.size).toBeGreaterThanOrEqual(8);
        expect(flatten(ours)).toEqual(flatten(approved));
    });

    it('keeps the five accents and the two width classes', () => {
        expect([...ours.keys()]).toEqual(
            expect.arrayContaining([
                ':root',
                ':root, [data-accent="green"]',
                '[data-accent="teal"]',
                '[data-accent="blue"]',
                '[data-accent="purple"]',
                '[data-accent="graphite"]',
            ]),
        );
        expect(readFileSync(OURS, 'utf8')).toContain('@media (max-width: 1023.98px)');
        expect(readFileSync(OURS, 'utf8')).toContain('@media (max-width: 599.98px)');
    });
});

describe('the tokens equal the tables of DESIGN_SYSTEM sections 3 to 5', () => {
    it('3.1 neutrals and text', () => {
        const rows = [...design.matchAll(/^\| (`--color-[^|]+) \| ([^|]+) \|/gm)];

        expect(rows.length).toBeGreaterThanOrEqual(10);

        for (const row of rows) {
            const written = [...(row[1] ?? '').matchAll(/`([^`]+)`/g)].map((match) => match[1] ?? '');
            // "`--color-border-input`, `-hover`": a name that starts with "-" extends the first.
            const names = written.map((name) => (name.startsWith('--') ? name : `${written[0] ?? ''}${name}`));
            const values = [...(row[2] ?? '').matchAll(/`(#[0-9a-f]{6})`/g)].map((match) => match[1]);

            expect(values).toHaveLength(names.length);
            names.forEach((name, index) => expect(root.get(name), name).toBe(values[index]));
        }
    });

    it('3.2 status tones and solid danger', () => {
        const sentence = /success (`#[0-9a-f]{6}`[^;]*); warning ([^;]*); pending ([^;]*); danger ([^;]*); info ([^;]*); neutral ([^.]*)\./.exec(design);

        expect(sentence).not.toBeNull();

        ['success', 'warning', 'pending', 'danger', 'info', 'neutral'].forEach((tone, index) => {
            const [bg, fg, dot] = (sentence?.[index + 1] ?? '').split('/').map((value) => value.replace(/`/g, '').trim());

            expect(root.get(`--color-${tone}-bg`), tone).toBe(bg);
            expect(root.get(`--color-${tone}-fg`), tone).toBe(fg);
            expect(root.get(`--color-${tone}-dot`), tone).toBe(dot);
        });
        expect(root.get('--color-danger-solid')).toBe('#b4373f');
        expect(root.get('--color-danger-solid-hover')).toBe('#9e2f36');
    });

    it('3.3 the accent set', () => {
        const rows = [...design.matchAll(/^\| `(green|teal|blue|purple|graphite)`(?: \(default\))? \| [^|]+ \| `(#[0-9a-f]{6})` \| `(#[0-9a-f]{6})` \| `(#[0-9a-f]{6})` \| `(#[0-9a-f]{6})` \| `(#[0-9a-f]{6})` \|/gm)];

        expect(rows).toHaveLength(5);

        for (const row of rows) {
            const block = row[1] === 'green' ? green : (ours.get(`[data-accent="${row[1] ?? ''}"]`) ?? new Map<string, string>());

            expect(block.get('--brand-primary'), row[1]).toBe(row[2]);
            expect(block.get('--brand-primary-hover'), row[1]).toBe(row[3]);
            expect(block.get('--brand-primary-press'), row[1]).toBe(row[4]);
            expect(block.get('--brand-accent-strong'), row[1]).toBe(row[5]);
            expect(block.get('--brand-tint-150'), row[1]).toBe(row[6]);
            expect(block.get('--brand-accent'), row[1]).toBe(row[2]);
        }
    });

    it('4 typography: the sizes, lines and tracking of each role', () => {
        const roles: [string, string][] = [
            ['Display', 'display'],
            ['Title', 'title'],
            ['Heading', 'heading'],
            ['Subheading', 'subheading'],
            ['Figure', 'figure'],
            ['Body large', 'body-lg'],
            ['Body', 'body'],
            ['Label', 'label'],
            ['Meta', 'meta'],
            ['Mono', 'mono'],
        ];

        for (const [role, token] of roles) {
            const row = new RegExp(`^\\| ${role} \\| (\\d+) / (\\d+)(?:, tracking )?(?:, )?(−0\\.0\\d+em)?`, 'm').exec(design);

            expect(row, role).not.toBeNull();
            expect(root.get(`--text-${token}-size`), role).toBe(`${row?.[1] ?? ''}px`);
            expect(root.get(`--text-${token}-lh`), role).toBe(`${row?.[2] ?? ''}px`);

            if (row?.[3] !== undefined) {
                expect(root.get(`--text-${token}-track`), role).toBe(row[3].replace('−', '-'));
            }
        }

        expect(root.get('--text-button-size')).toBe('15px');
        expect(root.get('--text-button-lg-size')).toBe('16px');
        expect(root.get('--text-nav-size')).toBe('15px');
        expect(root.get('--font-sans')?.startsWith('"Inter", "Inter Fallback"')).toBe(true);
    });

    it('5 spacing, radius, elevation, motion and layers', () => {
        const spacing = /\*\*Spacing:\*\* ([0-9, ]+) px/.exec(design)?.[1]?.split(', ') ?? [];
        const ours = [...root].filter(([name]) => name.startsWith('--space-')).map(([, value]) => value.replace('px', ''));

        expect(ours).toEqual(spacing);

        const radius = /\*\*Radius:\*\* 4, 6, 8 \(controls\), 10 \(cards, large buttons\), 12 \(dialogs, auth card\), full/.test(design);

        expect(radius).toBe(true);
        expect(['--radius-xs', '--radius-sm', '--radius-md', '--radius-lg', '--radius-xl'].map((name) => root.get(name))).toEqual(['4px', '6px', '8px', '10px', '12px']);
        expect(root.get('--shadow-card')).toBe('0 1px 3px rgba(20, 24, 16, .04)');
        expect(['--dur-instant', '--dur-moderate'].map((name) => root.get(name))).toEqual(['80ms', '320ms']);
        expect(design).toContain('**Layers:** raised 2, bar 30, popover 80, drawer 95, modal 100, live regions 110.');
        expect(['--z-raised', '--z-bar', '--z-popover', '--z-drawer', '--z-modal', '--z-live'].map((name) => root.get(name))).toEqual(['2', '30', '80', '95', '100', '110']);
        expect([root.get('--gutter'), root.get('--card-pad'), root.get('--section-gap'), root.get('--grid-gap')]).toEqual(['24px', '20px', '24px', '16px']);
    });
});
