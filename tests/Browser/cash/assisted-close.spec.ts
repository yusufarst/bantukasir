import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Locator, type Page, type Route } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { cashSession, type FixtureSession } from '../support/cash';
import { injected, prepareCommands, receiptOf, refusal } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-207 "Tutup sesi oleh Pemilik" and the Owner's review notes of UXP-206 (AC-032-01, AC-032-02,
// AC-032-04, AC-032-05; COPY_DECK sections 7.11, 7.12 and 15; INTERACTION_INVENTORY UXI-140, UXI-144 to
// UXI-158; NAVIGATION_CONTRACTS UXP-206, UXP-207; UI_STATES section 6; WF-09, WF-10; suites UX01, UX02,
// SESSION07 and SESSION10; TASK-032, L5). The Owner opens a cashier's OPEN session, "Tutup sesi oleh
// Pemilik", the preview with the responsible user, the count with its reason, the review that says she stays
// responsible, the password confirmation and the report, at every width of KEY_SCREENS section 4 and 1440 px.
// At the first width, an unresolved checkout of hers keeps "Lanjut hitung" disabled until his "Cek status"
// settles it, the unsaved-changes guard asks once a count or a reason is entered, and two review notes are
// added to the report. Her session is the fixture's and is closed in the test, so her home stays Buka sesi
// kas; his own OPEN session, if any, is closed before he signs in, so his home stays Akun saya.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const REASON = 'Kasir pulang sebelum menutup sesi.';

const CLOSE_COMMAND = '**/commands/cash_session.close_assisted';

const NOTE_COMMAND = '**/commands/review_note.add';

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

function continueCount(page: Page): Locator {
    return page.getByRole('button', { name: /^Lanjut hitung/ });
}

function physicalCash(page: Page): Locator {
    return page.getByLabel('Uang fisik di laci', { exact: true });
}

function reason(page: Page): Locator {
    return page.getByLabel('Alasan', { exact: true });
}

function reviewDialog(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Tinjau tutup sesi oleh Pemilik' });
}

function leaveDialog(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Perubahan belum disimpan' });
}

/** The recent-authentication dialog, the first time in a sign-in session (UXS-11). */
async function confirmPassword(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
}

/** UXP-206 of her session to UXP-207 by "Tutup sesi oleh Pemilik" (UXI-140), its preview loaded (UXI-149). */
async function gotoAssistedClose(page: Page, session: FixtureSession): Promise<void> {
    await page.goto(`/cash-sessions/${String(session.id)}`);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
    await Promise.all([
        page.waitForURL((url) => url.pathname === `/cash-sessions/${String(session.id)}/assisted-close`),
        page.getByRole('link', { name: 'Tutup sesi oleh Pemilik', exact: true }).click(),
    ]);
    await expect(page.getByRole('heading', { level: 1, name: 'Tutup sesi oleh Pemilik' })).toBeVisible();
    await expect(page.getByRole('main')).toContainText('Uang seharusnya di laci');
}

test('the Owner closes a cashier’s session with a count and a reason and adds review notes to its report (AC-032-01, AC-032-02, AC-032-04, AC-032-05)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const [index, width] of WIDTHS.entries()) {
        const first = index === 0;
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);
        const accounts = await resetPeople(context, ['Hendra']);

        await prepareCommands(context, ['Hendra', 'Rina']);
        await cashSession(context, 'Hendra');
        const { session } = await cashSession(context, 'Rina', { open: '20000', sales: ['CASH'] });

        if (session === null) {
            throw new Error('The fixture opens a session.');
        }

        try {
            await signIn(page, accounts.Hendra);
            await gotoAssistedClose(page, session);

            // Step 1 (UXI-149): the responsible user, the register and the server's figures.
            const main = page.getByRole('main');
            await expect(main).toContainText('Penanggung jawab');
            await expect(main).toContainText('Rina');
            await expect(main).toContainText('Rp70.000');
            await expect(page.getByRole('link', { name: 'Kembali ke Detail sesi kas' })).toHaveAttribute('href', `/cash-sessions/${String(session.id)}`);
            await checked(page);

            if (first) {
                // BR-CASH-16, D-087: an unresolved checkout of hers is listed with the Owner's "Cek status",
                // and "Lanjut hitung" (UXI-150) waits with its reason until his check settles it (D-089).
                await receiptOf(context, 'Rina', 'sale.checkout');
                await page.reload();
                await expect(main).toContainText('Ada penjualan di sesi ini yang statusnya belum pasti.');
                await expect(continueCount(page)).toHaveAttribute('aria-disabled', 'true');
                await checked(page);
                await main.getByRole('button', { name: /^Cek status Penjualan/ }).click();
                await expect(main).toContainText('Tidak tersimpan, dan tidak akan pernah tersimpan.');
                await expect(main).not.toContainText('Ada penjualan di sesi ini yang statusnya belum pasti.');
                await expect(continueCount(page)).not.toHaveAttribute('aria-disabled', 'true');
            }

            // Step 2 (UXI-151, UXI-152): both fields mandatory; Enter sends nothing (UXA-10).
            await continueCount(page).click();
            await expect(physicalCash(page)).toBeVisible();
            await page.getByRole('button', { name: /^Tinjau/ }).click();
            await expect(page.getByRole('dialog')).toHaveCount(0);
            await expect(page.getByText('Isi uang fisik di laci dan alasan.')).toBeVisible();
            await physicalCash(page).fill('69000');
            await reason(page).fill(REASON);
            await reason(page).press('Enter');
            await expect(page.getByRole('dialog')).toHaveCount(0);
            await checked(page);

            if (first) {
                // With a count and a reason, "Batal" asks first and the page stays (UXI-157; UXS-45).
                await page.getByRole('link', { name: 'Batal' }).click();
                await expect(leaveDialog(page)).toBeVisible();
                await leaveDialog(page).getByRole('button', { name: 'Tetap di halaman ini' }).click();
                await expect(leaveDialog(page)).toHaveCount(0);
                await expect(reason(page)).toHaveValue(REASON);
            }

            // The review (UXI-154): the count, expected cash, the variance in words, and the responsible user
            // who stays; "Kembali ubah" first.
            await page.getByRole('button', { name: /^Tinjau/ }).click();
            const review = reviewDialog(page);
            await expect(review.getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
            await expect(review).toContainText('Rp69.000');
            await expect(review).toContainText('Rp1.000 (kurang)');
            await expect(review).toContainText('Penanggung jawab sesi kas ini tetap Rina. Anda tercatat sebagai yang menutup.');
            await checked(page);

            // "Tutup sesi" asks for the password first (UXS-11), then leads to the report (UXP-206).
            await review.getByRole('button', { name: /^Tutup sesi/ }).click();
            await Promise.all([page.waitForURL((url) => url.pathname === `/cash-sessions/${String(session.id)}`), confirmPassword(page, accounts.Hendra.password)]);
            await expect(page.getByText(`Sesi kas ${session.number} ditutup.`)).toBeVisible();
            await expect(main).toContainText('Laporan sesi kas');
            await expect(main).toContainText(REASON);
            await expect(main).toContainText('Catatan tinjauan Pemilik');
            await expect(main).toContainText('Belum ada catatan tinjauan.');
            await expect(page.getByRole('link', { name: 'Tutup sesi oleh Pemilik' })).toHaveCount(0);
            await checked(page);

            if (first) {
                // UXI-145 to UXI-147, WF-10: "Batal" discards the text; two notes, each kept with its line
                // breaks, its author and time, none with an edit or a delete (D-069 (f)).
                const notes = main.locator('section').filter({ has: page.getByRole('heading', { level: 2, name: 'Catatan tinjauan Pemilik' }) });
                const note = page.getByLabel('Catatan', { exact: true });

                await notes.getByRole('button', { name: 'Tambah catatan tinjauan' }).click();
                await expect(note).toBeVisible();
                await note.fill('Dibatalkan');
                await notes.getByRole('button', { name: 'Batal' }).click();
                await expect(note).toHaveCount(0);
                await notes.getByRole('button', { name: 'Tambah catatan tinjauan' }).click();
                await expect(note).toHaveValue('');

                for (const text of ['Selisih Rp1.000 karena uang receh.\nSudah dibicarakan dengan Rina.', 'Koreksi: selisih dari uang kembalian.']) {
                    await note.fill(text);
                    await note.press('Enter');
                    await expect(note).toBeVisible();
                    await notes.getByRole('button', { name: 'Simpan catatan' }).click();
                    await expect(notes.getByText('Catatan tinjauan disimpan.')).toBeVisible();
                    await expect(note).toHaveCount(0);

                    if (text !== 'Koreksi: selisih dari uang kembalian.') {
                        await notes.getByRole('button', { name: 'Tambah catatan tinjauan' }).click();
                    }
                }

                await expect(notes).toContainText('Sudah dibicarakan dengan Rina.');
                await expect(notes).toContainText('Koreksi: selisih dari uang kembalian.');
                await expect(notes.getByText(/^Oleh Hendra, /)).toHaveCount(2);
                await expect(notes.getByRole('button', { name: /ubah|hapus/i })).toHaveCount(0);
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

test('the assisted close recounts, says an unreadable preview, a lost or held answer and a session closed meanwhile, and a lost note is checked (UXS-14, UXS-15, UXS-19, UXS-22, UXS-30; review R-03, R-04)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 4);
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const accounts = await resetPeople(context, ['Hendra']);
    const main = page.getByRole('main');
    const uncertainPanel = main.getByRole('alert').filter({ hasText: 'Status belum pasti' });

    // UXP-207 of a new OPEN session of hers, counted at Rp0 with a reason and reviewed.
    const counted = async (session: FixtureSession): Promise<void> => {
        await gotoAssistedClose(page, session);
        await continueCount(page).click();
        await physicalCash(page).fill('0');
        await reason(page).fill(REASON);
        await page.getByRole('button', { name: /^Tinjau/ }).click();
        await expect(reviewDialog(page).getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
    };
    const opened = async (): Promise<FixtureSession> => {
        const { session } = await cashSession(context, 'Rina', { open: '0' });

        if (session === null) {
            throw new Error('The fixture opens a session.');
        }

        return session;
    };

    await prepareCommands(context, ['Hendra', 'Rina']);
    await cashSession(context, 'Hendra');

    try {
        await signIn(page, accounts.Hendra);

        // UXS-19 and UXS-22: RECOUNT_REQUIRED returns to the preview, which fails to load once and says so
        // with "Coba lagi"; "Lanjut hitung" waits for the figures; the count is discarded, the reason kept.
        const recounted = await opened();
        await counted(recounted);
        await page.route(CLOSE_COMMAND, (route) => route.fulfill({ status: 409, json: { ...refusal('RECOUNT_REQUIRED'), messageKey: 'ux.state.recount' } }), { times: 1 });
        await page.route(`**/cash-sessions/${String(recounted.id)}/assisted-close`, (route) => route.fulfill({ status: 503, body: '' }), { times: 1 });
        await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
        await confirmPassword(page, accounts.Hendra.password);
        await expect(main.getByRole('alert').filter({ hasText: 'Ada transaksi baru; hitung ulang kas' })).toBeVisible();
        await expect(main).toContainText('Bagian ini tidak dapat dimuat.');
        await expect(continueCount(page)).toHaveAttribute('aria-disabled', 'true');
        await main.getByRole('button', { name: 'Coba lagi' }).click();
        await expect(main).toContainText('Uang seharusnya di laci');
        await expect(continueCount(page)).not.toHaveAttribute('aria-disabled', 'true');
        await checked(page);
        await continueCount(page).click();
        await expect(physicalCash(page)).toHaveValue('');
        await expect(reason(page)).toHaveValue(REASON);

        // UXS-19's variant when the server holds an unresolved checkout of hers.
        await physicalCash(page).fill('0');
        await page.getByRole('button', { name: /^Tinjau/ }).click();
        await page.route(CLOSE_COMMAND, (route) => route.fulfill({ status: 409, json: { ...refusal('RECOUNT_REQUIRED'), messageKey: 'ux.state.recount.unresolved_checkouts' } }), { times: 1 });
        await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
        await expect(main.getByRole('alert').filter({ hasText: 'Ada penjualan yang statusnya belum pasti' })).toBeVisible();
        await expect(main).toContainText('Uang seharusnya di laci');

        // UXS-14: a lost answer freezes the close on its panel, never counted again; "Cek status" finds it
        // and opens its report (RC-50; NC-13).
        await continueCount(page).click();
        await physicalCash(page).fill('0');
        await page.getByRole('button', { name: /^Tinjau/ }).click();
        await page.route(
            CLOSE_COMMAND,
            async (route: Route) => {
                await route.fetch();
                await route.abort();
            },
            { times: 1 },
        );
        await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
        await expect(uncertainPanel).toContainText('Jangan hitung ulang laci atau tutup sesi ini lagi. Sesi kas ini mungkin sudah ditutup.');
        await expect(page.getByRole('button', { name: /^Tinjau/ })).toHaveAttribute('aria-disabled', 'true');
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === `/cash-sessions/${String(recounted.id)}`), uncertainPanel.getByRole('button', { name: 'Cek status' }).click()]);
        await expect(page.getByText(`Sesi kas ${recounted.number} ditutup.`)).toBeVisible();

        // Review R-04: a note whose answer is lost keeps its panel, can be neither cancelled nor written
        // again, and "Cek status" finds it; it then stands in the list once.
        const notes = main.locator('section').filter({ has: page.getByRole('heading', { level: 2, name: 'Catatan tinjauan Pemilik' }) });
        await notes.getByRole('button', { name: 'Tambah catatan tinjauan' }).click();
        await page.getByLabel('Catatan', { exact: true }).fill('Catatan yang jawabannya hilang.');
        await page.route(
            NOTE_COMMAND,
            async (route: Route) => {
                await route.fetch();
                await route.abort();
            },
            { times: 1 },
        );
        await notes.getByRole('button', { name: 'Simpan catatan' }).click();
        await expect(uncertainPanel).toContainText('Jangan menulis catatan ini lagi.');
        await expect(notes.getByRole('button', { name: 'Simpan catatan' })).toHaveAttribute('aria-disabled', 'true');
        await expect(notes.getByRole('button', { name: 'Batal' })).toHaveAttribute('aria-disabled', 'true');
        await expect(notes.getByRole('button', { name: 'Tambah catatan tinjauan' })).toHaveCount(0);
        await checked(page);
        await uncertainPanel.getByRole('button', { name: 'Cek status' }).click();
        await expect(notes.getByText('Catatan tinjauan disimpan.')).toBeVisible();
        await expect(notes.getByText('Catatan yang jawabannya hilang.', { exact: true })).toHaveCount(1);

        // UXS-15: a held close offers "Cek status" only, which finds it.
        const held = await opened();
        await counted(held);
        await page.route(
            CLOSE_COMMAND,
            async (route: Route) => {
                await route.fetch();
                await route.fulfill({ status: 409, json: refusal('IDEMPOTENCY_CONFLICT', 'HELD') });
            },
            { times: 1 },
        );
        await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
        await expect(uncertainPanel).toContainText('Permintaan ini tidak bisa dikirim ulang karena mungkin sudah tersimpan.');
        await expect(uncertainPanel.getByRole('button', { name: 'Kirim ulang' })).toHaveCount(0);
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === `/cash-sessions/${String(held.id)}`), uncertainPanel.getByRole('button', { name: 'Cek status' }).click()]);
        await expect(page.getByText(`Sesi kas ${held.number} ditutup.`)).toBeVisible();

        // UXS-30: the session closed meanwhile, here by her own close through the fixture: the count is not
        // kept, and the page leads to the report.
        const closed = await opened();
        await counted(closed);
        await cashSession(context, 'Rina');
        await reviewDialog(page).getByRole('button', { name: /^Tutup sesi/ }).click();
        await expect(main).toContainText('Sesi kas ini sudah ditutup, mungkin oleh Pemilik. Hitungan Anda tidak disimpan.');
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === `/cash-sessions/${String(closed.id)}`), main.getByRole('link', { name: 'Buka Detail sesi kas', exact: true }).click()]);
        await expect(main).toContainText('Laporan sesi kas');

        expect(injected(watched.problems)).toEqual([]);
    } finally {
        await cashSession(context, 'Rina');
        await context.close();
    }
});
