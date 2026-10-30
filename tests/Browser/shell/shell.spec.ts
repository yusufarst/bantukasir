import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected, type Person } from '../support/access';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-000, the application shell, on the application's own pages (AC-007-06;
// DESIGN_SYSTEM 7.2; NAVIGATION_CONTRACTS UXP-000; INTERACTION_INVENTORY
// UXI-023, UXI-024, UXI-026, UXI-038; ADAPTIVE_AND_ACCESSIBILITY UXA-03,
// UXA-13; suites UX01, UX02; TASK-007 scope item 8). For each role and the
// combined account: only the Owner's "Pengaturan" exists yet (TASK-010), so the
// shell shows it, the shop's name and the account menu — "Akun saya" and
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
            expect(new URL(page.url()).pathname).toBe(HOME);
            // The document title names the shop (UXA-13; TASK-010).
            await expect(page).toHaveTitle('Akun saya — Toko Contoh');
            await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toHaveCount(1);
            await expect(page.getByRole('banner')).toHaveCount(1);
            await expect(page.getByRole('main')).toHaveCount(1);

            // The one destination that exists is the Owner's "Pengaturan"
            // (TASK-010; EXC-52): in the side navigation, or the drawer at
            // compact width; the other roles have none yet.
            if (persona === 'owner' && width === WIDTHS[7]) {
                await expect(page.getByRole('button', { name: 'Buka menu' })).toHaveCount(1);
            } else if (persona === 'owner') {
                await expect(page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link')).toHaveText(['Pengaturan']);
                await expect(page.getByRole('banner').getByText('Toko Contoh')).toBeVisible();
            } else {
                await expect(page.getByRole('navigation')).toHaveCount(0);
                await expect(page.getByRole('button', { name: 'Buka menu' })).toHaveCount(0);
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
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), page.getByRole('link', { name: 'Kembali ke halaman awal' }).click()]);
    await expect(page.getByRole('button', { name: 'Menu akun Rina' })).toBeVisible();

    expect(unexpected(watched.problems).filter((problem) => !problem.includes('404'))).toEqual([]);
});
