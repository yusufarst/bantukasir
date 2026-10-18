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
