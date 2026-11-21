import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-502 "Laporan keuangan" (AC-040-01, AC-040-02, AC-040-03, AC-040-05; KEY_SCREENS KS-10 with its states
// `default`, `incomplete` and `table`; COPY_DECK sections 10.1 and 10.3; INTERACTION_INVENTORY UXI-308 to UXI-312;
// NAVIGATION_CONTRACTS UXP-502; UI_STATES UXS-02, UXS-05, UXS-25; ADAPTIVE_AND_ACCESSIBILITY UXA-19; D-018; suites
// UX01, UX02, FIN03; TASK-040, L5 KS-10 and the chart): today's report, made incomplete by a Sale whose cost is
// UNKNOWN (the fixture `finance` of tests/Harness/Browser/FixturesController.php), with the as-of line, the counts,
// the link "Biaya perlu dilengkapi" and no margin; the trend reachable with the keyboard, each point shown, and the
// same data as a table; a custom range refused at its date fields with nothing fetched — its start after its end,
// more than 366 days, its end after today — and "Batal" keeping the period applied; a custom range of 2020, which
// the local database holds nothing of, empty and complete; the applied period kept for the sign-in session; 320 px
// and 200 % zoom. The figures of other periods depend on what other tests sold, so only their presence is checked
// here; their values are FinanceReportTest's. These tests sign in the people of Toko Contoh, so they run one at a
// time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const PAGE = '/reports/finance';

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** A Sale of today whose cost is UNKNOWN, by the fixture. */
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

/** Tomorrow in Asia/Jakarta, UTC+07:00, as a date field holds it. */
function tomorrow(): string {
    return new Date(Date.now() + (7 + 24) * 60 * 60 * 1000).toISOString().slice(0, 10);
}

/** Whether an address's query holds exactly these parameters, in whatever order the page writes them (F-9). */
function hasQuery(expected: Record<string, string>): (url: URL) => boolean {
    return (url) => {
        const actual = [...url.searchParams.entries()];

        return actual.length === Object.keys(expected).length && actual.every(([key, value]) => expected[key] === value);
    };
}

/** The reads of the report that the page makes from now on. */
function reads(page: Page): { count: () => number } {
    let count = 0;

    page.on('request', (request) => {
        if (new URL(request.url()).pathname === PAGE) {
            count += 1;
        }
    });

    return { count: () => count };
}

async function signedIn(context: BrowserContext, page: Page): Promise<void> {
    const accounts = await resetPeople(context, ['Hendra']);

    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
}

test('the Owner reads an incomplete report of today, its trend and its table, and a custom range refused at its fields', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[1]!);
    const page = await context.newPage();
    await signedIn(context, page);
    const watched = await watch(page);
    await unknownSale(context);

    await page.goto(`${PAGE}?period=today`);
    const main = page.getByRole('main');
    await expect(page.getByRole('heading', { level: 1, name: 'Laporan keuangan' })).toBeVisible();
    await expect(page).toHaveTitle('Laporan keuangan — Toko Contoh');
    await expect(main.getByText(/^Data per \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2} WIB$/)).toBeVisible();
    await expect(page.getByRole('radio', { name: 'Hari Ini' })).toHaveAttribute('aria-checked', 'true');

    // KS-10 `incomplete` (UXS-25): completeness before the figures, the counts, the link, and no margin.
    await expect(main.getByText('Data biaya belum lengkap').first()).toBeVisible();
    await expect(main.getByText(/^Baris dengan biaya belum diketahui: [1-9][0-9.]*$/)).toBeVisible();
    await expect(main.getByText(/^Produk dengan laporan ditandai belum lengkap: [0-9.]+$/)).toBeVisible();
    // The completeness notice's link, apart from the same item of the Laporan sub-navigation (TASK-041).
    await expect(main.locator('.bk-notice').getByRole('link', { name: 'Biaya perlu dilengkapi' })).toHaveAttribute('href', '/reports/costs-to-complete');
    for (const label of ['Penjualan bruto', 'Pengembalian uang', 'Penjualan neto', 'HPP', 'Laba kotor', 'Jumlah penjualan', 'Pembayaran tunai', 'Pembayaran transfer bank', 'Barang terjual', 'Barang diretur']) {
        await expect(main.getByText(label, { exact: true }).first()).toBeVisible();
    }

    await expect(main.getByText('Margin', { exact: true })).toHaveCount(0);
    await checked(page);

    // UXI-310 (UXA-19): the chart is reached with Tab, and its point shows and announces its date and value.
    const chart = page.getByTestId('trend-chart');
    await expect(chart).toHaveAttribute('aria-label', 'Grafik tren Penjualan neto');
    await chart.focus();
    await page.keyboard.press('End');
    await expect(page.getByTestId('trend-tip')).toHaveText(/^\d{2} \w+ \d{4}: −?Rp[0-9.]+$/);
    await checked(page);

    // UXI-311, KS-10 `table`: the same data as a table, and back.
    await page.getByRole('button', { name: 'Tampilkan sebagai tabel' }).click();
    const table = main.getByRole('table', { name: 'Grafik tren Penjualan neto' });
    await expect(table).toBeVisible();
    await expect(table.getByRole('columnheader')).toHaveText(['Tanggal', 'Penjualan neto']);
    await expect(table.getByRole('row')).toHaveCount(2);
    await checked(page);
    await page.getByRole('button', { name: 'Tampilkan sebagai grafik' }).click();
    await expect(chart).toBeVisible();

    // UXS-22 (AC-040-01; review R-01, R-02): a read of another period that fails shows no figure, the period choice
    // stays on the period applied, and "Coba lagi" reads the report again.
    await page.route((url) => url.pathname === PAGE, (route) => route.abort(), { times: 1 });
    await page.getByRole('radio', { name: '7 Hari' }).click();
    await expect(main.getByText('Gagal memuat')).toBeVisible();
    await expect(main.getByText('Penjualan bruto', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('radio', { name: 'Hari Ini' })).toHaveAttribute('aria-checked', 'true');
    await expect(page).toHaveURL((url) => url.search === '?period=today');
    await checked(page);
    await Promise.all([page.waitForResponse((response) => new URL(response.url()).pathname === PAGE), main.getByRole('button', { name: 'Coba lagi' }).click()]);
    await expect(main.getByText('Penjualan bruto', { exact: true })).toBeVisible();
    await expect(main.getByText(/^Data per \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2} WIB$/)).toBeVisible();

    // UXI-309: the custom range is checked at its date fields; a refused range fetches nothing (AC-040-02).
    const fetched = reads(page);
    await page.getByRole('radio', { name: 'Rentang Kustom' }).click();
    const from = page.getByLabel('Dari tanggal', { exact: true });
    const to = page.getByLabel('Sampai tanggal', { exact: true });

    await from.fill('2020-01-31');
    await to.fill('2020-01-01');
    await page.getByRole('button', { name: 'Terapkan' }).click();
    await expect(page.locator('#finance-from-error')).toHaveText('Tanggal awal tidak boleh setelah tanggal akhir.');
    await checked(page);

    await from.fill('2019-01-01');
    await to.fill('2020-01-03');
    await page.getByRole('button', { name: 'Terapkan' }).click();
    await expect(page.locator('#finance-to-error')).toHaveText('Rentang paling lama 366 hari.');

    await from.fill('2020-01-01');
    await to.fill(tomorrow());
    await page.getByRole('button', { name: 'Terapkan' }).click();
    await expect(page.locator('#finance-to-error')).toHaveText('Periksa isian');
    expect(fetched.count(), 'a refused range fetches nothing').toBe(0);

    // "Batal" keeps the period applied before.
    await page.getByRole('button', { name: 'Batal' }).click();
    await expect(from).toHaveCount(0);
    await expect(page.getByRole('radio', { name: 'Hari Ini' })).toHaveAttribute('aria-checked', 'true');
    expect(fetched.count()).toBe(0);

    // January 2020: nothing sold (UXS-02), its dates named.
    await page.getByRole('radio', { name: 'Rentang Kustom' }).click();
    await from.fill('2020-01-01');
    await to.fill('2020-01-31');
    const january = { period: 'custom', from: '2020-01-01', to: '2020-01-31' };
    await Promise.all([page.waitForURL(hasQuery(january)), page.getByRole('button', { name: 'Terapkan' }).click()]);
    await expect(main.getByText('Belum ada penjualan pada periode ini.')).toBeVisible();
    await expect(main.getByText('01 Januari 2020 – 31 Januari 2020', { exact: true })).toBeVisible();
    await checked(page);
    await fitsNarrow(page);

    // The applied period is kept for the sign-in session (NAVIGATION_CONTRACTS UXP-502).
    await page.goto(PAGE);
    await page.waitForURL(hasQuery(january));
    await expect(main.getByText('Belum ada penjualan pada periode ini.')).toBeVisible();

    // A preset reads its report at once: Bulan Ini, with its figures and its trend.
    await Promise.all([page.waitForURL((url) => url.search === '?period=this_month'), page.getByRole('radio', { name: 'Bulan Ini' }).click()]);
    await expect(main.getByText('Penjualan neto', { exact: true }).first()).toBeVisible();
    await expect(chart).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('at 200 % zoom the report fits and its trend works from the keyboard', async ({ browser }) => {
    // 640 × 400 at a scale of 2, the 200 % zoom of 1280 × 800 (EXC-53).
    const context = await contextAt(browser, WIDTHS[4]!);
    const page = await context.newPage();
    await signedIn(context, page);
    const watched = await watch(page);
    await unknownSale(context);

    await page.goto(`${PAGE}?period=7d`);
    await expect(page.getByRole('heading', { level: 1, name: 'Laporan keuangan' })).toBeVisible();
    await checked(page);

    // The chart moves between its seven points with the arrow keys, Home and End (UXA-19).
    const chart = page.getByTestId('trend-chart');
    await chart.focus();
    await expect(chart).toBeFocused();
    await page.keyboard.press('Home');
    const first = (await page.getByTestId('trend-tip').textContent()) ?? '';
    await page.keyboard.press('ArrowRight');
    await expect(page.getByTestId('trend-tip')).not.toHaveText(first);
    await page.keyboard.press('End');
    await expect(page.getByTestId('trend-tip')).toHaveText(/^\d{2} \w+ \d{4}: −?Rp[0-9.]+$/);

    // "Tampilkan sebagai tabel" by Enter: seven days, as rows or, in a narrow container, as cards (DESIGN_SYSTEM 8.11).
    const toggle = page.getByRole('button', { name: 'Tampilkan sebagai tabel' });
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('button', { name: 'Tampilkan sebagai grafik' })).toBeVisible();
    await expect(page.getByRole('main').locator('.bk-table tbody tr')).toHaveCount(7);
    await checked(page);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
