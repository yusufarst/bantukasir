import { expect as baseExpect, test, type Browser, type BrowserContext } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected, type Account, type Person } from '../support/access';
import { watch } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The client timers of the sign-in session and the shared device (AC-007-06;
// SECURITY_MODEL SEC-SESS-02, SEC-AUTH-10, SEC-CH-04, SEC-CH-05, THR-18; UI_STATES
// UXS-23; UXA-21; INTERACTION_INVENTORY UXI-034, UXI-035; suites SIGNIN01 and
// AUTH02; TASK-007 scope item 8). The browser's clock is controlled; the
// server's is not, so a timer that ends the sign-in session in the browser is
// seen by its effects there — the history key and the storage cleared, the
// sign-in page asked for — while the server's own end is proven by
// SignInSessionTest. A signed-out user's pages are never shown to the next
// one, through Back, Forward or a reload, in this tab or another.

test.describe.configure({ mode: 'serial', timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

test('the sign-in-ending dialog warns before the idle limit and "Tetap masuk" keeps the session', async ({ page, context }) => {
    const watched = await watch(page);
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await page.clock.install();
    await signIn(page, rina);
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();

    await page.clock.fastForward('27:00');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    await page.clock.fastForward('01:01');
    const dialog = page.getByRole('dialog', { name: 'Sesi masuk akan berakhir' });

    await expect(dialog).toContainText('Anda akan keluar otomatis dalam');
    await expect(dialog.getByRole('button', { name: 'Tetap masuk' })).toBeFocused();
    await expect(page.getByTestId('live-assertive')).toContainText('Sesi masuk akan berakhir dalam');

    const activity = page.waitForRequest((request) => request.url().endsWith('/session/activity') && request.method() === 'POST');
    await dialog.getByRole('button', { name: 'Tetap masuk' }).click();
    expect((await (await activity).response())?.status()).toBe(204);
    await expect(dialog).toBeHidden();

    // The idle limit now counts from that answer: still no dialog 27 minutes later.
    await page.clock.fastForward('27:00');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(unexpected(watched.problems)).toEqual([]);
});

test('at the idle limit the browser clears the history key and its storage and loads the sign-in page', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await page.clock.install();
    await signIn(page, rina);
    // The shell's timers start when it has rendered the home; the clock moves only then.
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
    await page.evaluate(() => window.localStorage.setItem('bk-test', 'x'));

    // The application's address, which the server answers with the sign-in
    // page and its note once the sign-in session has ended there too.
    const address = page.waitForRequest((request) => new URL(request.url()).pathname === '/' && request.resourceType() === 'document');
    await page.clock.fastForward('30:01');
    await address;

    // The server's sign-in session is still alive by its own clock, so it
    // sends the browser home again; what matters here is what the browser
    // cleared before it asked.
    await page.waitForURL((url) => url.pathname === HOME);
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
    expect(await page.evaluate(() => window.localStorage.getItem('bk-test'))).toBeNull();
});

test('"Keluar" in the sign-in-ending dialog signs out', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await page.clock.install();
    await signIn(page, rina);
    await expect(page.getByRole('heading', { level: 1, name: 'Akun saya' })).toBeVisible();
    await page.clock.fastForward('28:01');
    await Promise.all([
        page.waitForURL((url) => url.pathname === '/login'),
        page.getByRole('dialog', { name: 'Sesi masuk akan berakhir' }).getByRole('button', { name: 'Keluar' }).click(),
    ]);
    await expect(page.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
    await page.goto(HOME);
    expect(new URL(page.url()).pathname).toBe('/login');
});

test('the end of the 12-hour limit is told in its last 15 minutes', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await signIn(page, rina);
    const notice = page.getByText(/^Sesi masuk ini berakhir pukul \d{2}\.\d{2}, batas 12 jam sejak masuk\. Batas ini tidak bisa diperpanjang\.$/);

    await expect(notice).toHaveCount(0);

    // The next answer of the home is changed to a sign-in session with ten
    // minutes left of its 12 hours; its end, as an instant, is the server's own.
    await page.route('**/account', async (route) => {
        const response = await route.fetch();
        const body = (await response.text()).replace(/("absoluteEndsIn":)\d+/, '$1600');

        await route.fulfill({ response, body });
    });
    await page.goto(HOME);

    await expect(notice).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
});

test('after a sign-out, the next user sees nothing of the first through Back, Forward or a reload', async ({ page, context }) => {
    const { Agus: agus, Rina: rina } = await resetPeople(context, ['Agus', 'Rina']);
    const watched = await watch(page);

    await signIn(page, agus);
    await expect(page.getByText(agus.email)).toBeVisible();
    await page.getByRole('link', { name: 'Ubah kata sandi' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Ubah kata sandi' })).toBeVisible();

    await page.getByRole('button', { name: 'Menu akun Agus' }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === '/login'), page.getByRole('menuitem', { name: 'Keluar' }).click()]);
    await signIn(page, rina);
    await expect(page.getByText(rina.email)).toBeVisible();

    for (const step of ['back', 'back', 'back', 'forward', 'reload'] as const) {
        if (step === 'back') {
            await page.goBack();
        } else if (step === 'forward') {
            await page.goForward();
        } else {
            await page.reload();
        }

        await page.waitForLoadState('load');
        await expect(page.getByText(agus.email)).toHaveCount(0);
        await expect(page.getByText('Admin Operasional')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Menu akun Agus' })).toHaveCount(0);
    }

    expect(unexpected(watched.problems)).toEqual([]);
});

test('a sign-out in one tab ends the sign-in session in the other tabs of the browser', async ({ context }) => {
    const { Agus: agus } = await resetPeople(context, ['Agus']);
    const first = await context.newPage();

    await signIn(first, agus);
    const second = await context.newPage();
    await second.goto('/account/password');
    await expect(second.getByRole('heading', { level: 1, name: 'Ubah kata sandi' })).toBeVisible();

    await first.getByRole('button', { name: 'Menu akun Agus' }).click();
    await Promise.all([first.waitForURL((url) => url.pathname === '/login'), first.getByRole('menuitem', { name: 'Keluar' }).click()]);

    await second.waitForURL((url) => url.pathname === '/login');
    await expect(second.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
    await second.goBack();
    await expect(second.getByText(agus.email)).toHaveCount(0);
});

/**
 * Ends a person's sign-in sessions on the server from another browser, as a
 * deactivation, a role change, a password change on another device or a
 * one-time link does (SECURITY_MODEL SEC-SESS-04): no tab of this browser is
 * told.
 */
async function endOnServer(browser: Browser, person: Person): Promise<void> {
    const elsewhere = await browser.newContext();

    try {
        await resetPeople(elsewhere, [person]);
    } finally {
        await elsewhere.close();
    }
}

/** Signs a person in to this browser without a tab, so that no tab tells the others. */
async function signInWithoutTab(context: BrowserContext, account: Account): Promise<void> {
    expect((await context.request.get('/login')).status()).toBe(200);
    const token = decodeURIComponent((await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '');
    const answer = await context.request.post('/login', { form: { email: account.email, password: account.password }, headers: { 'X-XSRF-TOKEN': token } });

    expect(new URL(answer.url()).pathname).toBe(HOME);
}

test("another user's sign-in in another tab ends the page of a user whose sign-in session ended on the server (review finding R-27)", async ({ browser, context }) => {
    const { Agus: agus, Rina: rina } = await resetPeople(context, ['Agus', 'Rina']);
    const first = await context.newPage();

    await signIn(first, agus);
    await expect(first.getByText(agus.email)).toBeVisible();

    await endOnServer(browser, 'Agus');
    const second = await context.newPage();
    await signIn(second, rina);
    await expect(second.getByText(rina.email)).toBeVisible();

    // Without any action in the first tab, nothing of Agus stays there: it
    // loaded the application's address again, which is now Rina's.
    await expect(first.getByRole('button', { name: 'Menu akun Rina' })).toBeVisible();
    await expect(first.getByText(agus.email)).toHaveCount(0);
    await expect(first.getByRole('button', { name: 'Menu akun Agus' })).toHaveCount(0);
});

test("an answer for another user clears the tab's history key and storage and loads the page again (review finding R-28)", async ({ browser, context }) => {
    const { Agus: agus, Rina: rina } = await resetPeople(context, ['Agus', 'Rina']);
    const page = await context.newPage();

    await signIn(page, agus);
    await page.goto('/account/password');
    await expect(page.getByRole('button', { name: 'Menu akun Agus' })).toBeVisible();
    await page.evaluate(() => window.localStorage.setItem('bk-test', 'x'));
    const historyKey = await page.evaluate(() => window.sessionStorage.getItem('historyKey'));

    expect(historyKey).not.toBeNull();

    await endOnServer(browser, 'Agus');
    await signInWithoutTab(context, rina);

    // The tab's next Inertia answer — the empty form refused, the page given
    // again — is Rina's. The pages' links are full page loads, so a form is
    // today's Inertia visit.
    await page.getByLabel('Kata sandi sekarang').press('Enter');
    await expect(page.getByRole('button', { name: 'Menu akun Rina' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Menu akun Agus' })).toHaveCount(0);
    expect(await page.evaluate(() => window.localStorage.getItem('bk-test'))).toBeNull();
    expect(await page.evaluate(() => window.sessionStorage.getItem('historyKey'))).not.toBe(historyKey);
});
