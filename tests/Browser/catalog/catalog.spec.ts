import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page, type Route } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-301 to UXP-304 and UXP-307 (AC-017-01 to AC-017-04; COPY_DECK sections 8.1 to 8.5 and
// 8.8; NAVIGATION_CONTRACTS section 2, A3; UI_STATES section 6; suites UX01, UX02,
// SEARCH02, SEARCH03; TASK-017, L5): the pages per role, their states and widths, the
// search-first flow with a scanned code, the barcodes, the list's way back, and scan
// capture, which pauses while a dialog is open. These tests sign in the people of Toko
// Contoh, so they run one at a time (playwright.config.ts). Every product of a test gets
// new random texts, because the local database keeps its products and a similar name is
// found by its letters (BR-CAT-04).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const SEARCH = /\/data\/products\/search/;

const CHECK = /\/data\/products\/check/;

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
    await page.getByLabel('Nama kategori').fill(name);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText(`Kategori ${name} ditambahkan.`)).toBeVisible();

    return name;
}

interface NewProduct {
    readonly name: string;
    readonly sku: string;
    readonly barcode: string;
    readonly id: string;
}

/** Step 2 of UXP-303, filled and saved; the page is left on UXP-302. */
async function fillAndSave(page: Page, fields: { sku: string; category: string; price?: string }): Promise<void> {
    await page.getByLabel('SKU', { exact: true }).fill(fields.sku);
    await page.getByLabel('Kategori', { exact: true }).selectOption({ label: fields.category });
    await page.getByLabel('Satuan', { exact: true }).fill('pcs');
    await page.getByLabel('Harga jual', { exact: true }).fill(fields.price ?? '7500');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
}

/** A new product through UXP-303, with a barcode; the page is left on its UXP-302. */
async function newProduct(page: Page, category: string): Promise<NewProduct> {
    const product = { name: `Z${letters(14)}`, sku: `SKU-${letters(8)}`, barcode: digits() };

    await page.goto('/products/new');
    await page.getByLabel('Nama', { exact: true }).fill(product.name);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Lanjut isi data' }).click();
    await page.getByLabel(/^Barcode pabrik/).fill(product.barcode);
    await fillAndSave(page, { sku: product.sku, category });
    await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname));

    return { ...product, id: new URL(page.url()).pathname.split('/').at(-1) ?? '' };
}

/** A row of RD-01 for a stubbed answer. */
function stubRow(index: number): Record<string, unknown> {
    return { id: 900000 + index, name: `Produk contoh ${index}`, sku: `CTH-${index}`, baseUnit: 'pcs', quantityPrecision: 0, price: { amount: '1000', revisionId: index }, active: index % 2 === 0, thumbnail: null };
}

// 1440 × 900, the 200 % zoom of 1280 × 800 and 320 × 640.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`an Operations admin creates a category and a product, searches it and edits it, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Agus');

        // UXI-005 and UXI-009, UXI-010: the navigation and the sub-navigation.
        await page.goto('/products');
        await expect(page.getByRole('heading', { level: 1, name: 'Produk' })).toBeVisible();

        if (width === WIDTHS[7]) {
            await page.getByRole('navigation', { name: 'Submenu Produk' }).getByRole('button', { name: 'Produk' }).click();
            await expect(page.getByRole('menuitem', { name: 'Kategori' })).toBeVisible();
            await page.keyboard.press('Escape');
        } else {
            await expect(page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link')).toHaveText(['Produk']);
            await expect(page.getByRole('navigation', { name: 'Submenu Produk' }).getByRole('link')).toHaveText(['Produk', 'Kategori']);
        }

        await checked(page);

        // UXP-307 with its inline form.
        const category = await newCategory(page);
        await checked(page);

        // UXP-303: the search-first step, then the form.
        await page.goto('/products');
        await Promise.all([page.waitForURL((url) => url.pathname === '/products/new'), page.getByRole('link', { name: 'Tambah produk' }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Tambah produk' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Cari dulu' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Produk' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Cek', exact: true })).toHaveAttribute('aria-disabled', 'true');
        await checked(page);

        const name = `Z${letters(14)}`;
        const sku = `SKU-${letters(8)}`;

        await page.getByLabel('Nama', { exact: true }).fill(name);
        await page.getByRole('button', { name: 'Cek', exact: true }).click();
        await expect(page.getByText('Tidak ada produk yang sama atau mirip.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Tetap buat produk baru' })).toHaveCount(0);
        await page.getByRole('button', { name: 'Lanjut isi data' }).click();

        // The search-first inputs carry into the form; Enter in a field sends nothing (UXA-10).
        await expect(page.getByLabel('Nama', { exact: true })).toHaveValue(name);
        await expect(page.getByRole('button', { name: 'Simpan produk' })).toHaveAttribute('aria-disabled', 'true');
        await expect(page.getByText('Produk baru mulai dengan stok 0.', { exact: false })).toBeVisible();
        await checked(page);
        let sent = 0;
        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().includes('/commands/product.create')) {
                sent += 1;
            }
        });
        await page.getByLabel('SKU', { exact: true }).fill(sku);
        await page.getByLabel('SKU', { exact: true }).press('Enter');
        await page.getByLabel('Kategori', { exact: true }).selectOption({ label: category });
        await page.getByLabel('Satuan', { exact: true }).fill('pcs');
        await page.getByLabel('Harga jual', { exact: true }).fill('7500');
        await expect(page.getByLabel('Harga jual', { exact: true })).toHaveValue('7.500');
        await page.getByLabel('Harga jual', { exact: true }).press('Enter');
        expect(sent).toBe(0);
        await page.getByRole('button', { name: 'Simpan produk' }).click();

        // UXP-302 with its message; the way back leads to the list.
        await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname));
        const detail = new URL(page.url()).pathname;
        await expect(page.getByRole('heading', { level: 1, name: 'Detail produk' })).toBeVisible();
        await expect(page.getByText(`Produk ${name} ditambahkan.`)).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Produk' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Riwayat harga jual' })).toBeVisible();
        await expect(page.getByText('Rp7.500 · mulai', { exact: false })).toBeVisible();
        await expect(page.getByRole('button', { name: /Hapus/ })).toHaveCount(0);
        await checked(page);

        // UXP-301: the row, with no cost, and its way there and back.
        await page.goto('/products');
        await page.getByLabel('Cari produk').fill(name);
        await expect(page).toHaveURL(new RegExp(`q=${name}`));
        await expect(page.getByRole('main').getByRole('table').locator('tbody tr')).toHaveCount(1);
        const row = page.getByRole('link', { name, exact: true });
        await expect(row).toBeVisible();
        await expect(page.getByRole('main').getByText('Rp7.500')).toBeVisible();
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === detail), row.click()]);
        await Promise.all([page.waitForURL((url) => url.pathname === '/products' && url.searchParams.get('q') === name), page.getByRole('link', { name: 'Kembali ke Produk' }).click()]);
        await expect(page.getByLabel('Cari produk')).toHaveValue(name);
        await expect(page.getByRole('link', { name, exact: true })).toBeVisible();

        // UXP-304: nothing changed yet, so the save says why it waits.
        await page.goto(detail);
        await Promise.all([page.waitForURL((url) => url.pathname === `${detail}/edit`), page.getByRole('link', { name: 'Ubah produk' }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Ubah produk' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Simpan perubahan' })).toHaveAttribute('aria-disabled', 'true');
        await expect(page.getByText('Belum ada perubahan.')).toBeVisible();
        await checked(page);

        // A changed price shows old and new; leaving asks first (UXS-45).
        await page.getByLabel('Harga jual', { exact: true }).fill('8000');
        await expect(page.getByText('Harga lama Rp7.500 → harga baru Rp8.000', { exact: false })).toBeVisible();
        await page.getByRole('link', { name: 'Kembali ke Detail produk' }).click();
        await expect(page.getByRole('dialog', { name: 'Perubahan belum disimpan' })).toBeVisible();
        await page.getByRole('button', { name: 'Tetap di halaman ini' }).click();
        await page.getByRole('button', { name: 'Simpan perubahan' }).click();
        await page.waitForURL((url) => url.pathname === detail);
        await expect(page.getByText('Perubahan produk disimpan.')).toBeVisible();
        await expect(page.getByText('Rp8.000 · mulai', { exact: false })).toBeVisible();
        await expect(page.getByText('Rp7.500 · mulai', { exact: false })).toBeVisible();

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    });
}

test('the search-first step: a scanned code and a SKU that exist, a similar name, and the refusals at their fields', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const held = await newProduct(page, category);

    // A scan on the page, with the focus on no field: it reaches "Barcode", and its Enter
    // runs the check only (UXA-10, UXA-11). An exact match goes no further (AC-017-02).
    await page.goto('/products/new');
    await page.locator('h1').click();
    await page.keyboard.type(held.barcode);
    await expect(page.getByLabel('Barcode', { exact: true })).toHaveValue(held.barcode);
    const checks = page.waitForRequest(CHECK);
    await page.keyboard.press('Enter');
    expect(new URL((await checks).url()).searchParams.get('barcode')).toBe(held.barcode);
    await expect(page.getByText('Produk dengan barcode atau SKU ini sudah ada:')).toBeVisible();
    const link = page.getByRole('link', { name: held.name, exact: true });
    await expect(link).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tetap buat produk baru' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Lanjut isi data' })).toHaveCount(0);
    await checked(page);

    // The product it found says where the way back leads, once.
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${held.id}`), link.click()]);
    await expect(page.getByRole('link', { name: 'Kembali ke Tambah produk' })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('link', { name: 'Kembali ke Produk' })).toBeVisible();

    // An exact SKU, without regard to letter case.
    await page.goto('/products/new');
    await page.getByLabel('SKU', { exact: true }).fill(held.sku.toUpperCase());
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await expect(page.getByRole('link', { name: held.name, exact: true })).toBeVisible();

    // A similar name needs "Tetap buat produk baru" (UXS-47), each product openable.
    await page.goto('/products/new');
    await page.getByLabel('Nama', { exact: true }).fill(held.name.slice(0, 10));
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await expect(page.getByText('Produk mirip')).toBeVisible();
    await expect(page.getByRole('link', { name: held.name, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Lanjut isi data' })).toHaveCount(0);
    await checked(page);

    // A change makes the answer out of date.
    await page.getByLabel('Nama', { exact: true }).fill(`${held.name.slice(0, 10)}x`);
    await expect(page.getByText('Produk mirip')).toHaveCount(0);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Tetap buat produk baru' }).click();
    const name = page.getByLabel('Nama', { exact: true });
    await name.fill(`Z${letters(14)}`);

    // A taken SKU is refused at its field, with the way to the product that holds it (UXS-37).
    await fillAndSave(page, { sku: held.sku, category });
    await expect(page.locator('.bk-field .bk-error').getByText('SKU ini sudah dipakai produk lain.', { exact: false })).toBeVisible();
    await expect(page.getByRole('link', { name: `Buka ${held.name}` })).toBeVisible();
    await checked(page);

    // A barcode in use, then a code of the reserved namespace (UXS-38).
    const sku = `SKU-${letters(8)}`;
    await page.getByLabel('SKU', { exact: true }).fill(sku);
    await page.getByLabel(/^Barcode pabrik/).fill(held.barcode);
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    await expect(page.locator('.bk-field .bk-error').getByText('Barcode ini sudah dipakai produk lain.', { exact: false })).toBeVisible();
    await expect(page.getByRole('link', { name: `Buka ${held.name}` })).toBeVisible();
    await page.getByLabel(/^Barcode pabrik/).fill(`BK-${letters(6)}`);
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    await expect(page.getByText('Barcode tidak dapat dipakai')).toBeVisible();
    await expect(page.locator('.bk-field .bk-error').getByText('Kode yang diawali BK- hanya untuk barcode internal', { exact: false })).toBeVisible();

    // A field error goes to the summary, which takes the focus (UXS-05).
    await page.getByLabel(/^Barcode pabrik/).fill('');
    await page.getByLabel('Stok minimum', { exact: true }).fill('1,5');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    const summary = page.locator('.bk-error-summary');
    await expect(summary).toBeFocused();
    await expect(page.locator('.bk-field .bk-error').getByText('Produk ini dihitung utuh.', { exact: false })).toBeVisible();

    // Corrected, it is saved, and its stock is a whole number of the unit.
    await page.getByLabel('Stok minimum', { exact: true }).fill('5');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname));
    await expect(page.getByText('5 pcs')).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the list: a scanned code looked up exactly, an unknown code kept, the filters, and the way back with them', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    // A scan with no field focused reaches the search; Enter looks the code up, with no filter (TASK-016 F-4).
    await page.goto('/products');
    await page.locator('h1').click();
    await page.keyboard.type(product.barcode);
    await expect(page.getByLabel('Cari produk')).toHaveValue(product.barcode);
    const exact = page.waitForRequest((request) => SEARCH.test(request.url()) && new URL(request.url()).searchParams.get('mode') === 'exact');
    await page.keyboard.press('Enter');
    const parameters = new URL((await exact).url()).searchParams;
    expect([...parameters.keys()].sort()).toEqual(['context', 'mode', 'q']);
    await expect(page.getByRole('link', { name: product.name, exact: true })).toBeVisible();
    await expect(page.getByRole('main').getByRole('table').getByRole('link')).toHaveCount(1);

    // An unknown code stays in the field, and no product is created (UXS-33).
    const unknown = digits();
    await page.getByLabel('Cari produk').fill(unknown);
    await page.getByLabel('Cari produk').press('Enter');
    await expect(page.getByText('Kode tidak dikenal')).toBeVisible();
    await expect(page.getByLabel('Cari produk')).toHaveValue(unknown);
    await checked(page);

    // A search with nothing found offers to clear it (UXS-03).
    await page.getByLabel('Cari produk').fill(letters(20));
    await expect(page.getByText('Tidak ada hasil')).toBeVisible();
    await page.getByRole('button', { name: 'Hapus filter' }).click();
    await expect(page.getByLabel('Cari produk')).toHaveValue('');

    // The filters of RD-01 stand in the address, and the way back returns to them (NC-10).
    await page.getByLabel('Kategori', { exact: true }).selectOption({ label: category });
    await expect(page).toHaveURL(/category=\d+/);
    await page.getByRole('button', { name: 'Aktif', exact: true }).click();
    await expect(page).toHaveURL(/status=active/);
    await expect(page.getByRole('link', { name: product.name, exact: true })).toBeVisible();
    const list = page.url();
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${product.id}`), page.getByRole('link', { name: product.name, exact: true }).click()]);
    await Promise.all([page.waitForURL((url) => url.href === list), page.getByRole('link', { name: 'Kembali ke Produk' }).click()]);
    await expect(page.getByRole('button', { name: 'Aktif', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('link', { name: product.name, exact: true })).toBeVisible();

    // The browser's Back finds them too.
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${product.id}`), page.getByRole('link', { name: product.name, exact: true }).click()]);
    await page.goBack();
    await expect(page).toHaveURL(list);
    await expect(page.getByRole('button', { name: 'Aktif', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('link', { name: product.name, exact: true })).toBeVisible();

    // An inactive filter shows none of the active products.
    await page.getByRole('button', { name: 'Nonaktif', exact: true }).click();
    await expect(page.getByRole('link', { name: product.name, exact: true })).toHaveCount(0);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the list: paging kept on a reload, a newer search over a late one, nothing yet, and a failed read', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');

    let empty = false;
    let failing = false;
    const hold: { late: Route | null } = { late: null };

    await page.route(SEARCH, async (route) => {
        const parameters = new URL(route.request().url()).searchParams;
        const sequence = route.request().headers()['x-search-sequence'] ?? '';

        expect(sequence).toMatch(/^[0-9]{1,16}$/);

        if (failing) {
            await route.abort();

            return;
        }

        if (empty) {
            await route.fulfill({ json: { rows: [], page: 1, perPage: 25, hasMore: false } });

            return;
        }

        if (parameters.get('q') === 'lama') {
            hold.late = route;

            return;
        }

        if (parameters.get('q') === 'baru') {
            await route.fulfill({ json: { rows: [stubRow(99)], page: 1, perPage: 25, hasMore: false } });

            return;
        }

        if (parameters.get('perPage') === '100') {
            await route.fulfill({ json: { rows: Array.from({ length: 30 }, (_, index) => stubRow(index + 1)), page: 1, perPage: 100, hasMore: false } });
        } else if (parameters.get('page') === '2') {
            await route.fulfill({ json: { rows: Array.from({ length: 5 }, (_, index) => stubRow(index + 26)), page: 2, perPage: 25, hasMore: false } });
        } else {
            await route.fulfill({ json: { rows: Array.from({ length: 25 }, (_, index) => stubRow(index + 1)), page: 1, perPage: 25, hasMore: true } });
        }
    });

    // "Muat lebih banyak" adds the next page and the address holds it.
    await page.goto('/products');
    const rows = page.getByRole('main').getByRole('table').locator('tbody tr');
    await expect(rows).toHaveCount(25);
    await expect(page.getByText('Nonaktif', { exact: true }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Muat lebih banyak' }).click();
    await expect(rows).toHaveCount(30);
    await expect(page).toHaveURL(/page=2/);
    await expect(page.getByRole('button', { name: 'Muat lebih banyak' })).toHaveCount(0);

    // A reload shows as many.
    await page.reload();
    await expect(rows).toHaveCount(30);

    // A late answer never replaces a newer one (TR-PAGE-04).
    await page.goto('/products');
    await expect(rows).toHaveCount(25);
    await page.getByLabel('Cari produk').fill('lama');
    await expect.poll(() => hold.late !== null).toBe(true);
    await page.getByLabel('Cari produk').fill('baru');
    await expect(page.getByRole('link', { name: 'Produk contoh 99', exact: true })).toBeVisible();
    await hold.late?.fulfill({ json: { rows: [stubRow(1), stubRow(2)], page: 1, perPage: 25, hasMore: false } });
    await expect(page.getByRole('link', { name: 'Produk contoh 1', exact: true })).toHaveCount(0);
    await expect(rows).toHaveCount(1);

    // No product at all (UXS-02), with the words of a role that may create.
    empty = true;
    await page.goto('/products');
    await expect(page.getByText('Belum ada produk.', { exact: false })).toBeVisible();
    await expect(page.getByText('Tambah produk pertama, atau impor dari CSV.', { exact: false })).toBeVisible();
    await checked(page);

    // A failed read says so, never an empty list (UXS-22), and "Coba lagi" reads again.
    empty = false;
    failing = true;
    await page.goto('/products');
    await expect(page.getByText('Gagal memuat')).toBeVisible();
    await expect(page.getByText('Belum ada produk.', { exact: false })).toHaveCount(0);
    failing = false;
    await page.getByRole('button', { name: 'Coba lagi' }).click();
    await expect(rows).toHaveCount(25);
    await expect(page.getByText('Gagal memuat')).toHaveCount(0);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the barcodes: add, generate, retire with a reason, a duplicate refused, and capture paused in a dialog', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const other = await newProduct(page, category);
    const product = await newProduct(page, category);

    // UXI-171: the inline form; Enter ends the code and saves nothing.
    const added = digits();
    await page.getByRole('button', { name: 'Tambah barcode pabrik' }).click();
    const token = page.getByLabel('Barcode', { exact: true });
    await expect(token).toBeFocused();
    await checked(page);
    let sent = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().includes('/commands/barcode.add')) {
            sent += 1;
        }
    });
    await page.keyboard.type(other.barcode);
    await page.keyboard.press('Enter');
    expect(sent).toBe(0);

    // A code in use is refused at the field, with the way to its product (UXS-37).
    await page.getByRole('button', { name: 'Simpan barcode' }).click();
    await expect(page.locator('.bk-field .bk-error').getByText('Barcode ini sudah dipakai produk lain.', { exact: false })).toBeVisible();
    await expect(page.getByRole('link', { name: `Buka ${other.name}` })).toBeVisible();
    await token.fill(added);
    await page.getByRole('button', { name: 'Simpan barcode' }).click();
    await expect(page.getByText('Barcode ditambahkan.')).toBeVisible();
    await expect(page.getByRole('rowheader', { name: added })).toBeVisible();

    // "Batal" closes the form and sends nothing.
    await page.getByRole('button', { name: 'Tambah barcode pabrik' }).click();
    await page.getByLabel('Barcode', { exact: true }).fill(digits());
    await page.getByRole('button', { name: 'Batal' }).click();
    await expect(page.getByLabel('Barcode', { exact: true })).toHaveCount(0);

    // UXI-172: an internal code, named in the message.
    await page.getByRole('button', { name: 'Buat barcode internal' }).click();
    await expect(page.getByText(/^Barcode internal BK-P-[0-9A-Z]{16} dibuat\.$/)).toBeVisible();
    await expect(page.getByRole('rowheader', { name: /^BK-P-/ })).toBeVisible();
    await checked(page);

    // UXI-173: the dialog asks for the reason class; Enter never confirms.
    await page.getByRole('button', { name: `Tindakan lain ${added}` }).click();
    await page.getByRole('menuitem', { name: 'Pensiunkan barcode' }).click();
    const dialog = page.getByRole('dialog', { name: `Pensiunkan barcode ${added}?` });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Pensiunkan barcode' })).toHaveAttribute('aria-disabled', 'true');
    await dialog.getByRole('radio', { name: 'Salah pasang' }).click();
    await expect(dialog.getByText('Barcode ini terpasang pada produk yang salah.', { exact: false })).toBeVisible();
    await checked(page);
    await dialog.getByRole('button', { name: 'Pensiunkan barcode' }).focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Pensiunkan barcode' }).click();
    await expect(page.getByText('Barcode dipensiunkan.')).toBeVisible();
    await expect(page.getByText('Dipensiunkan · salah pasang')).toBeVisible();

    // A retired code cannot be attached again (BR-CAT-19).
    await page.getByRole('button', { name: 'Tambah barcode pabrik' }).click();
    await page.getByLabel('Barcode', { exact: true }).fill(added);
    await page.getByRole('button', { name: 'Simpan barcode' }).click();
    await expect(page.getByText('Barcode tidak dapat dipakai')).toBeVisible();
    await page.getByRole('button', { name: 'Batal' }).click();

    // Scan capture pauses while a dialog is open (UXA-11): a scan reaches neither field.
    await page.getByRole('button', { name: 'Tambah barcode pabrik' }).click();
    await page.getByRole('button', { name: 'Tindakan lain', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Nonaktifkan' }).click();
    const confirm = page.getByRole('dialog', { name: `Nonaktifkan ${product.name}?` });
    await expect(confirm).toBeVisible();
    await expect(confirm.getByRole('button', { name: 'Batal' })).toBeFocused();
    await page.keyboard.type('12345');
    await expect(page.getByLabel('Barcode', { exact: true })).toHaveValue('');
    await expect(confirm).toBeVisible();

    // UXI-166: never by Enter; the product is marked, and comes back.
    await confirm.getByRole('button', { name: 'Nonaktifkan' }).focus();
    await page.keyboard.press('Enter');
    await expect(confirm).toBeVisible();
    await confirm.getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(page.getByText('Produk dinonaktifkan.')).toBeVisible();
    await expect(page.getByRole('main').locator('[data-state="inactive"]')).toBeVisible();
    await checked(page);
    await page.getByRole('button', { name: 'Tindakan lain', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Aktifkan kembali' }).click();
    await page.getByRole('dialog', { name: `Aktifkan kembali ${product.name}?` }).getByRole('button', { name: 'Aktifkan kembali' }).click();
    await expect(page.getByText('Produk diaktifkan kembali.')).toBeVisible();
    await expect(page.getByRole('main').locator('[data-state="inactive"]')).toHaveCount(0);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a product changed meanwhile shows its current values, and the next save holds (UXS-17)', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    await page.goto(`/products/${product.id}/edit`);
    await page.getByLabel(/^Merek/).fill('Merek baru');

    // The same user changes it in another tab.
    const second = await context.newPage();
    await second.goto(`/products/${product.id}/edit`);
    await second.getByLabel('Harga jual', { exact: true }).fill('9000');
    await second.getByRole('button', { name: 'Simpan perubahan' }).click();
    await second.waitForURL((url) => url.pathname === `/products/${product.id}`);
    await second.close();

    await page.getByRole('button', { name: 'Simpan perubahan' }).click();
    await expect(page.getByText('Data sudah berubah')).toBeVisible();
    await expect(page.locator('.bk-kv').getByText('Rp9.000')).toBeVisible();
    await expect(page.getByLabel(/^Merek/)).toHaveValue('Merek baru');
    await checked(page);

    await page.getByRole('button', { name: 'Simpan perubahan' }).click();
    await page.waitForURL((url) => url.pathname === `/products/${product.id}`);
    await expect(page.getByText('Perubahan produk disimpan.')).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('categories: rename, deactivate and reactivate, by the filter; an inactive one is not offered', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Dewi');
    const name = await newCategory(page);
    const renamed = `${name} baru`;

    // UXI-204: Enter in the field sends nothing; "Batal" discards the name.
    await page.getByRole('button', { name: `Ubah nama kategori ${name}` }).click();
    const field = page.getByRole('main').getByLabel('Nama kategori');
    await expect(field).toBeFocused();
    await checked(page);
    await field.fill('Tidak dipakai');
    await page.getByRole('button', { name: 'Batal' }).click();
    await expect(page.getByRole('rowheader', { name, exact: true })).toBeVisible();

    await page.getByRole('button', { name: `Ubah nama kategori ${name}` }).click();
    await page.getByRole('main').getByLabel('Nama kategori').fill(renamed);
    await page.getByRole('main').getByLabel('Nama kategori').press('Enter');
    await expect(page.getByText('Nama kategori disimpan.')).toHaveCount(0);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Nama kategori disimpan.')).toBeVisible();
    await expect(page.getByRole('rowheader', { name: renamed, exact: true })).toBeVisible();

    // UXI-205, in its dialog, then the filter.
    await page.getByRole('button', { name: `Tindakan lain ${renamed}` }).click();
    await page.getByRole('menuitem', { name: 'Nonaktifkan' }).click();
    const dialog = page.getByRole('dialog', { name: `Nonaktifkan kategori ${renamed}?` });
    await expect(dialog.getByText('Produk yang sudah memakainya tidak berubah.', { exact: false })).toBeVisible();
    await checked(page);
    await dialog.getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(page.getByText('Kategori dinonaktifkan.')).toBeVisible();
    await expect(page.getByRole('row', { name: new RegExp(renamed) }).locator('[data-state="inactive"]')).toBeVisible();

    await page.getByRole('button', { name: 'Aktif', exact: true }).click();
    await expect(page).toHaveURL(/status=active/);
    await expect(page.getByRole('rowheader', { name: renamed, exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Nonaktif', exact: true }).click();
    await expect(page).toHaveURL(/status=inactive/);
    await expect(page.getByRole('rowheader', { name: renamed, exact: true })).toBeVisible();

    // An inactive category is not offered for a new product (BR-CAT-09).
    await page.goto('/products/new');
    await page.getByLabel('Nama', { exact: true }).fill(`Z${letters(14)}`);
    await page.getByRole('button', { name: 'Cek', exact: true }).click();
    await page.getByRole('button', { name: 'Lanjut isi data' }).click();
    await expect(page.getByLabel('Kategori', { exact: true }).locator('option', { hasText: renamed })).toHaveCount(0);

    // It comes back.
    await page.goto('/categories?status=inactive');
    await page.getByRole('button', { name: `Tindakan lain ${renamed}` }).click();
    await page.getByRole('menuitem', { name: 'Aktifkan kembali' }).click();
    await page.getByRole('dialog', { name: `Aktifkan kembali kategori ${renamed}?` }).getByRole('button', { name: 'Aktifkan kembali' }).click();
    await expect(page.getByText('Kategori diaktifkan kembali.')).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a cashier has no catalogue in its navigation and is refused the pages', async ({ page, context }) => {
    await signedIn(context, page, 'Rina');

    await expect(page.getByRole('link', { name: 'Produk' })).toHaveCount(0);

    for (const path of ['/products', '/products/new', '/categories']) {
        const refused = await page.goto(path);

        expect(refused?.status(), path).toBe(403);
        await expect(page.getByText('Anda tidak memiliki akses')).toBeVisible();
    }
});
