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
