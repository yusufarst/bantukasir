import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { HOME, resetPeople, ACCESS_TIMEOUT, ACCESS_WAIT, SECURE_LOCAL_ORIGIN, signIn, unexpected, type Account } from '../support/access';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-001 "Masuk" and RA-01 (KS-01; AC-006-02, AC-006-09; the L5 sign-in path
// of EXECUTION_CONTEXT EXC-34, which every later pull request runs; suites
// SIGNIN01, UX01, UX02). The states of KS-01 — default, failed, signed-out and
// throttled — at the widths of KEY_SCREENS section 4 and 1440 px with the
// accessibility checks; a sign-in is a full page load to the role's home; a
// failure keeps the email and clears the password; no request leaves the
// origin. The person of this file is Rina (support/access.ts): the files run side by side, and each has its own.

test.describe.configure({ mode: 'serial', timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function axe(page: Page): Promise<string[]> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    return results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`);
}

async function checked(page: Page): Promise<void> {
    expect(await axe(page)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallText).toEqual([]);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

test('a sign-in is a full page load to the home and the sign-in session reaches the account page', async ({ page, context }) => {
    const watched = await watch(page);
    const { Rina: rina } = await resetPeople(context, ['Rina']);
    let navigations = 0;

    page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame()) {
            navigations += 1;
        }
    });

    await signIn(page, rina);
    expect(navigations, 'the sign-in page and then a full page load').toBeGreaterThanOrEqual(2);
    expect(new URL(page.url()).pathname).toBe(HOME);

    await page.goto('/account');
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
    await expect(page.getByText(rina.email)).toBeVisible();
    await expect(page.getByText('Kasir', { exact: true })).toBeVisible();
    expect(unexpected(watched.problems)).toEqual([]);
});

test('a failed sign-in shows the one uniform message, keeps the email and clears the password', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await page.goto('/login');
    await expect(page.getByLabel('Email')).toBeFocused();
    await page.getByLabel('Email').fill(rina.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(`Salah#${rina.password}`);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();

    const alert = page.getByRole('alert');
    await expect(alert).toContainText('Gagal masuk');
    await expect(alert).toContainText('Periksa email dan kata sandi Anda, lalu coba lagi.');
    await expect(page.getByLabel('Email')).toHaveValue(rina.email);
    await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveValue('');
    await expect(page.getByLabel('Kata sandi', { exact: true })).toBeFocused();
    await checked(page);
});

test('the throttled state names its retry time', async ({ page }) => {
    await page.route('**/login', async (route) => {
        if (route.request().method() !== 'POST') {
            await route.continue();

            return;
        }

        await route.fulfill({
            status: 429,
            headers: { 'Retry-After': '60', 'Content-Type': 'application/json' },
            body: JSON.stringify({ outcome: 'REJECTED', code: 'THROTTLED', messageKey: 'ux.state.throttled', params: {}, fields: {}, lines: {} }),
        });
    });

    await page.goto('/login');
    await page.getByLabel('Email').fill('rina@contoh.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('Apa#saja1');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();

    const alert = page.getByRole('alert');
    await expect(alert).toContainText('Terlalu banyak percobaan');
    await expect(alert).toContainText('Belum ada yang diproses. Coba lagi dalam 60 detik.');
    await checked(page);
});

test('an ended sign-in session leads to the sign-in page with its note', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await signIn(page, rina);
    await page.goto('/account');
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();

    // The sign-in session is revoked on the server, as a deactivation or a link would.
    await resetPeople(context, ['Rina']);
    await page.goto('/account');

    expect(new URL(page.url()).pathname).toBe('/login');
    await expect(page.getByRole('status')).toContainText('Sesi masuk berakhir');
    await expect(page.getByRole('status')).toContainText('Sesi kas yang masih dibuka tidak ikut tertutup.');
    await checked(page);
});

test('the password toggle shows and hides the password and Enter submits the form', async ({ page, context }) => {
    const { Rina: rina }: { Rina: Account } = await resetPeople(context, ['Rina']);

    await page.goto('/login');
    const password = page.getByLabel('Kata sandi', { exact: true });
    const toggle = page.getByRole('button', { name: 'Tampilkan kata sandi' });

    await password.fill(rina.password);
    await expect(password).toHaveAttribute('type', 'password');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await toggle.click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(page.getByRole('button', { name: 'Sembunyikan kata sandi' })).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('button', { name: 'Sembunyikan kata sandi' }).click();
    await expect(password).toHaveAttribute('type', 'password');

    await page.getByLabel('Email').fill(rina.email);
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), password.press('Enter')]);
});

test('a lost answer shows the connection notice and keeps the email', async ({ page, context }) => {
    const { Rina: rina }: { Rina: Account } = await resetPeople(context, ['Rina']);

    // UI_STATES section 5: the sign-in is repeated, with the same result.
    await page.route('**/login', (route) => (route.request().method() === 'POST' ? route.abort('connectionreset') : route.continue()));
    await page.goto('/login');
    await page.getByLabel('Email').fill(rina.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(rina.password);
    await page.getByRole('button', { name: 'Masuk' }).click();

    await expect(page.getByText('Tidak ada koneksi')).toBeVisible();
    await expect(page.getByLabel('Email')).toHaveValue(rina.email);
    expect(new URL(page.url()).pathname).toBe('/login');

    await page.unroute('**/login');
    await page.getByLabel('Kata sandi', { exact: true }).fill(rina.password);
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), page.getByRole('button', { name: 'Masuk' }).click()]);
});

for (const width of WIDTHS) {
    test(`the sign-in page at ${width.name} passes the accessibility checks and the measurements`, async ({ browser }) => {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await page.goto('/login');
        await expect(page.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
        await expect(page).toHaveTitle('Masuk');
        await expect(page.getByText('BantuKasir')).toHaveCount(0);
        await checked(page);
        expect(unexpected(watched.problems)).toEqual([]);

        await context.close();
    });
}
