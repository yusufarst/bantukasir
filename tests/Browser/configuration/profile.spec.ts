import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected } from '../support/access';
import { prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-601, UXP-607 and UXP-608 and the shop in the shell (AC-010-05,
// AC-010-06, AC-010-07; DESIGN_SYSTEM sections 3.3 and 7.2; COPY_DECK sections
// 11.1, 11.7 and 11.8; NAVIGATION_CONTRACTS UXP-601, UXP-607, UXP-608; suites
// UX01, UX02, IDENTITY01; TASK-010, L5): the Owner reaches the settings from
// the navigation, opens the BusinessProfile, changes it with a logo and the
// accent "Toska" — a multipart command after the recent-authentication
// dialog — and lands on UXP-607 with the new version in force; the accent then
// applies to every page and the document title names the shop. The test puts
// the profile back to green at its end. The built assets hold no client name.
// These tests sign in the people of Toko Contoh, so they run one at a time.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/** A PNG of one pixel: a synthetic logo. */
const LOGO = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

async function accent(page: Page): Promise<string | null> {
    return page.evaluate(() => document.documentElement.getAttribute('data-accent'));
}

/** Saves the form; the first time in a sign-in session the password is confirmed first (UXS-11). */
async function save(page: Page, password: string | null): Promise<void> {
    const button = page.getByRole('button', { name: 'Simpan sebagai versi baru' });

    if (password === null) {
        await Promise.all([page.waitForURL((url) => url.pathname === '/settings/business-profile'), button.click()]);

        return;
    }

    await button.click();
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings/business-profile'), dialog.getByRole('button', { name: 'Konfirmasi' }).click()]);
}

for (const width of [WIDTHS[0], WIDTHS[7]]) {
    test(`the Owner changes the business profile with a logo and the accent at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);
        const { Hendra: hendra } = await resetPeople(context, ['Hendra']);
        await prepareCommands(context, ['Hendra']);

        await signIn(page, hendra);
        expect(new URL(page.url()).pathname).toBe(HOME);
        await expect(page).toHaveTitle('Akun saya — Toko Contoh');
        expect(await accent(page)).toBe('green');

        // UXI-008 "Pengaturan", in the navigation or the drawer.
        if (width === WIDTHS[0]) {
            await page.getByRole('button', { name: 'Buka menu' }).click();
            await expect(page.getByRole('dialog', { name: 'Menu utama' }).getByText('Toko Contoh')).toBeVisible();
        } else {
            await expect(page.getByRole('banner').getByText('Toko Contoh')).toBeVisible();
        }

        await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link', { name: 'Pengaturan' }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Pengaturan' })).toBeVisible();
        await expect(page.getByRole('main').getByRole('link')).toHaveCount(1);
        await checked(page);

        // UXI-327, then UXP-607 with the revision in force.
        await Promise.all([page.waitForURL((url) => url.pathname === '/settings/business-profile'), page.getByRole('link', { name: /Profil usaha/ }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Profil usaha' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Versi yang berlaku' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Pengaturan' })).toBeVisible();
        await checked(page);

        // UXP-608: nothing changed yet, so the save is disabled with its reason.
        await Promise.all([page.waitForURL((url) => url.pathname === '/settings/business-profile/edit'), page.getByRole('link', { name: 'Ubah profil usaha' }).click()]);
        await expect(page.getByLabel('Nama toko')).toHaveValue('Toko Contoh');
        await expect(page.getByRole('button', { name: 'Simpan sebagai versi baru' })).toHaveAttribute('aria-disabled', 'true');
        await expect(page.getByText('Belum ada perubahan.')).toBeVisible();
        await checked(page);

        // A new logo and the accent "Toska": the request is multipart.
        await page.getByLabel(/Alamat/).fill('Jl. Contoh No. 1');
        await page.getByLabel(/^Logo/).setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: LOGO });
        await page.getByRole('radio', { name: 'Toska' }).check();
        const sent = page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === '/commands/business_profile.change' && (request.headers()['content-type'] ?? '').startsWith('multipart/form-data'));
        await save(page, hendra.password);
        await sent;

        // UXP-607 with the new version in force — the first of the page, before
        // the earlier versions — and the accent everywhere.
        await expect(page.getByText('Profil usaha disimpan sebagai versi baru.')).toBeVisible();
        await expect(page.getByRole('main').getByText('Jl. Contoh No. 1').first()).toBeVisible();
        await expect(page.getByRole('main').getByText('Toska').first()).toBeVisible();
        const logo = page.getByRole('main').getByRole('img', { name: 'Logo' }).first();
        await expect(logo).toBeVisible();
        await expect.poll(() => logo.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth)).toBe(1);
        await expect.poll(() => accent(page)).toBe('teal');
        await expect(page.getByRole('heading', { level: 2, name: 'Versi sebelumnya' })).toBeVisible();
        await checked(page);

        await page.goto(HOME);
        await expect.poll(() => accent(page)).toBe('teal');
        await expect(page).toHaveTitle('Akun saya — Toko Contoh');

        // Back to green, the accent of every other test.
        await page.goto('/settings/business-profile/edit');
        await page.getByLabel(/Alamat/).fill('');
        await page.getByRole('radio', { name: 'Hijau' }).check();
        await save(page, null);
        await expect.poll(() => accent(page)).toBe('green');

        // The 423 of the first save, before the password was confirmed, is expected.
        expect(unexpected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
        await context.close();
    });
}

test('every other role has no settings in its navigation and is refused the pages', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await signIn(page, rina);
    await expect(page).toHaveTitle('Akun saya — Toko Contoh');
    await expect(page.getByRole('link', { name: 'Pengaturan' })).toHaveCount(0);

    const refused = await page.goto('/settings/business-profile');
    expect(refused?.status()).toBe(403);
    await expect(page.getByRole('heading', { level: 1, name: 'Anda tidak memiliki akses' })).toBeVisible();
});

test('the built assets hold no client name and no bundled picture (AC-010-07)', () => {
    const build = resolve('public/build');
    const client = 'ASNATAL'.split('').reverse().join('');
    const files: string[] = [];
    const walk = (directory: string): void => {
        for (const name of readdirSync(directory)) {
            const path = join(directory, name);

            if (statSync(path).isDirectory()) {
                walk(path);
            } else {
                files.push(path);
            }
        }
    };

    walk(build);
    expect(files.length).toBeGreaterThan(0);
    expect(files.filter((file) => readFileSync(file, 'latin1').toLowerCase().includes(client.toLowerCase()))).toEqual([]);
    expect(files.filter((file) => /\.(png|jpe?g|webp|gif|svg|ico|avif)$/i.test(file))).toEqual([]);
});
