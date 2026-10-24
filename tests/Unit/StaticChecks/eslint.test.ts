import { ESLint } from 'eslint';
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// Negative and compliant samples of ESLint with react/jsx-no-literals (ADR-005
// item 5, TEST_STRATEGY section 6, AC-001-04). Each sample is linted with the
// project's configuration as if it were interface code under resources/js; the
// real tree passes in npm run lint.
const eslint = new ESLint();

async function lintSample(sample: string): Promise<ESLint.LintResult> {
    const code = readFileSync(`tests/Fixtures/StaticChecks/eslint/${sample}`, 'utf8');
    const [result] = await eslint.lintText(code, { filePath: `resources/js/static-check-${sample}` });

    if (result === undefined) {
        throw new Error(`ESLint returned no result for ${sample}`);
    }

    return result;
}

describe('ESLint and react/jsx-no-literals', () => {
    it('refuses text and attribute literals', async () => {
        const result = await lintSample('literals.tsx');
        const reported = result.messages.map((message) => `${message.line}:${message.ruleId ?? 'fatal'}`);

        // Line 5: title="Judul", reported as a prop value and as an attribute
        // string, and the text "Teks", whose JSX text node starts after the
        // opening tag; line 7: placeholder="Cari", reported twice, and
        // {'Label'}; line 8: {'Teks lain'}.
        expect(result.errorCount).toBe(reported.length);
        expect(reported).toEqual([
            '5:react/jsx-no-literals',
            '5:react/jsx-no-literals',
            '5:react/jsx-no-literals',
            '7:react/jsx-no-literals',
            '7:react/jsx-no-literals',
            '7:no-restricted-syntax',
            '8:no-restricted-syntax',
        ]);
    });

    it('passes text from translation keys', async () => {
        const result = await lintSample('keys.tsx');

        expect(result.messages).toEqual([]);
    });
});

/** The lines of a sample that end with a tag such as `// N01`, by line number. */
function taggedLines(sample: string): Map<number, string> {
    const lines = readFileSync(`tests/Fixtures/StaticChecks/eslint/${sample}`, 'utf8').split('\n');
    const tags = new Map<number, string>();

    lines.forEach((line, index) => {
        const tag = /\/\/ ([A-Z][0-9]{2})\b/.exec(line)?.[1];

        if (tag !== undefined) {
            tags.set(index + 1, tag);
        }
    });

    return tags;
}

/** The tags of a sample whose line has no message of one of the rules given. */
async function unreported(sample: string, rules: readonly string[]): Promise<string[]> {
    const result = await lintSample(sample);
    const fatal = result.messages.filter((message) => message.fatal === true);

    if (fatal.length > 0) {
        throw new Error(`${sample} does not parse: ${JSON.stringify(fatal)}`);
    }

    const reported = new Set(result.messages.filter((message) => rules.includes(message.ruleId ?? '')).map((message) => message.line));

    return [...taggedLines(sample)].filter(([line]) => !reported.has(line)).map(([, tag]) => tag);
}

describe('the exact-number rules refuse the forms of the class-A review (TASK-003 finding A-03)', () => {
    it.each(['exact-number-bypasses.ts', 'exact-number-bypass.mts', 'exact-number-bypass.cts', 'exact-number-bypass.js', 'exact-number-bypass.jsx'])(
        'every tagged line of %s is refused',
        async (sample) => {
            expect(taggedLines(sample).size).toBeGreaterThan(0);
            expect(await unreported(sample, ['no-restricted-syntax'])).toEqual([]);
        },
    );

    it('passes the integer-only members of Number, JSON.stringify, Date.now and type positions', async () => {
        expect((await lintSample('exact-number-controls.ts')).messages).toEqual([]);
    });
});

describe('the JSX literal rule refuses literals anywhere in an expression (TASK-003 finding A-08)', () => {
    it('refuses every tagged line', async () => {
        expect(taggedLines('jsx-literal-bypasses.tsx').size).toBe(16);
        expect(await unreported('jsx-literal-bypasses.tsx', ['no-restricted-syntax', 'react/jsx-no-literals'])).toEqual([]);
    });

    it('passes keys of t(), non-text attributes, object keys and comparisons', async () => {
        expect((await lintSample('jsx-literal-controls.tsx')).messages).toEqual([]);
    });
});

describe('lint refuses a type assertion to TranslationKey (TASK-003 finding A-06)', () => {
    it('refuses every tagged line', async () => {
        expect(taggedLines('key-assertions.ts').size).toBe(13);
        expect(await unreported('key-assertions.ts', ['no-restricted-syntax'])).toEqual([]);
    });

    it('passes a key typed TranslationKey without an assertion', async () => {
        expect((await lintSample('key-assertions-allowed.ts')).messages).toEqual([]);
    });

    it('lets resources/js/lib/i18n.ts and the generated key file assert keys', async () => {
        const code = "import type { TranslationKey } from '@/types/translation-keys';\nexport const key = 'ux.format.variance' as TranslationKey;\n";

        for (const filePath of ['resources/js/lib/i18n.ts', 'resources/js/types/translation-keys.ts']) {
            const [result] = await eslint.lintText(code, { filePath });

            expect(result?.messages, filePath).toEqual([]);
        }
    });
});

describe('ESLint and exact numbers (ADR-006 items 5 and 6, TASK-003)', () => {
    it('refuses Number(), parseFloat, parseInt, unary + and locale formatting', async () => {
        const result = await lintSample('exact-numbers.ts');
        const reported = result.messages.map((message) => `${message.line}:${message.ruleId ?? 'fatal'}`);

        // Lines 6 to 13: Number(), parseFloat, Number.parseFloat, unary +,
        // Intl.NumberFormat, toLocaleDateString, parseInt and window.Number.
        expect(result.errorCount).toBe(reported.length);
        expect(reported).toEqual([6, 7, 8, 9, 10, 11, 12, 13].map((line) => `${line}:no-restricted-syntax`));
    });

    it('passes canonical strings, BigInt and integer checks', async () => {
        const result = await lintSample('exact-numbers-allowed.ts');

        expect(result.messages).toEqual([]);
    });

    it('refuses them in TSX too, beside the literal rule', async () => {
        const code = 'declare const price: string;\nexport const Total = () => <span>{+price}</span>;\n';
        const [result] = await eslint.lintText(code, { filePath: 'resources/js/static-check-total.tsx' });

        expect(result?.messages.map((message) => `${message.line}:${message.ruleId ?? 'fatal'}`)).toEqual(['2:no-restricted-syntax']);
    });
});

describe('the JSX literal rule beside the attributes TASK-005 allows for the design system', () => {
    it('passes SVG geometry, input and shortcut attributes, data attributes and the fixed-value props', async () => {
        expect((await lintSample('design-system-controls.tsx')).messages).toEqual([]);
    });

    it('still refuses a literal in every attribute or prop that holds text', async () => {
        expect(taggedLines('design-system-literals.tsx').size).toBe(7);
        expect(await unreported('design-system-literals.tsx', ['no-restricted-syntax', 'react/jsx-no-literals'])).toEqual([]);
    });
});
