import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Browser, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands, refusal, REQUESTS_PAGE } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS, type PageWatch, type Width } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-101 "Kasir", UXP-102 "Pembayaran" and UXP-103 "Struk" (AC-029-01 to AC-029-07; KEY_SCREENS KS-03 to
// KS-05 with their states; COPY_DECK sections 7.1 to 7.3 and 14.1; INTERACTION_INVENTORY UXI-066 to UXI-076,
// UXI-078 to UXI-089, UXI-091, UXI-093 and UXI-056; NAVIGATION_CONTRACTS UXP-101 to UXP-103 and NC-13;
// UI_STATES UXS-14, UXS-15, UXS-18, UXS-20, UXS-31, UXS-33; ADAPTIVE_AND_ACCESSIBILITY UXA-07, UXA-09 to
// UXA-11; USER_TASKS section 5; WF-05, WF-07; suites SEARCH03, POS03, POS04, POS02, UX01, UX02, CMD01;
// TEST_STRATEGY TS-22; TASK-029, L5). The Owner sells in his own OPEN session: the people of the other
// files never hold one, so that their homes stay as tests/Browser/support/access.ts says, and his session
// is opened by tests/Browser/cash/, which runs first, or else by the till's fixture. Every product is new
// (tests/Harness/Browser/FixturesController.php, `till`). These tests are the L5 critical path of
// checkout in cash and by bank transfer that every later pull request runs (EXECUTION_CONTEXT EXC-34).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const TILL = '/till';

const PAYMENT = '/till/payment';

const CHECKOUT = '**/commands/sale.checkout';

const RECEIPT = /^\/sales\/\d+\/receipt$/;

interface TillProduct {
    readonly id: number;
    readonly sku: string;
    readonly name: string;
    readonly code: string;
    readonly unit: string;
    readonly price: string;
}

interface Till {
    readonly context: BrowserContext;
    readonly page: Page;
    readonly watched: PageWatch;
    readonly products: readonly [TillProduct, TillProduct, TillProduct];
}

async function token(context: BrowserContext): Promise<string> {
    const cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    return decodeURIComponent(cookie?.value ?? '');
}

/** Three new products with their barcodes and stock, and the Owner's OPEN session (the fixture `till`). */
async function tillProducts(context: BrowserContext): Promise<readonly [TillProduct, TillProduct, TillProduct]> {
    const answer = await context.request.post('/testing/browser/till', { data: {}, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    const products = ((await answer.json()) as { products: TillProduct[] }).products;
    const [pen, paper, book] = products;

    if (pen === undefined || paper === undefined || book === undefined) {
        throw new Error('The fixture gives three products.');
    }

    return [pen, paper, book];
}

/** The Owner's new price of a product (the fixture `till`, through RC-18's Action). */
async function reprice(context: BrowserContext, product: TillProduct, price: string): Promise<void> {
    const answer = await context.request.post('/testing/browser/till', { data: { reprice: { productId: product.id, price } }, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);
}

/** The Owner signed in, his till open with three new products. */
async function openTill(browser: Browser, width: Width = WIDTHS[0]!): Promise<Till> {
    const context = await contextAt(browser, width);
    const page = await context.newPage();
    const watched = await watch(page);
    const accounts = await resetPeople(context, ['Hendra']);

    await prepareCommands(context, ['Hendra']);
    const products = await tillProducts(context);

    await signIn(page, accounts.Hendra);
    await page.goto(TILL);
    await expect(page.getByRole('heading', { level: 1, name: 'Kasir' })).toBeVisible();

    return { context, page, watched, products };
}

function searchField(page: Page): Locator {
    return page.getByRole('searchbox', { name: 'Cari atau scan produk (F2)' });
}

/** A keyboard-wedge scan: the code's keys, then the scanner's Enter (BR-CAT-28). */
async function scan(page: Page, code: string): Promise<void> {
    await page.keyboard.type(code);
    await page.keyboard.press('Enter');
}

function cart(page: Page): Locator {
    return page.getByRole('region', { name: 'Keranjang' });
}

function lines(page: Page): Locator {
    return cart(page).getByRole('listitem').filter({ has: page.locator('.bk-line__name') });
}

function quantity(page: Page, product: TillProduct): Locator {
    return page.getByRole('textbox', { name: `Jumlah ${product.name}`, exact: true });
}

function pay(page: Page): Locator {
    return page.getByRole('button', { name: 'Bayar (F8)' });
}

function finalAction(page: Page): Locator {
    return page.getByRole('button', { name: /^(Selesaikan|Dana sudah diterima — Selesaikan) \(Ctrl\+Enter\)$/ });
}

function uncertainPanel(page: Page): Locator {
    return page.getByRole('main').getByRole('alert').filter({ hasText: 'Status belum pasti' });
}

/** The requests of RC-01 that the page sends. */
function checkouts(page: Page): string[] {
    const sent: string[] = [];

    page.on('request', (request) => {
        if (request.method() === 'POST' && new URL(request.url()).pathname === '/commands/sale.checkout') {
            sent.push(request.url());
        }
    });

    return sent;
}

/** A gate that a route waits on until the test opens it. */
function gate(): { readonly wait: Promise<void>; readonly open: () => void } {
    let open: () => void = () => undefined;
    const wait = new Promise<void>((done) => {
        open = done;
    });

    return { wait, open };
}

/** An amount in rupiah as the pages show it: "Rp12.500". */
function rupiah(amount: bigint): string {
    return `Rp${amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

/** The next checkout reaches the server, which commits it, and its answer is lost (TS-22). */
async function loseNextCheckout(page: Page): Promise<void> {
    await page.route(
        CHECKOUT,
        async (route) => {
            await route.fetch();
            await route.abort();
        },
        { times: 1 },
    );
}

/** The next checkout reaches the server and is answered HELD (UXS-15, TS-22). */
async function holdNextCheckout(page: Page): Promise<void> {
    await page.route(
        CHECKOUT,
        async (route) => {
            await route.fetch();
            await route.fulfill({ status: 409, json: refusal('IDEMPOTENCY_CONFLICT', 'HELD') });
        },
        { times: 1 },
    );
}

/** From an empty till to the payment screen with one product, the cash entered exactly. */
async function payOne(page: Page, product: TillProduct): Promise<void> {
    await page.goto(TILL);
    await scan(page, product.code);
    await expect(quantity(page, product)).toHaveValue('1');
    await page.keyboard.press('F8');
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    await page.keyboard.press('Alt+U');
    await expect(finalAction(page)).not.toHaveAttribute('aria-disabled', 'true');
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

test('scans add one base unit each in their order; empty terminators, a held Enter and an unknown code add nothing; a fraction is typed', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen, paper] = products;
    const search = searchField(page);

    // UXA-09: the search field has the focus on arrival.
    await expect(search).toBeFocused();

    // The first lookup answers last: the scans still apply in the order they were made (BR-CAT-30).
    let held = true;

    await page.route('**/data/products/search*', async (route) => {
        if (held && new URL(route.request().url()).searchParams.get('q') === pen.code) {
            held = false;
            await new Promise((done) => setTimeout(done, 1500));
        }

        await route.continue();
    });
    await scan(page, pen.code);
    await scan(page, paper.code);
    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('2');
    await expect(quantity(page, paper)).toHaveValue('1');
    await expect(lines(page).nth(0)).toContainText(pen.name);
    await expect(lines(page).nth(1)).toContainText(paper.name);
    await expect(page.getByTestId('live-polite')).toContainText(`Ditambahkan: ${pen.name}, jumlah 2 pcs.`);

    // An empty terminator adds nothing; a held Enter that repeats adds one base unit only (BR-CAT-29).
    await page.keyboard.type(pen.code);
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await expect(quantity(page, pen)).toHaveValue('3');
    await page.keyboard.type(pen.code);
    await page.keyboard.down('Enter');
    await page.keyboard.down('Enter');
    await page.keyboard.down('Enter');
    await page.keyboard.up('Enter');
    await expect(quantity(page, pen)).toHaveValue('4');
    await expect(lines(page)).toHaveCount(2);

    // An unknown code stays in the field and adds nothing (UXS-33; BR-CAT-31).
    await scan(page, '0000000000000');
    await expect(page.getByText('Kode tidak dikenal', { exact: true })).toBeVisible();
    await expect(search).toHaveValue('0000000000000');
    await expect(lines(page)).toHaveCount(2);
    await search.fill('');

    // A fraction only by typing it; Enter ends the edit and returns to the search field (UXI-070, UXA-09).
    await quantity(page, paper).fill('2,5');
    await quantity(page, paper).press('Enter');
    await expect(search).toBeFocused();
    await expect(lines(page).nth(1)).toContainText('Rp17.500');
    await expect(cart(page).locator('.bk-total__value')).toHaveText('Rp35.500');
    await scan(page, paper.code);
    await expect(quantity(page, paper)).toHaveValue('3,5');

    // A fraction of a product counted whole is refused at its line, and "Bayar" waits (UXS-13).
    await quantity(page, pen).fill('1,5');
    await expect(lines(page).nth(0)).toContainText('Produk ini dihitung utuh. Isi tanpa koma.');
    await expect(pay(page)).toHaveAttribute('aria-disabled', 'true');
    await expect(cart(page)).toContainText('Perbaiki baris yang ditandai dulu.');
    await quantity(page, pen).fill('4');
    await expect(pay(page)).not.toHaveAttribute('aria-disabled', 'true');

    // "+" and "−" step by one base unit; "−" stops at the smallest quantity, with its reason (UXI-071).
    await page.getByRole('button', { name: `Kurangi satu m ${paper.name}` }).click();
    await expect(quantity(page, paper)).toHaveValue('2,5');
    await expect(search).toBeFocused();

    // A search by name: the arrow keys move into the results and Enter adds the focused one (UXI-067, UXA-09).
    await search.fill(pen.name);
    const result = page.getByRole('button', { name: `Tambah ${pen.name} ke keranjang` });
    await expect(result).toBeVisible();
    await search.press('ArrowDown');
    await expect(result).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(quantity(page, pen)).toHaveValue('5');
    await expect(search).toBeFocused();

    // F2 brings the focus back to the search field from anywhere on the page (UXA-09).
    await page.getByRole('button', { name: 'Filter' }).focus();
    await page.keyboard.press('F2');
    await expect(search).toBeFocused();

    // Scan capture pauses while a dialog is open: a scan there adds nothing (BR-CAT-32).
    await page.getByRole('button', { name: 'Kosongkan keranjang' }).click();
    const dialog = page.getByRole('dialog', { name: 'Kosongkan keranjang?' });
    await expect(dialog.getByRole('button', { name: 'Batal' })).toBeFocused();
    await page.keyboard.type(pen.code);
    await page.keyboard.press('Enter');
    await expect(dialog).toHaveCount(0);
    await expect(quantity(page, pen)).toHaveValue('5');

    // "Kosongkan keranjang" after its confirmation (UXI-073); "Bayar" says why it waits.
    await page.getByRole('button', { name: 'Kosongkan keranjang' }).click();
    await dialog.getByRole('button', { name: 'Kosongkan keranjang' }).click();
    await expect(cart(page)).toContainText('Keranjang masih kosong. Scan atau cari produk.');
    await expect(pay(page)).toHaveAttribute('aria-disabled', 'true');

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a sale paid in cash takes three actions by keyboard, and its receipt starts the next sale with a scan (AC-029-03, AC-029-07)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen, paper] = products;
    const sent = checkouts(page);

    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');

    // 1: "Bayar" (F8). One payment screen: Tunai chosen, "Uang diterima" focused, never the final action,
    // no checkbox and no review dialog (D-040, D-075).
    await page.keyboard.press('F8');
    await expect(page).toHaveURL((url) => url.pathname === PAYMENT);
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    await expect(page.getByRole('radio', { name: 'Tunai (Alt+1)' })).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByLabel('Uang diterima', { exact: true })).toBeFocused();
    await expect(finalAction(page)).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByText('Uang diterima kurang Rp4.500.')).toBeVisible();
    await expect(page.getByRole('checkbox')).toHaveCount(0);
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // 2: "Uang Pas" (Alt+U): the change shows before the final action (BR-SALE-11).
    await page.keyboard.press('Alt+U');
    await expect(page.locator('.bk-change')).toContainText('Rp0');
    await expect(finalAction(page)).not.toHaveAttribute('aria-disabled', 'true');

    // 3: "Selesaikan" (Ctrl+Enter): the receipt follows with no further step (BR-SALE-14).
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);
    expect(sent).toHaveLength(1);
    await expect(page.getByRole('heading', { level: 1, name: 'Struk' })).toBeVisible();
    await expect(page.getByText(/^Penjualan \d{8}-\d{6,} tersimpan\.$/)).toBeVisible();
    const paper58 = page.getByRole('article', { name: 'Struk' });
    await expect(paper58).toContainText(pen.name);
    await expect(paper58).toContainText(`SKU ${pen.sku}`);
    await expect(paper58).toContainText('1 pcs × Rp4.500');
    await expect(paper58).toContainText('Pembayaran: Tunai');
    await expect(paper58).toContainText('Uang diterima');
    await expect(paper58).toContainText('Kembalian');
    await expect(page.getByRole('link', { name: 'Kembali ke Kasir' })).toHaveAttribute('href', TILL);
    await expect(page.getByRole('link', { name: 'Transaksi baru' })).toHaveAttribute('href', TILL);

    // UXI-091: the size of the receipt, the A4 "Nota Penjualan".
    await page.getByRole('radio', { name: 'A4' }).click();
    await expect(paper58).toContainText('Nota Penjualan');

    // The next sale with one scan on the receipt page, and nothing else (UXI-089; USER_TASKS section 5); a
    // second scan before the till opens is added after the first, never in its place (review R-05).
    await page.getByRole('radio', { name: '58 mm' }).click();
    await searchField(page).focus();
    const tillOpens = gate();

    await page.route(
        `**${TILL}`,
        async (route) => {
            await tillOpens.wait;
            await route.continue();
        },
        { times: 1 },
    );
    await scan(page, paper.code);
    await scan(page, pen.code);
    tillOpens.open();
    await page.waitForURL((url) => url.pathname === TILL);
    await expect(quantity(page, paper)).toHaveValue('1');
    await expect(quantity(page, pen)).toHaveValue('1');
    await expect(lines(page)).toHaveCount(2);
    await expect(lines(page).nth(0)).toContainText(paper.name);

    // Back never returns to the cart that was paid (NC-04, NC-13 (e)).
    await page.goBack();
    await expect(page).toHaveURL((url) => RECEIPT.test(url.pathname));
    await page.goBack();
    await expect(page).toHaveURL((url) => url.pathname === TILL);
    await expect(cart(page)).toContainText('Keranjang masih kosong. Scan atau cari produk.');
    expect(sent).toHaveLength(1);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a sale paid by bank transfer takes three actions by keyboard, its final action stating the confirmation (AC-029-03)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen] = products;

    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');
    await page.keyboard.press('F8');
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    await page.keyboard.press('Alt+2');
    await expect(page.getByRole('radio', { name: 'Transfer bank (Alt+2)' })).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByRole('button', { name: 'Dana sudah diterima — Selesaikan (Ctrl+Enter)' })).not.toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByText('Tangkapan layar atau ponsel pelanggan saja tidak cukup.', { exact: false })).toBeVisible();
    await page.getByLabel('Referensi pembayaran').fill('TRF-001');
    await page.getByLabel('Nama pelanggan').fill('Bu Sari');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);

    const paper = page.getByRole('article', { name: 'Struk' });
    await expect(paper).toContainText('Pembayaran: Transfer bank');
    await expect(paper).toContainText('Pelanggan: Bu Sari');
    await expect(paper).not.toContainText('Kembalian');

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a lost answer, a reload, a status check and a held request recover the original Sale, never a second one (AC-029-04, TS-22)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen] = products;
    const sent = checkouts(page);

    const toPayment = async (): Promise<void> => {
        await page.goto(TILL);
        await scan(page, pen.code);
        await expect(quantity(page, pen)).toHaveValue('1');
        await page.keyboard.press('F8');
        await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
        await page.keyboard.press('Alt+U');
        await expect(finalAction(page)).not.toHaveAttribute('aria-disabled', 'true');
    };

    const loseAnswer = async (): Promise<void> => {
        await page.route(
            CHECKOUT,
            async (route) => {
                await route.fetch();
                await route.abort();
            },
            { times: 1 },
        );
        await page.keyboard.press('Control+Enter');
        await expect(uncertainPanel(page)).toContainText('Jangan minta pelanggan membayar atau mentransfer lagi. Penjualan ini mungkin sudah tersimpan.');
    };

    // A lost answer (UXS-14): the request frozen, no final action, the way back closed with its reason; Esc
    // does nothing; "Kirim ulang" sends the same request, which answers the original Sale.
    await toPayment();
    await loseAnswer();
    await expect(finalAction(page)).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Kembali ke keranjang (Esc)' })).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByText('Tunggu sampai hasil pembayaran diketahui.')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL((url) => url.pathname === PAYMENT);
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), uncertainPanel(page).getByRole('button', { name: 'Kirim ulang' }).click()]);
    expect(sent).toHaveLength(2);

    // A lost answer, then the till: "Bayar" waits with its reason, and "Cek pembayaran yang belum pasti" leads to
    // UXP-006, whose "Cek status" and "Lihat hasil" open the receipt (NC-13; UXI-056, UXI-075, UXI-076).
    await toPayment();
    await loseAnswer();
    await page.goto(TILL);
    await expect(pay(page)).toHaveAttribute('aria-disabled', 'true');
    await expect(cart(page)).toContainText('Ada pembayaran yang statusnya belum pasti. Cek statusnya dulu.');
    await Promise.all([page.waitForURL((url) => url.pathname === REQUESTS_PAGE), page.getByRole('link', { name: 'Cek pembayaran yang belum pasti' }).click()]);
    const card = page.getByRole('main').getByRole('listitem').filter({ hasText: 'Penjualan' });
    await card.getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByRole('main')).toContainText('Sudah tersimpan.');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.getByRole('link', { name: 'Lihat hasil' }).click()]);
    expect(sent).toHaveLength(3);

    // A lost answer, then a reload of the payment screen: it opens on the request's panel, with "Cek status"
    // alone, never a new draft (NC-13 (b)); the check answers the Sale and opens its receipt.
    await toPayment();
    await loseAnswer();
    await page.reload();
    await expect(page).toHaveURL((url) => url.pathname === PAYMENT);
    await expect(uncertainPanel(page)).toBeVisible();
    await expect(uncertainPanel(page).getByRole('button', { name: 'Kirim ulang' })).toHaveCount(0);
    await expect(finalAction(page)).toHaveCount(0);
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);
    expect(sent).toHaveLength(4);

    // A held request (UXS-15) offers "Cek status" only.
    await toPayment();
    await page.route(
        CHECKOUT,
        async (route) => {
            await route.fetch();
            await route.fulfill({ status: 409, json: refusal('IDEMPOTENCY_CONFLICT', 'HELD') });
        },
        { times: 1 },
    );
    await page.keyboard.press('Control+Enter');
    await expect(uncertainPanel(page)).toContainText('Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan.');
    await expect(uncertainPanel(page).getByRole('button', { name: 'Kirim ulang' })).toHaveCount(0);
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);
    expect(sent).toHaveLength(5);

    // A 429 before any attempt reached the server (UXS-12): nothing ran, the way back waits with its reason,
    // and the final action repeats the frozen request once: one Sale (review R-02).
    await toPayment();
    await page.route(CHECKOUT, (route) => route.fulfill({ status: 429, headers: { 'Retry-After': '1' }, json: refusal('THROTTLED') }), { times: 1 });
    await page.keyboard.press('Control+Enter');
    await expect(page.getByRole('main')).toContainText('Terlalu banyak percobaan');
    await expect(page.getByRole('button', { name: 'Kembali ke keranjang (Esc)' })).toHaveAttribute('aria-disabled', 'true');
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL((url) => url.pathname === PAYMENT);
    await expect(finalAction(page)).not.toHaveAttribute('aria-disabled', 'true');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);
    await expect(page.getByRole('article', { name: 'Struk' })).toContainText(pen.name);
    expect(sent).toHaveLength(7);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('"Bayar" waits for the scans still out, so that the payment shows every scan and no answer changes it later (review R-01)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen, paper] = products;
    const lookup = gate();

    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');
    await page.route('**/data/products/search*', async (route) => {
        if (new URL(route.request().url()).searchParams.get('q') === paper.code) {
            await lookup.wait;
        }

        await route.continue();
    });

    // F8 while the second scan's lookup is out: the till stays until it is applied, then the payment shows both.
    await scan(page, paper.code);
    await page.keyboard.press('F8');
    await expect(page.getByRole('heading', { level: 1, name: 'Kasir' })).toBeVisible();
    await expect(lines(page)).toHaveCount(1);
    lookup.open();
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    await expect(page.getByText(`Uang diterima kurang ${rupiah(BigInt(pen.price) + BigInt(paper.price))}.`)).toBeVisible();

    // The sale is the one shown; the next cart starts empty.
    await page.keyboard.press('Alt+U');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);
    const paper58 = page.getByRole('article', { name: 'Struk' });
    await expect(paper58).toContainText(pen.name);
    await expect(paper58).toContainText(paper.name);
    await page.goto(TILL);
    await expect(cart(page)).toContainText('Keranjang masih kosong. Scan atau cari produk.');

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a definite refusal returns to the review with its reason at its line, never changing the total silently (AC-029-05)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen, , book] = products;

    // PRICE_CHANGED: back to the till with the changed line marked and the new total; "Bayar" waits for
    // "Setuju harga baru" (UXS-18, BR-SALE-05).
    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');
    await reprice(context, pen, '5000');
    await page.keyboard.press('F8');
    await page.keyboard.press('Alt+U');
    await expect(page.locator('.bk-change')).toContainText('Rp0');
    await page.keyboard.press('Control+Enter');
    await expect(page).toHaveURL((url) => url.pathname === TILL);
    const notice = page.getByRole('main').getByRole('alert').filter({ hasText: 'Harga berubah' });
    await expect(notice).toContainText('Harga 1 produk berubah sejak dimasukkan ke keranjang. Periksa harga dan total yang baru.');
    await expect(notice).toContainText('Total baru Rp5.000');
    await expect(lines(page).nth(0)).toContainText('Harga lama Rp4.500 → harga baru Rp5.000.');
    await expect(pay(page)).toHaveAttribute('aria-disabled', 'true');
    await expect(cart(page)).toContainText('Setujui harga baru dulu.');
    await notice.getByRole('button', { name: 'Setuju harga baru' }).click();
    await expect(pay(page)).not.toHaveAttribute('aria-disabled', 'true');
    await expect(cart(page).locator('.bk-total__value')).toHaveText('Rp5.000');

    // The payment screen starts again for the new total.
    await page.keyboard.press('F8');
    await expect(page.getByText('Uang diterima kurang Rp5.000.')).toBeVisible();
    await page.keyboard.press('Alt+U');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);
    await expect(page.getByRole('article', { name: 'Struk' })).toContainText('Rp5.000');

    // INSUFFICIENT_STOCK: the payment screen says which line, with the stock available; back in the cart
    // the line keeps it until it changes (UXS-31).
    await page.goto(TILL);
    await scan(page, book.code);
    await scan(page, book.code);
    await scan(page, book.code);
    await expect(quantity(page, book)).toHaveValue('3');
    await page.keyboard.press('F8');
    await page.keyboard.press('Alt+U');
    await expect(page.locator('.bk-change')).toContainText('Rp0');
    await page.keyboard.press('Control+Enter');
    const short = page.getByRole('main').getByRole('alert').filter({ hasText: 'Stok tidak cukup' });
    await expect(short).toContainText(`${book.name}: Tersedia 2 pcs`);
    await expect(page).toHaveURL((url) => url.pathname === PAYMENT);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL((url) => url.pathname === TILL);
    await expect(lines(page).nth(0)).toContainText('Tersedia 2 pcs');
    await page.getByRole('button', { name: `Kurangi satu pcs ${book.name}` }).click();
    await expect(quantity(page, book)).toHaveValue('2');
    await expect(lines(page).nth(0)).not.toContainText('Tersedia 2 pcs');
    await page.keyboard.press('F8');
    await page.keyboard.press('Alt+U');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('a scanner Enter never confirms the payment; offline, nothing is queued and posting is refused (AC-029-02)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen, paper] = products;
    const sent = checkouts(page);

    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');
    await page.keyboard.press('F8');
    await page.keyboard.press('Alt+U');
    await expect(finalAction(page)).not.toHaveAttribute('aria-disabled', 'true');

    // Enter on the final action and a scan into "Uang diterima" send nothing (BR-SALE-13, UXA-10, UXA-11).
    await finalAction(page).focus();
    await page.keyboard.press('Enter');
    await page.getByLabel('Uang diterima', { exact: true }).focus();
    await scan(page, paper.code);
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    expect(sent).toHaveLength(0);

    // Offline: the banner, the final action refused with its reason, a scan in the cart not queued (UXS-20).
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL((url) => url.pathname === TILL);
    await context.setOffline(true);
    await expect(page.getByText('Tidak ada koneksi', { exact: true })).toBeVisible();
    await scan(page, paper.code);
    await expect(page.getByRole('main').getByText('Gagal memuat', { exact: true })).toBeVisible();
    await expect(lines(page)).toHaveCount(1);
    await page.keyboard.press('F8');
    await page.keyboard.press('Alt+U');
    await expect(finalAction(page)).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByRole('main')).toContainText('Penyimpanan berhenti sampai koneksi kembali.');
    await page.keyboard.press('Control+Enter');
    expect(sent).toHaveLength(0);

    // Back online, nothing was queued: the cart is as it was.
    await context.setOffline(false);
    await page.keyboard.press('Escape');
    await expect(lines(page)).toHaveCount(1);
    expect(sent).toHaveLength(0);

    expect(injected(watched.problems).filter((problem) => !problem.includes('net::ERR_INTERNET_DISCONNECTED'))).toEqual([]);
    await context.close();
});

test('KS-03 with its states `empty`, `default`, `price-changed` and `unresolved` at every width (AC-029-06)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const width of WIDTHS) {
        const { context, page, watched, products } = await openTill(browser, width);
        const [pen, paper] = products;

        // `empty`, then `default`.
        await expect(cart(page)).toContainText('Keranjang masih kosong. Scan atau cari produk.');
        await checked(page);
        await scan(page, pen.code);
        await scan(page, paper.code);
        await expect(quantity(page, paper)).toHaveValue('1');
        await checked(page);

        // `price-changed` (UXS-18): a newer read of a product in the cart carries another price.
        await reprice(context, pen, '5000');
        await scan(page, pen.code);
        const notice = page.getByRole('main').getByRole('alert').filter({ hasText: 'Harga berubah' });
        await expect(notice).toContainText('Total baru');
        await expect(pay(page)).toHaveAttribute('aria-disabled', 'true');
        await checked(page);
        await notice.getByRole('button', { name: 'Setuju harga baru' }).click();
        await expect(pay(page)).not.toHaveAttribute('aria-disabled', 'true');

        // `unresolved` (NC-13): a checkout whose answer was lost; "Bayar" waits, "Cek pembayaran yang belum pasti" leads.
        await page.keyboard.press('F8');
        await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
        await page.keyboard.press('Alt+U');
        await loseNextCheckout(page);
        await page.keyboard.press('Control+Enter');
        await expect(uncertainPanel(page)).toBeVisible();
        await page.goto(TILL);
        await expect(page.getByRole('link', { name: 'Cek pembayaran yang belum pasti' })).toBeVisible();
        await expect(pay(page)).toHaveAttribute('aria-disabled', 'true');
        await checked(page);

        // Its status check answers the Sale, so that the next width starts without it.
        await page.goto(PAYMENT);
        await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);

        expect(injected(watched.problems), width.name).toEqual([]);
        await context.close();
    }
});

test('KS-04 with its states `short`, `default`, `transfer`, `submitting`, `uncertain` and `held` at every width, the final action usable on the tablet (AC-029-03, AC-029-06)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const width of WIDTHS) {
        const { context, page, watched, products } = await openTill(browser, width);
        const [pen, paper] = products;

        await scan(page, pen.code);
        await scan(page, paper.code);
        await expect(quantity(page, paper)).toHaveValue('1');
        await page.keyboard.press('F8');
        await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();

        // `short` (UXS-34), then `default` with the change.
        await page.getByLabel('Uang diterima', { exact: true }).fill('1000');
        await expect(page.locator('.bk-change')).toHaveAttribute('data-short', 'true');
        await expect(finalAction(page)).toHaveAttribute('aria-disabled', 'true');
        await checked(page);
        await page.keyboard.press('Alt+U');
        await expect(page.locator('.bk-change')).toContainText('Rp0');
        await checked(page);

        if (width.width === 768) {
            // The tablet: the final action 64 px high, at least 24 px below the controls above it (UXA-07; review R-06).
            const final = await finalAction(page).boundingBox();
            const field = await page.getByLabel('Nama pelanggan').boundingBox();

            expect(final?.height ?? 0).toBeGreaterThanOrEqual(64);
            expect((final?.y ?? 0) - ((field?.y ?? 0) + (field?.height ?? 0))).toBeGreaterThanOrEqual(24);
        }

        // `transfer`.
        await page.keyboard.press('Alt+2');
        await expect(page.getByRole('radio', { name: 'Transfer bank (Alt+2)' })).toHaveAttribute('aria-checked', 'true');
        await checked(page);

        // `submitting`: the request out, the final action busy with "Sedang diproses", the screen frozen.
        const answer = gate();

        await page.route(
            CHECKOUT,
            async (route) => {
                await answer.wait;
                await route.continue();
            },
            { times: 1 },
        );
        await page.keyboard.press('Control+Enter');
        await expect(page.getByRole('button', { name: 'Sedang diproses' })).toHaveAttribute('aria-busy', 'true');
        await expect(page.getByLabel('Nama pelanggan')).toBeDisabled();
        await checked(page);
        answer.open();
        await page.waitForURL((url) => RECEIPT.test(url.pathname));

        // `uncertain` (UXS-14).
        await payOne(page, pen);
        await loseNextCheckout(page);
        await page.keyboard.press('Control+Enter');
        await expect(uncertainPanel(page)).toContainText('Jangan minta pelanggan membayar atau mentransfer lagi.');
        await checked(page);
        await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);

        // `held` (UXS-15).
        await payOne(page, pen);
        await holdNextCheckout(page);
        await page.keyboard.press('Control+Enter');
        await expect(uncertainPanel(page)).toContainText('Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan.');
        await checked(page);
        await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);

        expect(injected(watched.problems), width.name).toEqual([]);
        await context.close();
    }
});

test('KS-05 in each size at every width (AC-029-06)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 5);

    for (const width of WIDTHS) {
        const { context, page, watched, products } = await openTill(browser, width);
        const [pen] = products;

        await payOne(page, pen);
        await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);
        await expect(page.getByRole('heading', { level: 1, name: 'Struk' })).toBeVisible();
        const paper58 = page.getByRole('article', { name: 'Struk' });

        for (const size of ['58 mm', '80 mm', 'A4']) {
            await page.getByRole('radio', { name: size }).click();
            await expect(page.getByRole('radio', { name: size })).toHaveAttribute('aria-checked', 'true');
            await checked(page);
        }

        await expect(paper58).toContainText('Nota Penjualan');
        // The size stays the browser session's choice; the next receipt opens in 58 mm again.
        await page.getByRole('radio', { name: '58 mm' }).click();

        expect(injected(watched.problems), width.name).toEqual([]);
        await context.close();
    }
});

test('the refusals of RC-01 on the payment screen show their states (UI_STATES UXP-102; UXS-27, UXS-28, UXS-29, UXS-30, UXS-32, UXS-46)', async ({ browser }) => {
    const { context, page, watched, products } = await openTill(browser);
    const [pen] = products;
    const sent = checkouts(page);
    const line = { '0': { productId: pen.id } };
    // Each answered in place of the server, which never sees the request: their server paths are RC-01's tests (TASK-028).
    const cases: readonly { readonly code: string; readonly title: string; readonly lines: Record<string, unknown> }[] = [
        { code: 'ONBOARDING_FREEZE', title: 'Masa input stok awal aktif', lines: {} },
        { code: 'POSTING_BLOCKED', title: 'Mutasi diblokir', lines: line },
        { code: 'NO_OPEN_SESSION', title: 'Belum ada sesi kas', lines: {} },
        { code: 'SESSION_NOT_OPEN', title: 'Sesi kas sudah ditutup', lines: {} },
        { code: 'PRODUCT_INACTIVE', title: 'Nonaktif', lines: line },
        { code: 'METHOD_NOT_ALLOWED', title: 'Metode pembayaran tidak tersedia', lines: {} },
    ];

    await payOne(page, pen);

    for (const refused of cases) {
        await page.route(CHECKOUT, (route) => route.fulfill({ status: 422, json: { ...refusal(refused.code), lines: refused.lines } }), { times: 1 });
        await page.keyboard.press('Control+Enter');
        const state = page.getByRole('main').getByRole('alert').filter({ hasText: refused.title });

        await expect(state, refused.code).toBeVisible();
        await expect(page).toHaveURL((url) => url.pathname === PAYMENT);

        if (refused.code === 'PRODUCT_INACTIVE') {
            await expect(state).toContainText(`Produk ${pen.name} sudah nonaktif dan tidak bisa dipakai.`);
        }

        await checked(page);
    }

    expect(sent).toHaveLength(cases.length);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('at the tablet width a sale takes three taps in cash and three by bank transfer (AC-029-07; USER_TASKS section 5)', async ({ browser }) => {
    const tablet = WIDTHS.find((width) => width.width === 768);

    if (tablet === undefined) {
        throw new Error('KEY_SCREENS section 4 has the tablet width 768.');
    }

    const { context, page, watched, products } = await openTill(browser, tablet);
    const [pen] = products;
    const counted = async (taps: string[], target: Locator, name: string): Promise<void> => {
        taps.push(name);
        await target.click();
    };

    // Cash: "Bayar" → "Uang Pas" → "Selesaikan".
    const cash: string[] = [];

    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');
    await counted(cash, pay(page), 'Bayar');
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    await counted(cash, page.getByRole('button', { name: 'Uang Pas (Alt+U)' }), 'Uang Pas');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), counted(cash, finalAction(page), 'Selesaikan')]);
    expect(cash).toEqual(['Bayar', 'Uang Pas', 'Selesaikan']);

    // Bank transfer: "Bayar" → "Transfer bank" → "Dana sudah diterima — Selesaikan".
    const transfer: string[] = [];

    await page.goto(TILL);
    await scan(page, pen.code);
    await expect(quantity(page, pen)).toHaveValue('1');
    await counted(transfer, pay(page), 'Bayar');
    await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
    await counted(transfer, page.getByRole('radio', { name: 'Transfer bank (Alt+2)' }), 'Transfer bank');
    await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), counted(transfer, finalAction(page), 'Dana sudah diterima — Selesaikan')]);
    await expect(page.getByRole('article', { name: 'Struk' })).toContainText('Pembayaran: Transfer bank');
    expect(transfer).toEqual(['Bayar', 'Transfer bank', 'Dana sudah diterima — Selesaikan']);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
