import { expect as baseExpect, test } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, issueLink, linkAddress, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected } from '../support/access';
import { watch } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The security baseline in a real browser, through Nginx and PHP-FPM
// (AC-007-04, AC-007-07; SECURITY_MODEL SEC-HDR-02, SEC-HDR-03, THR-07, THR-15;
// suite SEC01; TASK-007 scope item 5): the content security policy of
// SEC-HDR-03 with its nonce blocks nothing and reports no violation on any
// page that exists — the pre-authentication pages, the signed-in pages in the
// shell, the error page and the two specimens; every answer, the static files
// and the web server's own refusals included, carries the headers of
// SEC-HDR-02; the application refuses to be framed; and the environment file,
// vendor/, storage/ and the source are unreachable through the local web
// server (completed for the release image by TASK-012).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const HEADERS = {
    'x-content-type-options': 'nosniff',
    'x-frame-options': 'DENY',
    'cross-origin-opener-policy': 'same-origin',
    'permissions-policy': 'camera=(), microphone=(), geolocation=(), payment=()',
};

test('no page that exists breaks or reports a violation of the content security policy', async ({ page, context }) => {
    const watched = await watch(page);
    const { Agus: agus } = await resetPeople(context, ['Agus']);
    const link = await issueLink(context, 'Agus');

    for (const [address, heading] of [
        ['/login', 'Masuk'],
        [linkAddress(link), 'Atur kata sandi'],
        ['/tidak-ada', 'Tidak ditemukan'],
        ['/testing/specimen', 'Spesimen komponen'],
        ['/testing/shell-specimen', 'Spesimen kerangka'],
    ] as const) {
        const response = await page.goto(address);
        const policy = response?.headers()['content-security-policy'] ?? '';

        expect(policy).toMatch(/script-src 'self' 'nonce-[A-Za-z0-9]{40}'/);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
    }

    await signIn(page, agus);

    for (const [address, heading] of [
        ['/account', 'Akun saya'],
        ['/account/password', 'Ubah kata sandi'],
    ] as const) {
        await page.goto(address);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
        // The menu of the shell, whose layer libraries style at run time.
        await page.getByRole('button', { name: 'Menu akun Agus' }).click();
        await expect(page.getByRole('menuitem', { name: 'Keluar' })).toBeVisible();
        await page.keyboard.press('Escape');
    }

    await page.goto('/tidak-ada');
    await expect(page.getByRole('heading', { level: 1, name: 'Tidak ditemukan' })).toBeVisible();

    expect(unexpected(watched.problems).filter((problem) => !problem.includes('404'))).toEqual([]);
    expect(watched.problems.filter((problem) => problem.includes('CSP') || problem.includes('Content Security Policy'))).toEqual([]);
});

test('every answer carries the headers, the static files and the web server\'s refusals included', async ({ page, request }) => {
    await page.goto('/login');
    const assets = await page.evaluate(() => [...document.querySelectorAll<HTMLScriptElement | HTMLLinkElement>('script[src], link[rel="stylesheet"]')].map((element) => ('src' in element ? element.src : element.href)));

    expect(assets.length).toBeGreaterThan(0);

    for (const address of ['/login', '/up', '/', '/tidak-ada', '/fonts/inter/OFL.txt', ...assets.map((asset) => new URL(asset).pathname), '/index.php.bak', '/.env', '/artisan.php']) {
        const answer = await request.get(address, { maxRedirects: 0 });

        for (const [name, value] of Object.entries(HEADERS)) {
            expect(answer.headers()[name], `${name} of ${address}`).toBe(value);
        }

        expect(answer.headers()['content-security-policy'], address).toContain("frame-ancestors 'none'");
    }
});

test('the application refuses to be framed', async ({ page }) => {
    const target = new URL('/login', process.env.E2E_BASE_URL ?? 'http://localhost:8080').href;
    const refused = page.waitForEvent('console', (message) => /frame-ancestors|X-Frame-Options/.test(message.text()));

    await page.setContent(`<iframe src="${target}" title="framed"></iframe>`);
    await refused;
    const framed = page.frames().find((frame) => frame !== page.mainFrame());

    expect(framed).toBeDefined();
    expect(await framed?.locator('input').count()).toBe(0);
});

test('the environment file, vendor, storage and the source are unreachable through the web server', async ({ request }) => {
    for (const address of [
        '/.env',
        '/.env.example',
        '/../.env',
        '/%2e%2e/.env',
        '/index.php/../.env',
        '/vendor/autoload.php',
        '/vendor/composer/installed.json',
        '/storage/logs/laravel.log',
        '/storage/framework/sessions/',
        '/composer.json',
        '/composer.lock',
        '/package.json',
        '/artisan',
        '/.git/config',
        '/app/Models/User.php',
        '/config/app.php',
        '/routes/web.php',
        '/database/setup/setup.sql',
        '/tests/Harness/Fixtures/TokoContoh.php',
    ]) {
        const answer = await request.get(address, { maxRedirects: 0 });
        const body = await answer.text();

        expect([400, 403, 404], `${address} answered ${answer.status()}`).toContain(answer.status());
        expect(body, address).not.toMatch(/APP_KEY|DB_PASSWORD|<\?php|"require"|\[core\]|CREATE ROLE/);
    }
});
