import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Locator, type Page, type Route } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { cashSession, type FixtureRegister, type FixtureSale } from '../support/cash';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-203 "Pengembalian uang dan retur" and UXP-204 "Pengembalian tercatat", with the linked corrections of
// UXP-202 and the drawer-funded refunds of UXP-206 (AC-034-01 to AC-034-05, AC-034-07; COPY_DECK sections 7.7
// to 7.9, 7.11 and 14.1; INTERACTION_INVENTORY UXI-118, UXI-120, UXI-122 to UXI-134 and UXI-143;
// NAVIGATION_CONTRACTS UXP-203, UXP-204; UI_STATES UXS-11, UXS-14, UXS-30, UXS-36, UXS-45; WF-11; RACE-15;
// suites UX01 and RET02; TASK-034, L5). The Owner refunds part of a cashier's Sale in cash from her drawer at
// every width of KEY_SCREENS section 4 and 1440 px: the line with what remains on it, the method, the source
// with the drawer's expected cash and none chosen by default, the review with its drawer note, the password
// confirmation, and the instruction to take the cash now — the L5 critical path of the drawer-funded refund
// that every later pull request runs (EXECUTION_CONTEXT EXC-34). At the first width "Tinjau" says what is
// missing, the unsaved-changes guard asks once something is entered, and the refund stands in the Sale's
// detail and, once her session is closed, apart in its report. A second test loses the answer of a drawer
// refund after its commit: the panel says that no cash may leave the drawer, a new refund of the Sale waits,
// and the status check finds the refund, which stands once (RACE-15); the drawer, now at Rp0, refuses the next
// refund with its expected cash and the source stays as chosen, and "Dana Pemilik" records it; a session closed
// meanwhile is refused. Every session and Sale is the fixture's, through the Actions. Her session is closed at
// the end, so her home stays Buka sesi kas, and his own OPEN session, if any, is closed before he signs in, so
// his home stays Akun saya.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const REASON = 'Pelanggan membatalkan sebagian pembelian.';

const DRAWER_COMMAND = '**/commands/sale_correction.drawer_funded';

const CORRECTION = /^\/sales\/\d+\/corrections\/\d+$/;

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

function reviewButton(page: Page): Locator {
    return page.getByRole('button', { name: /^Tinjau/ });
}

function reviewDialog(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Tinjau pengembalian uang dan retur' });
}

function leaveDialog(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
}

function amountField(page: Page): Locator {
    return page.getByLabel('Uang dikembalikan', { exact: true });
}

function reasonField(page: Page): Locator {
    return page.getByLabel('Alasan', { exact: true });
}

function cashMethod(page: Page): Locator {
    return page.getByRole('radio', { name: 'Tunai', exact: true });
}

/** Her drawer as UXI-124 names it, with its expected cash now. */
function drawerSource(page: Page, register: FixtureRegister, expected: string): Locator {
    return page.getByRole('radio', { name: `${register.label} · Rina · uang seharusnya ${expected}`, exact: true });
}

/** The section of a page under its heading, the nearest one. */
function sectionOf(page: Page, level: 2 | 3, name: string): Locator {
    return page.getByRole('heading', { level, name, exact: true }).locator('xpath=ancestor::section[1]');
}

/** The recent-authentication dialog, the first time in a sign-in session (UXS-11). */
async function confirmPassword(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
}

/** UXP-202 of the Sale to UXP-203 by "Pengembalian uang dan retur" (UXI-118). */
async function gotoRefund(page: Page, sale: FixtureSale): Promise<void> {
    await page.goto(`/sales/${String(sale.id)}`);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail penjualan' })).toBeVisible();
    await Promise.all([
        page.waitForURL((url) => url.pathname === `/sales/${String(sale.id)}/refund`),
        page.getByRole('link', { name: 'Pengembalian uang dan retur', exact: true }).click(),
    ]);
    await expect(page.getByRole('heading', { level: 1, name: 'Pengembalian uang dan retur' })).toBeVisible();
    await expect(page.getByRole('main')).toContainText(`Pilih barang dan uang yang dikembalikan untuk penjualan ${sale.number}. Penjualan asli tidak berubah.`);
}

/** The refund of money on the Sale's one line, in cash from a source, with the reason; the review opened. */
async function reviewed(page: Page, amount: string, source: Locator): Promise<void> {
    await amountField(page).fill(amount);
    await cashMethod(page).click();
    await source.click();
    await reasonField(page).fill(REASON);
    await reviewButton(page).click();
    await expect(reviewDialog(page).getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
}

test('the Owner refunds part of a Sale in cash from the cashier’s drawer and is told to take the cash now (AC-034-01, AC-034-02, AC-034-07, EXC-34)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const [index, width] of WIDTHS.entries()) {
        const first = index === 0;
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);
        const accounts = await resetPeople(context, ['Hendra']);

        await prepareCommands(context, ['Hendra', 'Rina']);
        await cashSession(context, 'Hendra');
        const { session, register, sales } = await cashSession(context, 'Rina', { open: '0', sales: ['CASH'] });
        const sale = sales[0];

        if (session === null || register === null || sale === undefined) {
            throw new Error('The fixture opens a session with a Sale.');
        }

        try {
            await signIn(page, accounts.Hendra);
            const main = page.getByRole('main');

            // UXI-118 to UXP-203: the line with what remains on it to refund and to return (UXI-122).
            await gotoRefund(page, sale);
            await expect(main).toContainText('Sisa bisa dikembalikan: Rp50.000');
            await expect(main).toContainText('Sisa bisa diretur: 1 pcs');
            await expect(page.getByRole('link', { name: 'Kembali ke Detail penjualan' })).toHaveAttribute('href', `/sales/${String(sale.id)}`);

            if (first) {
                await checked(page);

                // UXI-127 with nothing entered opens no review and says what is missing.
                await reviewButton(page).click();
                await expect(page.getByRole('dialog')).toHaveCount(0);
                await expect(main).toContainText('Isi jumlah barang yang diretur atau uang yang dikembalikan, lalu lengkapi isian yang wajib.');
            }

            // UXI-123 and UXI-124: Tunai, then the OPEN sessions' drawers with their expected cash and "Dana
            // Pemilik", none chosen by default (BR-RFD-06, BR-RFD-16); the reason (UXI-126). Enter sends nothing.
            await amountField(page).fill('20000');
            await expect(cashMethod(page)).toHaveAttribute('aria-checked', 'false');
            await cashMethod(page).click();
            const drawer = drawerSource(page, register, 'Rp50.000');
            await expect(drawer).toHaveAttribute('aria-checked', 'false');
            await expect(page.getByRole('radio', { name: 'Dana Pemilik', exact: true })).toHaveAttribute('aria-checked', 'false');
            await expect(main).toContainText('Pilih satu sumber uang. Tidak ada pilihan otomatis, dan satu pengembalian tidak dibagi ke beberapa sumber.');
            await drawer.click();
            await reasonField(page).fill(REASON);
            await reasonField(page).press('Enter');
            await expect(page.getByRole('dialog')).toHaveCount(0);
            await checked(page);

            if (first) {
                // UXI-131 with something entered asks first, and the page stays as it was (UXS-45).
                await page.getByRole('link', { name: 'Batal', exact: true }).click();
                await expect(leaveDialog(page)).toBeVisible();
                await leaveDialog(page).getByRole('button', { name: 'Tetap di halaman ini' }).click();
                await expect(leaveDialog(page)).toHaveCount(0);
                await expect(reasonField(page)).toHaveValue(REASON);
                await expect(drawer).toHaveAttribute('aria-checked', 'true');
            }

            // UXI-128: the total, the method, the source and the reason, with the drawer note; "Kembali ubah"
            // first (UXI-129).
            await reviewButton(page).click();
            const review = reviewDialog(page);
            await expect(review.getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
            await expect(review).toContainText('Total dikembalikan: Rp20.000');
            await expect(review).toContainText('Cara: Tunai');
            await expect(review).toContainText(`Sumber uang: ${register.label} · Rina · uang seharusnya Rp50.000`);
            await expect(review).toContainText(`Alasan: ${REASON}`);
            await expect(review).toContainText('Ambil uang dari laci hanya setelah pengembalian ini tercatat.');
            await checked(page);

            // "Konfirmasi pengembalian" asks for the password first (UXS-11), then UXP-204 says to take the
            // cash now from her drawer (UXI-133; D-068), with the reference that the server made (D-087).
            await review.getByRole('button', { name: /^Konfirmasi pengembalian/ }).click();
            await Promise.all([page.waitForURL((url) => CORRECTION.test(url.pathname)), confirmPassword(page, accounts.Hendra.password)]);
            await expect(page.getByRole('heading', { level: 1, name: 'Pengembalian tercatat' })).toBeVisible();
            await expect(main).toContainText(`Ambil Rp20.000 dari ${register.label} sekarang dan serahkan kepada pelanggan.`);
            await expect(main).toContainText('Serahkan sebelum modal awal sesi berikutnya di laci kas ini dihitung.');
            await expect(main).toContainText(`${sale.number}/1`);
            await expect(main).toContainText(`Sesi kas ${session.number}`);
            await expect(main).toContainText(`Alasan: ${REASON}`);
            await checked(page);

            // UXI-134 to UXP-202, where the refund stands with its method and drawer beside the Sale (UXI-120).
            await Promise.all([page.waitForURL((url) => url.pathname === `/sales/${String(sale.id)}`), page.getByRole('link', { name: 'Kembali ke Detail penjualan', exact: true }).click()]);
            const corrections = sectionOf(page, 2, 'Pengembalian uang dan retur');
            await expect(corrections).toContainText(`Rp20.000 · Tunai · ${register.label} · `);
            await expect(corrections).toContainText(`Alasan: ${REASON}`);

            if (first) {
                await checked(page);

                // Scope item 2, AC-034-02: once her session is closed, its report lists the refund apart, with
                // the Owner who made it (UXI-143; DC-02).
                await cashSession(context, 'Rina');
                await page.goto(`/cash-sessions/${String(session.id)}`);
                await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
                const refunds = sectionOf(page, 3, 'Pengembalian dari laci kas');
                await expect(refunds).toContainText(`Rp20.000 · penjualan ${sale.number} · `);
                await expect(refunds).toContainText(' · oleh Hendra');
                await checked(page);
            }

            expect(injected(watched.problems), width.name).toEqual([]);
        } finally {
            // Her session never stays OPEN for a later test (tests/Browser/support/access.ts).
            await cashSession(context, 'Rina');
            await context.close();
        }
    }
});

test('a lost answer is checked before any cash leaves the drawer and never replaced, a drawer at Rp0 refuses the next refund, "Dana Pemilik" records it, and a closed session is refused (AC-034-03 to AC-034-05; RACE-15; UXS-14, UXS-30, UXS-36)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 4);
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const accounts = await resetPeople(context, ['Hendra']);
    const main = page.getByRole('main');
    const uncertainPanel = main.getByRole('alert').filter({ hasText: 'Status belum pasti' });

    await prepareCommands(context, ['Hendra', 'Rina']);
    await cashSession(context, 'Hendra');
    // Rp50.000 in her drawer, and two Sales by bank transfer, which add no cash to it.
    const { session, register, sales } = await cashSession(context, 'Rina', { open: '50000', sales: ['BANK_TRANSFER', 'BANK_TRANSFER'] });
    const [lost, short] = sales;

    if (session === null || register === null || lost === undefined || short === undefined) {
        throw new Error('The fixture opens a session with two Sales.');
    }

    try {
        await signIn(page, accounts.Hendra);

        // RACE-15, UXS-14: the answer of a refund of all of her drawer's cash is lost after its commit. The
        // panel says that no cash may leave the drawer and no new refund be made, the form stays frozen, and
        // UXI-118 of the Sale waits in another tab (NC-13); "Cek status" finds the refund, and only then does
        // UXP-204 say to take the cash (AC-034-04).
        await gotoRefund(page, lost);
        let dropped = false;
        await page.route(DRAWER_COMMAND, async (route: Route) => {
            const response = await route.fetch();

            // The answer that asks for the password reaches the page; the refund's own answer is lost once.
            if (dropped || !response.ok()) {
                await route.fulfill({ response });

                return;
            }

            dropped = true;
            await route.abort();
        });
        await reviewed(page, '50000', drawerSource(page, register, 'Rp50.000'));
        await reviewDialog(page).getByRole('button', { name: /^Konfirmasi pengembalian/ }).click();
        await confirmPassword(page, accounts.Hendra.password);
        await expect(uncertainPanel).toContainText('Jangan ambil uang dari laci sampai pengembalian ini tercatat. Jangan buat pengembalian baru.');
        await expect(reviewButton(page)).toHaveAttribute('aria-disabled', 'true');
        await expect(amountField(page)).toBeDisabled();
        await expect(main).not.toContainText('sekarang dan serahkan kepada pelanggan.');
        await checked(page);

        const other = await context.newPage();
        await other.goto(`/sales/${String(lost.id)}`);
        await expect(other.getByRole('main')).toContainText('Ada pengembalian uang yang statusnya belum pasti. Cek statusnya dulu.');
        await expect(other.getByRole('button', { name: 'Pengembalian uang dan retur', exact: true })).toHaveAttribute('aria-disabled', 'true');
        await other.close();

        await Promise.all([page.waitForURL((url) => CORRECTION.test(url.pathname)), uncertainPanel.getByRole('button', { name: 'Cek status' }).click()]);
        await page.unroute(DRAWER_COMMAND);
        await expect(main).toContainText(`Ambil Rp50.000 dari ${register.label} sekarang dan serahkan kepada pelanggan.`);
        await expect(main).toContainText(`${lost.number}/1`);
        await Promise.all([page.waitForURL((url) => url.pathname === `/sales/${String(lost.id)}`), page.getByRole('link', { name: 'Kembali ke Detail penjualan', exact: true }).click()]);
        await expect(sectionOf(page, 2, 'Pengembalian uang dan retur').getByText(/^Rp50\.000 · Tunai · /)).toHaveCount(1);

        // AC-034-03, BR-RFD-15, BR-RFD-16, UXS-36: her drawer now holds Rp0, reached and never passed. A refund
        // of Rp10.000 from it is refused with its expected cash and nothing written; the source stays as
        // chosen, never switched by the page.
        await gotoRefund(page, short);
        const empty = drawerSource(page, register, 'Rp0');
        await reviewed(page, '10000', empty);
        await reviewDialog(page).getByRole('button', { name: /^Konfirmasi pengembalian/ }).click();
        const shortNotice = main.getByRole('alert').filter({ hasText: 'Uang seharusnya di laci tidak cukup' });
        await expect(shortNotice).toContainText(`Uang seharusnya di ${register.label} hanya Rp0, kurang dari pengembalian ini. Tidak ada yang disimpan. Jangan ambil uang dari laci. Pilih sesi kas lain yang cukup, atau Dana Pemilik.`);
        await expect(empty).toHaveAttribute('aria-checked', 'true');
        await expect(amountField(page)).toHaveValue('10.000');
        expect(new URL(page.url()).pathname).toBe(`/sales/${String(short.id)}/refund`);
        await checked(page);

        // UXI-124, UXI-125 (AC-034-01): "Dana Pemilik" with the reference typed, which needs no drawer; UXP-204
        // says the refund recorded, with no cash to take from a drawer.
        await page.getByRole('radio', { name: 'Dana Pemilik', exact: true }).click();
        await page.getByLabel('Referensi pengembalian uang', { exact: true }).fill('DANA-PEMILIK-01');
        await reviewButton(page).click();
        const review = reviewDialog(page);
        await expect(review.getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
        await expect(review).toContainText('Sumber uang: Dana Pemilik');
        await expect(review).not.toContainText('Ambil uang dari laci hanya setelah pengembalian ini tercatat.');
        await Promise.all([page.waitForURL((url) => CORRECTION.test(url.pathname)), review.getByRole('button', { name: /^Konfirmasi pengembalian/ }).click()]);
        await expect(main).toContainText('Pengembalian uang Rp10.000 tercatat.');
        await expect(main).toContainText('DANA-PEMILIK-01');
        await expect(main).not.toContainText('sekarang dan serahkan kepada pelanggan.');
        await checked(page);

        // AC-034-02, AC-034-05, UXS-30: a session closed meanwhile, here by her own close through the fixture,
        // refuses the refund with nothing written; the sessions are read again without it, and no source is
        // chosen for the Owner.
        await Promise.all([page.waitForURL((url) => url.pathname === `/sales/${String(short.id)}`), page.getByRole('link', { name: 'Kembali ke Detail penjualan', exact: true }).click()]);
        await Promise.all([
            page.waitForURL((url) => url.pathname === `/sales/${String(short.id)}/refund`),
            page.getByRole('link', { name: 'Pengembalian uang dan retur', exact: true }).click(),
        ]);
        await reviewed(page, '10000', drawerSource(page, register, 'Rp0'));
        await cashSession(context, 'Rina');
        await reviewDialog(page).getByRole('button', { name: /^Konfirmasi pengembalian/ }).click();
        await expect(main.getByRole('alert').filter({ hasText: 'Sesi kas sudah ditutup' })).toContainText(
            'Sesi kas yang dipilih sudah ditutup. Tidak ada yang disimpan dan tidak ada uang yang boleh diambil dari lacinya. Pilih sumber uang lain.',
        );
        await expect(drawerSource(page, register, 'Rp0')).toHaveCount(0);
        await expect(page.getByRole('radio', { name: 'Dana Pemilik', exact: true })).toHaveAttribute('aria-checked', 'false');
        await checked(page);

        await page.goto(`/sales/${String(short.id)}`);
        await expect(sectionOf(page, 2, 'Pengembalian uang dan retur').getByText(/^Rp10\.000 · Tunai · Dana Pemilik · /)).toHaveCount(1);
        await expect(sectionOf(page, 2, 'Pengembalian uang dan retur').getByText(/^Rp10\.000 · /)).toHaveCount(1);

        expect(injected(watched.problems)).toEqual([]);
    } finally {
        await cashSession(context, 'Rina');
        await context.close();
    }
});
