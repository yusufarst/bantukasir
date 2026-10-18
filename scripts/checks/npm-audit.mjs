// Dependency audit of the npm tree (TEST_STRATEGY section 6, THR-14): fails on a
// high or critical advisory unless scripts/checks/npm-audit-accepted.json records
// it as accepted, with its reason. Each entry of that file is
//     { "advisory": "GHSA-…", "package": "…", "reason": "…" }
// and it may be added only on the Owner's acceptance of the risk.
//
// The check fails closed (THR-14). It reads a report only when the report is a
// JSON object with a `vulnerabilities` object and a `metadata.vulnerabilities`
// object, and without an `error` key. Anything else exits 2 without printing
// npm's output: no output, output that is not JSON, `{}`, or the error report
// that npm prints when it cannot reach the registry.
//
// Usage: node scripts/checks/npm-audit.mjs [DIRECTORY] [--report=FILE] [--accepted=FILE]
//   --report reads a saved `npm audit --json` report instead of running npm.
// Exit status: 0 no blocking advisory; 1 a high or critical advisory that is
// not accepted; 2 no usable report.
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

// Stops the check without a verdict. The reason is fixed text: npm's output and
// error messages are not printed, so no value of the environment is.
function refuse(reason) {
    console.error(`npm audit: no usable report (${reason}); the check fails closed. Run npm audit to see npm's own message.`);
    process.exit(2);
}

function isObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function readOutput() {
    if (reportFile !== null) {
        try {
            return readFileSync(reportFile, 'utf8');
        } catch {
            refuse('the saved report cannot be read');
        }
    }

    // npm audit exits non-zero both when it finds advisories and when it fails,
    // so only the report's shape tells the two apart.
    const run = spawnSync('npm', ['audit', '--json'], { cwd: directory, encoding: 'utf8' });

    if (run.error !== undefined || run.signal !== null) {
        refuse('npm could not be run');
    }

    return run.stdout;
}

function readReport() {
    const output = readOutput();

    if (output.trim() === '') {
        refuse('no output');
    }

    let report;

    try {
        report = JSON.parse(output);
    } catch {
        refuse('the output is not JSON');
    }

    if (!isObject(report)) {
        refuse('the report is not a JSON object');
    }

    if (Object.hasOwn(report, 'error')) {
        refuse('npm reported an error, for example an unreachable registry');
    }

    if (!isObject(report.vulnerabilities) || !isObject(report.metadata) || !isObject(report.metadata.vulnerabilities)) {
        refuse('the report lacks the vulnerabilities or metadata.vulnerabilities object');
    }

    for (const vulnerability of Object.values(report.vulnerabilities)) {
        if (!isObject(vulnerability) || !Array.isArray(vulnerability.via)) {
            refuse('a vulnerability has no via list');
        }
    }

    return report;
}

const accepted = new Map(
    JSON.parse(readFileSync(acceptedFile, 'utf8'))
        .filter((entry) => typeof entry.reason === 'string' && entry.reason.trim() !== '')
        .map((entry) => [`${entry.package} ${entry.advisory}`, entry]),
);

const blocking = new Map();

for (const [name, vulnerability] of Object.entries(readReport().vulnerabilities)) {
    for (const via of vulnerability.via) {
        if (!isObject(via) || !blockingSeverities.has(via.severity)) {
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
