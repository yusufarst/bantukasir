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
        // lodash 4.17.15 has three high advisories in the registry, so the proof
        // does not rest on one advisory, which GitHub may withdraw, as it
        // withdrew GHSA-vfj7-8cjw-p6xm of braces on 23 October 2026. An empty
        // list of accepted advisories keeps the proof independent of the
        // project's own acceptances.
        const project = temporaryDirectory();
        writeFileSync(path.join(project, 'package.json'), JSON.stringify({ name: 'audit-sample', private: true, dependencies: { lodash: '4.17.15' } }));
        const accepted = path.join(project, 'accepted.json');
        writeFileSync(accepted, '[]');
        const lock = spawnSync('npm', ['install', '--package-lock-only', '--ignore-scripts', '--no-audit', '--no-fund'], { cwd: project, encoding: 'utf8' });
        expect(lock.status, lock.stderr).toBe(0);

        const run = spawnSync('node', ['scripts/checks/npm-audit.mjs', project, `--accepted=${accepted}`], { encoding: 'utf8' });

        expect(run.status).toBe(1);
        expect(run.stdout).toMatch(/^ {2}lodash: GHSA-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4} \((high|critical)\) /m);
    });
});
