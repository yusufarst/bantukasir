import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, homeOf, OPEN_SESSION_HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected, type Person } from '../support/access';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-000, the application shell, on the application's own pages (AC-007-06;
// DESIGN_SYSTEM 7.2; NAVIGATION_CONTRACTS UXP-000; INTERACTION_INVENTORY
// UXI-023, UXI-024, UXI-026, UXI-038; ADAPTIVE_AND_ACCESSIBILITY UXA-03,
// UXA-13; suites UX01, UX02; TASK-007 scope item 8). For each role and the
// combined account: the destinations that exist (TASK-010, TASK-017, TASK-022),
// the home of each (NC-12), the shop's name and the account menu — "Akun saya" and
// "Keluar" — the landmarks and the title ":halaman — :toko", and guards
// entered data when the page is left. The frame with every destination of a
// role is proven on the shell's specimen (frame.spec.ts). These tests sign in
// the people of Toko Contoh, so they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const PEOPLE: Readonly<Record<'owner' | 'ops' | 'cashier' | 'combined', Person>> = { owner: 'Hendra', ops: 'Agus', cashier: 'Rina', combined: 'Dewi' };

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallText).toEqual([]);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

for (const persona of Object.keys(PEOPLE) as (keyof typeof PEOPLE)[]) {
    for (const width of [WIDTHS[0], WIDTHS[7]]) {
        test(`a signed-in ${persona} at ${width?.name ?? ''} lands on the home in the shell, with its account menu, landmarks and title`, async ({ browser }) => {
            const context = await contextAt(browser, width ?? WIDTHS[0]!);
            const page = await context.newPage();
            const watched = await watch(page);
            const person = PEOPLE[persona];
            const accounts = await resetPeople(context, [person]);

            await signIn(page, accounts[person]);
            // Operations and the combined account land on Stok (TASK-022), a cashier without an
            // OPEN session on Buka sesi kas (TASK-027), the Owner on Akun saya.
            const home = homeOf(person);
            const landing = home === HOME ? 'Akun saya' : home === OPEN_SESSION_HOME ? 'Buka sesi kas' : 'Stok';

            expect(new URL(page.url()).pathname).toBe(home);
            // The document title names the shop (UXA-13; TASK-010).
            await expect(page).toHaveTitle(`${landing} — Toko Contoh`);
            await expect(page.getByRole('heading', { level: 1, name: landing, exact: true })).toHaveCount(1);
            await expect(page.getByRole('banner')).toHaveCount(1);
            await expect(page.getByRole('main')).toHaveCount(1);

            // The destinations that exist are "Kasir" (TASK-027), "Penjualan" (TASK-031), "Sesi kas" of
            // a cashier and the combined account (TASK-030), "Stok" (TASK-022), with its badge when
            // products need attention, "Produk" (TASK-017) and the Owner's "Pengaturan" (TASK-010;
            // EXC-52): in the side navigation, or the drawer at compact width.
            const destinations: Readonly<Record<keyof typeof PEOPLE, readonly (string | RegExp)[]>> = {
                owner: ['Kasir', 'Penjualan', /^Stok/, 'Produk', 'Pengaturan'],
                ops: [/^Stok/, 'Produk'],
                cashier: ['Kasir', 'Penjualan', 'Sesi kas'],
                combined: ['Kasir', 'Penjualan', 'Sesi kas', /^Stok/, 'Produk'],
            };

            // Buka sesi kas is a page of the till, whose side navigation is the icon rail without the
            // shop's name (UXA-03, KS-06); the name stays in the document title.
            if (width === WIDTHS[7]) {
                await expect(page.getByRole('button', { name: 'Buka menu' })).toHaveCount(1);
            } else {
                await expect(page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link')).toHaveText([...destinations[persona]]);

                if (home !== OPEN_SESSION_HOME) {
                    await expect(page.getByRole('banner').getByText('Toko Contoh')).toBeVisible();
                }
            }

            await checked(page);

            // "Akun saya" leads to the page; "Keluar" signs out with a full page load.
            await page.getByRole('button', { name: `Menu akun ${person}` }).click();
            await page.getByRole('menuitem', { name: 'Akun saya' }).click();
            await expect(page).toHaveURL(new RegExp(`${HOME}$`));
            await page.getByRole('button', { name: `Menu akun ${person}` }).click();
            await Promise.all([page.waitForURL((url) => url.pathname === '/login'), page.getByRole('menuitem', { name: 'Keluar' }).click()]);
            await expect(page.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
            expect(await page.evaluate(() => [window.localStorage.length, window.sessionStorage.getItem('historyKey')])).toEqual([0, null]);

            expect(unexpected(watched.problems)).toEqual([]);
            await context.close();
        });
    }
}

test('leaving entered data through the shell asks first (UXI-038)', async ({ page, context }) => {
    const { Agus: agus } = await resetPeople(context, ['Agus']);

    await signIn(page, agus);
    await page.goto('/account/password');
    await page.getByLabel('Kata sandi sekarang', { exact: true }).fill('sesuatu');

    // Through the account menu's "Akun saya".
    await page.getByRole('button', { name: 'Menu akun Agus' }).click();
    await page.getByRole('menuitem', { name: 'Akun saya' }).click();
    const dialog = page.getByRole('dialog', { name: 'Perubahan belum disimpan' });

    await expect(dialog.getByRole('button', { name: 'Tetap di halaman ini' })).toBeFocused();
    await dialog.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    expect(new URL(page.url()).pathname).toBe('/account/password');
    await expect(page.getByLabel('Kata sandi sekarang', { exact: true })).toHaveValue('sesuatu');

    // Through "Keluar": the sign-out waits for the choice.
    await page.getByRole('button', { name: 'Menu akun Agus' }).click();
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    await expect(dialog).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === '/login'), dialog.getByRole('button', { name: 'Tinggalkan' }).click()]);
});

test('a path that does not exist is the page of UXS-08, whose way back is the home', async ({ page, context }) => {
    const watched = await watch(page);
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    const guest = await page.goto('/tidak-ada');
    expect(guest?.status()).toBe(404);
    await expect(page.getByRole('heading', { level: 1, name: 'Tidak ditemukan' })).toBeVisible();
    await expect(page.getByText('Data yang Anda cari tidak ditemukan.')).toBeVisible();
    await expect(page.getByRole('banner')).toHaveCount(0);
    await checked(page);

    // A path that no route serves reads no sign-in session, so the page is the
    // same for a signed-in user, whose way back leads home.
    await signIn(page, rina);
    const signedIn = await page.goto('/tidak-ada');
    expect(signedIn?.status()).toBe(404);
    await expect(page.getByRole('heading', { level: 1, name: 'Tidak ditemukan' })).toBeVisible();
    await expect(page.getByText(rina.email)).toHaveCount(0);
    await Promise.all([page.waitForURL((url) => url.pathname === homeOf('Rina')), page.getByRole('link', { name: 'Kembali ke halaman awal' }).click()]);
    await expect(page.getByRole('button', { name: 'Menu akun Rina' })).toBeVisible();

    expect(unexpected(watched.problems).filter((problem) => !problem.includes('404'))).toEqual([]);
});
