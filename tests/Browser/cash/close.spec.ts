import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Locator, type Page, type Route } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, OPEN_SESSION_HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { cashSession, type FixtureRegister, type FixtureSession } from '../support/cash';
import { injected, prepareCommands, receiptOf, refusal, REQUESTS_PAGE } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-105 "Tutup sesi kas" with KS-07's states `default`, `count`, `review`, `recount` and
// `unresolved-checkout`, and UXP-206 "Detail sesi kas" after the close (AC-030-01, AC-030-03, AC-030-07;
// COPY_DECK sections 7.5, 7.11 and 15; INTERACTION_INVENTORY UXI-077, UXI-101 to UXI-111 and UXI-148;
// NAVIGATION_CONTRACTS UXP-105, UXP-206; UI_STATES UXS-19; WF-08; suites UX01, UX02, SESSION01 and
// SESSION05; TASK-030, L5). The Owner closes his session after a Sale in cash and one by bank transfer:
// the preview of the server, the count, the review with the variance in words, the report — the L5
// critical path of the close that every later pull request runs (EXECUTION_CONTEXT EXC-34). The cashier
// opens her own session at every width (KS-03 `opened`), sells once in cash at the first (KS-03 to KS-05)
// and closes it again each time, so that her home stays Buka sesi kas (tests/Browser/support/access.ts;
// TASK-029 review R-07). The count is discarded back at the preview, the unsaved-changes guard asks once
// a count is entered, a failed reload of the preview is said in place, and a lost or held close is
// checked, never counted again, as a session closed meanwhile is said (review R-01, R-05, R-06, R-10).
// Each session is the fixture's or the page's own, so this file runs on any database.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const TILL = '/till';

const CLOSE_PAGE = '/till/close-session';

const CLOSE_COMMAND = '**/commands/cash_session.close';

/** RP-105 itself, which the page reads again for its preview (a partial reload). */
const CLOSE_PAGE_ROUTE = '**/till/close-session';

const REPORT = /^\/cash-sessions\/\d+$/;

const RECEIPT = /^\/sales\/\d+\/receipt$/;

interface TillProduct {
    readonly id: number;
    readonly name: string;
    readonly code: string;
    readonly price: string;
}

async function token(context: BrowserContext): Promise<string> {
    const cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    return decodeURIComponent(cookie?.value ?? '');
}

/** A new product with its barcode and stock (the fixture `till`, TASK-029). */
async function tillProduct(context: BrowserContext): Promise<TillProduct> {
    const answer = await context.request.post('/testing/browser/till', { data: {}, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    const [product] = ((await answer.json()) as { products: TillProduct[] }).products;

    if (product === undefined) {
        throw new Error('The fixture gives a product.');
    }

    return product;
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

function searchField(page: Page): Locator {
    return page.getByRole('searchbox', { name: 'Cari atau scan produk (F2)' });
}

function continueCount(page: Page): Locator {
    return page.getByRole('button', { name: /^Lanjut hitung/ });
}

function physicalCash(page: Page): Locator {
    return page.getByLabel('Uang fisik di laci', { exact: true });
}

function reviewDialog(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Tinjau tutup sesi kas' });
}

function uncertainPanel(page: Page): Locator {
    return page.getByRole('main').getByRole('alert').filter({ hasText: 'Status belum pasti' });
}

function leaveDialog(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
}

/** A new OPEN session of the Owner through RC-02's Action, his earlier one closed through RC-03's. */
async function ownerSession(context: BrowserContext): Promise<FixtureSession> {
    const { session } = await cashSession(context, 'Hendra', { open: '0' });

    if (session === null) {
        throw new Error('The fixture opens a session.');
    }

    return session;
}

/** A full load of the till, its search field focused once the page has bound its keys (UXA-09). */
async function gotoTill(page: Page): Promise<void> {
    await page.goto(TILL);
    await expect(searchField(page)).toBeFocused();
}

/** "Tutup sesi kas" in the till's header (UXI-077) to the close page, its preview loaded (UXI-101). */
async function gotoClose(page: Page): Promise<void> {
    await gotoTill(page);
    await Promise.all([page.waitForURL((url) => url.pathname === CLOSE_PAGE), page.getByRole('link', { name: 'Tutup sesi kas', exact: true }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Tutup sesi kas' })).toBeVisible();
    await expect(page.getByRole('main')).toContainText('Uang seharusnya di laci');
}

/** UXP-104 to the till with the register and Rp0, through the review (KS-06), which says the session opened (KS-03 `opened`). */
async function openOwnSession(page: Page, register: FixtureRegister): Promise<void> {
    const name = `${register.code} · ${register.label}`;
    const choice = page.getByRole('radio', { name });

    await expect(page.getByRole('heading', { level: 1, name: 'Buka sesi kas' })).toBeVisible();

    if ((await choice.count()) > 0) {
        await choice.click();
    } else {
        await page.getByLabel('Laci kas', { exact: true }).selectOption({ label: name });
    }

    await page.getByLabel('Modal awal', { exact: true }).fill('0');
    await page.getByRole('button', { name: /^Tinjau/ }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === TILL), page.getByRole('dialog', { name: 'Tinjau buka sesi kas' }).getByRole('button', { name: /^Buka sesi/ }).click()]);
    await expect(page.getByText(/^Sesi kas \d{8}-\d{6,} dibuka\.$/)).toBeVisible();
}

test('the Owner closes his session after a sale in cash and one by bank transfer: preview, count, review and report (AC-030-01, EXC-34)', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const accounts = await resetPeople(context, ['Hendra']);

    await prepareCommands(context, ['Hendra']);
    const { session } = await cashSession(context, 'Hendra', { open: '20000', sales: ['CASH', 'BANK_TRANSFER'] });

    if (session === null) {
        throw new Error('The fixture opens a session.');
    }

    await signIn(page, accounts.Hendra);
    await gotoClose(page);

    // KS-07 `default`: the server's figures, BANK_TRANSFER apart and never in expected cash (G-05).
    const preview = page.getByRole('main');
    await expect(preview).toContainText('Modal awal');
    await expect(preview).toContainText('Rp20.000');
    await expect(preview).toContainText('Pembayaran tunai');
    await expect(preview).toContainText('Pembayaran transfer bank');
    await expect(preview).toContainText('Rp50.000');
    await expect(preview).toContainText('Rp70.000');
    await expect(page.getByRole('link', { name: 'Batal' })).toHaveAttribute('href', TILL);

    // Without a count, "Batal" leaves at once (UXI-110; UXS-45; review R-10).
    await Promise.all([page.waitForURL((url) => url.pathname === TILL), page.getByRole('link', { name: 'Batal' }).click()]);
    await expect(leaveDialog(page)).toHaveCount(0);
    await gotoClose(page);

    // `count`: a blank amount is refused with its reason, and Enter sends nothing (UXA-10).
    await continueCount(page).click();
    await expect(physicalCash(page)).toBeVisible();
    await page.getByRole('button', { name: /^Tinjau/ }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByText('Isi uang fisik di laci.')).toBeVisible();
    await physicalCash(page).fill('68000');
    await physicalCash(page).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // With a count, "Batal" and "Kembali ke Kasir" ask first, and the page stays (UXI-110, UXI-111; UXS-45;
    // review R-10).
    for (const name of ['Batal', 'Kembali ke Kasir']) {
        await page.getByRole('link', { name }).click();
        await expect(leaveDialog(page)).toBeVisible();
        await leaveDialog(page).getByRole('button', { name: 'Tetap di halaman ini' }).click();
        await expect(leaveDialog(page)).toHaveCount(0);
        await expect(page).toHaveURL((url) => url.pathname === CLOSE_PAGE);
        await expect(physicalCash(page)).toHaveValue(/68/);
    }

    // "Kembali ke pratinjau" (UXI-106) discards the count, so that the preview is counted again
    // (NAVIGATION_CONTRACTS UXP-105; review R-01).
    await page.getByRole('button', { name: 'Kembali ke pratinjau' }).click();
    await expect(physicalCash(page)).toHaveCount(0);
    await continueCount(page).click();
    await expect(physicalCash(page)).toHaveValue('');
    await physicalCash(page).fill('68000');

    // `review`: the count, expected cash and the variance in words; "Kembali ubah" first (UXI-107).
    await page.getByRole('button', { name: /^Tinjau/ }).click();
    const review = reviewDialog(page);
    await expect(review).toContainText('Rp68.000');
    await expect(review).toContainText('Rp70.000');
    await expect(review).toContainText('Rp2.000 (kurang)');
    await expect(review.getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();

    // "Tutup sesi": the session's report, which says it was closed (UXP-206).
    await Promise.all([page.waitForURL((url) => REPORT.test(url.pathname)), review.getByRole('button', { name: /^Tutup sesi/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
    await expect(page.getByText(`Sesi kas ${session.number} ditutup.`)).toBeVisible();
    const report = page.getByRole('main');
    await expect(report).toContainText('Laporan sesi kas');
    await expect(report).toContainText('Rp68.000');
    await expect(report).toContainText('Rp2.000 (kurang)');
    await expect(page.getByRole('link', { name: 'Kembali ke Sesi kas' })).toHaveAttribute('href', '/cash-sessions');
    await checked(page);

    // A session closed is never OPEN again: the till leads to Buka sesi kas.
    await page.goto(TILL);
    await expect(page).toHaveURL((url) => url.pathname === OPEN_SESSION_HOME);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the cashier opens her session at the till and closes it with KS-07 at every width (AC-030-03, AC-030-07; TASK-029 review R-07)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const [index, width] of WIDTHS.entries()) {
        const first = index === 0;
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);
        const accounts = await resetPeople(context, ['Rina']);

        await prepareCommands(context, ['Rina']);
        const { register } = await cashSession(context, 'Rina', { register: true });
        const product = first ? await tillProduct(context) : null;

        if (register === null) {
            throw new Error('The fixture gives a register.');
        }

        // KS-03 `opened`: her home is Buka sesi kas; the till says the session it opened.
        await signIn(page, accounts.Rina);
        await openOwnSession(page, register);
        await checked(page);

        // One sale in cash, KS-03 to KS-05, as the cashier.
        if (product !== null) {
            await gotoTill(page);
            await page.keyboard.type(product.code);
            await page.keyboard.press('Enter');
            await expect(page.getByRole('textbox', { name: `Jumlah ${product.name}`, exact: true })).toHaveValue('1');
            await checked(page);
            await page.keyboard.press('F8');
            await expect(page.getByRole('heading', { level: 1, name: 'Pembayaran' })).toBeVisible();
            await expect(page.getByLabel('Uang diterima', { exact: true })).toBeFocused();
            await page.keyboard.press('Alt+U');
            await checked(page);
            await Promise.all([page.waitForURL((url) => RECEIPT.test(url.pathname)), page.keyboard.press('Control+Enter')]);
            await expect(page.getByRole('heading', { level: 1, name: 'Struk' })).toBeVisible();
            await checked(page);
        }

        // KS-07 `default`.
        await gotoClose(page);
        await checked(page);

        if (first) {
            // `unresolved-checkout` (UXI-102, UXI-103; G-27): a checkout of hers that another device left
            // unresolved keeps "Lanjut hitung" disabled with its reason; the Owner's status check settles
            // it (D-089), and the count opens.
            await receiptOf(context, 'Rina', 'sale.checkout');
            await page.reload();
            await expect(page.getByRole('main')).toContainText('Ada penjualan di sesi ini yang statusnya belum pasti.');
            await expect(page.getByRole('link', { name: 'Cek permintaan yang belum pasti' })).toHaveAttribute('href', REQUESTS_PAGE);
            await expect(continueCount(page)).toHaveAttribute('aria-disabled', 'true');
            await checked(page);
            await prepareCommands(context, ['Rina']);
            await page.reload();
            await expect(page.getByRole('main')).toContainText('Uang seharusnya di laci');
            await expect(continueCount(page)).not.toHaveAttribute('aria-disabled', 'true');
        }

        // `count`, then `review`.
        await continueCount(page).click();
        await expect(physicalCash(page)).toBeVisible();
        await checked(page);
        await physicalCash(page).fill(product === null ? '0' : product.price);
        await page.getByRole('button', { name: /^Tinjau/ }).click();
        await expect(reviewDialog(page).getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
        await checked(page);

        if (first) {
            // `recount` (UXS-19): a Sale after the preview; the page returns to it, reloaded, and the
            // count is discarded, so that the new preview is counted again before a new review
            // (NAVIGATION_CONTRACTS UXP-105; review R-01). A reload that fails is said in place with
            // "Coba lagi" (UXS-22), and "Lanjut hitung" waits for the figures (review R-06).
            await page.route(CLOSE_COMMAND, (route) => route.fulfill({ status: 409, json: { ...refusal('RECOUNT_REQUIRED'), messageKey: 'ux.state.recount' } }), { times: 1 });
            await page.route(CLOSE_PAGE_ROUTE, (route) => route.fulfill({ status: 503, body: '' }), { times: 1 });
            await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
            await expect(page.getByRole('main').getByRole('alert').filter({ hasText: 'Ada transaksi baru; hitung ulang kas' })).toBeVisible();
            await expect(reviewDialog(page)).toHaveCount(0);
            await expect(page.getByRole('main')).toContainText('Bagian ini tidak dapat dimuat.');
            await expect(continueCount(page)).toHaveAttribute('aria-disabled', 'true');
            await page.getByRole('main').getByRole('button', { name: 'Coba lagi' }).click();
            await expect(page.getByRole('main')).toContainText('Uang seharusnya di laci');
            await expect(continueCount(page)).not.toHaveAttribute('aria-disabled', 'true');
            await checked(page);
            await continueCount(page).click();
            await expect(physicalCash(page)).toHaveValue('');
            await physicalCash(page).fill(product === null ? '0' : product.price);
            await page.getByRole('button', { name: /^Tinjau/ }).click();
        }

        // "Tutup sesi": the report, then her home is Buka sesi kas again.
        await Promise.all([page.waitForURL((url) => REPORT.test(url.pathname)), reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click()]);
        await expect(page.getByText(/^Sesi kas \d{8}-\d{6,} ditutup\.$/)).toBeVisible();
        await checked(page);
        await page.goto(TILL);
        await expect(page).toHaveURL((url) => url.pathname === OPEN_SESSION_HOME);

        expect(injected(watched.problems), width.name).toEqual([]);
        await context.close();
    }
});

test('a close whose answer is lost or held is checked, never counted again, and a session closed meanwhile is said (UXS-14, UXS-15, UXS-30; AC-030-04, TS-22; review R-05)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 3);
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const accounts = await resetPeople(context, ['Hendra']);

    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);

    // The close page of his OPEN session, counted at Rp0 and reviewed.
    const counted = async (): Promise<void> => {
        await gotoClose(page);
        await continueCount(page).click();
        await physicalCash(page).fill('0');
        await page.getByRole('button', { name: /^Tinjau/ }).click();
        await expect(reviewDialog(page).getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
    };

    // A lost answer (UXS-14): the close committed, the page frozen on its panel, never a second count;
    // "Cek status" finds it and opens its report, which says it was closed (RC-50; NC-13).
    const lost = await ownerSession(context);
    await counted();
    await page.route(
        CLOSE_COMMAND,
        async (route: Route) => {
            await route.fetch();
            await route.abort();
        },
        { times: 1 },
    );
    await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
    await expect(uncertainPanel(page)).toContainText('Jangan hitung ulang laci atau tutup sesi ini lagi. Sesi kas ini mungkin sudah ditutup.');
    await expect(page.getByRole('button', { name: /^Tinjau/ })).toHaveAttribute('aria-disabled', 'true');
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === `/cash-sessions/${String(lost.id)}`), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);
    await expect(page.getByText(`Sesi kas ${lost.number} ditutup.`)).toBeVisible();

    // A held close (UXS-15) offers "Cek status" only, which finds it.
    const held = await ownerSession(context);
    await counted();
    await page.route(
        CLOSE_COMMAND,
        async (route: Route) => {
            await route.fetch();
            await route.fulfill({ status: 409, json: refusal('IDEMPOTENCY_CONFLICT', 'HELD') });
        },
        { times: 1 },
    );
    await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
    await expect(uncertainPanel(page)).toContainText('Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan.');
    await expect(uncertainPanel(page).getByRole('button', { name: 'Kirim ulang' })).toHaveCount(0);
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === `/cash-sessions/${String(held.id)}`), uncertainPanel(page).getByRole('button', { name: 'Cek status' }).click()]);
    await expect(page.getByText(`Sesi kas ${held.number} ditutup.`)).toBeVisible();

    // A session closed meanwhile (UXS-30), here by the fixture's close as by another device: the count is
    // not kept, and the page leads to the report.
    const closed = await ownerSession(context);
    await counted();
    await cashSession(context, 'Hendra');
    await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
    await expect(page.getByRole('main')).toContainText('Sesi kas ini sudah ditutup, mungkin oleh Pemilik. Hitungan Anda tidak disimpan.');
    await checked(page);
    await Promise.all([
        page.waitForURL((url) => url.pathname === `/cash-sessions/${String(closed.id)}`),
        page.getByRole('main').getByRole('link', { name: 'Buka Detail sesi kas', exact: true }).click(),
    ]);
    await expect(page.getByRole('main')).toContainText('Laporan sesi kas');

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
