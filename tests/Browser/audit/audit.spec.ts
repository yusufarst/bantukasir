import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-612 "Riwayat aktivitas" and UXP-613 "Riwayat keamanan" (AC-042-01 to AC-042-03; COPY_DECK sections 11.1,
// 11.12 to 11.14; NAVIGATION_CONTRACTS UXP-604, UXP-612, UXP-613; INTERACTION_INVENTORY UXI-330, UXI-331,
// UXI-349, UXI-377 to UXI-385; UI_STATES section 6; suites UX01, UX02, AUDIT01; TASK-042, L5 RP-612 and RP-613):
// the Owner reaches both views from "Pengaturan"; filters the activity by "Jenis", which stands in the address;
// opens an event's details; follows its reference to the account's page, whose way back returns to the activity
// with its filter; sees a read that fails (UXS-22) and reads it again; filters the security log by "Jenis" and
// opens an entry's details; no field name of an event's change data shows; both fit 320 px and 200 %
// zoom and work from the keyboard; Operations is refused both. "Perubahan" (TASK-051, L5; COPY_DECK section
// 11.15): a cost correction, a price change and a receiving's table of lines, opened by Enter, at the widths and
// 200 % zoom. The events are the browser fixtures' own: the newest password link used is Hendra's, by
// resetPeople(), and the newest sign-in is his. These tests sign in the people of Toko Contoh, so they run one at
// a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const ACTIVITY = '/settings/activity';

const SECURITY = '/settings/security-log';

/** Waits until no transition of a finite length runs, so that axe reads the colours at rest (D-124). */
async function settled(page: Page): Promise<void> {
    await page.waitForFunction(
        () =>
            document.getAnimations().every((animation) => {
                const end = animation.effect?.getComputedTiming().endTime;

                return animation.playState !== 'running' || typeof end !== 'number' || end === Infinity;
            }),
        undefined,
        { timeout: ACCESS_WAIT },
    );
}

async function checked(page: Page): Promise<void> {
    await settled(page);
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => `${node.target.join(' ')} ${node.failureSummary ?? ''}`).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

/** The same checks at 320 px, then at the width it started at. */
async function fitsNarrow(page: Page): Promise<void> {
    const start = page.viewportSize();

    await page.setViewportSize({ width: WIDTHS[7]!.width, height: WIDTHS[7]!.height });
    await checked(page);

    if (start !== null) {
        await page.setViewportSize(start);
    }
}

/** The first row of the list's table, its header row apart. */
function firstRow(page: Page) {
    return page.getByRole('main').getByRole('table').getByRole('row').nth(1);
}

/** The newest row of the activity whose action is this label; a table of lines inside it comes after it. */
function actionRow(page: Page, action: string) {
    return page.getByRole('main').getByRole('table', { name: 'Riwayat aktivitas', exact: true }).getByRole('row').filter({ hasText: action }).first();
}

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** The audit events of one new product, by the fixture: its receiving, a price change and a cost correction (TASK-051). */
async function audited(context: BrowserContext): Promise<{ productId: number; name: string; reason: string }> {
    const answer = await context.request.post('/testing/browser/audit', { headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    return (await answer.json()) as { productId: number; name: string; reason: string };
}

test('the Owner filters the activity, opens an event, follows its reference and back, and reads the security log', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    // Hendra last, so that the newest password link used is his (LINK_CONSUME).
    const accounts = await resetPeople(context, ['Agus', 'Hendra']);
    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
    const watched = await watch(page);

    // UXI-330: "Riwayat aktivitas" on "Pengaturan".
    await page.goto('/settings');
    await expect(page.getByText('Siapa mengubah apa, dan kapan.')).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY), page.getByRole('main').getByRole('link', { name: /Riwayat aktivitas/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat aktivitas' })).toBeVisible();
    await expect(page).toHaveTitle('Riwayat aktivitas — Toko Contoh');
    for (const label of ['Tanggal', 'Pengguna', 'Jenis', 'Produk', 'Sesi kas', 'Referensi']) {
        await expect(page.getByRole('main').locator('input, select').and(page.getByLabel(label, { exact: true }))).toBeVisible();
    }

    // The two search forms are landmarks each named by its field (axe landmark-unique).
    for (const label of ['Sesi kas', 'Referensi']) {
        await expect(page.getByRole('main').getByRole('search', { name: label, exact: true })).toBeVisible();
    }

    await checked(page);

    // UXI-377: "Jenis", whose choice stands in the address (NC-10).
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY && url.searchParams.get('action') === 'LINK_CONSUME'), page.getByRole('main').getByLabel('Jenis', { exact: true }).selectOption({ label: 'Tautan kata sandi dipakai' })]);
    const row = firstRow(page);
    await expect(row).toContainText('Tautan kata sandi dipakai');
    await expect(row.getByRole('link', { name: 'Hendra' })).toBeVisible();

    // UXI-378: the event's "Perubahan" opens on demand, under the approved labels of COPY_DECK section 11.15 and
    // never a stored field name (AGENTS rule 14, EXC-51; OPEN_ITEMS T-56; TASK-051); it has no reason.
    await row.getByText('Tampilkan rincian').click();
    await expect(row.getByText('Perubahan', { exact: true })).toBeVisible();
    await expect(row.getByText('Jenis tautan', { exact: true })).toBeVisible();
    await expect(row.getByText('Sesi masuk yang diakhiri', { exact: true })).toBeVisible();
    await expect(row.getByText('Alasan', { exact: true })).toHaveCount(0);
    for (const name of ['kind', 'sessionsRevoked', 'FIRST_PASSWORD', 'RESET']) {
        await expect(row.getByText(name, { exact: true })).toHaveCount(0);
    }
    await checked(page);
    await fitsNarrow(page);

    // UXI-379, then UXI-349: the account's page, and back to the activity with its filter.
    await Promise.all([page.waitForURL((url) => /^\/settings\/users\/\d+$/.test(url.pathname)), row.getByRole('link', { name: 'Hendra' }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail pengguna' })).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY), page.getByRole('link', { name: 'Kembali ke Riwayat aktivitas' }).click()]);
    expect(Object.fromEntries(new URL(page.url()).searchParams)).toEqual({ action: 'LINK_CONSUME' });
    await expect(page.getByRole('main').getByLabel('Jenis', { exact: true })).toHaveValue('LINK_CONSUME');

    // UXS-22: a read that fails shows no row, then "Coba lagi" reads that address again.
    await page.route((url) => url.pathname === ACTIVITY, (route) => route.abort(), { times: 1 });
    await page.getByRole('main').getByLabel('Jenis', { exact: true }).selectOption('');
    await expect(page.getByText('Gagal memuat')).toBeVisible();
    await expect(page.getByRole('main').getByRole('table')).toHaveCount(0);
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY && !url.searchParams.has('action')), page.getByRole('button', { name: 'Coba lagi' }).click()]);
    await expect(page.getByRole('main').getByRole('table')).toHaveCount(1);

    // UXI-381: back to "Pengaturan"; UXI-331: "Riwayat keamanan".
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.getByRole('link', { name: 'Kembali ke Pengaturan' }).click()]);
    await expect(page.getByText('Upaya masuk, tautan kata sandi, dan akses yang ditolak.')).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === SECURITY), page.getByRole('main').getByRole('link', { name: /Riwayat keamanan/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat keamanan' })).toBeVisible();
    await checked(page);

    // UXI-382 and UXI-383: the newest sign-in is Hendra's, which succeeded; its details name the account and the
    // network address.
    await Promise.all([page.waitForURL((url) => url.pathname === SECURITY && url.searchParams.get('type') === 'SIGN_IN_SUCCEEDED'), page.getByRole('main').getByLabel('Jenis', { exact: true }).selectOption({ label: 'Berhasil masuk' })]);
    const entry = firstRow(page);
    await expect(entry).toContainText('Berhasil masuk');
    await expect(entry).toContainText('Hendra');
    await expect(entry).toContainText('Berhasil');
    await entry.getByText('Tampilkan rincian').click();
    await expect(entry.getByText('Akun terkait')).toBeVisible();
    await expect(entry.getByText('Alamat jaringan')).toBeVisible();
    await checked(page);
    await fitsNarrow(page);

    // UXI-385.
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.getByRole('link', { name: 'Kembali ke Pengaturan' }).click()]);

    expect(injected(watched.problems)).toEqual([]);

    // Operations is refused both views (AC-042-01).
    const other = await contextAt(browser, WIDTHS[0]!);
    const agus = await other.newPage();
    await signIn(agus, accounts.Agus);
    for (const path of [ACTIVITY, SECURITY]) {
        const refused = await agus.goto(path);
        expect(refused?.status(), path).toBe(403);
    }

    await other.close();
    await context.close();
});

test('at 200 % zoom both views fit and their controls work from the keyboard', async ({ browser }) => {
    // 640 × 400 at a scale of 2, the 200 % zoom of 1280 × 800 (EXC-53).
    const context = await contextAt(browser, WIDTHS[4]!);
    const page = await context.newPage();
    const accounts = await resetPeople(context, ['Hendra']);
    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
    const watched = await watch(page);

    for (const [path, title] of [
        [ACTIVITY, 'Riwayat aktivitas'],
        [SECURITY, 'Riwayat keamanan'],
    ] as const) {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
        await checked(page);

        // The first entry's details open by Enter; those of the activity's events by Enter are proven by the test of
        // "Perubahan" below (UXI-378, TASK-051).
        if (path === SECURITY) {
            const summary = firstRow(page).locator('summary');
            await summary.focus();
            await expect(summary).toBeFocused();
            await page.keyboard.press('Enter');
            await expect(firstRow(page).locator('details')).toHaveAttribute('open', '');
            await checked(page);
        }

        // "Kembali ke Pengaturan" by Enter.
        const back = page.getByRole('link', { name: 'Kembali ke Pengaturan' });
        await back.focus();
        await expect(back).toBeFocused();
        await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.keyboard.press('Enter')]);
        await expect(page.getByRole('heading', { level: 1, name: 'Pengaturan' })).toBeVisible();
    }

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('"Perubahan" shows a cost correction, a price change and a receiving\'s lines under their approved labels, by keyboard and at 200 % zoom', async ({ browser }) => {
    // AC-051-02 (COPY_DECK section 11.15; TASK-051, L5): the Owner's own events of one new product. At the first
    // width, then at 200 % zoom (640 × 400 at a scale of 2), each with the checks at rest and at 320 px.
    const first = await contextAt(browser, WIDTHS[0]!);
    const accounts = await resetPeople(first, ['Hendra']);
    await prepareCommands(first, ['Hendra']);
    const found = await audited(first);

    for (const width of [WIDTHS[0]!, WIDTHS[4]!]) {
        const context = width === WIDTHS[0] ? first : await contextAt(browser, width);
        const page = await context.newPage();
        await signIn(page, accounts.Hendra);
        const watched = await watch(page);

        await page.goto(`${ACTIVITY}?product=${String(found.productId)}`);
        await expect(page.getByRole('heading', { level: 1, name: 'Riwayat aktivitas' })).toBeVisible();

        // The cost correction, opened by Enter: its cost "lama → baru", the Owner's alone, the marks it cleared,
        // and its reason under "Alasan", not under "Perubahan".
        const correction = actionRow(page, 'Koreksi biaya');
        const summary = correction.locator('summary');
        await summary.focus();
        await expect(summary).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(correction.locator('details')).toHaveAttribute('open', '');
        await expect(correction.getByText('Perubahan', { exact: true })).toBeVisible();
        await expect(correction.getByText('Biaya', { exact: true })).toBeVisible();
        await expect(correction.getByText('Rp3.000 → Rp3.500', { exact: true })).toBeVisible();
        await expect(correction.getByText('Tanda belum lengkap yang dihapus', { exact: true })).toBeVisible();
        await expect(correction.getByText('Alasan', { exact: true })).toBeVisible();
        await expect(correction.getByText(found.reason, { exact: true })).toBeVisible();

        // The price change: "Harga jual" from the old price to the new.
        const price = actionRow(page, 'Harga jual diubah');
        await price.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(price.getByText('Harga jual', { exact: true })).toBeVisible();
        await expect(price.getByText('Rp5.000 → Rp6.000', { exact: true })).toBeVisible();

        // The receiving: its lines as a table under "Barang", with the cost column for the Owner.
        const receiving = actionRow(page, 'Barang masuk');
        await receiving.locator('summary').focus();
        await page.keyboard.press('Enter');
        const lines = receiving.getByRole('table', { name: 'Barang', exact: true });
        await expect(lines).toBeVisible();

        for (const text of ['Produk', 'Jumlah', 'Biaya', found.name, '10 pcs', 'Rp3.000']) {
            await expect(lines).toContainText(text);
        }

        // No stored field name or code reaches the page (ACV-01).
        for (const name of ['old', 'new', 'evidenceId', 'lineNo', 'productId', 'marksCleared', 'KNOWN', 'from', 'to']) {
            await expect(page.getByRole('main').getByText(name, { exact: true })).toHaveCount(0);
        }

        await checked(page);
        await fitsNarrow(page);
        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    }
});
