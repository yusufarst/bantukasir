// Dependency audit of the npm tree (TEST_STRATEGY section 6, THR-14): fails on a
// high or critical advisory unless scripts/checks/npm-audit-accepted.json records
// it as accepted, with its reason. Each entry of that file is
//     { "advisory": "GHSA-…", "package": "…", "reason": "…" }
// and it may be added only on the Owner's acceptance of the risk.
//
// Usage: node scripts/checks/npm-audit.mjs [DIRECTORY] [--report=FILE] [--accepted=FILE]
//   --report reads a saved `npm audit --json` report instead of running npm.
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const blockingSeverities = new Set(['high', 'critical']);

let directory = root;
let reportFile = null;
let acceptedFile = path.join(root, 'scripts/checks/npm-audit-accepted.json');

for (const argument of process.argv.slice(2)) {
    if (argument.startsWith('--report=')) {
        reportFile = argument.slice('--report='.length);
    } else if (argument.startsWith('--accepted=')) {
        acceptedFile = argument.slice('--accepted='.length);
    } else {
        directory = path.resolve(argument);
    }
}

function readReport() {
    if (reportFile !== null) {
        return JSON.parse(readFileSync(reportFile, 'utf8'));
    }

    // npm audit exits non-zero when it finds advisories; the report decides.
    const run = spawnSync('npm', ['audit', '--json'], { cwd: directory, encoding: 'utf8' });

    if (run.error !== undefined || run.stdout.trim() === '') {
        console.error('npm audit did not produce a report.');
        console.error(run.error?.message ?? run.stderr);
        process.exit(2);
    }

    return JSON.parse(run.stdout);
}

const accepted = new Map(
    JSON.parse(readFileSync(acceptedFile, 'utf8'))
        .filter((entry) => typeof entry.reason === 'string' && entry.reason.trim() !== '')
        .map((entry) => [`${entry.package} ${entry.advisory}`, entry]),
);

const blocking = new Map();

for (const [name, vulnerability] of Object.entries(readReport().vulnerabilities ?? {})) {
    for (const via of vulnerability.via) {
        if (typeof via !== 'object' || !blockingSeverities.has(via.severity)) {
            continue;
        }

        const advisory = String(via.url ?? via.source).split('/').pop();

        if (!accepted.has(`${name} ${advisory}`)) {
            blocking.set(`${name} ${advisory}`, `${name}: ${advisory} (${via.severity}) ${via.title}`);
        }
    }
}

if (blocking.size > 0) {
    console.log('npm audit: high or critical advisories not accepted:');
    for (const line of blocking.values()) {
        console.log(`  ${line}`);
    }
    process.exit(1);
}

console.log('npm audit: no high or critical advisory, apart from those accepted with a reason.');
