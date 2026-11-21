import { readdirSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// L1 check of the projects of playwright.config.ts (TASK-007, TASK-040): a browser test that signs in the people
// of Toko Contoh resets their accounts and ends their sign-in sessions, so it runs in the project `people`, one at a
// time, or in the project `maintenance`, alone; never in the project `chromium` beside them. A finance report test
// outside the project `people` ended the sign-in session of another test (pull request #55, the run 44841508286).

const config = readFileSync('playwright.config.ts', 'utf8');

/** The globs of one array constant of the configuration. */
function globs(name: string): string[] {
    const list = new RegExp(`const ${name} = \\[([^\\]]*)\\];`).exec(config)?.[1];

    expect(list, name).toBeDefined();

    return [...(list ?? '').matchAll(/'([^']+)'/g)].map((match) => match[1] ?? '');
}

/** A path relative to tests/Browser matched by a glob of the configuration: a folder with `/**`, or one file. */
function matches(path: string, glob: string): boolean {
    return glob.endsWith('/**') ? path.startsWith(glob.slice(0, -2)) : path === glob;
}

describe('the browser projects (playwright.config.ts)', () => {
    it('run every test that signs in the people of Toko Contoh one at a time', () => {
        const serial = [...globs('PEOPLE'), ...globs('MAINTENANCE')];
        const specs = (readdirSync('tests/Browser', { recursive: true, encoding: 'utf8' }) as string[]).map((path) => path.replace(/\\/g, '/')).filter((path) => path.endsWith('.spec.ts'));
        const signingIn = specs.filter((path) => /\b(?:resetPeople|signIn)\(/.test(readFileSync(`tests/Browser/${path}`, 'utf8')));

        expect(signingIn).toContain('reporting/finance.spec.ts');
        expect(signingIn.filter((path) => !serial.some((glob) => matches(path, glob)))).toEqual([]);
    });
});
