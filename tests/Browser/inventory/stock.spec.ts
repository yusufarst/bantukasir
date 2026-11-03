import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page, type Request } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-401 to UXP-403 and the badge of "Stok" (AC-022-01, AC-022-02, AC-022-06; KS-08; COPY_DECK
// sections 9.2 to 9.4; NAVIGATION_CONTRACTS UXP-401 to UXP-403; UI_STATES section 6; BR-INV-41;
// suites UX01, UX02, STOCK01; TASK-022, L5): the list with its state chips, its search and its
// states — rows, no results, empty and not refreshed — at each width; the refresh every 30 seconds
// while visible and on focus as background reads, a failed one keeping the rows with their time;
// the badge from RD-03; a row's history and the way back with the list's filters. These tests
// sign in the people of Toko Contoh, so they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const STOCK_DATA = '**/data/stock?**';

const SHELL_DATA = '**/data/shell';

function letters(length: number): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)), (byte) => String.fromCharCode(97 + (byte % 26))).join('');
}

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

async function signedIn(context: BrowserContext, page: Page, person: Person): Promise<void> {
    const accounts = await resetPeople(context, [person]);

    await prepareCommands(context, [person]);
    await signIn(page, accounts[person]);
}

/** A new product, with a new category, through UXP-307 and UXP-303: its name; its stock is 0, so it is Habis. */
async function newProduct(page: Page): Promise<string> {
    const category = `Kategori ${letters(10)}`;
    const name = `Z${letters(14)}`;

    await page.goto('/categories');
    await page.getByRole('button', { name: 'Tambah kategori' }).click();
    await page.getByLabel('Nama kategori', { exact: true }).fill(category);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText(`Kategori ${category} ditambahkan.`)).toBeVisible();

    await page.goto('/products/new');
    await page.getByLabel('Nama', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Lanjut isi data' }).click();
    await page.getByLabel('SKU', { exact: true }).fill(`SKU-${letters(8)}`);
    await page.getByLabel('Kategori', { exact: true }).selectOption({ label: category });
    await page.getByLabel('Satuan', { exact: true }).fill('pcs');
    await page.getByLabel('Harga jual', { exact: true }).fill('7500');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT });

    return name;
}

/**
 * The shell's own read of RD-03, answered at once with no request and no attention, for a test
 * that moves the page's clock; it returns a wait for none of these reads to be out. Each read waits
 * 30 seconds for its answer, so a jump of the clock while one is out ends it as failed, and the
 * shell then shows its own "Data belum diperbarui" beside the list's (CI run 41583276625). Set up
 * before the page opens, so that every read of the page is counted. A read of the document before
 * a new one may never end with an event once its document is gone, so the reads still out are
 * discarded when the main frame commits a new document; a navigation within the document keeps
 * them, and a late end of a discarded read changes nothing (CI run 41589075661).
 */
async function quietShell(page: Page): Promise<() => Promise<void>> {
    const out = new Set<Request>();
    let newDocument = false;
    const isShell = (request: Request): boolean => new URL(request.url()).pathname === '/data/shell';
    const ended = (request: Request): void => {
        out.delete(request);
    };

    page.on('request', (request) => {
        if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
            newDocument = true;
        } else if (isShell(request)) {
            out.add(request);
        }
    });
    page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame() && newDocument) {
            newDocument = false;
            out.clear();
        }
    });
    page.on('requestfinished', ended);
    page.on('requestfailed', ended);
    await page.route(SHELL_DATA, (route) => route.fulfill({ json: { unresolvedRequests: 0, stockAttention: 0, asOf: '2026-11-02T03:00:00.000000Z' } }));

    return async () => {
        await expect.poll(() => out.size).toBe(0);
    };
}

/** A stock row of RD-02 for a stubbed answer. */
function stubRow(index: number, state: 'OUT' | 'LOW' | 'NORMAL'): Record<string, unknown> {
    return { id: 900000 + index, name: `Produk contoh ${index}`, sku: `CTH-${index}`, baseUnit: 'pcs', quantityPrecision: 0, onHand: state === 'OUT' ? '0' : state === 'LOW' ? '3' : '9', minimum: '5', state };
}

// KS-08 at 1440 × 900, the 200 % zoom of 1280 × 800 and 320 × 640.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`the stock list with its chips, search, states and a row's history, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Agus');
        const name = await newProduct(page);

        await page.goto('/stock');
        await expect(page.getByRole('heading', { level: 1, name: 'Stok', exact: true })).toBeVisible();
        await expect(page.getByText(/^Diperbarui /)).toBeVisible();

        // The search: the product's row, Habis with stock 0 (BR-INV-40).
        await page.getByLabel('Cari produk', { exact: true }).fill(name);
        await expect(page).toHaveURL(new RegExp(`q=${name}`));
        const row = page.getByRole('main').getByRole('table').locator('tbody tr');
        await expect(row).toHaveCount(1);
        await expect(row).toContainText('Habis');
        await expect(row).toContainText('0 pcs');
        await checked(page);

        // The chips: Habis keeps it, Normal finds nothing (UXS-03) and "Hapus filter" clears.
        await page.getByRole('button', { name: 'Habis', exact: true }).click();
        await expect(page).toHaveURL(/state=OUT/);
        await expect(row).toHaveCount(1);
        await page.getByRole('button', { name: 'Normal', exact: true }).click();
        await expect(page).toHaveURL(/state=NORMAL/);
        await expect(page.getByText('Tidak ada hasil')).toBeVisible();
        await checked(page);
        await page.getByRole('button', { name: 'Hapus filter' }).click();
        await expect(page.getByLabel('Cari produk', { exact: true })).toHaveValue('');

        // A row leads to the product's history, whose way back returns to the list with its search.
        await page.getByLabel('Cari produk', { exact: true }).fill(name);
        await expect(row).toHaveCount(1);
        await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), page.getByRole('link', { name, exact: true }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Riwayat stok' })).toBeVisible();
        await expect(page.getByLabel('Produk', { exact: true })).toHaveValue(name);
        await expect(page.getByText('Tidak ada hasil')).toBeVisible();
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === '/stock' && url.searchParams.get('q') === name), page.getByRole('link', { name: 'Kembali ke Stok' }).click()]);
        await expect(page.getByLabel('Cari produk', { exact: true })).toHaveValue(name);

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    });
}

test('the list refreshes in the background every 30 seconds and on focus, and a failed refresh keeps its rows with their time (AC-022-02)', async ({ page }) => {
    const context = page.context();
    const reads: (string | undefined)[] = [];

    await page.clock.install();
    await signedIn(context, page, 'Dewi');
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === '/data/stock') {
            reads.push(request.headers()['x-background-request']);
        }
    });

    // The answers of RD-02, one of each state, so that the rows are known.
    await page.route(STOCK_DATA, (route) =>
        route.fulfill({ json: { rows: [stubRow(1, 'OUT'), stubRow(2, 'LOW'), stubRow(3, 'NORMAL')], outCount: 1, lowCount: 1, asOf: '2026-11-02T03:00:00.000000Z', nextCursor: null } }),
    );
    const shellSettled = await quietShell(page);
    await page.goto('/stock');
    const rows = page.getByRole('main').getByRole('table').locator('tbody tr');
    await expect(rows).toHaveCount(3);
    await expect(rows.nth(0)).toContainText('Habis');
    await expect(rows.nth(1)).toContainText('Menipis');
    await expect(rows.nth(2)).toContainText('Normal');

    // Every 30 seconds while visible, and on focus: background reads (RT-10).
    const opened = reads.length;
    await shellSettled();
    await page.clock.fastForward(30_000);
    await expect.poll(() => reads.length).toBeGreaterThan(opened);
    const timed = reads.length;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await expect.poll(() => reads.length).toBeGreaterThan(timed);
    expect(new Set(reads)).toEqual(new Set(['1']));

    // A failed refresh: the rows stay, with "Data belum diperbarui" and the last time; never empty or zero.
    await page.unroute(STOCK_DATA);
    await page.route(STOCK_DATA, (route) => route.fulfill({ status: 503, json: { code: 'SERVICE_BUSY' } }));
    await shellSettled();
    await page.clock.fastForward(30_000);
    await expect(page.getByText('Data belum diperbarui')).toBeVisible();
    await expect(page.getByText('Data terakhir diperbarui 02 November 2026, 10.00.00 dan mungkin sudah berubah.')).toBeVisible();
    await expect(rows).toHaveCount(3);
    await expect(page.getByText('Belum ada produk.')).toHaveCount(0);

    // An empty answer without a filter is the empty state, never "Normal".
    await page.unroute(STOCK_DATA);
    await page.route(STOCK_DATA, (route) => route.fulfill({ json: { rows: [], outCount: 0, lowCount: 0, asOf: '2026-11-02T03:01:00.000000Z', nextCursor: null } }));
    await page.getByRole('button', { name: 'Muat ulang' }).first().click();
    await expect(page.getByText('Belum ada produk.')).toBeVisible();
    await expect(page.getByText('Data belum diperbarui')).toHaveCount(0);
    await checked(page);
});

// Pull request #31, finding P2: a refresh on focus or at 30 seconds while a filter's read was out
// superseded it with the filters still shown, so the list and its address went back to them.
test('a refresh while a filter is being read never brings back the filters shown before it', async ({ page }) => {
    const context = page.context();
    const reads: { readonly state: string | null; readonly background: boolean }[] = [];
    let release = (): void => undefined;
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    let lowFails = false;

    await page.clock.install();
    await signedIn(context, page, 'Dewi');

    // RD-02: without a filter, one row of each state; the read of "Habis" held until released.
    await page.route(STOCK_DATA, async (route) => {
        const state = new URL(route.request().url()).searchParams.get('state');

        reads.push({ state, background: route.request().headers()['x-background-request'] === '1' });

        if (state === 'LOW' && lowFails) {
            await route.fulfill({ status: 503, json: { code: 'SERVICE_BUSY' } });

            return;
        }

        if (state === 'OUT') {
            await held;
            await route.fulfill({ json: { rows: [stubRow(1, 'OUT')], outCount: 1, lowCount: 1, asOf: '2026-11-02T03:00:00.000000Z', nextCursor: null } });

            return;
        }

        await route.fulfill({ json: { rows: [stubRow(1, 'OUT'), stubRow(2, 'LOW'), stubRow(3, 'NORMAL')], outCount: 1, lowCount: 1, asOf: '2026-11-02T03:00:00.000000Z', nextCursor: null } });
    });
    const shellSettled = await quietShell(page);
    await page.goto('/stock');
    const rows = page.getByRole('main').getByRole('table').locator('tbody tr');
    await expect(rows).toHaveCount(3);

    // "Habis", its read out; then the focus and the 30 seconds' refresh, which wait for it. The
    // clock goes 20 seconds before the click and 15 after it, so that the refresh falls due while
    // the read is out and the read's own wait of 30 seconds does not (review finding R-14).
    await shellSettled();
    await page.clock.fastForward(20_000);
    await page.getByRole('button', { name: 'Habis', exact: true }).click();
    await expect.poll(() => reads.some((read) => read.state === 'OUT' && !read.background)).toBe(true);
    const chosen = reads.length;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await shellSettled();
    await page.clock.fastForward(15_000);
    release();

    // The list and its address are those of "Habis", the chip pressed, the read never failed.
    await expect(page).toHaveURL(/state=OUT/);
    await expect(rows).toHaveCount(1);
    await expect(page.getByRole('button', { name: 'Habis', exact: true, pressed: true })).toBeVisible();
    await expect(page.getByText('Data belum diperbarui')).toHaveCount(0);
    expect(reads.slice(chosen).filter((read) => read.state !== 'OUT')).toEqual([]);

    // A later refresh reads the filter asked for.
    const settled = reads.length;
    await shellSettled();
    await page.clock.fastForward(30_000);
    await expect.poll(() => reads.length).toBeGreaterThan(settled);
    expect(reads.slice(settled)).toEqual(reads.slice(settled).map(() => ({ state: 'OUT', background: true })));
    await expect(rows).toHaveCount(1);
    await expect(page).toHaveURL(/state=OUT/);

    // "Menipis" whose read fails keeps the rows shown; "Muat ulang" then reads "Menipis", never
    // the filter shown (review finding R-15).
    lowFails = true;
    await page.getByRole('button', { name: 'Menipis', exact: true }).click();
    await expect(page.getByText('Data belum diperbarui')).toBeVisible();
    await expect(rows).toHaveCount(1);
    lowFails = false;
    await page.getByRole('button', { name: 'Muat ulang' }).first().click();
    await expect(page).toHaveURL(/state=LOW/);
    await expect(rows).toHaveCount(3);
    await expect(page.getByRole('button', { name: 'Menipis', exact: true, pressed: true })).toBeVisible();
    await expect(page.getByText('Data belum diperbarui')).toHaveCount(0);
});

test('the badge of "Stok" shows the number of RD-03 with its name (UXI-006)', async ({ page }) => {
    const context = page.context();

    await page.route(SHELL_DATA, (route) => route.fulfill({ json: { unresolvedRequests: 0, stockAttention: 7, asOf: '2026-11-02T03:00:00.000000Z' } }));
    await signedIn(context, page, 'Hendra');
    // The shell's page of the Owner's home: its content holds no rows that other tests of the
    // shared database add, so the checks below measure the badge and the shell alone.
    await page.goto(HOME);

    const stock = page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link', { name: /^Stok/ });
    await expect(stock).toContainText('7');
    await expect(stock).toHaveAccessibleName(/7 produk perlu restok/);
    await checked(page);
});
