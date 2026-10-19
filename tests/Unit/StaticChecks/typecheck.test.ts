import ts from 'typescript';
import { describe, expect, it } from 'vitest';

// Negative and compliant samples of the TypeScript typecheck (TEST_STRATEGY
// section 6, AC-001-04), checked with the compiler options of tsconfig.json;
// the real tree passes in npm run typecheck.
function errorsOf(sample: string): readonly ts.Diagnostic[] {
    const config = ts.readConfigFile('tsconfig.json', (path) => ts.sys.readFile(path));
    const { options } = ts.parseJsonConfigFileContent(config.config, ts.sys, '.');
    const program = ts.createProgram([`tests/Fixtures/StaticChecks/typescript/${sample}`], options);

    return ts.getPreEmitDiagnostics(program);
}

describe('the TypeScript typecheck', () => {
    it('refuses a type error', () => {
        const codes = errorsOf('type-error.ts').map((diagnostic) => diagnostic.code);

        // TS2322: a type is not assignable to the declared type.
        expect(codes).toEqual([2322]);
    });

    it('passes well-typed code', () => {
        expect(errorsOf('well-typed.ts')).toEqual([]);
    });
});

describe('the typecheck of t() (ADR-005 item 5, TASK-003)', () => {
    it('refuses a key missing from lang/id and wrong placeholders', () => {
        const errors = errorsOf('translation-keys.ts').map((diagnostic) => {
            const line = diagnostic.file?.getLineAndCharacterOfPosition(diagnostic.start ?? 0).line ?? -1;

            return `${line + 1}:${diagnostic.code}`;
        });

        // Line 5: a key missing from lang/id is not a TranslationKey (TS2345);
        // line 6: the placeholders are missing (TS2554); line 7: a text
        // without placeholders takes none (TS2554); line 8: a placeholder has
        // no value (TS2345).
        expect(errors).toEqual(['5:2345', '6:2554', '7:2554', '8:2345']);
    });

    it('passes a key of lang/id with exactly its placeholders', () => {
        expect(errorsOf('translation-keys-valid.ts')).toEqual([]);
    });
});
