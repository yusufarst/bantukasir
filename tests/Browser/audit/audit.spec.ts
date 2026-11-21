import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-612 "Riwayat aktivitas" and UXP-613 "Riwayat keamanan" (AC-042-01 to AC-042-03; COPY_DECK sections 11.1,
// 11.12 to 11.14; NAVIGATION_CONTRACTS UXP-604, UXP-612, UXP-613; INTERACTION_INVENTORY UXI-330, UXI-331,
// UXI-349, UXI-377 to UXI-385; UI_STATES section 6; suites UX01, UX02, AUDIT01; TASK-042, L5 RP-612 and RP-613):
// the Owner reaches both views from "Pengaturan"; filters the activity by "Jenis", which stands in the address;
// opens an event's details; follows its reference to the account's page, whose way back returns to the activity
// with its filter; filters the security log by "Jenis" and opens an entry's details; both fit 320 px and 200 %
// zoom and work from the keyboard; Operations is refused both. The events are the browser fixtures' own: the
// newest password link used is Hendra's, by resetPeople(), and the newest sign-in is his. These tests sign in
// the people of Toko Contoh, so they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const ACTIVITY = '/settings/activity';

const SECURITY = '/settings/security-log';

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

/** The same checks at 320 px, then at the width it started at. */
async function fitsNarrow(page: Page): Promise<void> {
    const start = page.viewportSize();

    await page.setViewportSize({ width: WIDTHS[7]!.width, height: WIDTHS[7]!.height });
    await checked(page);

    if (start !== null) {
        await page.setViewportSize(start);
    }
}

/** The first row of the list's table, its header row apart. */
function firstRow(page: Page) {
    return page.getByRole('main').getByRole('table').getByRole('row').nth(1);
}

test('the Owner filters the activity, opens an event, follows its reference and back, and reads the security log', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    // Hendra last, so that the newest password link used is his (LINK_CONSUME).
    const accounts = await resetPeople(context, ['Agus', 'Hendra']);
    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
    const watched = await watch(page);

    // UXI-330: "Riwayat aktivitas" on "Pengaturan".
    await page.goto('/settings');
    await expect(page.getByText('Siapa mengubah apa, dan kapan.')).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY), page.getByRole('main').getByRole('link', { name: /Riwayat aktivitas/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat aktivitas' })).toBeVisible();
    await expect(page).toHaveTitle('Riwayat aktivitas — Toko Contoh');
    for (const label of ['Tanggal', 'Pengguna', 'Jenis', 'Produk', 'Sesi kas', 'Referensi']) {
        await expect(page.getByRole('main').getByLabel(label, { exact: true })).toBeVisible();
    }

    await checked(page);

    // UXI-377: "Jenis", whose choice stands in the address (NC-10).
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY && url.searchParams.get('action') === 'LINK_CONSUME'), page.getByRole('main').getByLabel('Jenis', { exact: true }).selectOption({ label: 'Tautan kata sandi dipakai' })]);
    const row = firstRow(page);
    await expect(row).toContainText('Tautan kata sandi dipakai');
    await expect(row.getByRole('link', { name: 'Hendra' })).toBeVisible();

    // UXI-378: the event's details, on demand.
    await row.getByText('Tampilkan rincian').click();
    await expect(row.getByText('Perubahan')).toBeVisible();
    await expect(row.getByText('kind', { exact: true })).toBeVisible();
    await checked(page);
    await fitsNarrow(page);

    // UXI-379, then UXI-349: the account's page, and back to the activity with its filter.
    await Promise.all([page.waitForURL((url) => /^\/settings\/users\/\d+$/.test(url.pathname)), row.getByRole('link', { name: 'Hendra' }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail pengguna' })).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === ACTIVITY), page.getByRole('link', { name: 'Kembali ke Riwayat aktivitas' }).click()]);
    expect(Object.fromEntries(new URL(page.url()).searchParams)).toEqual({ action: 'LINK_CONSUME' });
    await expect(page.getByRole('main').getByLabel('Jenis', { exact: true })).toHaveValue('LINK_CONSUME');

    // UXI-381: back to "Pengaturan"; UXI-331: "Riwayat keamanan".
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.getByRole('link', { name: 'Kembali ke Pengaturan' }).click()]);
    await expect(page.getByText('Upaya masuk, tautan kata sandi, dan akses yang ditolak.')).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === SECURITY), page.getByRole('main').getByRole('link', { name: /Riwayat keamanan/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Riwayat keamanan' })).toBeVisible();
    await checked(page);

    // UXI-382 and UXI-383: the newest sign-in is Hendra's, which succeeded; its details name the account and the
    // network address.
    await Promise.all([page.waitForURL((url) => url.pathname === SECURITY && url.searchParams.get('type') === 'SIGN_IN_SUCCEEDED'), page.getByRole('main').getByLabel('Jenis', { exact: true }).selectOption({ label: 'Berhasil masuk' })]);
    const entry = firstRow(page);
    await expect(entry).toContainText('Berhasil masuk');
    await expect(entry).toContainText('Hendra');
    await expect(entry).toContainText('Berhasil');
    await entry.getByText('Tampilkan rincian').click();
    await expect(entry.getByText('Akun terkait')).toBeVisible();
    await expect(entry.getByText('Alamat jaringan')).toBeVisible();
    await checked(page);
    await fitsNarrow(page);

    // UXI-385.
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.getByRole('link', { name: 'Kembali ke Pengaturan' }).click()]);

    expect(injected(watched.problems)).toEqual([]);

    // Operations is refused both views (AC-042-01).
    const other = await contextAt(browser, WIDTHS[0]!);
    const agus = await other.newPage();
    await signIn(agus, accounts.Agus);
    for (const path of [ACTIVITY, SECURITY]) {
        const refused = await agus.goto(path);
        expect(refused?.status(), path).toBe(403);
    }

    await other.close();
    await context.close();
});

test('at 200 % zoom both views fit and their controls work from the keyboard', async ({ browser }) => {
    // 640 × 400 at a scale of 2, the 200 % zoom of 1280 × 800 (EXC-53).
    const context = await contextAt(browser, WIDTHS[4]!);
    const page = await context.newPage();
    const accounts = await resetPeople(context, ['Hendra']);
    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
    const watched = await watch(page);

    for (const [path, title] of [
        [ACTIVITY, 'Riwayat aktivitas'],
        [SECURITY, 'Riwayat keamanan'],
    ] as const) {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
        await checked(page);

        // The first row's details open by Enter.
        const summary = firstRow(page).locator('summary');
        await summary.focus();
        await expect(summary).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(firstRow(page).locator('details')).toHaveAttribute('open', '');
        await checked(page);

        // "Kembali ke Pengaturan" by Enter.
        const back = page.getByRole('link', { name: 'Kembali ke Pengaturan' });
        await back.focus();
        await expect(back).toBeFocused();
        await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.keyboard.press('Enter')]);
        await expect(page.getByRole('heading', { level: 1, name: 'Pengaturan' })).toBeVisible();
    }

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
