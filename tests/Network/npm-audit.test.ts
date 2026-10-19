import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

// Negative proof of the npm dependency audit against the registry
// (TEST_STRATEGY section 6, THR-14, AC-001-04). It needs the network, like the
// audit itself, so it is not an L1 test, which runs without network (TS-08):
// npm run lint runs it, through vitest.network.config.ts, right after the audit
// has passed on the real tree. The sample project is written at run time in the
// test's own temporary directory outside the tracked tree, which the test
// removes afterwards.
const directories: string[] = [];

function temporaryDirectory(): string {
    const directory = mkdtempSync(path.join(tmpdir(), 'bantukasir-npm-audit-'));
    directories.push(directory);

    return directory;
}

afterEach(() => {
    for (const directory of directories.splice(0)) {
        rmSync(directory, { recursive: true, force: true });
    }
});

describe('the npm dependency audit against the registry', () => {
    it('fails on a high advisory of a locked package', () => {
        // braces 3.0.3 has the high advisory GHSA-vfj7-8cjw-p6xm and no fix.
        const project = temporaryDirectory();
        writeFileSync(path.join(project, 'package.json'), JSON.stringify({ name: 'audit-sample', private: true, dependencies: { braces: '3.0.3' } }));
        const lock = spawnSync('npm', ['install', '--package-lock-only', '--ignore-scripts', '--no-audit', '--no-fund'], { cwd: project, encoding: 'utf8' });
        expect(lock.status, lock.stderr).toBe(0);

        const run = spawnSync('node', ['scripts/checks/npm-audit.mjs', project], { encoding: 'utf8' });

        expect(run.status).toBe(1);
        expect(run.stdout).toContain('braces: GHSA-vfj7-8cjw-p6xm (high)');
    });
});
