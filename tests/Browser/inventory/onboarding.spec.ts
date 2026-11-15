import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-412 "Stok awal" and UXP-413 "Input stok awal" (AC-035-06; COPY_DECK sections 9.13 and 9.14;
// NAVIGATION_CONTRACTS UXP-412, UXP-413; INTERACTION_INVENTORY UXI-016, UXI-030 and UXI-285 to UXI-300;
// UI_STATES UXS-04, UXS-11, UXS-27; INTERACTION_CONTRACTS section 1.5; WF-24, WF-25; suites UX01, UX02,
// OPEN01, AUTH03; TASK-035, L5): the Owner reaches the hub from the sub-navigation, starts the one
// onboarding period after its confirmation and the password once, and the banner of UXS-27 shows; he
// enters a counted product with its cost and count reference and a product counted zero, reviewed and
// confirmed; the checklist shows them; he reverses the counted product's OPENING from UXP-403 and records
// its replacement on UXP-413, reviewed and confirmed by the keyboard; he withdraws the zero count with a
// reason; he ends the period after its review of BR-INV-28, and the hub is read only. The entry, the
// replacement, their reviews and the hub fit the 200 % zoom of 1280 x 800 and 320 px as well (KEY_SCREENS
// section 4): the hub resized, the entry and the replacement each in a tab of its own at each width, since the
// shell lays the compact width out anew, which empties a draft held in the page's memory. The draft is never
// in the browser's storage. These
// tests sign in the people of Toko Contoh, so they run one at a time (playwright.config.ts). Every product
// of a test gets new random texts, because the local database keeps its products; their names begin with
// "AA" so that they lead the checklist, in name order. The onboarding period runs once per database
// (D-071): the test ends it, and, should it fail on the way, its last step ends it, so that the tests
// after it post as before.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const HUB = '/stock/onboarding';

function letters(length: number): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)), (byte) => String.fromCharCode(97 + (byte % 26))).join('');
}

function digits(): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(12)), (byte) => String(byte % 10)).join('');
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

/**
 * The same checks at the widths of KEY_SCREENS section 4 that the other pages of Stok are checked at — the
 * 200 % zoom of 1280 x 800, as its 640 px, and 320 px — then 1440 px again. The period runs once per
 * database, so its pages are measured inside this one test. Only for a page without a draft: a draft page
 * is measured by draftFitsNarrow().
 */
async function fitsNarrow(page: Page): Promise<void> {
    for (const width of [WIDTHS[4]!, WIDTHS[7]!]) {
        await page.setViewportSize({ width: width.width, height: width.height });
        await checked(page);
    }

    await page.setViewportSize({ width: WIDTHS[0]!.width, height: WIDTHS[0]!.height });
}

/**
 * A draft page at the widths of fitsNarrow(), with its draft and then its review. The shell lays the compact
 * width out anew, which mounts the page again and so empties a draft held in the page's memory: a draft page
 * is never resized. At each width a tab of its own, in the same sign-in session, opens the page, enters the
 * draft and opens its review, each checked; it closes with nothing sent.
 */
async function draftFitsNarrow(context: BrowserContext, address: string, enter: (page: Page) => Promise<void>, review: string): Promise<void> {
    for (const width of [WIDTHS[4]!, WIDTHS[7]!]) {
        const narrow = await context.newPage();
        const watched = await watch(narrow);

        await narrow.setViewportSize({ width: width.width, height: width.height });
        await narrow.goto(address);
        await enter(narrow);
        await checked(narrow);
        await narrow.getByRole('button', { name: 'Tinjau' }).click();
        await expect(narrow.getByRole('dialog', { name: review })).toBeVisible();
        await checked(narrow);
        await narrow.close();
        expect(injected(watched.problems)).toEqual([]);
    }
}

/** The entry's draft (UXI-292, UXI-294): the counted product with its cost and count reference, and one counted zero. */
async function enterDraft(page: Page, counted: { name: string; barcode: string }, zero: { name: string; barcode: string }): Promise<void> {
    const search = page.getByLabel('Cari atau scan produk', { exact: true });
    const list = page.getByRole('list', { name: 'Input stok awal' });

    await search.fill(counted.barcode);
    await search.press('Enter');
    const countedRow = list.getByRole('listitem').filter({ hasText: counted.name });
    await expect(countedRow).toHaveCount(1);
    await countedRow.getByLabel('Jumlah', { exact: true }).fill('6');
    await countedRow.getByLabel('Biaya', { exact: true }).fill('6000');
    await countedRow.getByLabel('Referensi hitung', { exact: true }).fill('LH-01');

    await search.fill(zero.barcode);
    await search.press('Enter');
    const zeroRow = list.getByRole('listitem').filter({ hasText: zero.name });
    await expect(zeroRow).toHaveCount(1);
    await zeroRow.getByLabel('Jumlah', { exact: true }).fill('0');
    await expect(zeroRow.getByLabel('Biaya', { exact: true })).toHaveCount(0);
    await expect(zeroRow.getByLabel('Referensi hitung', { exact: true })).toHaveCount(0);
}

/** The replacement's count, cost, count reference and reason (UXI-294, RC-48). */
async function enterReplacement(page: Page): Promise<void> {
    const replacement = page.getByRole('main');

    await replacement.getByLabel('Jumlah', { exact: true }).fill('5');
    await replacement.getByLabel('Biaya', { exact: true }).fill('5000');
    await replacement.getByLabel('Referensi hitung', { exact: true }).fill('LH-02');
    await replacement.getByLabel('Alasan', { exact: true }).fill('Hitung ulang');
}

async function signedIn(context: BrowserContext, page: Page, person: Person): Promise<string> {
    const accounts = await resetPeople(context, [person]);

    await prepareCommands(context, [person]);
    await signIn(page, accounts[person]);

    return accounts[person].password;
}

/** A new product with a barcode and no movement, in a new category, through UXP-307 and UXP-303. */
async function newProduct(page: Page): Promise<{ name: string; barcode: string }> {
    const category = `Kategori ${letters(10)}`;
    const product = { name: `AA${letters(12)}`, sku: `SKU-${letters(8)}`, barcode: digits() };

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

    return { name: product.name, barcode: product.barcode };
}

/** The password dialog of UXS-11, when the recent authentication has lapsed, then its confirmation. */
async function confirmPassword(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
    await expect(dialog).toHaveCount(0);
}

/** Waits for what a command leads to, after the password of UXS-11 when its recent authentication has lapsed. */
async function afterPassword(page: Page, password: string, done: Locator): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await expect(dialog.or(done).first()).toBeVisible();

    if (await dialog.isVisible()) {
        await confirmPassword(page, password);
    }

    await expect(done).toBeVisible();
}

/** Ends a period that a failed step left running, through UXI-289, so that the tests after it post. */
async function released(page: Page, password: string): Promise<void> {
    await page.goto(HUB);
    const release = page.getByRole('main').getByRole('button', { name: 'Akhiri masa input stok awal' });

    if ((await release.count()) === 0) {
        return;
    }

    await release.click();
    await page.getByRole('dialog', { name: 'Tinjau akhir masa input stok awal' }).getByRole('button', { name: /^Akhiri masa input stok awal/ }).click();

    if ((await page.getByRole('dialog', { name: 'Konfirmasi kata sandi' }).count()) > 0) {
        await confirmPassword(page, password);
    }

    await expect(page.getByText('Masa input stok awal selesai. Penjualan dan pencatatan stok bisa dilakukan lagi.')).toBeVisible();
}

test('the Owner starts onboarding, enters the opening stock, corrects a zero count and ends the period once', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const password = await signedIn(context, page, 'Hendra');

    try {
        const counted = await newProduct(page);
        const zero = await newProduct(page);

        // UXI-016: "Stok awal" in the sub-navigation of Stok, the Owner's.
        await page.goto('/stock');
        await Promise.all([page.waitForURL((url) => url.pathname === HUB), page.getByRole('navigation', { name: 'Submenu Stok' }).getByRole('link', { name: 'Stok awal', exact: true }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Stok awal' })).toBeVisible();
        await expect(page.getByText('Masa input stok awal belum dimulai.')).toBeVisible();
        const rows = page.getByRole('main').getByRole('table').getByRole('row');
        await expect(rows.filter({ hasText: counted.name })).toContainText('Belum dihitung');
        await checked(page);

        // UXI-285: the confirmation, which "Batal" closes with nothing sent; then the password once (UXS-11).
        const start = page.getByRole('button', { name: 'Mulai masa input stok awal' });
        await start.click();
        const confirmation = page.getByRole('dialog', { name: 'Mulai masa input stok awal?' });
        await expect(confirmation.getByText('Selama masa ini, penjualan dan pencatatan stok biasa berhenti sampai Anda mengakhirinya.', { exact: false })).toBeVisible();
        await expect(confirmation.getByRole('button', { name: 'Batal' })).toBeFocused();
        await checked(page);
        await confirmation.getByRole('button', { name: 'Batal' }).click();
        await expect(confirmation).toHaveCount(0);
        await start.click();
        await confirmation.getByRole('button', { name: 'Mulai masa input stok awal' }).click();
        await confirmPassword(page, password);

        // UXS-04, and the banner of UXS-27 on every page (UXI-030).
        await expect(page.getByText('Masa input stok awal dimulai.')).toBeVisible();
        await expect(page.getByText('Masa input stok awal aktif sejak', { exact: false })).toBeVisible();
        await page.goto('/stock');
        await expect(page.getByText('Masa input stok awal aktif', { exact: true })).toBeVisible();
        await Promise.all([page.waitForURL((url) => url.pathname === HUB), page.getByRole('link', { name: 'Buka Stok awal' }).click()]);
        await checked(page);

        // UXI-286, UXP-413: the counted product with its cost and count reference, and one counted zero.
        await Promise.all([page.waitForURL((url) => url.pathname === '/stock/onboarding/entry'), page.getByRole('link', { name: 'Input stok awal', exact: true }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Input stok awal' })).toBeVisible();
        await expect(page.getByText('Paling banyak 100 baris. Semua baris disimpan bersama, atau tidak ada yang disimpan.')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Stok awal' })).toHaveAttribute('href', HUB);
        const draft = (on: Page): Promise<void> => enterDraft(on, counted, zero);
        await draft(page);
        await checked(page);
        await draftFitsNarrow(context, page.url(), draft, 'Tinjau stok awal');
        expect(await page.evaluate(() => JSON.stringify([window.sessionStorage, window.localStorage]))).not.toContain('LH-01');

        // UXI-295 to UXI-297: the review, which Enter never confirms, then UXP-412 with its success.
        await page.getByRole('button', { name: 'Tinjau' }).click();
        const review = page.getByRole('dialog', { name: 'Tinjau stok awal' });
        await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
        await expect(review).toContainText(counted.name);
        await expect(review).toContainText('Dihitung nol');
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === HUB), review.getByRole('button', { name: /^Konfirmasi stok awal/ }).click()]);
        await expect(page.getByText('Stok awal tersimpan.')).toBeVisible();

        // UXI-287: the counted product with its quantity and cost status, never an amount; the zero count.
        const countedLine = rows.filter({ hasText: counted.name });
        await expect(countedLine).toContainText('6 pcs');
        await expect(countedLine).toContainText('Biaya diketahui');
        await expect(countedLine).not.toContainText('6.000');
        await expect(countedLine.getByRole('link', { name: `Detail mutasi stok ${counted.name}` })).toBeVisible();
        const zeroLine = rows.filter({ hasText: zero.name });
        await expect(zeroLine).toContainText('Dihitung nol');
        await checked(page);
        await fitsNarrow(page);

        // UXI-288 and UXI-228: the counted product's OPENING, from its row to UXP-403, whose way back is the
        // checklist, reversed there while the period is active (D-087).
        await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+$/.test(url.pathname)), countedLine.getByRole('link', { name: `Detail mutasi stok ${counted.name}` }).click()]);
        await expect(page.getByRole('link', { name: 'Kembali ke Stok awal' })).toHaveAttribute('href', HUB);
        await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+\/reverse$/.test(url.pathname)), page.getByRole('main').getByRole('link', { name: 'Batalkan mutasi', exact: true }).click()]);
        await page.getByLabel('Alasan', { exact: true }).fill('Salah hitung');
        await page.getByRole('button', { name: 'Tinjau' }).click();
        await page.getByRole('dialog', { name: 'Tinjau pembatalan mutasi' }).getByRole('button', { name: /^Konfirmasi pembatalan/ }).click();
        await afterPassword(page, password, page.getByText('Mutasi dibatalkan.'));

        // UXI-291: "Catat ulang stok awal" on its row, the replacement entry of RC-48 on UXP-413 (UXI-292).
        await page.goto(HUB);
        await expect(countedLine).toContainText('Stok awal dibatalkan; bisa dicatat ulang.');
        await Promise.all([page.waitForURL((url) => url.pathname === '/stock/onboarding/entry'), countedLine.getByRole('link', { name: `Catat ulang stok awal ${counted.name}` }).click()]);
        const replacement = page.getByRole('main');
        await expect(replacement.getByRole('heading', { level: 2, name: counted.name })).toBeVisible();
        await expect(replacement.getByText('Stok awal dibatalkan; bisa dicatat ulang.')).toBeVisible();
        await enterReplacement(page);

        // Enter in a field sends nothing (UXA-10); Ctrl+Enter is "Tinjau" (UXI-295) and, on the review, whose
        // confirmation Enter never activates, "Konfirmasi stok awal" (UXI-296).
        await replacement.getByLabel('Alasan', { exact: true }).press('Enter');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await checked(page);
        await draftFitsNarrow(context, page.url(), enterReplacement, 'Tinjau stok awal pengganti');
        await replacement.getByLabel('Alasan', { exact: true }).focus();
        await page.keyboard.press('Control+Enter');
        const replacementReview = page.getByRole('dialog', { name: 'Tinjau stok awal pengganti' });
        await expect(replacementReview.getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
        await expect(replacementReview).toContainText('Hitung ulang');
        await checked(page);
        await replacementReview.getByRole('button', { name: /^Konfirmasi stok awal/ }).focus();
        await page.keyboard.press('Enter');
        await expect(replacementReview).toBeVisible();
        await replacementReview.getByRole('button', { name: /^Kembali ubah/ }).focus();
        await page.keyboard.press('Control+Enter');
        await afterPassword(page, password, page.getByText('Stok awal pengganti tersimpan.'));
        await expect(countedLine).toContainText('5 pcs');

        // UXI-291: "Tarik hitungan nol", with its reason and the password not asked again within its time.
        await zeroLine.getByRole('button', { name: `Tarik hitungan nol ${zero.name}` }).click();
        const withdrawal = page.getByRole('dialog', { name: 'Tarik hitungan nol?' });
        await expect(withdrawal.getByText('Hitungan nol ini tetap tercatat di riwayat, lalu produk ini bisa dihitung lagi.')).toBeVisible();
        await withdrawal.getByLabel('Alasan', { exact: true }).fill('Salah hitung');
        await checked(page);
        await withdrawal.getByRole('button', { name: 'Tarik hitungan nol' }).click();
        await expect(page.getByText('Hitungan nol ditarik.')).toBeVisible();
        await expect(zeroLine).toContainText('Belum dihitung');

        // UXI-289: the review of BR-INV-28, which says the period cannot start again; then read only.
        await page.getByRole('main').getByRole('button', { name: 'Akhiri masa input stok awal' }).click();
        const ending = page.getByRole('dialog', { name: 'Tinjau akhir masa input stok awal' });
        await expect(ending.getByText('Setelah diakhiri, masa input stok awal tidak bisa dimulai lagi, juga setelah data dipulihkan dari cadangan lama.')).toBeVisible();
        await expect(ending.getByText('produk sudah dihitung', { exact: false })).toBeVisible();
        await checked(page);
        await ending.getByRole('button', { name: /^Akhiri masa input stok awal/ }).click();
        await expect(page.getByText('Masa input stok awal selesai. Penjualan dan pencatatan stok bisa dilakukan lagi.')).toBeVisible();
        await expect(page.getByText('Masa input stok awal selesai pada', { exact: false })).toBeVisible();
        await expect(page.getByText('Koreksi stok sekarang memakai Sesuaikan stok, Batalkan mutasi, atau Koreksi biaya.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Mulai masa input stok awal' })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Input stok awal', exact: true })).toHaveCount(0);
        await checked(page);
        await fitsNarrow(page);
        await page.goto('/stock');
        await expect(page.getByText('Masa input stok awal aktif', { exact: true })).toHaveCount(0);
        await checked(page);
    } finally {
        await released(page, password);
    }

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
