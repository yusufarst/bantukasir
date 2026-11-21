// The scenarios of PERF01 (ACCEPTANCE_SUITES PERF01; TECHNICAL_RULES section 13.1; TASK-043 scope item
// 2, AC-043-02), for k6 (TOOLCHAIN section 2), against the application of the local environment or,
// at TASK-048, the intended server. Two cashiers check out concurrently, each with exact barcode
// lookups, carts of 50 lines and now and then of 200, while the Owner runs the period report and the
// report over the maximum range, Product Search, the stock attention refresh and ordinary page visits;
// meanwhile the Owner's commands close cash sessions on the third register, refund Sale lines, import a
// CSV file of 5,000 rows and correct the cost source of the product of 10,000 movements, which replays
// them. Each operation of section 13.1 has its trend `op_*` and its failure counter `fail_*`
// (tests/Performance/Budgets.php); the summary goes to storage/app/perf/summary-<run>.json, which
// `perf.php record` turns into the run's record. Nothing here judges a budget: a dry run records only.
//
// Every request is the one a page sends: the sign-in, the CSRF header from the cookie, the command
// envelope of INTERACTION_CONTRACTS section 1.1 with a new intent and key, the Inertia visits with the
// page's version, the partial reload of a deferred prop. The plan (tests/Performance/Plan.php) gives
// the people, whose passwords it made for this run, and the records the commands name.
//
//   docker compose run --rm -e PERF_RUN=1 -e PERF_SECONDS=120 k6 run tests/Performance/scenarios/perf01.js

/* global __ENV, open */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE = __ENV.PERF_BASE_URL || 'http://bantukasir-app:8080';
const RUN = __ENV.PERF_RUN || '1';
const SECONDS = parseInt(__ENV.PERF_SECONDS || '120', 10);
const plan = JSON.parse(open(__ENV.PERF_PLAN || '/app/storage/app/perf/plan.json'));

const OPERATIONS = [
    'barcode_lookup',
    'product_search',
    'sale_50_lines',
    'sale_200_lines',
    'session_close',
    'refund_commit',
    'report_period',
    'report_max_range',
    'stock_attention',
    'page_visit',
    'csv_import',
    'cost_replay',
];

const trends = {};
const failures = {};

for (const name of OPERATIONS) {
    trends[name] = new Trend(`op_${name}`, true);
    failures[name] = new Counter(`fail_${name}`);
}

// k6 empties each VU's cookie jar after every iteration unless told not to; the cashiers and the Owner's
// reports sign in once, in their first iteration, and keep that session, so the jar must stay.
export const options = {
    noCookiesReset: true,
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(95)', 'count'],
    scenarios: {
        cashier_a: { executor: 'constant-vus', vus: 1, duration: `${SECONDS}s`, exec: 'cashierA' },
        cashier_b: { executor: 'constant-vus', vus: 1, duration: `${SECONDS}s`, exec: 'cashierB' },
        owner_reports: { executor: 'constant-vus', vus: 1, duration: `${SECONDS}s`, exec: 'ownerReports' },
        owner_commands: { executor: 'per-vu-iterations', vus: 1, iterations: 1, maxDuration: `${SECONDS + 180}s`, exec: 'ownerCommands', startTime: '5s' },
    },
};

// ---------------------------------------------------------------- the browser's part

function xsrf() {
    const cookies = http.cookieJar().cookiesForURL(BASE);
    const token = cookies['XSRF-TOKEN'];

    return token && token.length > 0 ? decodeURIComponent(token[0]) : '';
}

function jsonHeaders(extra) {
    return Object.assign({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() }, extra || {});
}

/** The page object of a full visit, from the script of Inertia's root view. */
function pageOf(response) {
    const found = /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/.exec(String(response.body || ''));

    return found ? JSON.parse(found[1]) : null;
}

function uuid() {
    const hex = [];

    for (let i = 0; i < 32; i++) {
        hex.push(Math.floor(Math.random() * 16).toString(16));
    }

    hex[12] = '4';
    hex[16] = ['8', '9', 'a', 'b'][Math.floor(Math.random() * 4)];
    const text = hex.join('');

    return `${text.slice(0, 8)}-${text.slice(8, 12)}-${text.slice(12, 16)}-${text.slice(16, 20)}-${text.slice(20)}`;
}

/** Signs a person of the plan in, as the sign-in page does, and reads the version and the recovery epoch of the home. */
function signIn(person) {
    http.get(`${BASE}/login`);
    const answer = http.post(`${BASE}/login`, JSON.stringify({ email: person.email, password: person.password }), { headers: jsonHeaders() });

    if (!check(answer, { 'signed in': (r) => r.status === 200 || r.status === 204 })) {
        throw new Error(`The sign-in of the plan's person was refused with ${answer.status}.`);
    }

    const page = pageOf(http.get(`${BASE}/`));

    if (page === null || !page.props || typeof page.props.recoveryEpoch !== 'string') {
        throw new Error('The home of the signed-in person carries no recovery epoch.');
    }

    return { version: page.version, epoch: page.props.recoveryEpoch };
}

/** Recent authentication for the commands that need it (INTERACTION_CONTRACTS section 1.5). */
function confirmPassword(person) {
    const answer = http.post(`${BASE}/user/confirm-password`, JSON.stringify({ password: person.password }), { headers: jsonHeaders() });

    check(answer, { 'password confirmed': (r) => r.status === 200 || r.status === 201 || r.status === 204 });
}

// Every operation's trend holds its successful answers only, and its failure counter the others, so that a
// trend's count is its successful samples for every metric (review R-12).

/** A command with a new intent and key; its time joins the operation's trend when one is named and it committed. */
function command(session, operation, expected, payload, measured) {
    const envelope = { operation, version: 1, sourceIntentId: uuid(), idempotencyKey: uuid(), recoveryEpoch: session.epoch, expected, payload };
    const answer = http.post(`${BASE}/commands/${operation}`, JSON.stringify(envelope), { headers: jsonHeaders(), timeout: '65s', tags: { name: operation } });
    const committed = answer.status === 201 || answer.status === 200;

    if (measured) {
        if (committed) {
            trends[measured].add(answer.timings.duration);
        } else {
            failures[measured].add(1);
        }
    }

    check(answer, { [`${operation} committed`]: () => committed });

    return committed ? answer.json('result') : null;
}

/** An Inertia visit after the first page, as a link or the page's own reload sends it. */
// A prop that the page answers as null when its read failed (UXS-22, TR-TX-07), named by `required`, makes
// the visit a failure, kept out of the operation's trend: a report that hit its statement limit answers
// 200 with `report: null`, which is never a successful sample.
function visit(session, path, measured, partial, required) {
    const headers = { Accept: 'text/html, application/xhtml+xml', 'X-Requested-With': 'XMLHttpRequest', 'X-Inertia': 'true', 'X-Inertia-Version': session.version || '' };

    if (partial) {
        headers['X-Inertia-Partial-Component'] = partial.component;
        headers['X-Inertia-Partial-Data'] = partial.data;
    }

    const answer = http.get(`${BASE}${path}`, { headers, tags: { name: path.split('?')[0] } });
    const props = answer.status === 200 ? answer.json('props') : null;
    const ok = props !== null && (!required || (props[required] !== null && props[required] !== undefined));

    if (measured) {
        if (ok) {
            trends[measured].add(answer.timings.duration);
        } else {
            failures[measured].add(1);
        }
    }

    check(answer, { [`visit ${path.split('?')[0]}`]: () => ok });

    return ok ? props : null;
}

/** A read of the data routes, which a page sends without a navigation, with the headers the page adds. */
function read(path, measured, background, extra) {
    const headers = Object.assign({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, extra || {});

    if (background) {
        headers['X-Background-Request'] = '1';
    }

    const answer = http.get(`${BASE}${path}`, { headers, tags: { name: path.split('?')[0] } });

    if (answer.status === 200) {
        trends[measured].add(answer.timings.duration);
    } else {
        failures[measured].add(1);
    }

    check(answer, { [`read ${path.split('?')[0]}`]: (r) => r.status === 200 });
}

function pick(items) {
    return items[Math.floor(Math.random() * items.length)];
}

function distinct(items, count) {
    const copy = items.slice();

    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy.slice(0, count);
}

/** A date in Asia/Jakarta, as the report's fields take it, this many days before today. */
function jakartaDate(daysBefore) {
    return new Date(Date.now() + 7 * 3600 * 1000 - daysBefore * 86400 * 1000).toISOString().slice(0, 10);
}

/** A Sale of these products, paid in cash with the exact total. */
function checkout(session, sessionId, products, measured) {
    const lines = products.map((p) => ({ productId: p.id, quantity: '1' }));
    const total = products.reduce((sum, p) => sum + p.price, 0);

    return command(session, 'sale.checkout', { lines: products.map((p) => ({ priceRevisionId: p.revision })) }, { sessionId, lines, payment: { method: 'CASH', tendered: String(total) } }, measured);
}

// ---------------------------------------------------------------- the cashiers

const cashiers = {};

function cashier(role) {
    if (!cashiers[role]) {
        const person = plan.people[role];
        const session = signIn(person);
        let sessionId = person.openSession;

        if (sessionId === null) {
            const opened = command(session, 'cash_session.open', {}, { registerId: plan.registers[role], openingCash: '200000' }, null);
            sessionId = opened ? opened.id : null;
        }

        cashiers[role] = { session, sessionId, iteration: 0 };
    }

    const state = cashiers[role];
    state.iteration++;

    for (let i = 0; i < 3; i++) {
        read(`/data/products/search?context=till&mode=exact&q=${encodeURIComponent(pick(plan.tokens))}`, 'barcode_lookup', false);
        sleep(0.3);
    }

    if (state.iteration % 8 === 0) {
        checkout(state.session, state.sessionId, distinct(plan.till, 200), 'sale_200_lines');
    } else {
        checkout(state.session, state.sessionId, distinct(plan.till, 50), 'sale_50_lines');
    }

    sleep(2);
}

export function cashierA() {
    cashier('cashierA');
}

export function cashierB() {
    cashier('cashierB');
}

// ---------------------------------------------------------------- the Owner's reports

let reports = null;
let reportLoops = 0;
let searchSequence = 0;

export function ownerReports() {
    if (reports === null) {
        reports = signIn(plan.people.owner);
    }

    reportLoops++;

    const today = jakartaDate(0);

    visit(reports, `/reports/finance?period=custom&from=${jakartaDate(30)}&to=${today}`, 'report_period', null, 'report');
    sleep(1);

    // Product Search (RP-301): the page's shell, then its results, which the page reads from RD-01 in the
    // context `catalog` with its sequence header; the results are what the budget times.
    const term = encodeURIComponent(pick(plan.searchTerms));
    searchSequence++;
    visit(reports, `/products?q=${term}`, null, null);
    read(`/data/products/search?context=catalog&q=${term}`, 'product_search', false, { 'X-Search-Sequence': String(searchSequence) });
    sleep(1);
    read('/data/stock?state=LOW', 'stock_attention', true);
    read('/data/stock?state=OUT', 'stock_attention', true);
    sleep(1);
    // The costs-to-complete worklist's condition runs over every cost source (TASK-025 F-5), so it is among the visits.
    visit(reports, pick(['/home', '/stock', '/sales', '/cash-sessions', '/reports/costs-to-complete']), 'page_visit', null);
    sleep(1);

    if (reportLoops % 4 === 1) {
        visit(reports, `/reports/finance?period=custom&from=${jakartaDate(365)}&to=${today}`, 'report_max_range', null, 'report');
        sleep(1);
    }
}

// ---------------------------------------------------------------- the Owner's commands

function closeOnce(owner, openSession) {
    let sessionId = openSession;

    if (sessionId === null) {
        const opened = command(owner, 'cash_session.open', {}, { registerId: plan.registers.close, openingCash: '100000' }, null);

        if (!opened) {
            return null;
        }

        sessionId = opened.id;
        checkout(owner, sessionId, distinct(plan.till, 5), null);
    }

    const props = visit(owner, '/till/close-session', null, { component: 'cash/CloseSession', data: 'preview' });

    if (!props || !props.preview) {
        failures.session_close.add(1);

        return null;
    }

    command(owner, 'cash_session.close', { activityVersion: props.preview.activityVersion }, { sessionId, physicalCash: props.preview.figures.expectedCash }, 'session_close');

    return null;
}

function importRows(owner) {
    const header = 'SKU;Nama;Kategori;Merek;Satuan;Desimal;Harga jual;Stok minimum;Barcode';
    const rows = [header];

    for (let i = 1; i <= plan.import.rows; i++) {
        rows.push(`${plan.import.prefix}-${String(i).padStart(5, '0')};Produk Impor ${plan.import.prefix} ${i};${plan.import.category};;pcs;0;12.500;;`);
    }

    const preview = http.post(`${BASE}/products/import/preview`, { file: http.file(`${rows.join('\r\n')}\r\n`, 'produk.csv', 'text/csv') }, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() }, timeout: '65s' });

    if (!check(preview, { 'import previewed': (r) => r.status === 200 })) {
        failures.csv_import.add(1);

        return;
    }

    command(owner, 'csv_import.confirm', {}, { importId: preview.json('importId'), fileSha256: preview.json('fileSha256') }, 'csv_import');
}

function replay(owner) {
    if (!plan.replay) {
        failures.cost_replay.add(1);

        return;
    }

    const amount = String(parseInt(plan.replay.amount, 10) + parseInt(RUN, 10) * 100);

    command(owner, 'cost_correction.confirm', { correctionNo: plan.replay.correctionNo }, { evidenceId: plan.replay.evidenceId, newCost: { amount }, reason: `Uji kinerja ${RUN}` }, 'cost_replay');
}

export function ownerCommands() {
    const person = plan.people.owner;
    const owner = signIn(person);

    confirmPassword(person);

    let open = person.openSession;

    for (let i = 0; i < 3; i++) {
        open = closeOnce(owner, open);
        sleep(2);
    }

    for (const refund of plan.refunds.slice(0, 10)) {
        command(owner, 'sale_correction.other', {}, {
            saleId: refund.saleId,
            lines: [{ saleLineId: refund.saleLineId, refundAmount: String(refund.amount), returnedSaleable: '0', returnedNotSaleable: '0' }],
            reason: 'Uji kinerja',
            payout: { method: 'BANK_TRANSFER', reference: `TRF-UJI-${RUN}` },
        }, 'refund_commit');
        sleep(2);
    }

    importRows(owner);
    sleep(2);
    confirmPassword(person);
    replay(owner);
}

export function handleSummary(data) {
    return { [`/app/storage/app/perf/summary-${RUN}.json`]: JSON.stringify(data, null, 2) };
}
