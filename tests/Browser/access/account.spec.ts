import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { newPassword, resetPeople, ACCESS_TIMEOUT, ACCESS_WAIT, SECURE_LOCAL_ORIGIN, signIn, signOut, unexpected } from '../support/access';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-003 "Akun saya", UXP-004 "Ubah kata sandi" and RA-02 (AC-006-05,
// AC-006-09; NAVIGATION_CONTRACTS UXP-003, UXP-004; SECURITY_MODEL SEC-AUTH-10,
// SEC-AUTH-11, SEC-CH-04; suites SIGNIN01, UX01, UX02). The own account; the
// password change with its field errors, its uniform refusal and its success
// on UXP-003; the unsaved-changes guard of "Batal" and "Kembali ke Akun saya";
// and after a sign-out, Back shows nothing of the signed-out user: the
// history key is cleared and the page is asked of the server again. The
// person of this file is Agus (support/access.ts).

test.describe.configure({ mode: 'serial', timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallText).toEqual([]);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

test('after a sign-out, Back, Forward and a reload show nothing of the signed-out user', async ({ page, context }) => {
    const { Agus: agus } = await resetPeople(context, ['Agus']);

    await signIn(page, agus);
    await page.goto('/account');
    await expect(page.getByText(agus.email)).toBeVisible();

    await signOut(page);
    expect(new URL(page.url()).pathname).toBe('/login');

    await page.goBack();
    await expect(page.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/login');
    await expect(page.getByText(agus.email)).toHaveCount(0);
    await expect(page.getByText('Admin Operasional')).toHaveCount(0);

    await page.goForward();
    await page.reload();
    await expect(page.getByText(agus.email)).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
});

test('a password change keeps this session, refuses a wrong current password and lands on the account page', async ({ page, context }) => {
    const { Agus: agus } = await resetPeople(context, ['Agus']);
    const changed = newPassword();

    await signIn(page, agus);
    await page.goto('/account');
    await expect(page.getByText('Nama dan email Anda hanya dapat diubah oleh Pemilik.')).toBeVisible();
    await page.getByRole('link', { name: 'Ubah kata sandi' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Ubah kata sandi' })).toBeVisible();
    await expect(page.getByLabel('Kata sandi sekarang', { exact: true })).toBeFocused();

    // Field errors (UXS-05), the passwords not kept.
    await page.getByLabel('Kata sandi sekarang', { exact: true }).fill(agus.password);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill('lemah');
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill('berbeda');
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    await expect(page.getByText('Periksa isian')).toBeVisible();
    await expect(page.getByText('Kata sandi yang diulang tidak sama.').first()).toBeVisible();
    await expect(page.getByLabel('Kata sandi sekarang', { exact: true })).toHaveValue('');
    await checked(page);

    // A wrong current password: one uniform message (UXS-42).
    await page.getByLabel('Kata sandi sekarang', { exact: true }).fill(`Salah#${agus.password}`);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(changed);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(changed);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Kata sandi sekarang tidak cocok' })).toBeVisible();

    // The change, with Enter: back on UXP-003 with its message.
    await page.getByLabel('Kata sandi sekarang', { exact: true }).fill(agus.password);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(changed);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(changed);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).press('Enter');
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
    await expect(page.getByText('Kata sandi diubah. Sesi masuk Anda di perangkat dan peramban lain sudah diakhiri.')).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/account');

    await signOut(page);
    await signIn(page, { ...agus, password: changed });
});

test('cancel and back ask before entered passwords are lost', async ({ page, context }) => {
    const { Agus: agus } = await resetPeople(context, ['Agus']);

    await signIn(page, agus);
    await page.goto('/account/password');

    // Nothing entered: both lead straight to UXP-003.
    await page.getByRole('link', { name: 'Batal' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
    await page.goto('/account/password');

    await page.getByLabel('Kata sandi sekarang', { exact: true }).fill('sesuatu');
    await page.getByRole('link', { name: 'Kembali ke Akun saya' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Perubahan belum disimpan');
    await expect(dialog.getByRole('button', { name: 'Tetap di halaman ini' })).toBeFocused();
    await dialog.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    await expect(dialog).toBeHidden();
    expect(new URL(page.url()).pathname).toBe('/account/password');

    await page.getByRole('link', { name: 'Batal' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Tinggalkan' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
});

test('a lost answer of the password change says how to tell whether it was saved', async ({ page, context }) => {
    const { Agus: agus } = await resetPeople(context, ['Agus']);
    const changed = newPassword();

    await signIn(page, agus);
    await page.goto('/account/password');

    // UI_STATES section 5: repeat; a refused current password then means
    // that the change was saved.
    await page.route('**/user/password', (route) => route.abort('connectionreset'));
    await page.getByLabel('Kata sandi sekarang', { exact: true }).fill(agus.password);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(changed);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(changed);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();

    await expect(page.getByText('Hasilnya belum diterima. Ulangi sekali lagi. Jika kata sandi sekarang ditolak, berarti perubahan sudah tersimpan dan kata sandi baru yang berlaku.')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: 'Ubah kata sandi' })).toBeVisible();
});

for (const width of WIDTHS) {
    test(`the account pages at ${width.name} pass the accessibility checks and the measurements`, async ({ browser }) => {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const { Agus: agus } = await resetPeople(context, ['Agus']);
        const watched = await watch(page);

        await signIn(page, agus);
        await page.goto('/account');
        await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
        await checked(page);
        await page.goto('/account/password');
        await expect(page.getByRole('heading', { level: 1, name: 'Ubah kata sandi' })).toBeVisible();
        await checked(page);
        expect(unexpected(watched.problems)).toEqual([]);

        await context.close();
    });
}
