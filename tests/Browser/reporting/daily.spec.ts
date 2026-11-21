import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-501 "Rekap harian" (AC-041-01, AC-041-04; COPY_DECK sections 4.3, 10.1 and 10.2; INTERACTION_INVENTORY UXI-007,
// UXI-018, UXI-306, UXI-307; NAVIGATION_CONTRACTS UXP-501; UI_STATES UXS-02, UXS-05, UXS-22, UXS-25; suites UX01,
// UX02, SESSION09; TASK-041, L5): "Laporan" opens the summary of today, the first item of its sub-navigation; today's
// figures, made incomplete by a Sale of UNKNOWN cost in the Owner's OPEN session (the fixture `finance` of
// tests/Harness/Browser/FixturesController.php), with the as-of line and completeness before them; his session in
// the section of sessions still open, leading to its detail, the date kept on the way back; the note that opening
// cash and variance are not sales; the day before and back, no day after today; a date after today refused at its
// field with nothing fetched; a day of 2020, which the local database holds nothing of, empty; a read that fails
// with "Coba lagi"; 320 px. Other tests of the shared database add Sales and sessions, so only the presence of the
// figures is checked here; their values are DailySummaryTest's. These tests sign in the people of Toko Contoh, so
// they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const PAGE = '/reports/daily';

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** A Sale of today whose cost is UNKNOWN, in the Owner's OPEN session, by the fixture. */
async function unknownSale(context: BrowserContext): Promise<void> {
    const answer = await context.request.post('/testing/browser/finance', { headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);
}

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

/** A date in Asia/Jakarta, UTC+07:00, a number of days from today, as a date field holds it. */
function jakartaDate(days: number): string {
    return new Date(Date.now() + (7 + 24 * days) * 60 * 60 * 1000).toISOString().slice(0, 10);
}

/** The reads of the summary that the page makes from now on. */
function reads(page: Page): { count: () => number } {
    let count = 0;

    page.on('request', (request) => {
        if (new URL(request.url()).pathname === PAGE) {
            count += 1;
        }
    });

    return { count: () => count };
}

test('the Owner reads the summary of a day with its sessions, moves between days and is refused a later one', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[1]!);
    const page = await context.newPage();
    const accounts = await resetPeople(context, ['Hendra']);
    await prepareCommands(context, ['Hendra']);
    await unknownSale(context);
    await signIn(page, accounts.Hendra);
    const watched = await watch(page);
    const today = jakartaDate(0);
    const yesterday = jakartaDate(-1);

    // UXI-007 "Laporan" opens Rekap harian (COPY_DECK section 4.3), the first item of its sub-navigation (UXI-018).
    await Promise.all([page.waitForURL((url) => url.pathname === PAGE), page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link', { name: 'Laporan' }).click()]);
    const main = page.getByRole('main');
    await expect(page.getByRole('heading', { level: 1, name: 'Rekap harian' })).toBeVisible();
    await expect(page).toHaveTitle('Rekap harian — Toko Contoh');
    await expect(page.getByRole('navigation', { name: 'Submenu Laporan' }).getByRole('link', { name: 'Rekap harian' })).toHaveAttribute('aria-current', 'page');
    await expect(main.getByText(/^Data per \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2} WIB$/)).toBeVisible();

    // UXS-25: completeness before the figures, which still show the sales and payment totals.
    await expect(main.getByText('Data biaya belum lengkap').first()).toBeVisible();
    for (const label of ['Penjualan bruto', 'Pengembalian uang', 'Penjualan neto', 'HPP', 'Laba kotor', 'Jumlah penjualan', 'Pembayaran tunai', 'Pembayaran transfer bank']) {
        await expect(main.getByText(label, { exact: true }).first()).toBeVisible();
    }

    // UXI-307, BR-RPT-02: his session among those still open, leading to its detail; opening cash and variance are
    // never sales or profit.
    await expect(main.getByText('Modal awal dan selisih kas bukan penjualan dan tidak masuk laba kotor.')).toBeVisible();
    const open = main.getByRole('table', { name: 'Masih dibuka' });
    await expect(open.getByRole('columnheader')).toHaveText(['Penanggung jawab', 'Laci kas', 'Dibuka', 'Ditutup', 'Status', 'Selisih']);
    const own = open.getByRole('link', { name: 'Hendra', exact: true }).first();
    await expect(own).toHaveAttribute('href', /^\/cash-sessions\/\d+$/);
    await checked(page);

    // UXI-306: no day after today; the day before, then back.
    await expect(page.getByRole('button', { name: 'Hari berikutnya' })).toHaveCount(0);
    await Promise.all([page.waitForURL((url) => url.searchParams.get('date') === yesterday), page.getByRole('button', { name: 'Hari sebelumnya' }).click()]);
    await expect(page.getByLabel('Pilih tanggal', { exact: true })).toHaveValue(yesterday);
    await Promise.all([page.waitForURL((url) => url.searchParams.get('date') === today), page.getByRole('button', { name: 'Hari berikutnya' }).click()]);
    await expect(page.getByRole('button', { name: 'Hari berikutnya' })).toHaveCount(0);

    // A date after today is refused at its field, and nothing is fetched (INTERACTION_CONTRACTS section 3.4).
    const fetched = reads(page);
    await page.getByLabel('Pilih tanggal', { exact: true }).fill(jakartaDate(1));
    await expect(page.locator('#daily-date-error')).toHaveText('Periksa isian');
    expect(fetched.count(), 'a refused date fetches nothing').toBe(0);
    await checked(page);

    // 15 January 2020: nothing sold (UXS-02), and no session.
    await Promise.all([page.waitForURL((url) => url.searchParams.get('date') === '2020-01-15'), page.getByLabel('Pilih tanggal', { exact: true }).fill('2020-01-15')]);
    await expect(main.getByText('Belum ada penjualan pada tanggal ini.')).toBeVisible();
    await expect(main.getByText('Belum ada sesi kas.')).toBeVisible();
    await expect(main.getByText('15 Januari 2020', { exact: true })).toBeVisible();
    await checked(page);
    await fitsNarrow(page);

    // UXS-22: a read of another day that fails shows no figure, the date stays, and "Coba lagi" reads it again.
    await page.route((url) => url.pathname === PAGE, (route) => route.abort(), { times: 1 });
    await page.getByRole('button', { name: 'Hari berikutnya' }).click();
    await expect(main.getByText('Gagal memuat')).toBeVisible();
    await expect(page).toHaveURL((url) => url.searchParams.get('date') === '2020-01-15');
    await Promise.all([page.waitForResponse((response) => new URL(response.url()).pathname === PAGE), main.getByRole('button', { name: 'Coba lagi' }).click()]);
    await expect(main.getByText('Belum ada penjualan pada tanggal ini.')).toBeVisible();

    // A session's detail, and back: the date is kept (NAVIGATION_CONTRACTS UXP-501).
    await page.goto(`${PAGE}?date=${today}`);
    await Promise.all([page.waitForURL((url) => /^\/cash-sessions\/\d+$/.test(url.pathname)), own.click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL((url) => url.pathname === PAGE && url.searchParams.get('date') === today);
    await expect(page.getByRole('heading', { level: 1, name: 'Rekap harian' })).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
