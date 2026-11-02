import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands, receiptOf } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-305 and the entry on UXP-302 (AC-020-01, AC-020-02, AC-020-04; COPY_DECK section 8.6;
// NAVIGATION_CONTRACTS UXP-305; INTERACTION_INVENTORY UXI-174, UXI-190 to UXI-198; UI_STATES
// section 6; WF-17; suites UX01, UX02, BARCODE01; TASK-020, L5). Every product of a test gets new
// random texts, because the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const LOOKUP = /\/data\/barcode-tokens\/lookup/;

const SEARCH = /\/data\/products\/search/;

const COMMAND = '**/commands/barcode.correct_misassignment';

const TITLE = 'Koreksi salah pasang barcode';

const REVIEW = 'Tinjau koreksi salah pasang barcode';

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

interface NewProduct {
    readonly name: string;
    readonly barcode: string;
    readonly id: string;
}

/** A new product with a barcode through UXP-303; the page is left on its UXP-302. */
async function newProduct(page: Page, category: string): Promise<NewProduct> {
    const product = { name: `Z${letters(14)}`, sku: `SKU-${letters(8)}`, barcode: digits() };

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

    return { name: product.name, barcode: product.barcode, id: new URL(page.url()).pathname.split('/').at(-1) ?? '' };
}

/** UXI-174: from the barcodes' overflow of UXP-302 to UXP-305. */
async function openCorrection(page: Page, product: NewProduct): Promise<void> {
    await page.goto(`/products/${product.id}`);
    await page.getByRole('button', { name: 'Tindakan lain Barcode' }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${product.id}/barcode-correction`), page.getByRole('menuitem', { name: TITLE }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: TITLE })).toBeVisible();
}

/** UXI-190: a scan into "Barcode", whose Enter runs the lookup. */
async function scanToken(page: Page, token: string): Promise<void> {
    await Promise.all([page.waitForResponse(LOOKUP), (async () => {
        await page.getByLabel('Barcode', { exact: true }).fill(token);
        await page.getByLabel('Barcode', { exact: true }).press('Enter');
    })()]);
}

/** "Produk yang benar" by its role: while results stand, their list carries the same name. */
function productInput(page: Page): Locator {
    return page.getByRole('textbox', { name: 'Produk yang benar', exact: true });
}

/** UXI-191: the product chosen from the shared Product Search. */
async function chooseProduct(page: Page, product: NewProduct): Promise<void> {
    const field = page.getByLabel('Produk yang benar', { exact: true });

    await field.fill(product.name);
    await page.getByRole('list', { name: 'Produk yang benar' }).getByRole('button', { name: product.name }).click();
    await expect(field).toHaveValue(product.name);
    await expect(page.getByRole('list', { name: 'Produk yang benar' })).toHaveCount(0);
}

/** "Tinjau" and "Konfirmasi koreksi", with the password when the page asks for it. */
async function confirmCorrection(page: Page, password?: string): Promise<void> {
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await page.getByRole('dialog', { name: REVIEW }).getByRole('button', { name: 'Konfirmasi koreksi' }).click();

    if (password !== undefined) {
        const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

        await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
        await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
    }
}

// 1440 × 900, the 200 % zoom of 1280 × 800 and 320 × 640.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`the Owner moves an active barcode to the correct product, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);
        const password = await signedIn(context, page, 'Hendra');
        const category = await newCategory(page);
        const right = await newProduct(page, category);
        const wrong = await newProduct(page, category);
        let sent = 0;

        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().includes('/commands/barcode.correct_misassignment')) {
                sent += 1;
            }
        });

        await openCorrection(page, wrong);
        await expect(page.getByText('Pindahkan barcode pabrik dari produk yang salah ke produk yang benar. Riwayatnya tetap tersimpan.')).toBeVisible();

        // UXI-193: disabled with its reason, until the form is whole (UXS-13).
        await expect(page.getByRole('button', { name: 'Tinjau' })).toHaveAttribute('aria-disabled', 'true');
        await expect(page.getByText('Isi barcode, produk yang benar, dan alasan.')).toBeVisible();
        await expect(page.getByLabel('Produk yang benar', { exact: true })).toHaveCount(0);
        await checked(page);

        // UXI-190: the scan's Enter looks it up and sends nothing.
        await scanToken(page, wrong.barcode);
        await expect(page.getByText(`Sekarang terpasang pada ${wrong.name}`)).toBeVisible();
        expect(sent).toBe(0);

        // UXI-191: the product the token is on is not offered.
        await page.getByLabel('Produk yang benar', { exact: true }).fill(wrong.name);
        await expect(page.getByText('Tidak ada hasil', { exact: false })).toBeVisible();
        await chooseProduct(page, right);

        // UXI-192: Enter in the reason submits nothing (UXA-10).
        const reason = `Salah tempel ${letters(6)}`;
        await page.getByLabel('Alasan', { exact: true }).fill(reason);
        await page.getByLabel('Alasan', { exact: true }).press('Enter');
        await expect(page.getByRole('dialog', { name: REVIEW })).toHaveCount(0);
        await checked(page);

        // UXI-193 and UXI-194: the review names the move, starts on "Kembali ubah", and Esc goes back with every field kept.
        await page.getByRole('button', { name: 'Tinjau' }).click();
        const review = page.getByRole('dialog', { name: REVIEW });
        await expect(review.getByText(`Barcode ${wrong.barcode} dipindahkan dari ${wrong.name} ke ${right.name}. Penjualan sebelumnya tetap tercatat pada produk saat itu.`)).toBeVisible();
        await expect(review.getByText(reason)).toBeVisible();
        await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
        await checked(page);
        await page.keyboard.press('Escape');
        await expect(review).toHaveCount(0);
        await expect(page.getByLabel('Barcode', { exact: true })).toHaveValue(wrong.barcode);
        await expect(page.getByLabel('Produk yang benar', { exact: true })).toHaveValue(right.name);
        await expect(page.getByLabel('Alasan', { exact: true })).toHaveValue(reason);

        // Enter on the final action sends nothing (UXA-10); the password is asked first (UXS-11).
        await page.getByRole('button', { name: 'Tinjau' }).click();
        await review.getByRole('button', { name: 'Konfirmasi koreksi' }).focus();
        await page.keyboard.press('Enter');
        expect(sent).toBe(0);
        await expect(review).toBeVisible();
        await review.getByRole('button', { name: 'Kembali ubah' }).click();

        await Promise.all([page.waitForURL((url) => url.pathname === `/products/${right.id}`), confirmCorrection(page, password)]);
        await expect(page.getByText(`Barcode ${wrong.barcode} sekarang terpasang pada ${right.name}.`)).toBeVisible();
        await expect(page.getByRole('rowheader', { name: wrong.barcode })).toBeVisible();
        await checked(page);

        // The wrong product keeps the history, retired as a misassignment (BR-CAT-50).
        await page.goto(`/products/${wrong.id}`);
        await expect(page.getByRole('rowheader', { name: wrong.barcode })).toBeVisible();
        await expect(page.getByText('Dipensiunkan · salah pasang')).toBeVisible();

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    });
}

test('a barcode retired as a misassignment is attached again; what cannot be corrected says why, and an unknown code is kept', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const password = await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const right = await newProduct(page, category);
    const wrong = await newProduct(page, category);

    // Retired as a misassignment, on UXP-302.
    await page.getByRole('button', { name: `Tindakan lain ${wrong.barcode}` }).click();
    await page.getByRole('menuitem', { name: 'Pensiunkan barcode' }).click();
    const retire = page.getByRole('dialog', { name: `Pensiunkan barcode ${wrong.barcode}?` });
    await retire.getByRole('radio', { name: 'Salah pasang' }).click();
    await retire.getByRole('button', { name: 'Pensiunkan barcode' }).click();
    await expect(page.getByText('Barcode dipensiunkan.')).toBeVisible();

    // An internal code of the product, for the refusal below.
    await page.getByRole('button', { name: 'Buat barcode internal' }).click();
    await expect(page.getByRole('rowheader', { name: /^BK-P-/ })).toBeVisible();
    const internal = ((await page.getByRole('rowheader', { name: /^BK-P-/ }).locator('.bk-token').textContent()) ?? '').trim();

    await openCorrection(page, wrong);

    // UXS-33: a code that was never mapped stays in the field.
    const unknown = digits();
    await scanToken(page, unknown);
    await expect(page.getByText(`Tidak ada produk dengan kode “${unknown}”.`, { exact: false })).toBeVisible();
    await expect(page.getByLabel('Barcode', { exact: true })).toHaveValue(unknown);
    await expect(page.getByLabel('Produk yang benar', { exact: true })).toHaveCount(0);
    await checked(page);

    // UXS-38: never a `BK-P-` code.
    await scanToken(page, internal);
    await expect(page.getByText('Barcode ini tidak bisa dikoreksi.', { exact: false })).toBeVisible();
    await expect(page.getByLabel('Produk yang benar', { exact: true })).toHaveCount(0);
    await checked(page);

    // UXI-190, retired as a misassignment; the scan into "Produk yang benar" chooses by its code.
    await scanToken(page, wrong.barcode);
    await expect(page.getByText(`Dipensiunkan karena salah pasang dari ${wrong.name}`)).toBeVisible();

    // The product it left is not offered either (G-09; review findings R-01, R-11).
    await page.getByLabel('Produk yang benar', { exact: true }).fill(wrong.name);
    await expect(page.getByText('Tidak ada hasil', { exact: false })).toBeVisible();

    await page.getByLabel('Produk yang benar', { exact: true }).fill(right.barcode);
    await productInput(page).press('Enter');
    await expect(productInput(page)).toHaveValue(right.name);
    await page.getByLabel('Alasan', { exact: true }).fill('Terbukti salah pasang');

    // Ctrl+Enter opens the review, whose text names a re-attachment.
    await page.getByLabel('Alasan', { exact: true }).press('Control+Enter');
    const review = page.getByRole('dialog', { name: REVIEW });
    await expect(review.getByText(`Barcode ${wrong.barcode}, yang dipensiunkan karena salah pasang dari ${wrong.name}, dipasang ke ${right.name}.`)).toBeVisible();
    await page.keyboard.press('Escape');

    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${right.id}`), confirmCorrection(page, password)]);
    await expect(page.getByText(`Barcode ${wrong.barcode} sekarang terpasang pada ${right.name}.`)).toBeVisible();

    // UXI-197 and UXI-198: with something entered, leaving asks first; "Batal" and the way back lead to UXP-302.
    await openCorrection(page, right);
    await page.getByLabel('Alasan', { exact: true }).fill('Belum jadi');
    await page.getByRole('link', { name: 'Kembali ke Detail produk' }).click();
    const unsaved = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
    await expect(unsaved).toBeVisible();
    await unsaved.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    await page.getByRole('link', { name: 'Batal' }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${right.id}`), unsaved.getByRole('button', { name: 'Tinggalkan' }).click()]);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a mapping that changed since the lookup is said in place and read again (UXS-17)', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const password = await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const third = await newProduct(page, category);
    const right = await newProduct(page, category);
    const wrong = await newProduct(page, category);
    const second = await context.newPage();

    await openCorrection(page, wrong);
    await scanToken(page, wrong.barcode);
    await expect(page.getByText(`Sekarang terpasang pada ${wrong.name}`)).toBeVisible();
    await chooseProduct(page, third);
    await page.getByLabel('Alasan', { exact: true }).fill('Alasan pertama');

    // Another tab moves it first.
    await openCorrection(second, wrong);
    await scanToken(second, wrong.barcode);
    await chooseProduct(second, right);
    await second.getByLabel('Alasan', { exact: true }).fill('Alasan kedua');
    await Promise.all([second.waitForURL((url) => url.pathname === `/products/${right.id}`), confirmCorrection(second, password)]);
    await second.close();

    // This tab's recent authentication is the same session's: no second password.
    await confirmCorrection(page);
    await expect(page.getByText('Data sudah berubah')).toBeVisible();
    await expect(page.getByText(`Sekarang terpasang pada ${right.name}`)).toBeVisible();
    await expect(page.getByRole('dialog', { name: REVIEW })).toHaveCount(0);
    await checked(page);

    // The next review expects the mapping as it is now.
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${third.id}`), confirmCorrection(page)]);
    await expect(page.getByText(`Barcode ${wrong.barcode} sekarang terpasang pada ${third.name}.`)).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a failed read is said where it was asked and never reads as an unknown code; a retry recovers (UXS-22)', async ({ browser }) => {
    // Review finding R-04.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const right = await newProduct(page, category);
    const wrong = await newProduct(page, category);

    await openCorrection(page, wrong);

    // RD-05.
    await page.route(LOOKUP, (route) => route.abort());
    await page.getByLabel('Barcode', { exact: true }).fill(wrong.barcode);
    await page.getByLabel('Barcode', { exact: true }).press('Enter');
    await expect(page.getByText('Gagal memuat')).toBeVisible();
    await expect(page.getByText('Kode tidak dikenal')).toHaveCount(0);
    await expect(page.getByText('Sekarang terpasang pada', { exact: false })).toHaveCount(0);
    await expect(page.getByLabel('Produk yang benar', { exact: true })).toHaveCount(0);
    await page.unroute(LOOKUP);
    await page.getByRole('button', { name: 'Coba lagi' }).click();
    await expect(page.getByText(`Sekarang terpasang pada ${wrong.name}`)).toBeVisible();
    await expect(page.getByText('Gagal memuat')).toHaveCount(0);

    // RD-01 in its context.
    await page.route(SEARCH, (route) => route.abort());
    await page.getByLabel('Produk yang benar', { exact: true }).fill(right.name);
    await expect(page.getByText('Gagal memuat')).toBeVisible();
    await expect(page.getByText('Tidak ada hasil')).toHaveCount(0);
    await page.unroute(SEARCH);
    await page.getByRole('button', { name: 'Coba lagi' }).click();
    await expect(page.getByRole('list', { name: 'Produk yang benar' }).getByRole('button', { name: right.name })).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a late answer of the product search chooses nothing for another token (TR-PAGE-04)', async ({ browser }) => {
    // Review finding R-02.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const right = await newProduct(page, category);
    const other = await newProduct(page, category);
    const wrong = await newProduct(page, category);

    await openCorrection(page, wrong);
    await scanToken(page, wrong.barcode);

    // The exact read of "Produk yang benar" is held while the Owner scans another token.
    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    await page.route(SEARCH, async (route) => {
        await held;
        await route.continue();
    });
    const asked = page.waitForRequest(SEARCH);
    await page.getByLabel('Produk yang benar', { exact: true }).fill(right.barcode);
    await page.getByLabel('Produk yang benar', { exact: true }).press('Enter');
    await asked;

    await scanToken(page, other.barcode);
    await expect(page.getByText(`Sekarang terpasang pada ${other.name}`)).toBeVisible();

    const answered = page.waitForResponse(SEARCH);
    release();
    await answered;
    await page.unroute(SEARCH);

    // The new token's form is as it was: no product, and "Tinjau" says why.
    await page.getByLabel('Alasan', { exact: true }).fill('Alasan');
    await expect(page.getByLabel('Produk yang benar', { exact: true })).toHaveValue('');
    await expect(page.getByRole('button', { name: 'Tinjau' })).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByText('Isi barcode, produk yang benar, dan alasan.')).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a next page that a new search supersedes leaves "Muat lebih banyak" ready', async ({ browser }) => {
    // Review finding R-03.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const wrong = await newProduct(page, category);

    await openCorrection(page, wrong);
    await scanToken(page, wrong.barcode);

    // Stubbed answers with another page; the second page is held until the test ends.
    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    const row = (index: number): Record<string, unknown> => ({ id: 900000 + index, name: `Produk contoh ${index}`, sku: `CTH-${index}`, baseUnit: 'pcs', quantityPrecision: 0, price: { amount: '1000', revisionId: index }, active: true, thumbnail: null, activeBarcode: false });
    await page.route(SEARCH, async (route) => {
        if (new URL(route.request().url()).searchParams.get('page') === '2') {
            await held;
        }

        await route.fulfill({ json: { rows: [row(1)], page: 1, perPage: 25, hasMore: true } });
    });

    await page.getByLabel('Produk yang benar', { exact: true }).fill('satu');
    await page.getByRole('button', { name: 'Muat lebih banyak' }).click();
    await expect(page.getByRole('button', { name: 'Muat lebih banyak' })).toHaveCount(0);

    await productInput(page).fill('dua');
    await expect(page.getByRole('button', { name: 'Muat lebih banyak' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Muat lebih banyak' })).not.toHaveAttribute('aria-disabled', 'true');

    const late = page.waitForResponse((response) => new URL(response.url()).searchParams.get('page') === '2');
    release();
    await late;
    await page.unroute(SEARCH);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('an uncertain correction keeps its panel, blocks another, and its repeat commits once (NC-13)', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const password = await signedIn(context, page, 'Hendra');
    const category = await newCategory(page);
    const right = await newProduct(page, category);
    const wrong = await newProduct(page, category);

    await openCorrection(page, wrong);
    await scanToken(page, wrong.barcode);
    await chooseProduct(page, right);
    await page.getByLabel('Alasan', { exact: true }).fill('Alasan');

    // The answer is lost once: UXS-14, with the fields kept as they were sent.
    await page.route(COMMAND, (route) => route.abort());
    await confirmCorrection(page, password);
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await expect(page.getByText('Jangan mengirim koreksi lain sampai hasil ini diketahui.')).toBeVisible();
    await expect(page.getByLabel('Barcode', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Alasan', { exact: true })).toBeDisabled();
    await checked(page);
    await page.unroute(COMMAND);

    // UXI-196: "Kirim ulang" sends the same request, which commits.
    await Promise.all([page.waitForURL((url) => url.pathname === `/products/${right.id}`), page.getByRole('button', { name: 'Kirim ulang' }).click()]);
    await expect(page.getByRole('rowheader', { name: wrong.barcode })).toBeVisible();

    // UXI-174: an unresolved correction of this user disables the entry, with the way to UXP-006.
    await receiptOf(context, 'Hendra', 'barcode.correct_misassignment');
    await page.goto(`/products/${wrong.id}`);
    await expect(page.getByRole('button', { name: TITLE })).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByText('Ada koreksi barcode yang statusnya belum pasti. Cek statusnya dulu.')).toBeVisible();
    await expect(page.getByRole('main').getByRole('link', { name: 'Buka Permintaan belum pasti' }).first()).toBeVisible();
    await checked(page);

    // A direct visit opens on the request's panel, with "Cek status" only (UXS-14; NC-13 (b)).
    await page.goto(`/products/${wrong.id}/barcode-correction`);
    await expect(page.getByRole('button', { name: 'Cek status' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Kirim ulang' })).toHaveCount(0);
    await expect(page.getByLabel('Barcode', { exact: true })).toBeDisabled();
    await prepareCommands(context, ['Hendra']);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('only the Owner is offered the correction: an Operations admin neither sees it nor opens it', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const product = await newProduct(page, category);

    // UXP-302 shows the barcodes, without the entry.
    await expect(page.getByRole('rowheader', { name: product.barcode })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tindakan lain Barcode' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: TITLE })).toHaveCount(0);

    // The page and the read answer FORBIDDEN (SEC-AZ-07: hiding is not the authorization).
    const answer = await page.goto(`/products/${product.id}/barcode-correction`);
    expect(answer?.status()).toBe(403);
    await expect(page.getByText('Anda tidak memiliki akses')).toBeVisible();
    const read = await context.request.get(`/data/barcode-tokens/lookup?token=${product.barcode}`, { headers: { Accept: 'application/json' } });
    expect(read.status()).toBe(403);

    expect(injected(watched.problems).filter((problem) => !problem.includes('status of 403'))).toEqual([]);
    await context.close();
});
