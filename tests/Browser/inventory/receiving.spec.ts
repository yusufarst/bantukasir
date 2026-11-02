import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, homeOf, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { envelopeOf, injected, prepareCommands, refusal } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-404 to UXP-406 (AC-022-03, AC-022-06; KS-09; COPY_DECK sections 9.5 to 9.7; NAVIGATION_CONTRACTS
// UXP-404 to UXP-406 and NC-11; UI_STATES section 6; suites UX01, UX02, INV01; TASK-022, L5): a
// receiving with its review and its scans — a scanned code adds a line, a second scan one base unit
// more to it; an unknown code stays and opens "Tambah produk" over the page, whose way back keeps
// the draft and whose success adds the new product; the staff zero refused at its field; the
// unknown cost marked in the review; the draft never in the browser's storage; the document with
// its cost, its movement and the ways back. These tests sign in the people of Toko Contoh, so they
// run one at a time (playwright.config.ts). Every product of a test gets new random texts, because
// the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

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

/** A new category through UXP-307; its name. */
async function newCategory(page: Page): Promise<string> {
    const name = `Kategori ${letters(10)}`;

    await page.goto('/categories');
    await page.getByRole('button', { name: 'Tambah kategori' }).click();
    await page.getByLabel('Nama kategori', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText(`Kategori ${name} ditambahkan.`)).toBeVisible();

    return name;
}

/** Step 2 of UXP-303, filled and saved. */
async function fillAndSave(page: Page, fields: { sku: string; category: string }): Promise<void> {
    await page.getByLabel('SKU', { exact: true }).fill(fields.sku);
    await page.getByLabel('Kategori', { exact: true }).selectOption({ label: fields.category });
    await page.getByLabel('Satuan', { exact: true }).fill('pcs');
    await page.getByLabel('Harga jual', { exact: true }).fill('7500');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
}

/** A new product with a barcode through UXP-303, and its internal key; the page is left on its UXP-302. */
async function newProduct(page: Page, category: string): Promise<{ id: number; name: string; sku: string; barcode: string }> {
    const product = { name: `Z${letters(14)}`, sku: `SKU-${letters(8)}`, barcode: digits() };

    await page.goto('/products/new');
    await page.getByLabel('Nama', { exact: true }).fill(product.name);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Lanjut isi data' }).click();
    await page.getByLabel(/^Barcode pabrik/).fill(product.barcode);
    await fillAndSave(page, { sku: product.sku, category });
    await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT });

    return { id: Number(new URL(page.url()).pathname.split('/').pop()), ...product };
}

/** RC-07's route (ROUTE_CONTRACTS RC-07). */
const CONFIRM_ROUTE = '**/commands/receiving.confirm';

/** "Tinjau", then the review's confirmation, which sends RC-07. */
async function sendReview(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await page.getByRole('dialog', { name: 'Tinjau barang masuk' }).getByRole('button', { name: /^Konfirmasi barang masuk/ }).click();
}

/** UXP-405 with a draft of one line of the product, its cost filled. */
async function draftOf(page: Page, product: { name: string; barcode: string }): Promise<void> {
    await page.goto('/stock/receivings/new');
    await scan(page, product.barcode);
    await expect(line(page, product.name)).toHaveCount(1);
    await line(page, product.name).getByLabel('Biaya', { exact: true }).fill('4000');
}

/** The line of the draft for a product. */
function line(page: Page, name: string) {
    return page.getByRole('list', { name: 'Catat barang masuk' }).getByRole('listitem').filter({ hasText: name });
}

/** Scans a code into "Cari atau scan produk": the code, then the Enter that ends it. */
async function scan(page: Page, code: string): Promise<void> {
    const field = page.getByLabel('Cari atau scan produk', { exact: true });

    await field.fill(code);
    await field.press('Enter');
}

test('an Operations admin records a receiving with scans, a product made over the page, a review and its document', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');

    // RN-01: Operations lands on Stok (TASK-022).
    expect(new URL(page.url()).pathname).toBe(homeOf('Agus'));
    await expect(page.getByRole('heading', { level: 1, name: 'Stok', exact: true })).toBeVisible();

    const category = await newCategory(page);
    const product = await newProduct(page, category);

    // UXP-404: the sub-navigation, then "Catat barang masuk".
    await page.goto('/stock/receivings');
    await expect(page.getByRole('heading', { level: 1, name: 'Barang masuk' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Submenu Stok' }).getByRole('link')).toHaveText(['Stok', 'Riwayat stok', 'Barang masuk']);
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/receivings/new'), page.getByRole('link', { name: 'Catat barang masuk' }).click()]);

    // UXP-405: the draft note, the way back, and "Tinjau" waiting for a complete line.
    await expect(page.getByRole('heading', { level: 1, name: 'Catat barang masuk' })).toBeVisible();
    await expect(page.getByText('Draf ini hanya ada di halaman ini.', { exact: false })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Barang masuk' })).toBeVisible();
    await checked(page);

    // A scan adds a line of one base unit; a second scan of it one more (UXI-234).
    await scan(page, product.barcode);
    await expect(line(page, product.name)).toHaveCount(1);
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toHaveValue('1');
    await scan(page, product.barcode);
    await expect(line(page, product.name)).toHaveCount(1);
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toHaveValue('2');

    // Rp0 is the Owner's alone: refused at its field for staff (UXI-236; BR-COST-04).
    const cost = line(page, product.name).getByLabel('Biaya', { exact: true });
    await cost.fill('0');
    await expect(line(page, product.name).getByText('Biaya 0 hanya bisa diisi Pemilik.', { exact: false })).toBeVisible();
    await cost.fill('15000');
    await expect(cost).toHaveValue('15.000');

    // The draft is never in the browser's storage (BR-INV-17, SEC-CH-05).
    expect(await page.evaluate(() => JSON.stringify([window.sessionStorage, window.localStorage]))).not.toContain(product.name);

    // An unknown code stays in the field, with "Tambah produk" (UXS-33, UXI-235).
    const code = digits();
    await scan(page, code);
    await expect(page.getByText(`Tidak ada produk dengan kode “${code}”.`, { exact: false })).toBeVisible();
    await expect(page.getByLabel('Cari atau scan produk', { exact: true })).toHaveValue(code);
    await checked(page);

    // UXP-303 over the page: its way back returns to the draft untouched (NC-11).
    await page.getByRole('button', { name: 'Tambah produk' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Tambah produk' })).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/stock/receivings/new');
    await expect(page.getByLabel('Barcode', { exact: true })).toHaveValue(code);
    await page.getByRole('link', { name: 'Kembali ke Catat barang masuk' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Catat barang masuk' })).toBeVisible();
    await expect(line(page, product.name).getByLabel('Biaya', { exact: true })).toHaveValue('15.000');

    // Again, and the new product joins the draft as a new line.
    await scan(page, code);
    await page.getByRole('button', { name: 'Tambah produk' }).click();
    const created = { name: `Z${letters(14)}`, sku: `SKU-${letters(8)}` };
    await page.getByLabel('Nama', { exact: true }).fill(created.name);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Lanjut isi data' }).click();
    await fillAndSave(page, { sku: created.sku, category });
    await expect(page.getByRole('heading', { level: 1, name: 'Catat barang masuk' })).toBeVisible();
    await expect(line(page, created.name)).toHaveCount(1);
    expect(new URL(page.url()).pathname).toBe('/stock/receivings/new');

    // Its cost unknown: marked in the review (UXI-236, UXI-240).
    await line(page, created.name).getByRole('button', { name: 'Biaya belum diketahui' }).click();
    await expect(line(page, created.name).getByText('Pemilik akan melengkapinya nanti.')).toBeVisible();

    // "Tinjau" opens the review, never Enter; its confirmation sends RC-07 (UXI-239, UXI-240).
    await page.getByLabel('Cari atau scan produk', { exact: true }).fill('');
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau barang masuk' });
    await expect(review.getByText('2 baris.')).toBeVisible();
    await expect(review.getByText('1 baris dengan biaya belum diketahui.')).toBeVisible();
    await expect(review.getByText('Biaya perlu dilengkapi pemilik')).toBeVisible();
    await expect(review.getByText('Rp15.000')).toBeVisible();
    await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
    await checked(page);
    await Promise.all([page.waitForURL((url) => /^\/stock\/receivings\/\d+$/.test(url.pathname)), review.getByRole('button', { name: /^Konfirmasi barang masuk/ }).click()]);

    // UXP-406: the message, the lines with their cost, and the way back to the list.
    await expect(page.getByRole('heading', { level: 1, name: 'Detail barang masuk' })).toBeVisible();
    await expect(page.getByText('Barang masuk tercatat.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Barang masuk' })).toBeVisible();
    const table = page.getByRole('main').getByRole('table');
    await expect(table.getByRole('row').filter({ hasText: product.name })).toContainText('Rp15.000');
    await expect(table.getByRole('row').filter({ hasText: created.name })).toContainText('Biaya perlu dilengkapi pemilik');
    await checked(page);
    const detail = new URL(page.url()).pathname;

    // The movement of a line, and its way back to the document (UXI-248, UXI-229).
    await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+$/.test(url.pathname)), page.getByRole('link', { name: `Detail mutasi stok ${product.name}` }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail mutasi stok' })).toBeVisible();
    await expect(page.getByText('+2 pcs')).toBeVisible();
    await expect(page.getByText('Bukti biaya')).toHaveCount(0);
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === detail), page.getByRole('link', { name: 'Kembali ke Detail barang masuk' }).click()]);

    // The list holds the document, newest first.
    await page.goto('/stock/receivings');
    await expect(page.getByRole('main').getByRole('table').locator('tbody tr').first()).toContainText('2');

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('leaving a draft asks first, and Enter in a field sends nothing', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();

    await signedIn(context, page, 'Dewi');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    await page.goto('/stock/receivings/new');
    await scan(page, product.barcode);
    await expect(line(page, product.name)).toHaveCount(1);
    let sent = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/commands/receiving.confirm')) {
            sent += 1;
        }
    });
    await line(page, product.name).getByLabel('Biaya', { exact: true }).fill('5000');
    await line(page, product.name).getByLabel('Biaya', { exact: true }).press('Enter');
    await page.getByLabel(/^Referensi/).fill('NOTA');
    await page.getByLabel(/^Referensi/).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(sent).toBe(0);

    // "Batal" with a draft: the dialog says that it is kept nowhere (UXS-45, D-069).
    await page.getByRole('link', { name: 'Batal' }).click();
    const dialog = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
    await expect(dialog).toContainText('Draf barang masuk ini tidak disimpan di mana pun dan akan hilang.');
    await expect(dialog.getByRole('button', { name: 'Tetap di halaman ini' })).toBeFocused();
    await dialog.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    await expect(line(page, product.name)).toHaveCount(1);
    await page.getByRole('link', { name: 'Kembali ke Barang masuk' }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/receivings'), dialog.getByRole('button', { name: 'Tinggalkan' }).click()]);
    expect(sent).toBe(0);

    await context.close();
});

// Review finding R-03: UXS-14 and the refusals of RC-07 on UXP-405 (INTERACTION_CONTRACTS section
// 1.3; UI_STATES section 6).
test('an answer lost freezes the draft as it was sent, and "Kirim ulang" sends the same intent, which commits', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    await draftOf(page, product);
    const sent: ReturnType<typeof envelopeOf>[] = [];
    await page.route(CONFIRM_ROUTE, async (route) => {
        sent.push(envelopeOf(route.request()));
        await (sent.length === 1 ? route.abort('failed') : route.continue());
    });
    await sendReview(page);

    // UXS-14: the draft frozen as it was sent, with "Kirim ulang" and "Cek status".
    await expect(page.getByText('Status belum pasti', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Kirim ulang' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cek status' })).toBeVisible();
    await expect(line(page, product.name).getByLabel('Jumlah', { exact: true })).toBeDisabled();
    await expect(line(page, product.name).getByLabel('Biaya', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Cari atau scan produk', { exact: true })).toBeDisabled();
    await expect(page.getByLabel(/^Referensi/)).toBeDisabled();
    await checked(page);

    // "Kirim ulang" sends the same request — the same intent, key and lines — which commits.
    await Promise.all([page.waitForURL((url) => /^\/stock\/receivings\/\d+$/.test(url.pathname)), page.getByRole('button', { name: 'Kirim ulang' }).click()]);
    await expect(page.getByText('Barang masuk tercatat.')).toBeVisible();
    expect(sent).toHaveLength(2);
    expect(sent[1]).toEqual(sent[0]);
    expect(sent[0]?.payload).toMatchObject({ lines: [{ productId: product.id, quantity: '1', cost: { status: 'KNOWN', amount: '4000' } }] });

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('each refusal of RC-07 returns to the draft with its line marked and its message', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    await draftOf(page, product);
    let answer: { status: number; json: Record<string, unknown> } = { status: 422, json: refusal('VALIDATION_FAILED') };
    await page.route(CONFIRM_ROUTE, (route) => route.fulfill(answer));
    const named = { 0: { productId: product.id } };
    const marked = () => line(page, product.name).locator('[data-marked="true"]');
    const kept = async (): Promise<void> => {
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(line(page, product.name)).toHaveCount(1);
        await expect(line(page, product.name).getByLabel('Biaya', { exact: true })).toHaveValue('4.000');
    };

    // VALIDATION_FAILED: each field at its line, by the line's index as sent, and the document's own.
    answer = {
        status: 422,
        json: { ...refusal('VALIDATION_FAILED'), fields: { 'payload.lines.0.quantity': [{ key: 'ux.validation.quantity_max', params: {} }], 'payload.reference': [{ key: 'ux.validation.text_max', params: { maks: 100 } }] } },
    };
    await sendReview(page);
    await kept();
    await expect(page.getByText('Periksa isian', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: `${product.name}: Jumlah`, exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Referensi', exact: true })).toBeVisible();
    await expect(line(page, product.name).getByText('Jumlah terlalu besar. Paling banyak 99.999.999,999.')).toBeVisible();
    await expect(page.getByText('Paling banyak 100 karakter.')).toHaveCount(2);
    await checked(page);

    // STALE_VERSION: the product read again, its line marked.
    answer = { status: 409, json: { ...refusal('STALE_VERSION'), lines: named } };
    const reread = page.waitForRequest((request) => request.url().includes('/data/products/search') && new URL(request.url()).searchParams.get('q') === product.sku);
    await sendReview(page);
    await kept();
    await expect(page.getByText('Data sudah berubah', { exact: true }).first()).toBeVisible();
    await reread;
    await expect(marked()).toHaveCount(1);
    await expect(page.getByRole('link', { name: 'Referensi', exact: true })).toHaveCount(0);

    // ONBOARDING_FREEZE.
    answer = { status: 409, json: refusal('ONBOARDING_FREEZE') };
    await sendReview(page);
    await kept();
    await expect(page.getByText('Masa input stok awal aktif', { exact: true }).first()).toBeVisible();

    // PRODUCT_INACTIVE: the product named, its line marked.
    answer = { status: 409, json: { ...refusal('PRODUCT_INACTIVE'), lines: named } };
    await sendReview(page);
    await kept();
    await expect(page.getByText(`Produk ${product.name} sudah nonaktif dan tidak bisa dipakai.`, { exact: false }).first()).toBeVisible();
    await expect(marked()).toHaveCount(1);

    // POSTING_BLOCKED: the product named, and for staff whom to tell.
    answer = { status: 409, json: { ...refusal('POSTING_BLOCKED'), lines: named } };
    await sendReview(page);
    await kept();
    await expect(page.getByText(`Produk ${product.name} diblokir sementara`, { exact: false }).first()).toContainText('Beri tahu Pemilik.');
    await expect(marked()).toHaveCount(1);
    await checked(page);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

// Pull request #31, finding P1: the review's children are made with every render, so a quantity
// emptied or half typed reached the formatter and ended the page with its draft.
test('a quantity emptied or half typed keeps the page and its draft, and the review shows it once it is one', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    await draftOf(page, product);
    const quantity = line(page, product.name).getByLabel('Jumlah', { exact: true });

    for (const typed of ['', '0,', '1,', '01', '2,0']) {
        await quantity.fill(typed);
        await expect(quantity).toHaveValue(typed);
        await expect(page.getByRole('heading', { level: 1, name: 'Catat barang masuk' })).toBeVisible();
        await expect(line(page, product.name).getByLabel('Biaya', { exact: true })).toHaveValue('4.000');
    }

    // "2,0" of a product of whole units is 2: the review shows it in its form.
    await page.getByLabel('Cari atau scan produk', { exact: true }).fill('');
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau barang masuk' });
    await expect(review.getByRole('listitem').filter({ hasText: product.name })).toContainText('2 pcs');
    await review.getByRole('button', { name: 'Kembali ubah' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(quantity).toHaveValue('2,0');

    // No page error of the formatter, nor any other.
    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

// KS-09 at the widths of KEY_SCREENS section 4 and 1440 px: the page and its review.
for (const width of [WIDTHS[4], WIDTHS[7]]) {
    test(`UXP-405 and its review fit ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();

        await signedIn(context, page, 'Agus');
        const category = await newCategory(page);
        const product = await newProduct(page, category);

        await page.goto('/stock/receivings/new');
        await scan(page, product.barcode);
        await line(page, product.name).getByLabel('Biaya', { exact: true }).fill('2500');
        await page.getByLabel('Cari atau scan produk', { exact: true }).fill('');
        await checked(page);
        await page.getByRole('button', { name: 'Tinjau' }).click();
        await expect(page.getByRole('dialog', { name: 'Tinjau barang masuk' })).toBeVisible();
        await checked(page);
        await page.getByRole('dialog').getByRole('button', { name: 'Kembali ubah' }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await context.close();
    });
}
