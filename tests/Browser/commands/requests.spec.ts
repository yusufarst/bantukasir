import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { COMMAND_SPECIMEN, injected, prepareCommands, PROBE_ROUTE, receiptOf, REQUESTS_PAGE, storedReferences } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-006 "Permintaan belum pasti" and the shell's indicators (AC-009-03,
// AC-009-05, AC-009-06, AC-009-07; INTERACTION_CONTRACTS sections 2.2, 2.4 and
// 3.3; IC-10; SECURITY_MODEL SEC-CH-05; INTERACTION_INVENTORY UXI-025 to
// UXI-029, UXI-055, UXI-057, UXI-058; COPY_DECK sections 6.1, 6.7 and 14;
// suites CMD01, UX01 and L10N01; TASK-009 scope items 2, 5, 6 and 7): for a
// cashier, the union of the references and the receipts with the banner,
// the account-menu number and the sign-out warning; references that survive a
// reload and a sign-out, never shown to another user and released when
// resolved; and for the Owner, every other user's unresolved request with
// the user's name, whose check settles that user's receipts.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function accessible(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);
}

function main(page: Page) {
    return page.getByRole('main');
}

test('a cashier sees a request of another device in the banner, the account menu and UXP-006, is warned at sign-out, and resolves it', async ({ page }) => {
    const watched = await watch(page);
    const { Rina: rina } = await resetPeople(page.context(), ['Rina']);

    await prepareCommands(page.context(), ['Rina']);
    await receiptOf(page.context(), 'Rina', 'sale.checkout');
    await signIn(page, rina);

    // The shell, from RD-03 and RD-04 (UXI-029, UXI-025).
    await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Menu akun Rina' }).click();
    await expect(page.getByRole('menuitem', { name: 'Permintaan belum pasti (1)' })).toBeVisible();

    // The sign-out warning (UXI-027, UXI-028): "Tetap keluar" signs out.
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    const warning = page.getByRole('dialog', { name: 'Masih ada permintaan yang statusnya belum pasti' });
    await expect(warning).toContainText('Catatan permintaan itu tetap ada di peramban ini.');
    await accessible(page);
    await Promise.all([page.waitForURL((url) => url.pathname === '/login'), warning.getByRole('button', { name: 'Tetap keluar' }).click()]);

    // Signed in again: the server still lists it; the banner leads to UXP-006.
    await signIn(page, rina);
    await page.getByRole('link', { name: 'Buka Permintaan belum pasti' }).click();
    await expect(page).toHaveURL(new RegExp(`${REQUESTS_PAGE}$`));
    await expect(page).toHaveTitle('Permintaan belum pasti — Toko Contoh');
    await expect(main(page).getByRole('heading', { level: 1, name: 'Permintaan belum pasti' })).toBeVisible();
    await expect(main(page).getByText('Hasil permintaan berikut belum diketahui.')).toBeVisible();
    await expect(main(page).getByText('Jika permintaan tidak ada di daftar ini dan tidak ada di riwayat, tunggu 10 menit sejak dikirim.')).toBeVisible();
    const card = main(page).getByRole('listitem').filter({ hasText: 'Penjualan · dikirim' });
    await expect(card).toContainText('Dikirim dari perangkat lain atau sebelum data peramban dihapus.');
    await expect(card).toContainText('Jangan minta pelanggan membayar atau mentransfer lagi.');
    await expect(main(page).getByRole('link', { name: 'Kembali ke halaman awal' })).toHaveAttribute('href', '/');
    // The banner is not repeated on the page it leads to.
    await expect(page.getByRole('link', { name: 'Buka Permintaan belum pasti' })).toHaveCount(0);
    await accessible(page);

    // "Cek status" (UXI-055): never committed, so fenced; the card disappears.
    await card.getByRole('button', { name: 'Cek status' }).click();
    await expect(main(page).getByText('Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.').first()).toBeVisible();
    await expect(main(page).getByText('Tidak ada permintaan yang statusnya belum pasti.')).toBeVisible();
    await page.getByRole('button', { name: 'Menu akun Rina' }).click();
    await expect(page.getByRole('menuitem', { name: /Permintaan belum pasti/ })).toHaveCount(0);
    await page.keyboard.press('Escape');

    // UXI-057: back to the role's home.
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), main(page).getByRole('link', { name: 'Kembali ke halaman awal' }).click()]);
    await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toHaveCount(0);
    expect(injected(watched.problems)).toEqual([]);
});

test('a reference survives a reload and a sign-out, is never shown to another user, and is released when resolved', async ({ page }) => {
    const { Agus: agus, Dewi: dewi } = await resetPeople(page.context(), ['Agus', 'Dewi']);

    await prepareCommands(page.context(), ['Agus', 'Dewi']);
    await signIn(page, agus);
    await page.goto(COMMAND_SPECIMEN);

    // The request never reaches the server: only this browser knows it.
    await page.route(PROBE_ROUTE, (route) => route.abort('failed'));
    await main(page).getByRole('button', { name: 'Simpan' }).click();
    await expect(main(page).getByRole('alert').filter({ hasText: 'Status belum pasti' })).toBeVisible();
    await page.unroute(PROBE_ROUTE);
    const stored = await storedReferences(page);
    expect(Object.values(stored)).toHaveLength(1);
    // Operation, family, intent, key, epoch and time; never the payload (SEC-CH-05).
    expect(Object.values(stored)[0]).toMatch(/^testing\.probe review_note [0-9a-f-]{36} [0-9a-f-]{36} [0-9a-f]{64} [0-9T:.Z-]{24}$/);
    expect(Object.values(stored)[0]).not.toContain('Catatan uji');

    // A reload: the page opens on the reference's panel (NC-13 (b)).
    await page.reload();
    await expect(main(page).getByRole('alert').filter({ hasText: 'Status belum pasti' })).toBeVisible();
    await expect(main(page).getByRole('button', { name: 'Simpan' })).toHaveAttribute('aria-disabled', 'true');

    // A sign-out, through the warning: the reference stays, everything else goes.
    await page.evaluate(() => window.localStorage.setItem('bk-other', 'x'));
    await page.getByRole('button', { name: 'Menu akun Agus' }).click();
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === '/login'), page.getByRole('button', { name: 'Tetap keluar' }).click()]);
    expect(await storedReferences(page)).toEqual(stored);
    expect(await page.evaluate(() => window.localStorage.getItem('bk-other'))).toBeNull();

    // Another user in the same browser never sees it.
    await signIn(page, dewi);
    await page.goto(REQUESTS_PAGE);
    await expect(main(page).getByText('Tidak ada permintaan yang statusnya belum pasti.')).toBeVisible();
    await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toHaveCount(0);
    await page.goto(COMMAND_SPECIMEN);
    await expect(main(page).getByRole('button', { name: 'Simpan' })).toBeEnabled();
    expect(await storedReferences(page)).toEqual(stored);

    // Its user again: listed, without the note of a request of another device.
    await page.getByRole('button', { name: 'Menu akun Dewi' }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === '/login'), page.getByRole('menuitem', { name: 'Keluar' }).click()]);
    await signIn(page, agus);
    await page.goto(REQUESTS_PAGE);
    const card = main(page).getByRole('listitem').filter({ hasText: 'Catatan tinjauan · dikirim' });
    await expect(card).toBeVisible();
    await expect(card).not.toContainText('Dikirim dari perangkat lain');
    await card.getByRole('button', { name: 'Cek status' }).click();
    await expect(card).toHaveCount(0);
    expect(await storedReferences(page)).toEqual({});
});

test("the Owner sees every other user's unresolved request with the name and settles it with his check", async ({ page, browser }) => {
    const { Hendra: hendra, Rina: rina } = await resetPeople(page.context(), ['Hendra', 'Rina']);

    await prepareCommands(page.context(), ['Hendra', 'Rina']);
    await receiptOf(page.context(), 'Rina', 'sale.checkout');
    await signIn(page, hendra);

    // Another user's request never blocks or counts for the Owner.
    await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toHaveCount(0);
    await page.goto(REQUESTS_PAGE);
    const card = main(page).getByRole('listitem').filter({ hasText: 'Permintaan Rina' });
    await expect(card).toContainText('Penjualan · dikirim');
    await expect(card).toContainText('Cek status permintaan ini atas nama Pemilik. Jika belum tersimpan, permintaan ini tidak akan pernah tersimpan.');
    await expect(main(page).getByText('Tidak ada permintaan yang statusnya belum pasti.')).toHaveCount(0);
    await accessible(page);

    // UXI-058: the Owner's check fences it and settles Rina's receipt.
    await card.getByRole('button', { name: 'Cek status Rina' }).click();
    await expect(main(page).getByText('Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.').first()).toBeVisible();
    await expect(card).toHaveCount(0);

    const cashier = await (await browser.newContext()).newPage();
    await signIn(cashier, rina);
    await cashier.goto(REQUESTS_PAGE);
    await expect(main(cashier).getByText('Tidak ada permintaan yang statusnya belum pasti.')).toBeVisible();
    await cashier.context().close();
});

for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`UXP-006 and the uncertain panel fit ${width?.name ?? ''} without sideways scrolling and pass the checks of UX01`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const { Agus: agus } = await resetPeople(context, ['Agus']);

        await prepareCommands(context, ['Agus']);
        await receiptOf(context, 'Agus', 'receiving.confirm');

        try {
            await signIn(page, agus);

            await page.goto(REQUESTS_PAGE);
            await expect(main(page).getByRole('listitem').filter({ hasText: 'Barang masuk, barang keluar, atau penyesuaian stok · dikirim' })).toBeVisible();
            await checked(page);

            // The specimen of a blocked family: the panel and the reason.
            await page.route(PROBE_ROUTE, (route) => route.abort('failed'));
            await page.goto(COMMAND_SPECIMEN);
            await main(page).getByRole('button', { name: 'Simpan' }).click();
            await expect(main(page).getByRole('alert').filter({ hasText: 'Status belum pasti' })).toBeVisible();
            await checked(page);
        } finally {
            // Agus's receipt is resolved, so that no later test of another
            // file, whose fixtures leave receipts alone, starts with an
            // unresolved request and its sign-out warning.
            await prepareCommands(context, ['Agus']);
            await context.close();
        }
    });
}

/** The checks of UX01 on the page as it stands: axe, no sideways scrolling, text size, targets and contrast. */
async function checked(page: Page): Promise<void> {
    await accessible(page);
    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallText).toEqual([]);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

const SHELL_DATA = '**/data/shell';

test('the shell reads RD-03 as background requests on open, every 30 seconds and on focus, and a failed read keeps the last number with its time (review finding R-02)', async ({ page }) => {
    const context = page.context();
    const { Agus: agus } = await resetPeople(context, ['Agus']);
    const reads: (string | undefined)[] = [];

    await prepareCommands(context, ['Agus']);
    await receiptOf(context, 'Agus', 'receiving.confirm');

    try {
        page.on('request', (request) => {
            if (new URL(request.url()).pathname === '/data/shell') {
                reads.push(request.headers()['x-background-request']);
            }
        });
        // A focus shares the read in flight (RD-03, then RD-04 while a receipt
        // is counted) and starts one only when none is in flight, so the focus
        // is given again until a new read of RD-03 leaves. The poll ends long
        // before the next 30 seconds, so the read is the focus's.
        const readOnFocus = async (): Promise<void> => {
            const before = reads.length;
            await expect
                .poll(async () => {
                    await page.evaluate(() => window.dispatchEvent(new Event('focus')));

                    return reads.length;
                })
                .toBeGreaterThan(before);
        };

        await page.clock.install();
        await signIn(page, agus);

        // On open: the banner and the account-menu number (UXI-029, UXI-025).
        await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toBeVisible();
        const opened = reads.length;
        expect(opened).toBeGreaterThan(0);

        // Every 30 seconds while the page is visible, and when the tab regains focus.
        await page.clock.fastForward(30_000);
        await expect.poll(() => reads.length).toBeGreaterThan(opened);
        await readOnFocus();
        expect(new Set(reads)).toEqual(new Set(['1']));

        // A failed read after a good one: the last number stays, with UXS-21
        // and its time; never zero.
        await page.route(SHELL_DATA, (route) => route.fulfill({ status: 503, json: { code: 'SERVICE_BUSY' } }));
        await readOnFocus();
        await expect(page.getByText('Data belum diperbarui')).toBeVisible();
        await expect(page.getByText(/^Data terakhir diperbarui .+ dan mungkin sudah berubah\.$/)).toBeVisible();
        await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Menu akun Agus' }).click();
        await expect(page.getByRole('menuitem', { name: 'Permintaan belum pasti (1)' })).toBeVisible();
        await page.keyboard.press('Escape');

        // Without an earlier good read: the area could not be loaded (UXS-22),
        // and no number at all.
        await page.reload();
        await expect(page.getByText('Gagal memuat')).toBeVisible();
        await expect(page.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toHaveCount(0);
        await page.getByRole('button', { name: 'Menu akun Agus' }).click();
        await expect(page.getByRole('menuitem', { name: 'Keluar' })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: /^Permintaan belum pasti/ })).toHaveCount(0);
        await page.keyboard.press('Escape');
    } finally {
        await prepareCommands(context, ['Agus']);
    }
});

// Pull request #15's review: RD-04 is read to its last page, the Owner's list
// keeps "Muat lebih banyak" while a cursor remains, and a request the user
// starts counts as activity for the shell's idle limit. A first page of RD-04
// is answered here with a print request and a cursor, and the cursor's page
// is the server's own first page, which holds the fixture's receipt.

const REQUESTS_DATA = '/data/command-requests';

const LATER_PAGE = 'synthetic-later-page';

/** A print request of RD-04, which UXP-006 and the block of NC-13 leave out, every value made at run time. */
function printRow(owner: boolean): Record<string, unknown> {
    return {
        operation: 'document.print_receipt',
        family: 'print',
        sourceIntentId: crypto.randomUUID(),
        idempotencyKey: crypto.randomUUID(),
        epoch: 'current',
        receivedAt: new Date().toISOString(),
        outcome: 'PENDING',
        ...(owner ? { user: { id: 2_000_000_000, displayName: 'Rina' } } : {}),
    };
}

/** RD-04 of this scope with a print request and a cursor on its first page; the cursor's page is the server's first. */
async function pagedReceipts(page: Page, scope: 'own' | 'all'): Promise<void> {
    await page.route(
        (url) => url.pathname === REQUESTS_DATA && url.searchParams.get('scope') === scope,
        async (route) => {
            const url = new URL(route.request().url());

            if (url.searchParams.get('after') === null) {
                await route.fulfill({ json: { rows: [printRow(scope === 'all')], nextCursor: LATER_PAGE, previousCursor: null } });

                return;
            }

            expect(url.searchParams.get('after')).toBe(LATER_PAGE);
            url.searchParams.delete('after');
            await route.continue({ url: url.toString() });
        },
    );
}

test('a receipt on a later page of RD-04 is listed and counted like one on the first (pull request #15)', async ({ page }) => {
    const { Rina: rina } = await resetPeople(page.context(), ['Rina']);

    await prepareCommands(page.context(), ['Rina']);
    await receiptOf(page.context(), 'Rina', 'sale.checkout');

    try {
        await pagedReceipts(page, 'own');
        await signIn(page, rina);
        await page.goto(REQUESTS_PAGE);
        const card = main(page).getByRole('listitem').filter({ hasText: 'Penjualan · dikirim' });
        await expect(card).toContainText('Dikirim dari perangkat lain atau sebelum data peramban dihapus.');
        await expect(main(page).getByText('Tidak ada permintaan yang statusnya belum pasti.')).toHaveCount(0);
    } finally {
        await prepareCommands(page.context(), ['Rina']);
    }
});

test("the Owner's list keeps \"Muat lebih banyak\" while a page of RD-04 holds no other user's request (pull request #15)", async ({ page }) => {
    const { Hendra: hendra } = await resetPeople(page.context(), ['Hendra', 'Rina']);

    await prepareCommands(page.context(), ['Hendra', 'Rina']);
    await receiptOf(page.context(), 'Rina', 'sale.checkout');

    try {
        await pagedReceipts(page, 'all');
        await signIn(page, hendra);
        await page.goto(REQUESTS_PAGE);
        const more = main(page).getByRole('button', { name: 'Muat lebih banyak' });
        await expect(more).toBeVisible();
        await expect(main(page).getByText('Tidak ada permintaan yang statusnya belum pasti.')).toHaveCount(0);
        await expect(main(page).getByRole('listitem').filter({ hasText: 'Permintaan Rina' })).toHaveCount(0);

        await more.click();
        await expect(main(page).getByRole('listitem').filter({ hasText: 'Permintaan Rina' })).toContainText('Penjualan · dikirim');
        await expect(more).toHaveCount(0);
    } finally {
        await prepareCommands(page.context(), ['Hendra', 'Rina']);
    }
});

test('"Cek status" counts as user activity: the idle limit then counts from its answer (pull request #15)', async ({ page }) => {
    const context = page.context();
    const { Agus: agus } = await resetPeople(context, ['Agus']);

    await prepareCommands(context, ['Agus']);
    await receiptOf(context, 'Agus', 'receiving.confirm');

    try {
        await page.clock.install();
        await signIn(page, agus);
        await page.goto(REQUESTS_PAGE);
        const card = main(page).getByRole('listitem').filter({ hasText: 'Barang masuk, barang keluar, atau penyesuaian stok · dikirim' });
        await expect(card).toBeVisible();

        // 27 minutes without activity, then "Cek status" (UXI-055), which the user starts.
        await page.clock.fastForward('27:00');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        const checked = page.waitForResponse((response) => new URL(response.url()).pathname === '/commands/resolve');
        await card.getByRole('button', { name: 'Cek status' }).click();
        expect((await checked).status()).toBe(200);
        await expect(card).toHaveCount(0);

        // Past the page's first idle limit: no warning, and the page stays.
        await page.clock.fastForward('03:01');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect(main(page).getByRole('heading', { level: 1, name: 'Permintaan belum pasti' })).toBeVisible();

        // About 90 seconds before the new limit, 30 minutes after the check's
        // answer, the same page warns (UXS-23). Without the shell's listener the
        // page would have left at its first limit, and a page loaded then would
        // not warn before about 58 minutes, so this can hold only with the fix
        // (review finding R-01 of the fix's verification).
        await page.clock.fastForward('25:30');
        const warning = page.getByRole('dialog', { name: 'Sesi masuk akan berakhir' });
        await expect(warning).toBeVisible();
        await expect(warning.getByRole('button', { name: 'Tetap masuk' })).toBeVisible();
        await expect(page).toHaveURL(new RegExp(`${REQUESTS_PAGE}$`));
    } finally {
        await prepareCommands(context, ['Agus']);
    }
});
