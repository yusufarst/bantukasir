import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type Page } from '@playwright/test';
import { issueLink, linkAddress, newPassword, resetPeople, ACCESS_TIMEOUT, ACCESS_WAIT, SECURE_LOCAL_ORIGIN, signIn, unexpected } from '../support/access';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-002 "Atur kata sandi" and RA-06 (AC-006-06, AC-006-09; SECURITY_MODEL
// SEC-ACC-05, SEC-ACC-06; suite LINK01). The page reads the account and the
// token from the fragment and removes it before Inertia starts, so the token is
// in neither the address bar, the history state, the page, a request line nor
// a referrer; the page sends no referrer and loads nothing from another
// origin; a link sets the password once; a malformed or used link gives the
// one LINK_INVALID. The person of this file is Dewi (support/access.ts).

test.describe.configure({ mode: 'serial', timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => violation.id)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallText).toEqual([]);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

test('the fragment is read and removed, and the token reaches the server only in the body', async ({ page, context }) => {
    await resetPeople(context, ['Dewi']);
    const link = await issueLink(context, 'Dewi');
    const watched = await watch(page);
    const requests: string[] = [];

    page.on('request', (request) => {
        requests.push(`${request.url()} ${request.headers()['referer'] ?? ''}`);
    });

    const answer = await page.goto(linkAddress(link));

    expect(answer?.headers()['referrer-policy']).toBe('no-referrer');
    await expect(page.getByRole('heading', { level: 1, name: 'Atur kata sandi' })).toBeVisible();
    await expect(page.getByText('Buat kata sandi untuk akun Anda.')).toBeVisible();

    expect(page.url()).not.toContain('#');
    expect(page.url()).not.toContain(link.token);
    const state = await page.evaluate(() => JSON.stringify([window.history.state, window.location.href, window.history.length]));
    expect(state).not.toContain(link.token);
    expect(await page.content()).not.toContain(link.token);
    expect(await page.evaluate(() => JSON.stringify([{ ...window.sessionStorage }, { ...window.localStorage }]))).not.toContain(link.token);

    const password = newPassword();
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(password);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(password);

    const posted = page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === '/set-password');
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    const submission = await posted;

    expect(submission.url()).not.toContain(link.token);
    expect(submission.postData() ?? '').toContain(link.token);

    await expect(page.getByRole('status')).toContainText('Kata sandi tersimpan. Masuk dengan email dan kata sandi baru Anda.');
    const signInLink = page.getByRole('link', { name: 'Masuk', exact: true });
    await expect(signInLink).toHaveClass(/bk-btn--primary/);
    await expect(page.getByRole('button', { name: 'Simpan kata sandi' })).toHaveCount(0);

    expect(requests.filter((line) => line.includes(link.token))).toEqual([]);
    expect(unexpected(watched.problems)).toEqual([]);
    await checked(page);

    await signInLink.click();
    await signIn(page, { id: link.user, email: 'dewi@contoh.test', password });
});

test('a used, a malformed and a missing link give the one LINK_INVALID', async ({ page, context }) => {
    await resetPeople(context, ['Dewi']);
    const link = await issueLink(context, 'Dewi');

    const first = newPassword();
    await page.goto(linkAddress(link));
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(first);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(first);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    await expect(page.getByText('Kata sandi tersimpan. Masuk dengan email dan kata sandi baru Anda.')).toBeVisible();

    const again = newPassword();
    for (const address of [linkAddress(link), '/set-password#u=x&t=y', '/set-password']) {
        // A new document each time: a change of the fragment alone loads none.
        await page.goto('about:blank');
        await page.goto(address);
        await page.getByLabel('Kata sandi baru', { exact: true }).fill(again);
        await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(again);
        await page.getByRole('button', { name: 'Simpan kata sandi' }).click();

        await expect(page.getByText('Tautan tidak berlaku')).toBeVisible();
        await expect(page.getByText('Tautan ini tidak dapat dipakai. Minta tautan baru kepada Pemilik.')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Ke halaman masuk' })).toBeVisible();
        await checked(page);
    }
});

test('a new password that does not meet the profile is shown at its field and the link stays valid', async ({ page, context }) => {
    await resetPeople(context, ['Dewi']);
    const link = await issueLink(context, 'Dewi');

    await page.goto(linkAddress(link));
    await page.getByLabel('Kata sandi baru', { exact: true }).fill('pendek');
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill('pendek');
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();

    await expect(page.getByText('Periksa isian')).toBeVisible();
    await expect(page.getByText('Kata sandi belum memenuhi syarat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol.').first()).toBeVisible();
    await expect(page.getByLabel('Kata sandi baru', { exact: true })).toHaveValue('');
    await checked(page);

    const valid = newPassword();
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(valid);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(valid);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    await expect(page.getByText('Kata sandi tersimpan. Masuk dengan email dan kata sandi baru Anda.')).toBeVisible();
});

test('a lost answer names its recovery and sends nothing again by itself', async ({ page, context }) => {
    await resetPeople(context, ['Dewi']);
    const link = await issueLink(context, 'Dewi');
    const password = newPassword();
    let posts = 0;

    // UI_STATES section 5: the link may have been used, so the page says how
    // to find out instead of offering to send it again.
    await page.route('**/set-password', (route) => {
        if (route.request().method() !== 'POST') {
            return route.continue();
        }

        posts += 1;

        return route.abort('connectionreset');
    });
    await page.goto(linkAddress(link));
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(password);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();

    await expect(page.getByText('Hasilnya belum diterima. Coba masuk dengan kata sandi baru. Jika gagal, minta tautan baru kepada Pemilik, karena tautan ini mungkin sudah terpakai.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ke halaman masuk' })).toBeVisible();
    expect(posts).toBe(1);
    expect(page.url()).not.toContain(link.token);
});

for (const width of WIDTHS) {
    test(`the link page at ${width.name} passes the accessibility checks and the measurements`, async ({ browser }) => {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await page.goto('/set-password');
        await expect(page.getByRole('heading', { level: 1, name: 'Atur kata sandi' })).toBeVisible();
        await expect(page).toHaveTitle('Atur kata sandi');
        await checked(page);
        expect(unexpected(watched.problems)).toEqual([]);

        await context.close();
    });
}
