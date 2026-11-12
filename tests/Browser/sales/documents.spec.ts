import AxeBuilder from '@axe-core/playwright';
import { readFile } from 'node:fs/promises';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { cashSession, type FixtureSession } from '../support/cash';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-201 "Penjualan", UXP-202 "Detail penjualan", the print controls of UXP-103 and UXP-206 and the print
// views RP-103P and RP-206P (ROUTE_CONTRACTS RP-201, RP-202, RC-44 to RC-46, RP-103P, RP-206P; COPY_DECK
// sections 7.3, 7.6, 7.7, 7.11, 17.1 and 17.2; INTERACTION_INVENTORY UXI-003, UXI-090, UXI-092, UXI-112 to
// UXI-117, UXI-119, UXI-121, UXI-141, UXI-142; DOCUMENT_CONTRACTS DC-01 to DC-03; UI_STATES UXS-44; UXA-10,
// UXA-23; REQ-SALE-11; TASK-031 section 7, L5). The cashier finds her Sale at every width, reprints it in
// each size — the first print the original, every later one "Salinan" — prints from the receipt, survives
// a failed print with the Sale reprintable, and prints and downloads her closed session's report. Every
// session and Sale is the fixture's, through the Actions.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const SALE = /^\/sales\/\d+$/;

const RECEIPT_PRINT = /^\/sales\/\d+\/receipt\/print\/\d+$/;

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

/** The browser's print dialog, counted instead of opened, in every page of the context. */
async function countPrints(context: BrowserContext): Promise<void> {
    await context.addInitScript(() => {
        window.print = () => {
            const counted = window as unknown as { printed?: number };

            counted.printed = (counted.printed ?? 0) + 1;
        };
    });
}

/** Rina signed in with an OPEN session of hers holding a Sale in each method. */
async function withSales(context: BrowserContext, page: Page): Promise<FixtureSession> {
    const accounts = await resetPeople(context, ['Rina']);

    await prepareCommands(context, ['Rina']);
    const { session } = await cashSession(context, 'Rina', { open: '0', sales: ['CASH', 'BANK_TRANSFER'] });

    if (session === null) {
        throw new Error('The fixture opens a session.');
    }

    await signIn(page, accounts.Rina);

    return session;
}

/** Opens the print view from a print control, in its own tab, and waits for its paper. */
async function printFrom(context: BrowserContext, page: Page, name: string): Promise<Page> {
    const [tab] = await Promise.all([context.waitForEvent('page'), page.getByRole('button', { name }).click()]);

    await tab.waitForURL((url) => url.pathname.includes('/print/'));
    await expect(tab.locator('.bk-paper')).toBeVisible();

    return tab;
}

test('the cashier finds her Sale at every width and reprints it in each size, the first the original and every later one "Salinan" (UXP-201, UXP-202, RP-103P)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const [index, width] of WIDTHS.entries()) {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await countPrints(context);
        const session = await withSales(context, page);

        // UXI-003, UXI-112 to UXI-116: her own Sales, which the page says, newest first.
        await page.goto('/sales');
        await expect(page.getByRole('heading', { level: 1, name: 'Penjualan' })).toBeVisible();
        await expect(page.getByText('Menampilkan penjualan Anda sendiri.')).toBeVisible();
        await checked(page);

        // UXI-115 to UXP-202: the Sale with its lines, payment and session (UXI-119).
        await Promise.all([page.waitForURL((url) => SALE.test(url.pathname)), page.getByRole('main').getByRole('link', { name: /^\d{8}-\d{6,}$/ }).first().click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Detail penjualan' })).toBeVisible();
        await expect(page.getByRole('link', { name: `Sesi kas ${session.number}` })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Penjualan' })).toBeVisible();
        await checked(page);

        if (index === 0) {
            // UXI-117 in each size: RC-44, then RP-103P from the snapshot; "Salinan" first on every later print.
            for (const [size, paper, copy] of [
                ['58 mm', '58', false],
                ['80 mm', '80', true],
                ['A4', 'a4', true],
            ] as const) {
                await page.getByRole('radio', { name: size }).click();
                await expect(page.getByRole('radio', { name: size })).toHaveAttribute('aria-checked', 'true');
                const tab = await printFrom(context, page, 'Cetak ulang struk');

                expect(new URL(tab.url()).searchParams.get('size')).toBe(paper);
                await expect(tab.locator(`.bk-paper[data-paper="${paper}"]`)).toBeVisible();
                await expect(tab.locator('.bk-paper__copy')).toHaveCount(copy ? 1 : 0);

                if (copy) {
                    await expect(tab.locator('.bk-paper > :first-child')).toHaveText('Salinan');
                }

                if (paper === 'a4') {
                    await expect(tab.locator('.bk-paper')).toContainText('Nota Penjualan');
                    await expect(tab.getByRole('columnheader')).toHaveText(['Produk', 'Jumlah', 'Harga', 'Subtotal']);
                }

                await expect.poll(() => tab.evaluate(() => (window as unknown as { printed?: number }).printed ?? 0)).toBe(1);
                await checked(tab);
                await tab.close();
            }

            // The page stays for another print, and the way back keeps the list (UXI-121).
            expect(RECEIPT_PRINT.test(new URL(page.url()).pathname)).toBe(false);
            await Promise.all([page.waitForURL((url) => url.pathname === '/sales'), page.getByRole('link', { name: 'Kembali ke Penjualan' }).click()]);
        }

        expect(injected(watched.problems), width.name).toEqual([]);
        await cashSession(context, 'Rina');
        await context.close();
    }
});

test('the receipt prints and leads to its detail, and a failed print leaves the Sale intact and reprintable (UXP-103, REQ-SALE-11)', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await countPrints(context);
    await withSales(context, page);
    await page.goto('/sales');
    const sale = (await page.getByRole('main').getByRole('link', { name: /^\d{8}-\d{6,}$/ }).first().getAttribute('href')) ?? '';

    await page.goto(`${sale}/receipt`);
    await expect(page.getByRole('heading', { level: 1, name: 'Struk' })).toBeVisible();
    await expect(page.getByText('Pastikan struk benar-benar tercetak.')).toBeVisible();

    // UXS-44: the request fails; the tab closes, the page says so, and the Sale stays as it was.
    await page.route('**/commands/document.print_receipt', (route) => route.abort());
    await page.getByRole('button', { name: 'Cetak struk' }).click();
    await expect(page.getByText('Cetak atau unduh gagal')).toBeVisible();
    await expect.poll(() => context.pages().length).toBe(1);
    await page.unroute('**/commands/document.print_receipt');

    // "Coba lagi" repeats the same request, which opens its print view; Enter never prints (UXA-10).
    const [tab] = await Promise.all([context.waitForEvent('page'), page.getByRole('button', { name: 'Coba lagi' }).click()]);

    await tab.waitForURL((url) => RECEIPT_PRINT.test(url.pathname));
    await expect(tab.locator('.bk-paper')).toBeVisible();
    await tab.close();
    await expect(page.getByText('Cetak atau unduh gagal')).toHaveCount(0);
    await page.getByRole('button', { name: 'Cetak struk' }).focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);
    expect(context.pages()).toHaveLength(1);

    // UXI-092: "Detail penjualan".
    await Promise.all([page.waitForURL((url) => url.pathname === sale), page.getByRole('link', { name: 'Detail penjualan' }).click()]);
    await expect(page.getByRole('link', { name: 'Kembali ke Struk' })).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await cashSession(context, 'Rina');
    await context.close();
});

test('the closed session report prints on A4 and downloads as the CSV of DC-03 (UXP-206, RP-206P, RC-46)', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);

    await countPrints(context);
    const session = await withSales(context, page);

    await cashSession(context, 'Rina');
    await page.goto(`/cash-sessions/${String(session.id)}`);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cetak laporan' })).toBeVisible();
    await checked(page);

    // UXI-141: RC-45, then RP-206P.
    const tab = await printFrom(context, page, 'Cetak laporan');

    expect(new URL(tab.url()).pathname).toMatch(new RegExp(`^/cash-sessions/${String(session.id)}/report/print/\\d+$`));
    await expect(tab.locator('.bk-paper[data-paper="a4"]')).toContainText('Laporan sesi kas');
    await expect(tab.locator('.bk-paper')).toContainText(`No. sesi: ${session.number}`);
    await expect(tab.locator('.bk-paper')).toContainText('Zona waktu: WIB (Asia/Jakarta)');
    await expect(tab.locator('.bk-paper__copy')).toHaveCount(0);
    await checked(tab);
    await tab.close();

    // UXI-142: RC-46, whose answer is the file.
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Unduh CSV' }).click()]);

    expect(download.suggestedFilename()).toBe(`laporan-sesi-${session.number}.csv`);
    const content = await readFile(await download.path(), 'utf8');

    expect(content.startsWith(`\u{FEFF}"Laporan sesi kas";"${session.number}"\r\n`)).toBe(true);
    expect(content).toContain('"Jumlah penjualan";2\r\n');
    expect(content.endsWith('"Zona waktu: WIB (Asia/Jakarta)"\r\n')).toBe(true);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
