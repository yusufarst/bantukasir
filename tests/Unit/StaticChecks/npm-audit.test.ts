import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

// Negative samples of the npm dependency audit (TEST_STRATEGY section 6,
// THR-14, AC-001-04). Each sample is written at run time in the test's own
// temporary directory outside the tracked tree, which the test removes
// afterwards; the real tree passes in npm run lint.
const directories: string[] = [];

function temporaryDirectory(): string {
    const directory = mkdtempSync(path.join(tmpdir(), 'bantukasir-npm-audit-'));
    directories.push(directory);

    return directory;
}

function audit(...args: string[]) {
    return spawnSync('node', ['scripts/checks/npm-audit.mjs', ...args], { encoding: 'utf8' });
}

afterEach(() => {
    for (const directory of directories.splice(0)) {
        rmSync(directory, { recursive: true, force: true });
    }
});

describe('the npm dependency audit', () => {
    it('fails on a high advisory of a locked package', () => {
        // braces 3.0.3 has the high advisory GHSA-vfj7-8cjw-p6xm and no fix.
        const project = temporaryDirectory();
        writeFileSync(path.join(project, 'package.json'), JSON.stringify({ name: 'audit-sample', private: true, dependencies: { braces: '3.0.3' } }));
        const lock = spawnSync('npm', ['install', '--package-lock-only', '--ignore-scripts', '--no-audit', '--no-fund'], { cwd: project, encoding: 'utf8' });
        expect(lock.status, lock.stderr).toBe(0);

        const run = audit(project);

        expect(run.status).toBe(1);
        expect(run.stdout).toContain('braces: GHSA-vfj7-8cjw-p6xm (high)');
    });

    const report = {
        vulnerabilities: {
            'sample-high': { via: [{ source: 1, url: 'https://github.com/advisories/GHSA-0000-0000-0001', severity: 'high', title: 'Sample high advisory' }] },
            'sample-moderate': { via: [{ source: 2, url: 'https://github.com/advisories/GHSA-0000-0000-0002', severity: 'moderate', title: 'Sample moderate advisory' }] },
            'sample-parent': { via: ['sample-high'] },
        },
    };

    it('fails on a high advisory that is not accepted, and ignores moderate ones', () => {
        const directory = temporaryDirectory();
        writeFileSync(path.join(directory, 'report.json'), JSON.stringify(report));
        writeFileSync(path.join(directory, 'accepted.json'), '[]');

        const run = audit(`--report=${directory}/report.json`, `--accepted=${directory}/accepted.json`);

        expect(run.status).toBe(1);
        expect(run.stdout).toContain('sample-high: GHSA-0000-0000-0001 (high)');
        expect(run.stdout).not.toContain('sample-moderate');
    });

    it('passes a high advisory accepted with a reason, and not one accepted without', () => {
        const directory = temporaryDirectory();
        writeFileSync(path.join(directory, 'report.json'), JSON.stringify(report));

        writeFileSync(path.join(directory, 'accepted.json'), JSON.stringify([{ advisory: 'GHSA-0000-0000-0001', package: 'sample-high', reason: 'Sample reason' }]));
        expect(audit(`--report=${directory}/report.json`, `--accepted=${directory}/accepted.json`).status).toBe(0);

        writeFileSync(path.join(directory, 'accepted.json'), JSON.stringify([{ advisory: 'GHSA-0000-0000-0001', package: 'sample-high', reason: ' ' }]));
        expect(audit(`--report=${directory}/report.json`, `--accepted=${directory}/accepted.json`).status).toBe(1);
    });
});
