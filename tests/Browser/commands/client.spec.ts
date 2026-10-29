import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page, type Request, type Route } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Account } from '../support/access';
import { COMMAND_SPECIMEN, envelopeOf, injected, prepareCommands, PROBE_ROUTE, refusal, refuse, RESOLVE_ROUTE, storedReferences } from '../support/commands';
import { watch } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The client states of a command with the network faults of TEST_STRATEGY
// TS-22 (AC-009-01, AC-009-02, AC-009-07; INTERACTION_CONTRACTS sections 1.2,
// 1.3, 1.5, 1.7 and 2.1; UI_STATES UXS-06, UXS-10 to UXS-16; COPY_DECK
// sections 12 and 14; suites CMD01 and UX01; TASK-009 scope items 1, 4 and 8),
// on the command specimen, whose command is the probe operation through the
// real pipeline: a response dropped after the commit, 409 IDEMPOTENCY_CONFLICT
// and EPOCH_STALE, 503, 423, 419 and 429, a status check without an answer,
// IN_PROGRESS and its repeats, and a fake clock beyond the 65-second wait.
// Agus, the Operations admin, holds the probe's capability. These tests sign
// in a person of Toko Contoh, so they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function accessible(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);
}

/** Agus signed in, without an unresolved request, on the command specimen. */
async function opened(page: Page, clock = false): Promise<Account> {
    const context = page.context();
    const { Agus: agus } = await resetPeople(context, ['Agus']);

    await prepareCommands(context, ['Agus']);

    if (clock) {
        await page.clock.install();
    }

    await signIn(page, agus);
    await page.goto(COMMAND_SPECIMEN);
    await expect(page.getByRole('heading', { level: 1, name: 'Catatan tinjauan' })).toBeVisible();
    await expect(save(page)).toBeEnabled();

    return agus;
}

function save(page: Page) {
    return page.getByRole('main').getByRole('button', { name: 'Simpan' });
}

function panel(page: Page) {
    return page.getByRole('main').getByRole('alert').filter({ hasText: 'Status belum pasti' });
}

test('critical path (EXC-34): a command whose answer is lost after the commit is resolved through the status check', async ({ page }) => {
    const watched = await watch(page);
    await opened(page);
    const sent: Request[] = [];

    // TS-22: the server commits, the answer never reaches the browser.
    await page.route(PROBE_ROUTE, async (route: Route) => {
        sent.push(route.request());
        const committed = await route.fetch();
        expect(committed.status()).toBe(201);
        await route.abort('failed');
    });
    await save(page).click();

    await expect(panel(page)).toBeVisible();
    await expect(panel(page)).toContainText('Permintaan sudah dikirim, tetapi hasilnya belum diterima. Mungkin sudah tersimpan.');
    await expect(panel(page)).toContainText('Jangan menulis catatan ini lagi.');
    await expect(panel(page).getByRole('button')).toHaveText(['Kirim ulang', 'Cek status']);
    await expect(save(page)).toHaveAttribute('aria-disabled', 'true');
    const intent = envelopeOf(sent[0]!).sourceIntentId;
    expect(Object.values(await storedReferences(page)).join('\n')).toContain(intent);
    await accessible(page);

    // "Cek status": COMMITTED, the reference released, the result page shown.
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), panel(page).getByRole('button', { name: 'Cek status' }).click()]);
    expect(await storedReferences(page)).toEqual({});
    expect(sent).toHaveLength(1);
    expect(injected(watched.problems)).toEqual([]);
});

test('without an answer the client waits 65 seconds, says it is still working, and never refuses because of time', async ({ page }) => {
    await opened(page, true);
    const held: Route[] = [];

    await page.route(PROBE_ROUTE, (route) => {
        held.push(route);
    });
    await save(page).click();

    // The request has left (after the read of the user's receipts that
    // precedes it); the 65 seconds count from now.
    await expect(page.getByRole('main').getByRole('button', { name: 'Sedang diproses' })).toBeVisible();
    await expect.poll(() => held.length).toBe(1);
    await page.clock.fastForward(5_000);
    await expect(page.getByRole('main').getByText('Masih diproses. Tunggu sebentar.')).toBeVisible();
    await page.clock.fastForward(55_000);
    await expect(panel(page)).toHaveCount(0);
    await page.clock.fastForward(6_000);
    await expect(panel(page)).toBeVisible();
    await expect(page.getByRole('main').getByRole('alert').filter({ hasText: 'Tidak disimpan' })).toHaveCount(0);

    // "Kirim ulang" sends the same frozen request, which now commits.
    await page.unroute(PROBE_ROUTE);
    const resent = page.waitForRequest((request) => request.url().endsWith('/commands/testing.probe'));
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), panel(page).getByRole('button', { name: 'Kirim ulang' }).click()]);
    expect(envelopeOf(await resent).sourceIntentId).toBe(envelopeOf(held[0]!.request()).sourceIntentId);
    await held[0]?.abort().catch(() => undefined);
});

test('503 is uncertain and "Kirim ulang" repeats the same intent and key; a second press or Enter sends nothing', async ({ page }) => {
    const watched = await watch(page);
    await opened(page);
    const sent: Request[] = [];
    let release: (() => void) | null = null;

    // Enter never activates the final action (UXA-10).
    await save(page).focus();
    await page.keyboard.press('Enter');

    await page.route(PROBE_ROUTE, async (route) => {
        sent.push(route.request());

        if (sent.length === 1) {
            await new Promise<void>((resolve) => {
                release = resolve;
            });
            await refuse(route, 503, 'SERVICE_BUSY');
        } else {
            await route.continue();
        }
    });
    await save(page).click();
    await expect(page.getByRole('main').getByRole('button', { name: 'Sedang diproses' })).toBeVisible();
    await expect.poll(() => sent.length).toBe(1);

    // While SUBMITTING, a second press, a tap or Enter sends nothing (AC-009-02).
    const sending = page.getByRole('main').getByRole('button', { name: 'Sedang diproses' });
    await sending.click({ force: true });
    await sending.focus();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Space');
    expect(sent).toHaveLength(1);
    (release as (() => void) | null)?.();

    await expect(panel(page)).toBeVisible();
    await expect(panel(page).getByRole('button')).toHaveText(['Kirim ulang', 'Cek status']);
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), panel(page).getByRole('button', { name: 'Kirim ulang' }).click()]);

    expect(sent).toHaveLength(2);
    const [first, second] = sent.map(envelopeOf);
    expect([second?.sourceIntentId, second?.idempotencyKey, second?.recoveryEpoch, second?.payload]).toEqual([first?.sourceIntentId, first?.idempotencyKey, first?.recoveryEpoch, first?.payload]);
    expect(await storedReferences(page)).toEqual({});
    expect(injected(watched.problems)).toEqual([]);
});

test('409 IDEMPOTENCY_CONFLICT is held with "Cek status" only, and a check without an answer leaves it held', async ({ page }) => {
    await opened(page);

    await page.route(PROBE_ROUTE, (route) => refuse(route, 409, 'IDEMPOTENCY_CONFLICT', 'HELD'));
    await save(page).click();
    await expect(panel(page)).toContainText('Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan.');
    await expect(panel(page).getByRole('button')).toHaveText(['Cek status']);
    await accessible(page);

    // The check gets no answer: still held, "Cek status" only (D-084).
    await page.route(RESOLVE_ROUTE, (route) => route.abort('failed'));
    await panel(page).getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByRole('main').getByText('Cek status belum mendapat jawaban. Statusnya tetap belum pasti; cek lagi sebentar lagi.')).toBeVisible();
    await expect(panel(page).getByRole('button')).toHaveText(['Cek status']);

    // A real check: the request never reached the server, so the intent is
    // fenced NOT_COMMITTED and the reference released.
    await page.unroute(RESOLVE_ROUTE);
    await panel(page).getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByRole('main').getByText('Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.')).toBeVisible();
    await expect(panel(page)).toHaveCount(0);
    expect(await storedReferences(page)).toEqual({});
});

test('409 EPOCH_STALE is held with the text of a request from before a restore, and stays so while the check answers the same', async ({ page }) => {
    await opened(page);

    await page.route(PROBE_ROUTE, (route) => refuse(route, 409, 'EPOCH_STALE', 'HELD'));
    await page.route(RESOLVE_ROUTE, (route) => refuse(route, 409, 'EPOCH_STALE', 'HELD'));
    await save(page).click();

    const held = page.getByRole('main').getByRole('alert').filter({ hasText: 'Permintaan ini dibuat sebelum data dipulihkan dari cadangan.' });
    await expect(held).toBeVisible();
    await expect(held.getByRole('button')).toHaveText(['Cek status']);
    await held.getByRole('button', { name: 'Cek status' }).click();
    await expect(held).toBeVisible();
    await expect(held.getByRole('button')).toHaveText(['Cek status']);
    expect(Object.keys(await storedReferences(page))).toHaveLength(1);
});

test('IN_PROGRESS repeats the check after 3, 6 and 12 seconds as background requests until it concludes', async ({ page }) => {
    await opened(page, true);
    const checks: Request[] = [];

    await page.route(PROBE_ROUTE, (route) => refuse(route, 503, 'SERVICE_BUSY'));
    await page.route(RESOLVE_ROUTE, async (route) => {
        checks.push(route.request());

        if (checks.length <= 3) {
            await route.fulfill({ status: 200, json: { outcome: 'IN_PROGRESS', operation: 'testing.probe' } });
        } else {
            await route.continue();
        }
    });
    await save(page).click();
    await panel(page).getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByRole('main').getByText('Masih diproses. Cek lagi sebentar lagi.')).toBeVisible();

    // The page's clock runs and is moved on; the waits of 3, 6 and 12 seconds
    // themselves are proven by the unit test of recheckDelay().
    await page.clock.fastForward(3_000);
    await expect.poll(() => checks.length).toBeGreaterThanOrEqual(2);
    await page.clock.fastForward(6_000);
    await expect.poll(() => checks.length).toBeGreaterThanOrEqual(3);
    await page.clock.fastForward(12_000);
    await expect.poll(() => checks.length).toBe(4);
    await expect(page.getByRole('main').getByText('Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.')).toBeVisible();

    // The user's own check counts as activity; the automatic repeats never do (RT-10).
    expect(checks.map((check) => check.headers()['x-background-request'] ?? null)).toEqual([null, '1', '1', '1']);
});

test('423 opens the recent-authentication dialog and then sends the same frozen request; "Batal" sends nothing', async ({ page }) => {
    const agus = await opened(page);
    const sent: Request[] = [];

    await page.route(PROBE_ROUTE, async (route) => {
        sent.push(route.request());
        await (sent.length <= 2 ? refuse(route, 423, 'RECENT_AUTH_REQUIRED') : route.continue());
    });

    // "Batal" returns to the review with nothing sent.
    await save(page).click();
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
    await expect(dialog).toContainText('Untuk melanjutkan, masukkan kata sandi Anda. Isian dan tinjauan Anda tetap seperti semula.');
    await accessible(page);
    await dialog.getByRole('button', { name: 'Batal' }).click();
    await expect(dialog).toBeHidden();
    await expect(save(page)).toBeEnabled();
    expect(sent).toHaveLength(1);
    expect(await storedReferences(page)).toEqual({});

    // A wrong password is one uniform message (UXS-42).
    await save(page).click();
    await dialog.getByLabel('Kata sandi', { exact: true }).fill('Salah#12345');
    await dialog.getByLabel('Kata sandi', { exact: true }).press('Enter');
    await expect(dialog).toContainText('Konfirmasi gagal');

    // The right one: RA-03, then the same frozen request.
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(agus.password);
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), dialog.getByRole('button', { name: 'Konfirmasi' }).click()]);
    expect(sent).toHaveLength(3);
    expect(envelopeOf(sent[2]!).idempotencyKey).toBe(envelopeOf(sent[1]!).idempotencyKey);
    expect(envelopeOf(sent[2]!).sourceIntentId).toBe(envelopeOf(sent[1]!).sourceIntentId);
});

test('419 asks for a reload and 429 tells when to try again; nothing ran and no reference stays', async ({ page }) => {
    await opened(page);

    await page.route(PROBE_ROUTE, (route) => route.fulfill({ status: 419, body: '' }));
    await save(page).click();
    await expect(page.getByRole('main').getByText('Halaman ini perlu dimuat ulang sebelum dapat dipakai. Tidak ada yang disimpan.')).toBeVisible();
    expect(await storedReferences(page)).toEqual({});

    await page.reload();
    await page.unroute(PROBE_ROUTE);
    await page.route(PROBE_ROUTE, (route) => refuse(route, 429, 'THROTTLED', 'REJECTED', { 'Retry-After': '30' }));
    await save(page).click();
    await expect(page.getByRole('main').getByText('Belum ada yang diproses. Coba lagi dalam 30 detik.')).toBeVisible();
    expect(await storedReferences(page)).toEqual({});
});

test('a repeat of an uncertain request that runs nothing keeps its reference and its recovery: 423 and "Batal", 429, then 401 (review finding R-01)', async ({ page }) => {
    await opened(page);
    const sent: Request[] = [];

    // The first attempt gets no conclusive answer; each repeat is refused
    // before it runs: 423, then 429, then 401.
    await page.route(PROBE_ROUTE, async (route) => {
        sent.push(route.request());
        const answers = [() => refuse(route, 503, 'SERVICE_BUSY'), () => refuse(route, 423, 'RECENT_AUTH_REQUIRED'), () => refuse(route, 429, 'THROTTLED', 'REJECTED', { 'Retry-After': '30' })];
        await (answers[sent.length - 1] ?? (() => route.fulfill({ status: 401, json: refusal('UNAUTHENTICATED') })))();
    });

    await save(page).click();
    await expect(panel(page)).toBeVisible();
    const kept = await storedReferences(page);
    expect(Object.keys(kept)).toHaveLength(1);

    // 423 and "Batal": back on the uncertain panel, the reference kept.
    await panel(page).getByRole('button', { name: 'Kirim ulang' }).click();
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
    await dialog.getByRole('button', { name: 'Batal' }).click();
    await expect(dialog).toBeHidden();
    await expect(panel(page).getByRole('button')).toHaveText(['Kirim ulang', 'Cek status']);
    expect(await storedReferences(page)).toEqual(kept);

    // 429: when to try again, and the panel with both actions, the reference kept.
    await panel(page).getByRole('button', { name: 'Kirim ulang' }).click();
    await expect(page.getByRole('main').getByText('Belum ada yang diproses. Coba lagi dalam 30 detik.')).toBeVisible();
    await expect(panel(page).getByRole('button')).toHaveText(['Kirim ulang', 'Cek status']);
    expect(await storedReferences(page)).toEqual(kept);

    // 401: the page leaves the sign-in session with the reference kept, and
    // the page of the family then opens on the request's panel (NC-13 (b)).
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), panel(page).getByRole('button', { name: 'Kirim ulang' }).click()]);
    expect(sent).toHaveLength(4);
    expect(new Set(sent.map((request) => envelopeOf(request).sourceIntentId)).size).toBe(1);
    expect(await storedReferences(page)).toEqual(kept);
    await page.goto(COMMAND_SPECIMEN);
    await expect(panel(page).getByRole('button')).toHaveText(['Cek status']);
});

test('419 on a repeat of an uncertain request keeps its reference, and the reloaded page opens on its panel (review finding R-01)', async ({ page }) => {
    await opened(page);
    let attempts = 0;

    await page.route(PROBE_ROUTE, async (route) => {
        attempts += 1;
        await (attempts === 1 ? refuse(route, 503, 'SERVICE_BUSY') : route.fulfill({ status: 419, body: '' }));
    });

    await save(page).click();
    await expect(panel(page)).toBeVisible();
    const kept = await storedReferences(page);

    await panel(page).getByRole('button', { name: 'Kirim ulang' }).click();
    await expect(page.getByRole('main').getByText('Halaman ini perlu dimuat ulang sebelum dapat dipakai. Tidak ada yang disimpan.')).toBeVisible();
    expect(await storedReferences(page)).toEqual(kept);

    await page.reload();
    await expect(panel(page).getByRole('button')).toHaveText(['Cek status']);
    expect(await storedReferences(page)).toEqual(kept);
});
