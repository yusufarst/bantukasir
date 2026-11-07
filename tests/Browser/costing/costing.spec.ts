import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands, refusal } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-503 "Biaya perlu dilengkapi", UXP-504 "Lengkapi biaya" or "Koreksi biaya" and UXP-505 "Riwayat
// biaya" with their entries (AC-025-02, AC-025-05, AC-025-06; COPY_DECK sections 10.4 to 10.6;
// NAVIGATION_CONTRACTS UXP-503 to UXP-505; INTERACTION_INVENTORY UXI-176, UXI-249 and UXI-313 to
// UXI-325; UI_STATES UXS-24 to UXS-26 and UXS-40; INTERACTION_CONTRACTS section 1.5; D-047; WF-26;
// suites UX01, UX02, FIN02, AUTH03; TASK-025, L5): the Owner completes an UNKNOWN cost from the
// worklist and corrects a known one from its receiving, each reviewed with the old value and the new
// one, the password asked once before the first request, the success back on the page that opened
// it; the draft never in the browser's storage; the cost history with its value, average and
// corrections; a refused replay said with nothing changed; OPERATIONS_ADMIN sees the corrected cost,
// "Dikoreksi Pemilik" and the cost at confirmation, never the reason, and no cost page. These tests
// sign in the people of Toko Contoh, so they run one at a time (playwright.config.ts). Every product
// of a test gets new random texts, because the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/** RC-15's route (ROUTE_CONTRACTS RC-15). */
const CONFIRM_ROUTE = '**/commands/cost_correction.confirm';

/** RP-503. */
const WORKLIST = '/reports/costs-to-complete';

function letters(length: number): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)), (byte) => String.fromCharCode(97 + (byte % 26))).join('');
}

/** A code for a run, of digits made at run time. */
function digits(): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(12)), (byte) => String(byte % 10)).join('');
}

/**
 * Waits until no transition or animation of a finite length runs in the page, so that axe and the
 * measurements read the colours that each control settles in, never a step between two of its looks.
 * A button that leaves its disabled look — "Tinjau" once a refusal ends its request — moves its text
 * and its fill for --dur-hover (140 ms), and the contrast of those steps is not one the page shows at
 * rest (D-124). The endless animations of a spinner and a skeleton have no end and are not waited for.
 */
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

    // Each violation with axe's own summary, which for a contrast gives the colours and the ratio it measured (D-124).
    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => `${node.target.join(' ')} ${node.failureSummary ?? ''}`).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

/** Signed in as this person; the password, for the recent authentication. */
async function signedIn(context: BrowserContext, page: Page, person: Person): Promise<string> {
    const accounts = await resetPeople(context, [person]);

    await prepareCommands(context, [person]);
    await signIn(page, accounts[person]);

    return accounts[person].password;
}

/** A new product with a barcode, in a new category, through UXP-307 and UXP-303. */
async function newProduct(page: Page): Promise<{ id: number; name: string; sku: string; barcode: string }> {
    const category = `Kategori ${letters(10)}`;
    const product = { name: `Z${letters(14)}`, sku: `SKU-${letters(8)}`, barcode: digits() };

    await page.goto('/categories');
    await page.getByRole('button', { name: 'Tambah kategori' }).click();
    await page.getByLabel('Nama kategori', { exact: true }).fill(category);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText(`Kategori ${category} ditambahkan.`)).toBeVisible();

    await page.goto('/products/new');
    await page.getByLabel('Nama', { exact: true }).fill(product.name);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Lanjut isi data' }).click();
    await page.getByLabel(/^Barcode pabrik/).fill(product.barcode);
    await page.getByLabel('SKU', { exact: true }).fill(product.sku);
    await page.getByLabel('Kategori', { exact: true }).selectOption({ label: category });
    await page.getByLabel('Satuan', { exact: true }).fill('pcs');
    await page.getByLabel('Harga jual', { exact: true }).fill('7500');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT });

    return { id: Number(new URL(page.url()).pathname.split('/').pop()), ...product };
}

/** Scans a code into "Cari atau scan produk": the code, then the Enter that ends it. */
async function scan(page: Page, code: string): Promise<void> {
    const field = page.getByLabel('Cari atau scan produk', { exact: true });

    await field.fill(code);
    await field.press('Enter');
}

/** The product received through UXP-405, at this cost or, with none, at an UNKNOWN one; the page is left on its UXP-406. */
async function received(page: Page, product: { name: string; barcode: string }, quantity: string, cost: string | null): Promise<void> {
    await page.goto('/stock/receivings/new');
    await scan(page, product.barcode);
    const line = page.getByRole('list', { name: 'Catat barang masuk' }).getByRole('listitem').filter({ hasText: product.name });
    await expect(line).toHaveCount(1);
    await line.getByLabel('Jumlah', { exact: true }).fill(quantity);

    if (cost === null) {
        await line.getByRole('button', { name: 'Biaya belum diketahui' }).click();
    } else {
        await line.getByLabel('Biaya', { exact: true }).fill(cost);
    }

    await page.getByLabel('Cari atau scan produk', { exact: true }).fill('');
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await Promise.all([
        page.waitForURL((url) => /^\/stock\/receivings\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT }),
        page.getByRole('dialog', { name: 'Tinjau barang masuk' }).getByRole('button', { name: /^Konfirmasi barang masuk/ }).click(),
    ]);
}

/** The recent-authentication dialog, the first time in a sign-in session (UXS-11). */
async function confirmPassword(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
}

/** The address of "Lengkapi biaya" of the product's row on UXP-503 filtered to it. */
async function completionOf(page: Page, product: { id: number; name: string }): Promise<string> {
    await page.goto(`${WORKLIST}?product=${String(product.id)}`);
    const href = await page.getByRole('link', { name: `Lengkapi biaya ${product.name}` }).getAttribute('href');

    expect(href).toMatch(/^\/costs\/\d+\/correct\?from_page=worklist$/);

    return href ?? '';
}

test('the Owner completes an unknown cost from the worklist, with a review and the password once, and reads its cost history', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    const password = await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '4', null);

    // UXP-406: the line's cost UNKNOWN (UXS-24) with "Lengkapi biaya" (UXI-249).
    const line = page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: product.name });
    await expect(line).toContainText('Biaya perlu dilengkapi pemilik');
    await expect(line.getByRole('link', { name: `Lengkapi biaya ${product.name}` })).toBeVisible();

    // UXP-505 before the completion: the value UNKNOWN, never Rp0, and "Data biaya belum lengkap" (UXS-25).
    await page.goto(`/products/${String(product.id)}/cost-history?from_page=product`);
    const incomplete = page.getByRole('main').getByRole('link', { name: 'Biaya perlu dilengkapi', exact: true });
    await expect(page.getByText('Data biaya belum lengkap')).toBeVisible();
    await expect(incomplete).toHaveAttribute('href', `${WORKLIST}?product=${String(product.id)}`);
    await expect(page.getByText('Rp0', { exact: true })).toHaveCount(0);
    await checked(page);

    // UXP-503 filtered to the product (UXI-313): its row, UNKNOWN, with its two ways (UXI-314, UXI-315).
    await Promise.all([page.waitForURL((url) => url.pathname === WORKLIST), incomplete.click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Biaya perlu dilengkapi' })).toBeVisible();
    expect(new URL(page.url()).search).toBe(`?product=${String(product.id)}`);
    await expect(page.getByLabel('Produk', { exact: true })).toHaveValue(product.name);
    const rows = page.getByRole('main').getByRole('table').locator('tbody tr');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(product.name);
    await expect(rows.first()).toContainText('Biaya perlu dilengkapi pemilik');
    await expect(rows.first().getByRole('link', { name: `Riwayat biaya ${product.name}` })).toHaveAttribute('href', `/products/${String(product.id)}/cost-history?from_page=worklist`);
    await checked(page);
    await Promise.all([page.waitForURL((url) => /^\/costs\/\d+\/correct$/.test(url.pathname)), rows.first().getByRole('link', { name: `Lengkapi biaya ${product.name}` }).click()]);
    expect(new URL(page.url()).search).toBe('?from_page=worklist');

    // UXP-504: the source as it is now, the way back to its opener with its filter, and the draft.
    await expect(page.getByRole('heading', { level: 1, name: 'Lengkapi biaya' })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: 'Biaya sekarang: Biaya perlu dilengkapi pemilik' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Biaya perlu dilengkapi' })).toHaveAttribute('href', `${WORKLIST}?product=${String(product.id)}`);
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByText('Isi biaya baru dan alasan.')).toBeVisible();
    await checked(page);

    await page.getByLabel('Biaya baru', { exact: true }).fill('8000');
    await expect(page.getByLabel('Biaya baru', { exact: true })).toHaveValue('8.000');
    await page.getByLabel('Alasan', { exact: true }).fill('Nota pemasok ditemukan');

    // Enter in a field sends nothing (UXA-10); the draft is never in the browser's storage (NC-10, SEC-CH-05).
    await page.getByLabel('Alasan', { exact: true }).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(await page.evaluate(() => JSON.stringify([window.sessionStorage, window.localStorage]))).not.toContain('Nota pemasok ditemukan');

    // The review: the old value and the new one (UXI-320), then the password once (UXS-11).
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau koreksi biaya' });
    await expect(review.getByText('Biaya lama Biaya perlu dilengkapi pemilik → biaya baru Rp8.000.', { exact: false })).toBeVisible();
    await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
    await checked(page);
    await review.getByRole('button', { name: /^Konfirmasi koreksi/ }).click();
    await expect(page.getByRole('dialog', { name: 'Konfirmasi kata sandi' })).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === WORKLIST), confirmPassword(page, password)]);

    // The success, back on the worklist with its filter, which no longer holds the source (BR-COST-22).
    expect(new URL(page.url()).search).toBe(`?product=${String(product.id)}`);
    await expect(page.getByText('Biaya disimpan. Laporan sudah dihitung ulang.')).toBeVisible();
    await expect(page.getByRole('main').getByRole('table')).toHaveCount(0);
    await checked(page);

    // UXP-505 from the product's page (UXI-176): the value and the average now, and the completion.
    await page.goto(`/products/${String(product.id)}`);
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${String(product.id)}/cost-history`), page.getByRole('link', { name: 'Riwayat biaya', exact: true }).click()]);
    expect(new URL(page.url()).search).toBe('?from_page=product');
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat biaya' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Detail produk' })).toHaveAttribute('href', `/products/${String(product.id)}`);
    await expect(page.getByText('Rp8.000', { exact: true })).toBeVisible();
    await expect(page.getByText('Rp2.000', { exact: true })).toBeVisible();
    await expect(page.getByText('Data biaya belum lengkap')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 2, name: 'Koreksi', exact: true })).toBeVisible();
    await expect(page.getByText('alasan: Nota pemasok ditemukan', { exact: false })).toBeVisible();
    await expect(page.getByRole('link', { name: /^Koreksi biaya / })).toHaveCount(1);
    await checked(page);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the Owner corrects Rp5.000 to Rp5.500 from its receiving, and Operations sees Rp5.500, the mark and Rp5.000, never the reason', async ({ browser }) => {
    // AC-025-05, D-047, UXS-26.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    const password = await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '1', '5000');
    const detail = new URL(page.url()).pathname;
    const line = page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: product.name });

    // UXI-249: "Koreksi biaya" of the line, whose way back is the document.
    await Promise.all([page.waitForURL((url) => /^\/costs\/\d+\/correct$/.test(url.pathname)), line.getByRole('link', { name: `Koreksi biaya ${product.name}` }).click()]);
    expect(new URL(page.url()).search).toBe('?from_page=receiving');
    await expect(page.getByRole('heading', { level: 1, name: 'Koreksi biaya' })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: 'Biaya sekarang: Rp5.000' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Detail barang masuk' })).toHaveAttribute('href', detail);
    await page.getByLabel('Biaya baru', { exact: true }).fill('5500');
    await page.getByLabel('Alasan', { exact: true }).fill('Nota susulan pemasok');
    await checked(page);
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau koreksi biaya' });
    await expect(review.getByText('Biaya lama Rp5.000 → biaya baru Rp5.500.', { exact: false })).toBeVisible();
    await review.getByRole('button', { name: /^Konfirmasi koreksi/ }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === detail), confirmPassword(page, password)]);

    // UXP-406 for the Owner: the cost now, "Dikoreksi Pemilik" (UXS-26), the cost at confirmation, the history.
    await expect(page.getByText('Biaya disimpan. Laporan sudah dihitung ulang.')).toBeVisible();
    await expect(line).toContainText('Biaya sekarang: Rp5.500');
    await expect(line).toContainText('Dikoreksi Pemilik');
    await expect(line).toContainText('Biaya saat dikonfirmasi: Rp5.000');
    await expect(page.getByRole('heading', { level: 2, name: 'Riwayat koreksi' })).toBeVisible();
    await expect(page.getByText('alasan: Nota susulan pemasok', { exact: false })).toBeVisible();
    await checked(page);

    // Operations sees Rp5.500, "Dikoreksi Pemilik" and Rp5.000, never the reason, the history or a way to correct.
    const staff = await contextAt(browser, WIDTHS[0]!);
    const other = await staff.newPage();
    const otherWatched = await watch(other);
    await signedIn(staff, other, 'Agus');
    await other.goto(detail);
    const seen = other.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: product.name });
    await expect(seen).toContainText('Biaya sekarang: Rp5.500');
    await expect(seen).toContainText('Dikoreksi Pemilik');
    await expect(seen).toContainText('Biaya saat dikonfirmasi: Rp5.000');
    await expect(other.getByText('Nota susulan pemasok', { exact: false })).toHaveCount(0);
    await expect(other.getByRole('heading', { name: 'Riwayat koreksi' })).toHaveCount(0);
    await expect(other.getByRole('link', { name: /^Koreksi biaya/ })).toHaveCount(0);
    await checked(other);

    // The cost pages are the Owner's alone (CAP-19, CAP-20).
    for (const path of [WORKLIST, `/products/${String(product.id)}/cost-history`]) {
        const refused = await other.goto(path);
        expect(refused?.status()).toBe(403);
    }

    expect(injected(watched.problems)).toEqual([]);
    // The refusals themselves are the problems the browser reports.
    expect(injected(otherWatched.problems).filter((problem) => !problem.includes('status of 403'))).toEqual([]);
    await staff.close();
    await context.close();
});

// The worklist, the correction with its review and a refused replay, and the cost history at the
// 200 % zoom of 1280 × 800 and at 320 × 640, beside the 1440 px of the first tests (EXC-53; TS-25;
// UX01, UX02).
for (const width of [WIDTHS[4], WIDTHS[7]]) {
    test(`the cost pages and the review fit ${width?.name ?? ''}, and a refused replay changes nothing`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);

        const password = await signedIn(context, page, 'Hendra');
        const product = await newProduct(page);
        await received(page, product, '3', null);
        const completion = await completionOf(page, product);
        await expect(page.getByRole('heading', { level: 1, name: 'Biaya perlu dilengkapi' })).toBeVisible();
        // Its rows are cards here, never a table that scrolls sideways in a region of its own (UXA-12; review finding R-03).
        await expect(page.getByRole('region', { name: 'Biaya perlu dilengkapi', exact: true })).toHaveCount(0);
        expect(await page.getByRole('main').locator('table').evaluate((table) => table.scrollWidth <= table.clientWidth)).toBe(true);
        await checked(page);

        await page.goto(completion);
        await expect(page.getByRole('heading', { level: 1, name: 'Lengkapi biaya' })).toBeVisible();
        await page.getByLabel('Biaya baru', { exact: true }).fill('0');
        // An explicit zero asks for its evidence or reason (BR-COST-04).
        await page.getByLabel('Bukti atau alasan biaya nol', { exact: true }).fill('Hadiah pemasok');
        await page.getByLabel('Alasan', { exact: true }).fill('Barang hadiah');
        await checked(page);

        // REPLAY_REJECTED: nothing changed, reporting marked incomplete (UXS-40), the draft kept.
        await page.route(CONFIRM_ROUTE, (route) => route.fulfill({ status: 422, json: { ...refusal('REPLAY_REJECTED'), messageKey: 'ux.state.replay_rejected.cost' } }));
        await page.getByRole('button', { name: 'Tinjau' }).click();
        const review = page.getByRole('dialog', { name: 'Tinjau koreksi biaya' });
        await expect(review.getByText('biaya baru Rp0.', { exact: false })).toBeVisible();
        await checked(page);
        await review.getByRole('button', { name: /^Konfirmasi koreksi/ }).click();
        await confirmPassword(page, password);
        await expect(page.getByText('Koreksi tidak dapat dihitung ulang', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Alasan', { exact: true })).toHaveValue('Barang hadiah');
        expect(new URL(page.url()).pathname).toMatch(/^\/costs\/\d+\/correct$/);
        // The refusal ended the request, so "Tinjau" is available again; it leaves its disabled look before the check (D-124).
        await expect(page.getByRole('button', { name: 'Tinjau' })).toBeEnabled();
        await checked(page);

        // Sent again to the server, it commits and leads back to the worklist.
        await page.unroute(CONFIRM_ROUTE);
        await page.getByRole('button', { name: 'Tinjau' }).click();
        await Promise.all([page.waitForURL((url) => url.pathname === WORKLIST), page.getByRole('dialog', { name: 'Tinjau koreksi biaya' }).getByRole('button', { name: /^Konfirmasi koreksi/ }).click()]);
        await expect(page.getByText('Biaya disimpan. Laporan sudah dihitung ulang.')).toBeVisible();

        // UXP-505 with the zero and its evidence (BR-COST-04).
        await page.goto(`/products/${String(product.id)}/cost-history?from_page=worklist`);
        await expect(page.getByRole('heading', { level: 1, name: 'Riwayat biaya' })).toBeVisible();
        await expect(page.getByText('Bukti atau alasan biaya nol: Hadiah pemasok')).toBeVisible();
        await checked(page);

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    });
}
