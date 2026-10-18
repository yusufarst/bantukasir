import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

// Negative samples of the npm dependency audit (TEST_STRATEGY section 6,
// THR-14, AC-001-04), from saved reports, so that they need no network (TS-08).
// Each sample is written at run time in the test's own temporary directory
// outside the tracked tree, which the test removes afterwards; the real tree
// passes in npm run lint, which also runs the proof against the registry
// (tests/Network/npm-audit.test.ts).
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
    const passMessage = 'npm audit: no high or critical advisory';

    function counts(high: number, moderate: number) {
        return { info: 0, low: 0, moderate, high, critical: 0, total: high + moderate };
    }

    const report = {
        auditReportVersion: 2,
        vulnerabilities: {
            'sample-high': { via: [{ source: 1, url: 'https://github.com/advisories/GHSA-0000-0000-0001', severity: 'high', title: 'Sample high advisory' }] },
            'sample-moderate': { via: [{ source: 2, url: 'https://github.com/advisories/GHSA-0000-0000-0002', severity: 'moderate', title: 'Sample moderate advisory' }] },
            'sample-parent': { via: ['sample-high'] },
        },
        metadata: { vulnerabilities: counts(2, 1) },
    };

    // Writes a saved report in the test's own temporary directory and audits it
    // with no accepted advisory.
    function auditReport(content: string) {
        const directory = temporaryDirectory();
        writeFileSync(path.join(directory, 'report.json'), content);
        writeFileSync(path.join(directory, 'accepted.json'), '[]');

        return audit(`--report=${directory}/report.json`, `--accepted=${directory}/accepted.json`);
    }

    function expectFailedClosed(run: ReturnType<typeof audit>) {
        expect(run.status).toBe(2);
        expect(run.stderr).toContain('npm audit: no usable report');
        expect(run.stdout).not.toContain(passMessage);
    }

    it('passes a clean report', () => {
        const run = auditReport(JSON.stringify({ auditReportVersion: 2, vulnerabilities: {}, metadata: { vulnerabilities: counts(0, 0) } }));

        expect(run.status).toBe(0);
        expect(run.stdout).toContain(passMessage);
    });

    it('fails closed on the error report npm prints when it cannot reach the registry', () => {
        // The shape that npm 11.19.1 prints without network (TASK-001 evidence,
        // section 11), with a marker in place of the registry's address. Neither
        // the marker nor anything else of npm's report is printed.
        const marker = 'sample-unreachable-registry-marker';
        const npmError = { message: `request to ${marker} failed, reason: getaddrinfo EAI_AGAIN`, error: { summary: '', detail: '' } };

        for (const sample of [npmError, { ...report, ...npmError }]) {
            const run = auditReport(JSON.stringify(sample));

            expectFailedClosed(run);
            expect(run.stdout + run.stderr).not.toContain(marker);
        }
    });

    it('fails closed on an empty object', () => {
        expectFailedClosed(auditReport('{}'));
    });

    it('fails closed on output that is not JSON', () => {
        expectFailedClosed(auditReport('{"vulnerabilities": {'));
    });

    it('fails closed on no output', () => {
        expectFailedClosed(auditReport(''));
    });

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
