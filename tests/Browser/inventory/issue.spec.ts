import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { envelopeOf, injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-407 to UXP-409 (AC-023-06; COPY_DECK sections 9.8 to 9.10; NAVIGATION_CONTRACTS UXP-407 to
// UXP-409; INTERACTION_INVENTORY UXI-015 and UXI-252 to UXI-267; UI_STATES section 6; BR-INV-18 to
// BR-INV-22; D-018; suites UX01, UX02, INV02; TASK-023, L5): "Barang keluar" in the Stok
// sub-navigation, the Owner's explicit entry; a manual issue with its scans, its reason and note,
// and a review of the stock before, the change and the stock after; the issue with its lines and
// their movements, whose source and reason name it; insufficient stock refused with the stock
// available, never overridden; an answer lost and sent again; the draft never in the browser's
// storage. These tests sign in the people of Toko Contoh, so they run one at a time
// (playwright.config.ts). Every product of a test gets new random texts, because the local
// database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/** RC-08's route (ROUTE_CONTRACTS RC-08). */
const CONFIRM_ROUTE = '**/commands/manual_issue.confirm';

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

async function signedIn(context: BrowserContext, page: Page, person: Person): Promise<void> {
    const accounts = await resetPeople(context, [person]);

    await prepareCommands(context, [person]);
    await signIn(page, accounts[person]);
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

/** The line of the issue's draft for a product. */
function line(page: Page, name: string) {
    return page.getByRole('list', { name: 'Catat barang keluar' }).getByRole('listitem').filter({ hasText: name });
}

/** UXP-408 with a draft of one line of the product, its quantity and a reason. */
async function draftOf(page: Page, product: { name: string; barcode: string }, quantity: string): Promise<void> {
    await page.goto('/stock/issues/new');
    await scan(page, product.barcode);
    await expect(line(page, product.name)).toHaveCount(1);
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill(quantity);
    await page.getByLabel('Alasan', { exact: true }).selectOption({ label: 'Rusak' });
}

/** "Tinjau", then the review's confirmation, which sends RC-08. */
async function sendReview(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await page.getByRole('dialog', { name: 'Tinjau barang keluar' }).getByRole('button', { name: /^Konfirmasi barang keluar/ }).click();
}

test('an Operations admin records a manual issue with scans, a reason and a review of the stock, and its issue and movement name it', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const product = await newProduct(page);
    await received(page, product, '5');

    // UXP-407 from the sub-navigation (UXI-015), then "Catat barang keluar" (UXI-253).
    await page.goto('/stock/issues');
    await expect(page.getByRole('heading', { level: 1, name: 'Barang keluar' })).toBeVisible();
    const sub = page.getByRole('navigation', { name: 'Submenu Stok' });
    await expect(sub.getByRole('link')).toHaveText(['Stok', 'Riwayat stok', 'Barang masuk', 'Barang keluar']);
    await expect(sub.getByRole('link', { name: 'Barang keluar', exact: true })).toHaveAttribute('aria-current', 'page');
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/issues/new'), page.getByRole('link', { name: 'Catat barang keluar' }).click()]);

    // UXP-408: the intro, the way back, and "Tinjau" waiting for a line and a reason (UXI-259).
    await expect(page.getByRole('heading', { level: 1, name: 'Catat barang keluar' })).toBeVisible();
    await expect(page.getByText('Catat barang yang keluar bukan karena penjualan.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Barang keluar' })).toBeVisible();
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await expect(page.getByText('Tambahkan minimal satu baris yang lengkap dan pilih alasan. Paling banyak 200 baris.')).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await checked(page);

    // A scan adds a line of one base unit; a second scan one more (UXI-256).
    await scan(page, product.barcode);
    await expect(line(page, product.name)).toHaveCount(1);
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toHaveValue('1');
    await scan(page, product.barcode);
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toHaveValue('2');

    // The reason, one of the five, and the optional note (UXI-258).
    await expect(page.getByLabel('Alasan', { exact: true }).locator('option')).toHaveText(['', 'Rusak', 'Pemakaian internal', 'Hilang', 'Sampel', 'Lainnya']);
    await page.getByLabel('Alasan', { exact: true }).selectOption({ label: 'Rusak' });
    await page.getByLabel(/^Catatan/).fill('Pecah saat dipindah');
    await checked(page);

    // The draft is never in the browser's storage (SEC-CH-05).
    expect(await page.evaluate(() => JSON.stringify([window.sessionStorage, window.localStorage]))).not.toContain(product.name);

    // The review: the stock before, the change and the stock after (UXI-260; BR-INV-19).
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau barang keluar' });
    await expect(review.getByText('Rusak', { exact: true })).toBeVisible();
    await expect(review.getByText('5 pcs')).toBeVisible();
    await expect(review.getByText('−2 pcs')).toBeVisible();
    await expect(review.getByText('3 pcs')).toBeVisible();
    await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
    await checked(page);
    await Promise.all([page.waitForURL((url) => /^\/stock\/issues\/\d+$/.test(url.pathname)), review.getByRole('button', { name: /^Konfirmasi barang keluar/ }).click()]);

    // UXP-409: the message, the reason and note, the line and the way back (UXI-267).
    await expect(page.getByRole('heading', { level: 1, name: 'Detail barang keluar' })).toBeVisible();
    await expect(page.getByText('Barang keluar tercatat.')).toBeVisible();
    await expect(page.getByText('Pecah saat dipindah')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Barang keluar' })).toBeVisible();
    await expect(page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: product.name })).toContainText('2 pcs');
    await checked(page);
    const detail = new URL(page.url()).pathname;

    // The line's movement: its reason, and its source that leads back to the issue (UXI-224, UXI-265).
    await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+$/.test(url.pathname)), page.getByRole('link', { name: `Detail mutasi stok ${product.name}` }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail mutasi stok' })).toBeVisible();
    await expect(page.getByText('−2 pcs')).toBeVisible();
    await expect(page.getByText('Rusak', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: /^Barang keluar / })).toHaveAttribute('href', detail);
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === detail), page.getByRole('link', { name: 'Kembali ke Detail barang keluar' }).click()]);

    // The list holds the issue, newest first, and its filter "Alasan" keeps it.
    await page.goto('/stock/issues');
    await page.getByLabel('Alasan', { exact: true }).selectOption({ label: 'Rusak' });
    await expect(page).toHaveURL(/reason=DAMAGED/);
    await expect(page.getByRole('main').getByRole('table').locator('tbody tr').first()).toContainText('Rusak');
    await checked(page);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the Owner finds Barang keluar in the sub-navigation, and insufficient stock is refused with the stock available', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[6] ?? WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '1');

    // D-018: the Owner's explicit entry, a named item of the Stok sub-navigation.
    await page.goto('/stock');
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/issues'), page.getByRole('navigation', { name: 'Submenu Stok' }).getByRole('link', { name: 'Barang keluar', exact: true }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Barang keluar' })).toBeVisible();

    await draftOf(page, product, '3');
    let sent = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/commands/manual_issue.confirm')) {
            sent += 1;
        }
    });

    // Enter in a field sends nothing (UXA-10).
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).press('Enter');
    await page.getByLabel(/^Catatan/).fill('Hilang');
    await page.getByLabel(/^Catatan/).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(sent).toBe(0);

    // The review says that the stock does not suffice, and the server refuses it: a warning is
    // never an override (BR-INV-20, UXS-31).
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await expect(page.getByRole('dialog', { name: 'Tinjau barang keluar' }).getByText('Tersedia 1 pcs')).toBeVisible();
    await page.getByRole('dialog', { name: 'Tinjau barang keluar' }).getByRole('button', { name: /^Konfirmasi barang keluar/ }).click();
    await expect(page.getByText('Stok tidak cukup', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(line(page, product.name).getByText('Tersedia 1 pcs')).toBeVisible();
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toHaveValue('3');
    expect(new URL(page.url()).pathname).toBe('/stock/issues/new');
    await checked(page);

    // The quantity that the stock holds posts.
    await line(page, product.name).getByLabel('Jumlah', { exact: true }).fill('1');
    await Promise.all([page.waitForURL((url) => /^\/stock\/issues\/\d+$/.test(url.pathname)), sendReview(page)]);
    await expect(page.getByText('Barang keluar tercatat.')).toBeVisible();
    expect(sent).toBe(2);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('an answer lost freezes the draft as it was sent, "Kirim ulang" sends the same intent, and leaving a draft asks first', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Dewi');
    const product = await newProduct(page);
    await received(page, product, '4');

    // Leaving a draft asks first (UXS-45).
    await draftOf(page, product, '1');
    await page.getByRole('link', { name: 'Batal' }).click();
    const leave = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
    await expect(leave.getByRole('button', { name: 'Tetap di halaman ini' })).toBeFocused();
    await leave.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    await expect(line(page, product.name)).toHaveCount(1);

    // UXS-14: the draft frozen as it was sent, with "Kirim ulang" and "Cek status".
    const sent: ReturnType<typeof envelopeOf>[] = [];
    await page.route(CONFIRM_ROUTE, async (route) => {
        sent.push(envelopeOf(route.request()));
        await (sent.length === 1 ? route.abort('failed') : route.continue());
    });
    await sendReview(page);
    await expect(page.getByText('Status belum pasti', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Kirim ulang' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cek status' })).toBeVisible();
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Alasan', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Cari atau scan produk', { exact: true })).toBeDisabled();
    await checked(page);

    // "Kirim ulang" sends the same request — the same intent, key and lines — which commits.
    await Promise.all([page.waitForURL((url) => /^\/stock\/issues\/\d+$/.test(url.pathname)), page.getByRole('button', { name: 'Kirim ulang' }).click()]);
    await expect(page.getByText('Barang keluar tercatat.')).toBeVisible();
    expect(sent).toHaveLength(2);
    expect(sent[1]).toEqual(sent[0]);
    expect(sent[0]?.payload).toMatchObject({ reasonCode: 'DAMAGED', lines: [{ productId: product.id, quantity: '1' }] });

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
