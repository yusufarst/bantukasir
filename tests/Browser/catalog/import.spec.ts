import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-308 "Impor produk (CSV)" (AC-019-01 to AC-019-04; COPY_DECK section 8.9; NAVIGATION_CONTRACTS
// UXP-308, NC-03; UI_STATES section 6; WF-18; suites IMPORT01, UX01, UX02; TASK-019, L5). Every file
// of a test holds new random texts, because the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const PAGE = '/products/import';

const HEADER = 'SKU;Nama;Kategori;Merek;Satuan;Desimal;Harga jual;Stok minimum;Barcode';

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

/** A new category through its page. */
async function newCategory(page: Page): Promise<string> {
    const category = `Kategori ${letters(10)}`;

    await page.goto('/categories');
    await page.getByRole('button', { name: 'Tambah kategori' }).click();
    await page.getByLabel('Nama kategori', { exact: true }).fill(category);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText(`Kategori ${category} ditambahkan.`)).toBeVisible();

    return category;
}

function csvFile(rows: readonly string[], bom = false): { name: string; mimeType: string; buffer: Buffer } {
    return { name: 'produk.csv', mimeType: 'text/csv', buffer: Buffer.from(`${bom ? '﻿' : ''}${[HEADER, ...rows].join('\r\n')}\r\n`, 'utf8') };
}

function upload(page: Page) {
    return page.getByLabel('Pilih berkas CSV');
}

// 1440 × 900, the 200 % zoom of 1280 × 800 and 320 × 640.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`an Operations admin reads the columns, previews and imports a file, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Agus');
        const category = await newCategory(page);
        const first = { sku: `SKU-${letters(8)}`, name: `Z${letters(12)}` };
        const second = { sku: `SKU-${letters(8)}`, name: `Z${letters(12)}` };

        // UXI-206 and the empty upload state; UXI-011 is the current item of the sub-navigation.
        await page.goto(PAGE);
        await expect(page.getByRole('heading', { level: 1, name: 'Impor produk (CSV)' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Format kolom' })).toBeVisible();
        await expect(page.getByRole('listitem').filter({ hasText: /^Harga jual$/ })).toBeVisible();
        await expect(page.getByRole('listitem').filter({ hasText: /^Barcode \(opsional\)$/ })).toBeVisible();
        await expect(page.getByText('Paling besar 2 MB dan 5.000 baris data.')).toBeVisible();
        await expect(page.getByText('Belum ada berkas yang dipilih.')).toBeVisible();
        await checked(page);

        // UXI-207, UXI-208 and the review: the file with a mark, Indonesian numbers.
        await upload(page).setInputFiles(csvFile([`${first.sku};${first.name};${category};;pcs;0;15.000;;`, `${second.sku};${second.name};${category};;kg;1;Rp1.250;"1,5";`], true));
        await expect(page.getByRole('heading', { name: 'Pratinjau impor' })).toBeVisible();
        await expect(page.getByText('2 baris siap diimpor, 0 baris perlu diperbaiki.')).toBeVisible();
        await expect(page.getByText('Semua baris diimpor bersama, atau tidak ada yang diimpor.')).toBeVisible();
        await expect(page.getByText(first.name)).toBeVisible();
        await expect(page.getByText(second.sku)).toBeVisible();
        await expect(upload(page)).toHaveCount(0);
        await checked(page);

        // Enter never confirms (UXA-10).
        const confirm = page.getByRole('button', { name: 'Impor 2 produk' });

        await confirm.focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(200);
        await expect(page.getByRole('heading', { name: 'Pratinjau impor' })).toBeVisible();

        // UXI-210: on to the list of the new products, with the summary.
        await confirm.click();
        await page.waitForURL((url) => url.pathname === '/products' && /^\?import=\d+$/.test(url.search));
        await expect(page.getByText('2 produk baru ditambahkan.')).toBeVisible();
        await expect(page.getByRole('row')).toHaveCount(3);
        await expect(page.getByRole('link', { name: first.name })).toBeVisible();
        await expect(page.getByRole('link', { name: second.name })).toBeVisible();
        await checked(page);

        expect(injected(watched)).toEqual([]);
        await context.close();
    });
}

test('row errors stay in the preview and disable the confirmation, and a cancel removes the file', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const sku = `SKU-${letters(8)}`;
    const cancels: string[] = [];

    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().endsWith('/cancel')) {
            cancels.push(request.url());
        }
    });

    await page.goto(PAGE);
    await upload(page).setInputFiles(csvFile([`${sku};Benar;${category};;pcs;0;1000;;`, `${sku}-2;Salah;Tidak Ada ${letters(6)};;pcs;0;1000;;`, `=SUM(A1);Rumus;${category};;pcs;0;1000;;`]));

    await expect(page.getByText('1 baris siap diimpor, 2 baris perlu diperbaiki.')).toBeVisible();
    await expect(page.getByText('Berkas CSV belum benar')).toBeVisible();
    await expect(page.getByText('Ada 2 baris yang perlu diperbaiki.', { exact: false })).toBeVisible();
    await expect(page.getByText('Baris 3: Tidak ada kategori aktif dengan nama ini.')).toBeVisible();
    await expect(page.getByText('Baris 4: Sel yang diawali = tidak diterima.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Impor 3 produk' })).toBeDisabled();
    await expect(page.getByText('Perbaiki semua baris yang salah dulu.')).toBeVisible();

    // The rows with errors stand first.
    await expect(page.getByRole('row').nth(1)).toContainText('Baris 3');
    await expect(page.getByRole('row').nth(3)).toContainText('Benar');
    await checked(page);

    // UXI-209 leads to the categories, asking first (UXS-45), and staying keeps the preview.
    await page.getByRole('link', { name: 'Buat kategori di halaman Kategori' }).click();
    const leaving = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });

    await expect(leaving).toBeVisible();
    await expect(leaving.getByText('Pratinjau impor ini akan dibuang. Tidak ada produk yang dibuat.')).toBeVisible();
    await leaving.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    await expect(page.getByRole('heading', { name: 'Pratinjau impor' })).toBeVisible();

    // UXI-212: the dialog, whose first choice stays; then the file is removed.
    await page.getByRole('button', { name: 'Batalkan impor' }).click();
    const asking = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });

    await expect(asking.getByText('Pratinjau impor ini akan dibuang. Tidak ada produk yang dibuat.')).toBeVisible();
    await expect(asking.getByRole('button', { name: 'Tetap di halaman ini' })).toBeFocused();
    await asking.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    expect(cancels).toEqual([]);

    await page.getByRole('button', { name: 'Batalkan impor' }).click();
    await asking.getByRole('button', { name: 'Tinggalkan', exact: true }).click();
    await expect(page.getByText('Belum ada berkas yang dipilih.')).toBeVisible();
    await expect(upload(page)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pratinjau impor' })).toHaveCount(0);
    await expect.poll(() => cancels.length).toBe(1);
    await checked(page);

    expect(injected(watched)).toEqual([]);
    await context.close();
});

test('a refused file stays at its field, and a file above the limit sends no request', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    let posted = 0;

    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().endsWith('/products/import/preview')) {
            posted += 1;
        }
    });

    await page.goto(PAGE);

    // A workbook named CSV: judged by its content.
    await upload(page).setInputFiles({ name: 'produk.csv', mimeType: 'text/csv', buffer: Buffer.from([0x50, 0x4b, 0x03, 0x04, 0x14, 0x00]) });
    await expect(page.getByText('Berkas harus CSV dengan pengodean UTF-8. Dari aplikasi spreadsheet, simpan sebagai CSV UTF-8.')).toBeVisible();
    expect(posted).toBe(1);
    await checked(page);

    // An unknown column, naming it.
    await upload(page).setInputFiles({ name: 'produk.csv', mimeType: 'text/csv', buffer: Buffer.from(`${HEADER};Warna\n`, 'utf8') });
    await expect(page.getByText('Kolom tidak dikenal: Warna.')).toBeVisible();

    // Above the limit, here, with no request.
    await upload(page).setInputFiles({ name: 'produk.csv', mimeType: 'text/csv', buffer: Buffer.alloc(2 * 1024 * 1024 + 1, 0x41) });
    await expect(page.getByText('Berkas paling besar 2 MB.')).toBeVisible();
    expect(posted).toBe(2);

    expect(injected(watched)).toEqual([]);
    await context.close();
});

test('a category deactivated after the preview refuses the import, and a new file can be chosen', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const sku = `SKU-${letters(8)}`;

    await page.goto(PAGE);
    await upload(page).setInputFiles(csvFile([`${sku};Satu;${category};;pcs;0;1000;;`]));
    await expect(page.getByRole('button', { name: 'Impor 1 produk' })).toBeEnabled();

    // The same user, in another tab.
    const other = await context.newPage();

    await other.goto('/categories');
    await other.getByRole('button', { name: `Tindakan lain ${category}` }).click();
    await other.getByRole('menuitem', { name: 'Nonaktifkan' }).click();
    await other.getByRole('dialog', { name: `Nonaktifkan kategori ${category}?` }).getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(other.getByText('Kategori dinonaktifkan.')).toBeVisible();
    await other.close();

    await page.getByRole('button', { name: 'Impor 1 produk' }).click();
    await expect(page.getByText(`Kategori ${category} sudah nonaktif.`, { exact: false })).toBeVisible();
    await expect(page.getByText('Baris 2: Tidak ada kategori aktif dengan nama ini.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Buat kategori di halaman Kategori' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pratinjau impor' })).toHaveCount(0);
    await expect(upload(page)).toBeVisible();
    await checked(page);

    expect(injected(watched)).toEqual([]);
    await context.close();
});

test('a lost answer is uncertain, and the same request sent again commits once', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Agus');
    const category = await newCategory(page);
    const sku = `SKU-${letters(8)}`;

    await page.goto(PAGE);
    await upload(page).setInputFiles(csvFile([`${sku};Satu;${category};;pcs;0;1000;;`]));
    await expect(page.getByRole('button', { name: 'Impor 1 produk' })).toBeEnabled();

    // UXI-211: no answer reaches the page.
    const route = '**/commands/csv_import.confirm';

    await page.route(route, (request) => request.abort());
    await page.getByRole('button', { name: 'Impor 1 produk' }).click();
    await expect(page.getByRole('button', { name: 'Kirim ulang' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cek status' })).toBeVisible();
    await expect(page.getByText('Jangan mengimpor berkas yang sama lagi.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan impor' })).toHaveCount(0);
    await checked(page);

    await page.unroute(route);
    await page.getByRole('button', { name: 'Kirim ulang' }).click();
    await page.waitForURL((url) => url.pathname === '/products' && /^\?import=\d+$/.test(url.search));
    await expect(page.getByRole('link', { name: 'Satu' })).toBeVisible();

    // The reference is released: the page opens on its draft again.
    await page.goto(PAGE);
    await expect(upload(page)).toBeVisible();

    expect(injected(watched)).toEqual([]);
    await context.close();
});

test('a cashier finds no way to the import', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await signedIn(context, page, 'Rina');

    const answer = await page.goto(PAGE);

    expect(answer?.status()).toBe(403);
    await expect(page.getByRole('link', { name: 'Impor produk (CSV)' })).toHaveCount(0);
    await expect(upload(page)).toHaveCount(0);

    expect(injected(watched).filter((problem) => !problem.includes('status of 403'))).toEqual([]);
    await context.close();
});
