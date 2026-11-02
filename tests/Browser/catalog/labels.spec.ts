import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The photo of UXP-302, UXP-306 and the print view RP-306P (AC-018-01, AC-018-02, AC-018-05;
// COPY_DECK sections 8.3, 8.7 and 17.3; NAVIGATION_CONTRACTS UXP-306; UI_STATES section 6;
// DOCUMENT_CONTRACTS DC-05; suites UX01, UX02, MEDIA01, SESSION08; TASK-018, L5). Every product
// of a test gets new random texts, because the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

function letters(length: number): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)), (byte) => String.fromCharCode(97 + (byte % 26))).join('');
}

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

interface NewProduct {
    readonly name: string;
    readonly sku: string;
    readonly barcode: string;
    readonly id: string;
}

/** A new product with a barcode through the pages; the page is left on its UXP-302. */
async function newProduct(page: Page): Promise<NewProduct> {
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
    await page.getByLabel('Harga jual', { exact: true }).fill('7777');
    await page.getByRole('button', { name: 'Simpan produk' }).click();
    await page.waitForURL((url) => /^\/products\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT });

    return { ...product, id: new URL(page.url()).pathname.split('/').at(-1) ?? '' };
}

/** A PNG made in the browser at run time, never a committed image. */
async function pngFile(page: Page): Promise<{ name: string; mimeType: string; buffer: Buffer }> {
    const data = await page.evaluate(() => {
        const canvas = document.createElement('canvas');

        canvas.width = 64;
        canvas.height = 32;
        const drawing = canvas.getContext('2d');

        if (drawing !== null) {
            drawing.fillStyle = '#116949';
            drawing.fillRect(0, 0, 32, 32);
        }

        return canvas.toDataURL('image/png').split(',')[1] ?? '';
    });

    return { name: 'foto.png', mimeType: 'image/png', buffer: Buffer.from(data, 'base64') };
}

/** The Browser's print dialog, counted instead of opened, in every page of the context. */
async function countPrints(context: BrowserContext): Promise<void> {
    await context.addInitScript(() => {
        window.print = () => {
            const counted = window as unknown as { printed?: number };

            counted.printed = (counted.printed ?? 0) + 1;
        };
    });
}

/**
 * What the print view's own tab reports, from before its first script (SEC-HDR-03; review
 * finding R-03): `watch` of one page never sees another page of the context.
 */
async function watchTabs(context: BrowserContext): Promise<string[]> {
    const problems: string[] = [];

    await context.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            console.error(`CSP violation: ${event.violatedDirective} ${event.blockedURI}`);
        });
    });
    context.on('page', (tab) => {
        tab.on('console', (message) => {
            if (message.type() === 'error' || message.type() === 'warning') {
                problems.push(`console ${message.type()}: ${message.text()}`);
            }
        });
        tab.on('pageerror', (error) => problems.push(`page error: ${error.message}`));
    });

    return problems;
}

// 1440 × 900, the 200 % zoom of 1280 × 800 and 320 × 640.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`an Operations admin sets, replaces and removes a photo, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Agus');
        const product = await newProduct(page);

        // The placeholder, and the rules; no menu to remove what is not there.
        await expect(page.getByRole('heading', { name: 'Foto produk' })).toBeVisible();
        await expect(page.getByRole('img', { name: 'Belum ada foto' })).toBeVisible();
        await expect(page.getByText('JPG, PNG, atau WebP, paling besar 8 MB.')).toBeVisible();
        await checked(page);

        // A refused file, judged by its content, stays at its field and saves nothing.
        const upload = page.getByLabel('Unggah foto');

        await upload.setInputFiles({ name: 'foto.png', mimeType: 'image/png', buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>') });
        await expect(page.getByText('Berkas harus JPG, PNG, atau WebP.')).toBeVisible();
        await expect(page.getByRole('img', { name: 'Belum ada foto' })).toBeVisible();
        await checked(page);

        // A file above the limit, also above every outer limit, is refused at the field,
        // and no request leaves (review finding R-02). Enter opens no chooser (UXA-10).
        let posted = 0;
        let chooserOpened = 0;
        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().includes('/commands/product_photo.set')) {
                posted += 1;
            }
        });
        page.on('filechooser', () => {
            chooserOpened += 1;
        });
        await upload.setInputFiles({ name: 'foto.png', mimeType: 'image/png', buffer: Buffer.alloc(12 * 1024 * 1024) });
        await expect(page.getByText('Berkas paling besar 8 MB.')).toBeVisible();
        expect(posted).toBe(0);
        await upload.focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(200);
        expect(chooserOpened).toBe(0);

        // Accepted: the page shows it through RD-07.
        const file = await pngFile(page);

        await upload.setInputFiles(file);
        await expect(page.getByText('Foto disimpan.')).toBeVisible();
        expect(posted).toBe(1);
        const photo = page.getByRole('img', { name: `Foto ${product.name}` });

        await expect(photo).toBeVisible();
        await expect.poll(() => photo.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBe(64);
        expect(new URL((await photo.getAttribute('src')) ?? '', page.url()).pathname).toMatch(/^\/media\/\d+$/);
        const first = await photo.getAttribute('src');
        await checked(page);

        // The list shows its thumbnail.
        await page.goto(`/products?q=${product.sku}`);
        const thumbnail = page.getByRole('row', { name: new RegExp(product.name) }).getByRole('img', { name: `Foto ${product.name}` });

        await expect(thumbnail).toBeVisible();
        await expect.poll(() => thumbnail.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBe(64);
        expect(await thumbnail.getAttribute('src')).toBe(`${first ?? ''}?variant=thumb`);

        // Replaced: another file, and the page moves on to it.
        await page.goto(`/products/${product.id}`);
        await page.getByLabel('Ganti foto').setInputFiles(file);
        await expect(page.getByText('Foto disimpan.')).toBeVisible();
        await expect.poll(() => photo.getAttribute('src')).not.toBe(first);

        // Removed, in its dialog; the placeholder comes back.
        await page.getByRole('button', { name: /Foto produk/ }).click();
        await page.getByRole('menuitem', { name: 'Lepas foto' }).click();
        const dialog = page.getByRole('dialog', { name: 'Lepas foto produk?' });

        await expect(dialog.getByText('Foto tidak ditampilkan lagi. Berkasnya tetap disimpan.')).toBeVisible();
        await checked(page);
        await dialog.getByRole('button', { name: 'Lepas foto' }).click();
        await expect(page.getByText('Foto dilepas.')).toBeVisible();
        await expect(page.getByRole('img', { name: 'Belum ada foto' })).toBeVisible();
        await expect(page.getByLabel('Unggah foto')).toBeAttached();

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    });

    test(`an Operations admin prints labels with code, name and SKU and never a price, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        await countPrints(context);
        const problems = await watchTabs(context);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Agus');
        const product = await newProduct(page);

        // UXI-175, and the way back of UXI-201.
        await page.getByRole('link', { name: 'Cetak label barcode' }).click();
        await page.waitForURL((url) => url.pathname === `/products/${product.id}/labels`);
        await expect(page.getByRole('heading', { level: 1, name: 'Cetak label barcode' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Detail produk' })).toBeVisible();
        await expect(page.getByText('Ukuran label 50 × 30 mm.')).toBeVisible();

        // "Barcode" only with more than one active barcode.
        await expect(page.getByLabel('Barcode', { exact: true })).toHaveCount(0);
        await expect(page.getByText(product.barcode)).toBeVisible();
        await checked(page);

        // A field's label is a field's label, not the size of a printed one (review finding R-01).
        const fieldLabel = await page.locator('.bk-label').first().boundingBox();

        expect(fieldLabel?.height ?? 0).toBeLessThan(40);
        await expect(page.locator('.bk-barcode-label')).toHaveCount(0);

        // The field keeps to its limits, and Enter never prints (UXA-10).
        const quantity = page.getByLabel(/^Jumlah label/);
        const print = page.getByRole('button', { name: 'Cetak', exact: true });

        for (const refused of ['', '0', '101', 'abc']) {
            await quantity.fill(refused);
            await expect(print).toHaveAttribute('aria-disabled', 'true');
        }

        await quantity.fill('3');
        await expect(print).not.toHaveAttribute('aria-disabled', 'true');
        let sent = 0;
        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().includes('/commands/document.print_labels')) {
                sent += 1;
            }
        });
        await quantity.press('Enter');
        await page.waitForTimeout(200);
        expect(sent).toBe(0);

        // "Cetak" records the request, and the print view opens in its own tab.
        const [tab] = await Promise.all([context.waitForEvent('page'), print.click()]);

        await tab.waitForURL((url) => new RegExp(`^/products/${product.id}/labels/print/\\d+$`).test(url.pathname));
        expect(sent).toBe(1);
        const labels = tab.locator('.bk-barcode-label');

        await expect(labels).toHaveCount(3);
        await expect(tab.getByRole('heading', { level: 1, name: 'Cetak label barcode' })).toBeAttached();
        await expect(labels.first().getByText(product.name)).toBeVisible();
        await expect(labels.first().getByText(product.barcode, { exact: true })).toBeVisible();
        await expect(labels.first().getByText(`SKU ${product.sku}`)).toBeVisible();
        await expect(labels.first()).not.toContainText('7777');
        await expect(labels.first()).not.toContainText('Rp');

        // The bars: black on white, ten modules of quiet zone, at real size in millimetres.
        const symbol = await labels.first().locator('svg').evaluate((svg) => {
            const parts = (svg.getAttribute('viewBox') ?? '').split(' ').map(Number);
            const group = svg.querySelector('g');

            return {
                name: svg.getAttribute('aria-label'),
                modules: parts[2] ?? 0,
                background: svg.querySelector(':scope > rect')?.getAttribute('fill'),
                bars: group?.getAttribute('fill'),
                transform: group?.getAttribute('transform'),
                count: group?.querySelectorAll('rect').length ?? 0,
                styled: svg.matches('[style]') || svg.querySelector('[style]') !== null,
                width: svg.getBoundingClientRect().width,
                labelWidth: svg.parentElement?.getBoundingClientRect().width,
                labelHeight: svg.parentElement?.getBoundingClientRect().height,
            };
        });

        expect(symbol.name).toBe(product.barcode);
        expect(symbol.background).toBe('#ffffff');
        expect(symbol.bars).toBe('#000000');
        expect(symbol.transform).toBe('translate(10, 0)');
        expect(symbol.styled, 'the content security policy refuses a style attribute').toBe(false);
        expect(symbol.count).toBeGreaterThan(10);
        expect(symbol.modules).toBeGreaterThan(40);
        // 96 CSS pixels to the inch, 25.4 mm to the inch.
        expect(Math.abs((symbol.labelWidth ?? 0) - (50 * 96) / 25.4)).toBeLessThan(1);
        expect(Math.abs((symbol.labelHeight ?? 0) - (30 * 96) / 25.4)).toBeLessThan(1);
        expect(Math.abs(symbol.width - (50 * 96) / 25.4)).toBeLessThan(1);

        // The print dialog opens once; printing shows only the labels (UXA-23).
        await expect.poll(() => tab.evaluate(() => (window as unknown as { printed?: number }).printed ?? 0)).toBe(1);
        await tab.emulateMedia({ media: 'print' });
        await expect(labels.first()).toBeVisible();
        await expect(tab.getByRole('heading', { level: 1 })).toBeHidden();
        await tab.emulateMedia({ media: 'screen' });
        await checked(tab);
        await tab.close();

        // The page stays for another print, with its number kept, recorded anew (RC-47).
        await expect(page).toHaveURL(new RegExp(`/products/${product.id}/labels$`));
        await expect(quantity).toHaveValue('3');
        const [second] = await Promise.all([context.waitForEvent('page'), print.click()]);

        await second.waitForURL((url) => /\/labels\/print\/\d+$/.test(url.pathname));
        expect(sent).toBe(2);
        await second.close();

        // The way back, and UXI-163 from the list's row.
        await page.getByRole('link', { name: 'Kembali ke Detail produk' }).click();
        await page.waitForURL((url) => url.pathname === `/products/${product.id}`);
        await page.goto(`/products?q=${product.sku}`);
        await page.getByRole('row', { name: new RegExp(product.name) }).getByRole('button', { name: /Tindakan lain/ }).click();
        await page.getByRole('menuitem', { name: 'Cetak label barcode' }).click();
        await page.waitForURL((url) => url.pathname === `/products/${product.id}/labels`);

        expect(injected(watched.problems)).toEqual([]);
        expect(injected(problems)).toEqual([]);
        await context.close();
    });
}

test('the barcode chosen is the one printed, and the page works from the keyboard', async ({ browser }) => {
    // UXI-199 with two active barcodes (review finding R-07).
    const context = await contextAt(browser, WIDTHS[0]!);
    await countPrints(context);
    const page = await context.newPage();

    await signedIn(context, page, 'Agus');
    const product = await newProduct(page);

    await page.getByRole('button', { name: 'Buat barcode internal' }).click();
    await expect(page.getByText(/^Barcode internal BK-P-[0-9A-Z]{16} dibuat\.$/)).toBeVisible();
    const generated = /BK-P-[0-9A-Z]{16}/.exec((await page.getByText(/^Barcode internal BK-P-/).textContent()) ?? '')?.[0] ?? '';

    await page.goto(`/products/${product.id}/labels`);
    const barcode = page.getByLabel(/^Barcode/);

    await expect(barcode.locator('option')).toHaveText([product.barcode, generated]);
    await expect(barcode).toHaveValue(await barcode.locator('option').first().getAttribute('value') ?? '');
    await barcode.selectOption({ label: generated });

    // The keyboard path: the fields in order, Enter sends nothing, Space prints.
    await barcode.focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel(/^Jumlah label/)).toBeFocused();
    await page.keyboard.press('Tab');
    const print = page.getByRole('button', { name: 'Cetak', exact: true });

    await expect(print).toBeFocused();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(200);
    expect(context.pages()).toHaveLength(1);
    const [tab] = await Promise.all([context.waitForEvent('page'), page.keyboard.press('Space')]);

    await tab.waitForURL((url) => /\/labels\/print\/\d+$/.test(url.pathname));
    await expect(tab.locator('.bk-barcode-label')).toHaveCount(1);
    await expect(tab.locator('.bk-barcode-label').getByText(generated, { exact: true })).toBeVisible();
    await tab.close();

    // The page keeps its choice for another print.
    await expect(barcode.locator('option:checked')).toHaveText(generated);
    await context.close();
});

test('a request no longer active is stale, a failed print is asked again, and a cashier reaches nothing', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    await countPrints(context);
    const page = await context.newPage();

    await signedIn(context, page, 'Agus');
    const product = await newProduct(page);

    await page.goto(`/products/${product.id}/labels`);
    const print = page.getByRole('button', { name: 'Cetak', exact: true });

    // UXS-44: the server's answer is lost, the tab closes, and the same request is asked again.
    await page.route('**/commands/document.print_labels', (route) => route.abort());
    await print.click();
    await expect(page.getByText('Cetak atau unduh gagal')).toBeVisible({ timeout: 90_000 });
    await expect.poll(() => context.pages().length).toBe(1);

    // What is frozen stays read-only (review finding R-05), and the same request goes again.
    const quantity = page.getByLabel(/^Jumlah label/);

    await expect(quantity).toBeDisabled();
    await page.unroute('**/commands/document.print_labels');
    const [tab] = await Promise.all([context.waitForEvent('page'), page.getByRole('button', { name: 'Coba lagi' }).click()]);

    await tab.waitForURL((url) => /\/labels\/print\/\d+$/.test(url.pathname));
    await tab.close();
    await expect(page.getByText('Cetak atau unduh gagal')).toHaveCount(0);
    await expect(quantity).toBeEnabled();

    // A page to reload (UXS-09): the tab closes and "Cetak" opens no other (review finding R-06).
    await page.route('**/commands/document.print_labels', (route) => route.fulfill({ status: 419, contentType: 'application/json', body: '{}' }));
    await print.click();
    await expect(page.getByRole('button', { name: 'Muat ulang' })).toBeVisible();
    await expect(print).toHaveAttribute('aria-disabled', 'true');
    await expect.poll(() => context.pages().length).toBe(1);
    await print.click({ force: true });
    await page.waitForTimeout(200);
    expect(context.pages()).toHaveLength(1);
    await page.unroute('**/commands/document.print_labels');
    await page.reload();
    await expect(print).not.toHaveAttribute('aria-disabled', 'true');

    // UXS-17: the barcode was retired in another tab; the page reads its barcodes again.
    const other = await context.newPage();

    await other.goto(`/products/${product.id}`);
    await other.getByRole('button', { name: new RegExp(product.barcode) }).click();
    await other.getByRole('menuitem', { name: 'Pensiunkan barcode' }).click();
    await other.getByRole('radio', { name: 'Alasan lain' }).click();
    await other.getByRole('dialog').getByRole('button', { name: 'Pensiunkan barcode' }).click();
    await expect(other.getByText('Barcode dipensiunkan.')).toBeVisible();
    await other.close();

    await print.click();
    await expect(page.getByText('Data sudah berubah')).toBeVisible();
    await expect(page.getByText(product.barcode)).toHaveCount(0);
    await expect(print).toHaveAttribute('aria-disabled', 'true');
    await expect.poll(() => context.pages().length).toBe(1);
    await context.close();

    // A cashier: no page, whatever the address.
    const cashierContext = await contextAt(browser, WIDTHS[0]!);
    const cashier = await cashierContext.newPage();

    await signedIn(cashierContext, cashier, 'Rina');
    const refused = await cashier.goto(`/products/${product.id}/labels`);

    expect(refused?.status()).toBe(403);
    await cashierContext.close();
});
