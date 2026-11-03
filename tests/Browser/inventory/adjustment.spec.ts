import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { envelopeOf, injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-410 "Sesuaikan stok" and its entries (AC-024-01, AC-024-04; COPY_DECK section 9.11;
// NAVIGATION_CONTRACTS UXP-410; INTERACTION_INVENTORY UXI-217, UXI-221 and UXI-268 to UXI-276;
// UI_STATES section 6; INTERACTION_CONTRACTS section 1.5; BR-INV-31 to BR-INV-33; WF-22; suites
// UX01, UX02, INV03, AUTH03; TASK-024, L5): the Owner opens it from a row of UXP-401 or from
// UXP-402 filtered to a product, with that product as its first line; a decrease and an increase
// with its cost, each reviewed with the stock before, the change and the stock after; the password
// asked once before the first request; the result at UXP-402 filtered to the adjusted products;
// insufficient stock refused with the stock available; an answer lost and sent again; the draft
// never in the browser's storage; Operations sees no entry. These tests sign in the people of Toko
// Contoh, so they run one at a time (playwright.config.ts). Every product of a test gets new random
// texts, because the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/** RC-09's route (ROUTE_CONTRACTS RC-09). */
const CONFIRM_ROUTE = '**/commands/adjustment.confirm';

function letters(length: number): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)), (byte) => String.fromCharCode(97 + (byte % 26))).join('');
}

/** A code for a run, of digits made at run time. */
function digits(): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(12)), (byte) => String(byte % 10)).join('');
}

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

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

/** The product's stock, received through UXP-405 at a known cost. */
async function received(page: Page, product: { name: string; barcode: string }, quantity: string): Promise<void> {
    await page.goto('/stock/receivings/new');
    await scan(page, product.barcode);
    const line = page.getByRole('list', { name: 'Catat barang masuk' }).getByRole('listitem').filter({ hasText: product.name });
    await expect(line).toHaveCount(1);
    await line.getByLabel('Jumlah', { exact: true }).fill(quantity);
    await line.getByLabel('Biaya', { exact: true }).fill('5000');
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await Promise.all([
        page.waitForURL((url) => /^\/stock\/receivings\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT }),
        page.getByRole('dialog', { name: 'Tinjau barang masuk' }).getByRole('button', { name: /^Konfirmasi barang masuk/ }).click(),
    ]);
}

/** The line of the adjustment's draft for a product. */
function line(page: Page, name: string) {
    return page.getByRole('list', { name: 'Sesuaikan stok' }).getByRole('listitem').filter({ hasText: name });
}

/** The three mandatory fields of UXI-270. */
async function fields(page: Page): Promise<void> {
    await page.getByLabel('Alasan', { exact: true }).fill('Hitung fisik akhir bulan');
    await page.getByLabel('Referensi hitung', { exact: true }).fill('LH-11');
    await page.getByLabel('Referensi', { exact: true }).fill('BA-11');
}

/** The recent-authentication dialog, the first time in a sign-in session (UXS-11). */
async function confirmPassword(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
}

test('the Owner adjusts stock from a row of Stok and from Riwayat stok, with a review and the password once', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    const password = await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '5');

    // UXI-217: the row's overflow "Sesuaikan stok" on UXP-401, the Owner's.
    await page.goto(`/stock?q=${product.sku}`);
    await page.getByRole('button', { name: `Tindakan lain ${product.name}` }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/adjustments/new'), page.getByRole('menuitem', { name: 'Sesuaikan stok' }).click()]);
    expect(new URL(page.url()).search).toBe(`?product=${String(product.id)}&from_page=stock`);

    // UXP-410: the intro, the way back to the opener, and the product as the first line.
    await expect(page.getByRole('heading', { level: 1, name: 'Sesuaikan stok' })).toBeVisible();
    await expect(page.getByText('Samakan stok tercatat dengan hasil hitung fisik.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Stok' })).toBeVisible();
    await expect(line(page, product.name)).toHaveCount(1);
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await expect(page.getByText('Lengkapi baris, alasan, referensi hitung, dan referensi. Paling banyak 200 baris.')).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await checked(page);

    // A decrease of two, with the reason, the count and the reference (UXI-269, UXI-270).
    await line(page, product.name).getByRole('radio', { name: 'Kurangi' }).click();
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('2');
    await expect(line(page, product.name).getByLabel('Biaya', { exact: true })).toHaveCount(0);
    await fields(page);
    await checked(page);

    // The draft is never in the browser's storage (SEC-CH-05).
    expect(await page.evaluate(() => JSON.stringify([window.sessionStorage, window.localStorage]))).not.toContain('LH-11');

    // The review: the stock before, the change and the stock after (UXI-272), then the password once (UXS-11).
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau penyesuaian stok' });
    await expect(review.getByText('Hitung fisik akhir bulan')).toBeVisible();
    await expect(review.getByText('5 pcs')).toBeVisible();
    await expect(review.getByText('−2 pcs')).toBeVisible();
    await expect(review.getByText('3 pcs')).toBeVisible();
    await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
    await checked(page);
    await review.getByRole('button', { name: /^Konfirmasi penyesuaian/ }).click();
    await expect(page.getByRole('dialog', { name: 'Konfirmasi kata sandi' })).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), confirmPassword(page, password)]);

    // The result: UXP-402 filtered to the adjusted product, with no way back of its own (UXI-223).
    expect(new URL(page.url()).search).toBe(`?product=${String(product.id)}`);
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat stok' })).toBeVisible();
    await expect(page.getByText('Stok disesuaikan.')).toBeVisible();
    await expect(page.getByRole('link', { name: /^Kembali ke/ })).toHaveCount(0);
    const rows = page.getByRole('main').getByRole('table').locator('tbody tr');
    await expect(rows.first()).toContainText('Penyesuaian stok');
    await expect(rows.first()).toContainText('−2 pcs');
    await checked(page);

    // UXI-221: "Sesuaikan stok" on UXP-402 with the product filtered, then an increase with its cost.
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/adjustments/new'), page.getByRole('link', { name: 'Sesuaikan stok' }).click()]);
    expect(new URL(page.url()).search).toBe(`?product=${String(product.id)}&from_page=history`);
    await expect(page.getByRole('link', { name: 'Kembali ke Riwayat stok' })).toBeVisible();
    await line(page, product.name).getByRole('radio', { name: 'Tambah' }).click();
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('4');
    await line(page, product.name).getByLabel('Biaya', { exact: true }).fill('8000');
    await fields(page);
    await checked(page);
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const again = page.getByRole('dialog', { name: 'Tinjau penyesuaian stok' });
    await expect(again.getByText('+4 pcs')).toBeVisible();
    await expect(again.getByText('7 pcs')).toBeVisible();
    // The recent authentication still holds: no second password.
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), again.getByRole('button', { name: /^Konfirmasi penyesuaian/ }).click()]);
    await expect(page.getByText('Stok disesuaikan.')).toBeVisible();
    await expect(rows.first()).toContainText('+4 pcs');
    await expect(rows).toHaveCount(3);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('while an adjustment is uncertain, the entries of UXP-401 and UXP-402 are unavailable with their reason', async ({ browser }) => {
    // UXI-217, UXI-221: disabled with the reason (UXS-13) while this user holds an unresolved
    // receiving, manual issue or adjustment request, with the way to UXP-006 (NC-13; review finding R-03).
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    const password = await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '3');

    // An adjustment whose answer is lost: UXS-14, its reference kept in this browser.
    await page.goto(`/stock/adjustments/new?product=${String(product.id)}&from_page=stock`);
    await line(page, product.name).getByRole('radio', { name: 'Kurangi' }).click();
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('1');
    await fields(page);
    await page.route(CONFIRM_ROUTE, (route) => route.abort('failed'));
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await page.getByRole('dialog', { name: 'Tinjau penyesuaian stok' }).getByRole('button', { name: /^Konfirmasi penyesuaian/ }).click();
    await confirmPassword(page, password);
    await expect(page.getByText('Status belum pasti', { exact: true })).toBeVisible();

    // In another tab of the same browser: the row's overflow says why, and leads to UXP-006.
    const other = await context.newPage();
    const otherWatched = await watch(other);
    await other.goto(`/stock?q=${product.sku}`);
    await other.getByRole('button', { name: `Tindakan lain ${product.name}` }).click();
    const item = other.getByRole('menuitem', { name: 'Sesuaikan stok', exact: true });
    await expect(item).toHaveAttribute('aria-disabled', 'true');
    await expect(item).toHaveAccessibleDescription('Ada barang masuk, barang keluar, atau penyesuaian yang statusnya belum pasti. Cek statusnya dulu.');
    await expect(other.getByRole('menuitem', { name: 'Buka Permintaan belum pasti' })).toHaveAttribute('href', '/requests');
    await item.click();
    expect(new URL(other.url()).pathname).toBe('/stock');
    await checked(other);
    await other.keyboard.press('Escape');

    // UXP-402 filtered to the product: "Sesuaikan stok" disabled with its reason and the link.
    await other.goto(`/stock/history?product=${String(product.id)}`);
    const button = other.getByRole('button', { name: 'Sesuaikan stok' });
    await expect(button).toHaveAttribute('aria-disabled', 'true');
    await expect(other.getByRole('link', { name: 'Buka Permintaan belum pasti' })).toBeVisible();
    await checked(other);

    // Resolved by "Kirim ulang", the entries open UXP-410 again.
    await page.unroute(CONFIRM_ROUTE);
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), page.getByRole('button', { name: 'Kirim ulang' }).click()]);
    await other.reload();
    await expect(other.getByRole('link', { name: 'Sesuaikan stok' })).toHaveAttribute('href', `/stock/adjustments/new?product=${String(product.id)}&from_page=history`);

    expect(injected(watched.problems)).toEqual([]);
    expect(injected(otherWatched.problems)).toEqual([]);
    await context.close();
});

test('Operations sees no entry to an adjustment and its page is refused', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[1] ?? WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const product = await newProduct(page);
    await received(page, product, '2');

    await page.goto(`/stock?q=${product.sku}`);
    await expect(page.getByRole('main').getByRole('table').locator('tbody tr').filter({ hasText: product.name })).toHaveCount(1);
    await expect(page.getByRole('button', { name: `Tindakan lain ${product.name}` })).toHaveCount(0);
    await page.goto(`/stock/history?product=${String(product.id)}`);
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat stok' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sesuaikan stok' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Sesuaikan stok' })).toHaveCount(0);

    const refused = await page.goto(`/stock/adjustments/new?product=${String(product.id)}`);
    expect(refused?.status()).toBe(403);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

// UXP-410 with its review at the 200 % zoom of 1280 × 800 and at 320 × 640, beside the 1440 px of
// the first test (EXC-53; TS-25; UX01, UX02).
for (const width of [WIDTHS[4], WIDTHS[7]]) {
    test(`the adjustment page and its review fit ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);

        const password = await signedIn(context, page, 'Hendra');
        const product = await newProduct(page);
        await received(page, product, '3');

        await page.goto(`/stock/adjustments/new?product=${String(product.id)}&from_page=stock`);
        await expect(page.getByRole('heading', { level: 1, name: 'Sesuaikan stok' })).toBeVisible();
        await line(page, product.name).getByRole('radio', { name: 'Tambah' }).click();
        await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('1');
        await line(page, product.name).getByRole('button', { name: 'Biaya belum diketahui' }).click();
        await fields(page);
        await checked(page);
        await page.getByRole('button', { name: 'Tinjau' }).click();
        const review = page.getByRole('dialog', { name: 'Tinjau penyesuaian stok' });
        await expect(review.getByText('4 pcs')).toBeVisible();
        await checked(page);
        await review.getByRole('button', { name: /^Konfirmasi penyesuaian/ }).click();
        await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), confirmPassword(page, password)]);
        await expect(page.getByText('Stok disesuaikan.')).toBeVisible();
        await checked(page);

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    });
}

test('insufficient stock is refused with the stock available, an answer lost freezes the draft, and "Kirim ulang" sends the same intent', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    const password = await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '1');

    await page.goto(`/stock/adjustments/new?product=${String(product.id)}&from_page=stock`);
    await line(page, product.name).getByRole('radio', { name: 'Kurangi' }).click();
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('3');
    await fields(page);

    // Enter in a field sends nothing (UXA-10).
    await page.getByLabel('Referensi', { exact: true }).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // BR-INV-33: the review says that the stock does not suffice, and the server refuses it (UXS-31).
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau penyesuaian stok' });
    await expect(review.getByText('Tersedia 1 pcs')).toBeVisible();
    await review.getByRole('button', { name: /^Konfirmasi penyesuaian/ }).click();
    await confirmPassword(page, password);
    await expect(page.getByText('Stok tidak cukup', { exact: true }).first()).toBeVisible();
    await expect(line(page, product.name).getByText('Tersedia 1 pcs')).toBeVisible();
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toHaveValue('3');
    expect(new URL(page.url()).pathname).toBe('/stock/adjustments/new');
    await checked(page);

    // Leaving the draft asks first (UXS-45).
    await page.getByRole('link', { name: 'Batal' }).click();
    const leave = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
    await leave.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    await expect(line(page, product.name)).toHaveCount(1);

    // UXS-14: the draft frozen as it was sent; "Kirim ulang" sends the same request, which commits.
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('1');
    const sent: ReturnType<typeof envelopeOf>[] = [];
    await page.route(CONFIRM_ROUTE, async (route) => {
        sent.push(envelopeOf(route.request()));
        await (sent.length === 1 ? route.abort('failed') : route.continue());
    });
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await page.getByRole('dialog', { name: 'Tinjau penyesuaian stok' }).getByRole('button', { name: /^Konfirmasi penyesuaian/ }).click();
    await expect(page.getByText('Status belum pasti', { exact: true })).toBeVisible();
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Alasan', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Cari atau scan produk', { exact: true })).toBeDisabled();
    await checked(page);

    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), page.getByRole('button', { name: 'Kirim ulang' }).click()]);
    await expect(page.getByText('Stok disesuaikan.')).toBeVisible();
    expect(sent).toHaveLength(2);
    expect(sent[1]).toEqual(sent[0]);
    expect(sent[0]?.payload).toMatchObject({ reason: 'Hitung fisik akhir bulan', countReference: 'LH-11', reference: 'BA-11', lines: [{ productId: product.id, direction: 'DECREASE', quantity: '1' }] });

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
