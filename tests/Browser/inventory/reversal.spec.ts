import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { injected, prepareCommands, refusal } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-411 "Batalkan mutasi" with its entries on UXP-403, UXP-406 and UXP-409 (AC-026-05; COPY_DECK
// sections 9.4 and 9.12; NAVIGATION_CONTRACTS UXP-411; INTERACTION_INVENTORY UXI-227, UXI-250, UXI-266
// and UXI-277 to UXI-284; UI_STATES UXS-13, UXS-31 and UXS-40; INTERACTION_CONTRACTS section 1.5;
// WF-23; suites UX01, UX02, INV03, AUTH03; TASK-026, L5): the Owner reverses a receipt from its
// document's line, the movement and its exact inverse with the stock now and after, no quantity field,
// reviewed and confirmed with the password once, the success on UXP-403 of the REVERSAL with its link
// to the source; the line then says that it is reversed; the reason never in the browser's storage; a
// refused replay said with nothing changed at every width of KEY_SCREENS section 4 and 1440 px; the
// pages of every other role carry no entry (ReversalPagesTest, L4). These tests
// sign in the people of Toko Contoh, so they run one at a time (playwright.config.ts). Every product of
// a test gets new random texts, because the local database keeps its products.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/** RC-10's route (ROUTE_CONTRACTS RC-10). */
const CONFIRM_ROUTE = '**/commands/reversal.confirm';

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

async function signedIn(context: BrowserContext, page: Page, person: Person): Promise<string> {
    const accounts = await resetPeople(context, [person]);

    await prepareCommands(context, [person]);
    await signIn(page, accounts[person]);

    return accounts[person].password;
}

/** A new product with a barcode, in a new category, through UXP-307 and UXP-303. */
async function newProduct(page: Page): Promise<{ id: number; name: string; barcode: string }> {
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

    return { id: Number(new URL(page.url()).pathname.split('/').pop()), name: product.name, barcode: product.barcode };
}

/** The product received through UXP-405 at this cost; the page is left on its UXP-406. */
async function received(page: Page, product: { name: string; barcode: string }, quantity: string, cost: string): Promise<void> {
    await page.goto('/stock/receivings/new');
    const search = page.getByLabel('Cari atau scan produk', { exact: true });
    await search.fill(product.barcode);
    await search.press('Enter');
    const line = page.getByRole('list', { name: 'Catat barang masuk' }).getByRole('listitem').filter({ hasText: product.name });
    await expect(line).toHaveCount(1);
    await line.getByLabel('Jumlah', { exact: true }).fill(quantity);
    await line.getByLabel('Biaya', { exact: true }).fill(cost);
    await search.fill('');
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await Promise.all([
        page.waitForURL((url) => /^\/stock\/receivings\/\d+$/.test(url.pathname), { timeout: ACCESS_WAIT }),
        page.getByRole('dialog', { name: 'Tinjau barang masuk' }).getByRole('button', { name: /^Konfirmasi barang masuk/ }).click(),
    ]);
}

test('the Owner reverses a receipt from its line, reviewed with the password once, and lands on the REVERSAL', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    const password = await signedIn(context, page, 'Hendra');
    const product = await newProduct(page);
    await received(page, product, '4', '8000');
    const detail = new URL(page.url()).pathname;
    const line = page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: product.name });

    // UXI-250: "Batalkan mutasi" of the line, whose way back is the document.
    await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+\/reverse$/.test(url.pathname)), line.getByRole('link', { name: `Batalkan mutasi ${product.name}` }).click()]);
    expect(new URL(page.url()).search).toBe('?from_page=receiving');

    // UXI-277: the movement, its exact inverse and the stock now and after; no quantity field (BR-INV-34).
    await expect(page.getByRole('heading', { level: 1, name: 'Batalkan mutasi' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Detail barang masuk' })).toHaveAttribute('href', detail);
    await expect(page.getByText('+4 pcs', { exact: true })).toBeVisible();
    await expect(page.getByText('−4 pcs', { exact: true })).toBeVisible();
    await expect(page.getByText('Mutasi asli tetap tercatat.', { exact: false })).toBeVisible();
    await expect(page.getByLabel('Jumlah', { exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Tinjau' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByText('Isi alasan.')).toBeVisible();
    await checked(page);

    await page.getByLabel('Alasan', { exact: true }).fill('Salah catat pemasok');
    await page.getByLabel('Alasan', { exact: true }).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(await page.evaluate(() => JSON.stringify([window.sessionStorage, window.localStorage]))).not.toContain('Salah catat pemasok');

    // The review, then the password once (UXS-11), then UXP-403 of the REVERSAL (UXS-04).
    await page.getByRole('button', { name: 'Tinjau' }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau pembatalan mutasi' });
    await expect(review.getByRole('button', { name: 'Kembali ubah' })).toBeFocused();
    await checked(page);
    await review.getByRole('button', { name: /^Konfirmasi pembatalan/ }).click();
    await expect(page.getByRole('dialog', { name: 'Konfirmasi kata sandi' })).toBeVisible();
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+$/.test(url.pathname)), dialog.getByRole('button', { name: 'Konfirmasi' }).click()]);

    await expect(page.getByText('Mutasi dibatalkan.')).toBeVisible();
    await expect(page.getByText('Pembatalan · ', { exact: false })).toBeVisible();
    await expect(page.getByRole('link', { name: /^Membatalkan mutasi / })).toBeVisible();
    await checked(page);

    // The source's line now says why it cannot be reversed again (UXS-13; BR-INV-35).
    await page.goto(detail);
    await expect(line.getByRole('button', { name: `Batalkan mutasi ${product.name}` })).toBeDisabled();
    await expect(line).toContainText('Mutasi ini sudah dibatalkan.');
    await checked(page);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a refused replay is said with nothing changed, at every width', async ({ browser }) => {
    for (const width of WIDTHS) {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        const password = await signedIn(context, page, 'Hendra');
        const product = await newProduct(page);
        await received(page, product, '2', '5000');
        const line = page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: product.name });
        await Promise.all([page.waitForURL((url) => /^\/stock\/movements\/\d+\/reverse$/.test(url.pathname)), line.getByRole('link', { name: `Batalkan mutasi ${product.name}` }).click()]);
        await checked(page);

        // UXS-40: REPLAY_REJECTED of a reversal (COPY_DECK section 13.1).
        await page.route(CONFIRM_ROUTE, (route) => route.fulfill({ status: 422, json: { ...refusal('REPLAY_REJECTED'), messageKey: 'ux.state.replay_rejected.reversal' } }));
        await page.getByLabel('Alasan', { exact: true }).fill('Salah catat');
        await page.getByRole('button', { name: 'Tinjau' }).click();
        await page.getByRole('dialog', { name: 'Tinjau pembatalan mutasi' }).getByRole('button', { name: /^Konfirmasi pembatalan/ }).click();
        const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
        await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
        await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
        await expect(page.getByText('Pembatalan tidak dapat dihitung ulang')).toBeVisible();
        await expect(page.getByLabel('Alasan', { exact: true })).toHaveValue('Salah catat');
        await checked(page);
        await page.unroute(CONFIRM_ROUTE);

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    }
});
