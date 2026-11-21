import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, OWNER_HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-007 "Beranda" (AC-041-03, AC-041-04; KEY_SCREENS KS-02 with its states `default`, `quiet`, `failed` and
// `loading`; COPY_DECK sections 6.8 and 10.1; INTERACTION_INVENTORY UXI-001, UXI-059 to UXI-064; NAVIGATION_CONTRACTS
// UXP-007, NC-12; UI_STATES UXS-01, UXS-02, UXS-22; suites UX01, UX02, STATUS01; TASK-041, L5 KS-02 states): the Owner
// lands on Beranda after his sign-in; "Hari ini" with its four figures and the as-of line, its link "Rekap harian";
// his OPEN session (the fixture `finance`, which also sells a product of UNKNOWN cost) leading to its detail; the
// attention items of a cost to complete, an open incident (the fixture `incident`) and a system status to check (the
// fixture `status`), each leading to its page. Then each state of KS-02 by the answers of the panels' own requests:
// `failed` — one panel's read fails, says so with "Coba lagi" and reads again while the others stand; `loading` — a
// panel waits while the others show; `quiet` — no open session and nothing to attend to. Other tests of the shared
// database add sessions, products and incidents, so the counts are checked by their form; their values are
// HomePageTest's. At 1440 px, 320 px and 200 % zoom. These tests sign in the people of Toko Contoh, so they run one
// at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const PAGE = OWNER_HOME;

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** A browser fixture of tests/Harness/Browser/FixturesController.php. */
async function fixture(context: BrowserContext, name: 'finance' | 'incident' | 'status', data?: Record<string, string>): Promise<void> {
    const answer = await context.request.post(`/testing/browser/${name}`, { data, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);
}

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

/** The panel whose heading names it. */
function panel(page: Page, title: string): Locator {
    return page.getByRole('main').locator('section').filter({ has: page.getByRole('heading', { level: 2, name: title, exact: true }) });
}

/**
 * Changes the next answer of one panel's own request — the deferred prop `name` read by name — as the server would
 * give it: `null` for a read that failed, or other evidence. Every other request goes on unchanged.
 */
async function nextAnswer(page: Page, name: string, change: (props: Record<string, unknown>) => void): Promise<void> {
    let done = false;

    await page.route(
        (url) => url.pathname === PAGE,
        async (route) => {
            if (done || route.request().headers()['x-inertia-partial-data'] !== name) {
                await route.fallback();

                return;
            }

            done = true;
            const response = await route.fetch();
            const body = (await response.json()) as { props: Record<string, unknown> };
            change(body.props);
            await route.fulfill({ response, json: body });
        },
    );
}

async function signedIn(context: BrowserContext, page: Page): Promise<void> {
    const accounts = await resetPeople(context, ['Hendra']);

    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
}

test('the Owner lands on Beranda with today, his open session and what needs his attention, in each state of KS-02', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    await fixture(context, 'finance');
    await fixture(context, 'incident');
    await fixture(context, 'status', { state: 'unverified' });
    await signedIn(context, page);
    const watched = await watch(page);

    // RN-01 and NC-12: the Owner's landing page after his sign-in, the first destination of his navigation.
    expect(new URL(page.url()).pathname).toBe(PAGE);
    const main = page.getByRole('main');
    await expect(page.getByRole('heading', { level: 1, name: 'Beranda' })).toBeVisible();
    await expect(page).toHaveTitle('Beranda — Toko Contoh');
    await expect(page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link', { name: 'Beranda' })).toHaveAttribute('aria-current', 'page');

    // KS-02 `default`: "Hari ini" with its four figures and the as-of line; "Rekap harian" for today (UXI-059).
    const today = panel(page, 'Hari ini');
    await expect(main.getByText(/^Data per \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2} WIB$/)).toBeVisible();
    for (const label of ['Penjualan neto', 'Jumlah penjualan', 'Pembayaran tunai', 'Pembayaran transfer bank']) {
        await expect(today.getByText(label, { exact: true })).toBeVisible();
    }

    await expect(today.getByRole('link', { name: 'Rekap harian' })).toHaveAttribute('href', '/reports/daily');

    // His OPEN session (UXI-060), with its register, his name and its opening time, leading to its detail.
    const sessions = panel(page, 'Sesi kas yang sedang dibuka');
    const own = sessions.getByRole('link', { name: /^Laci uji · Hendra · dibuka \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2}$/ }).first();
    await expect(own).toHaveAttribute('href', /^\/cash-sessions\/\d+$/);

    // "Perlu perhatian" (UXI-062 to UXI-064): each item names its condition and leads to its page.
    const attention = panel(page, 'Perlu perhatian');
    await expect(attention.getByRole('link', { name: /^Biaya perlu dilengkapi \([1-9][0-9.]*\)$/ })).toHaveAttribute('href', '/reports/costs-to-complete');
    await expect(attention.getByRole('link', { name: /^Insiden rekonsiliasi \([1-9][0-9.]*\)$/ })).toHaveAttribute('href', '/stock/incidents');
    await expect(attention.getByRole('link', { name: 'Status sistem perlu dicek' })).toHaveAttribute('href', '/settings/system-status');
    await checked(page);
    await fitsNarrow(page);

    // KS-02 `failed` (AC-041-03, UXS-22): the read of "Hari ini" fails; it says so, never a zero, and the other panels stand.
    await nextAnswer(page, 'today', (props) => {
        props.today = null;
    });
    await page.goto(PAGE);
    await expect(today.getByText('Gagal memuat')).toBeVisible();
    await expect(today.getByText('Penjualan neto', { exact: true })).toHaveCount(0);
    await expect(main.getByText(/^Data per /)).toHaveCount(0);
    await expect(sessions.getByRole('link').first()).toBeVisible();
    await expect(attention.getByRole('link', { name: 'Status sistem perlu dicek' })).toBeVisible();
    await checked(page);

    // "Coba lagi" reads that panel alone again.
    await Promise.all([
        page.waitForResponse((response) => new URL(response.url()).pathname === PAGE && response.request().headers()['x-inertia-partial-data'] === 'today'),
        today.getByRole('button', { name: 'Coba lagi' }).click(),
    ]);
    await expect(today.getByText('Penjualan neto', { exact: true })).toBeVisible();
    await expect(main.getByText(/^Data per \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2} WIB$/)).toBeVisible();

    // KS-02 `loading` (UXS-01): the attention panel waits while the others show.
    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    await page.route(
        (url) => url.pathname === PAGE,
        async (route) => {
            if (route.request().headers()['x-inertia-partial-data'] === 'attention') {
                await held;
            }

            await route.fallback();
        },
    );
    await page.goto(PAGE);
    await expect(attention.getByRole('status')).toBeVisible();
    await expect(today.getByText('Penjualan neto', { exact: true })).toBeVisible();
    await expect(attention.getByRole('link')).toHaveCount(0);
    release();
    await expect(attention.getByRole('link', { name: 'Status sistem perlu dicek' })).toBeVisible();

    // KS-02 `quiet` (UXS-02): no open session and nothing to attend to, each said in its panel.
    await nextAnswer(page, 'openSessions', (props) => {
        props.openSessions = [];
    });
    await nextAnswer(page, 'attention', (props) => {
        props.attention = { restock: { out: 0, low: 0 }, costs: 0, incidents: 0, status: false, onboarding: false };
    });
    await page.goto(PAGE);
    await expect(sessions.getByText('Tidak ada sesi kas yang sedang dibuka.')).toBeVisible();
    await expect(attention.getByText('Tidak ada yang perlu perhatian saat ini.')).toBeVisible();
    await expect(attention.getByRole('link')).toHaveCount(0);
    await checked(page);

    // UXI-060 leads to the session's detail.
    await page.goto(PAGE);
    await Promise.all([page.waitForURL((url) => /^\/cash-sessions\/\d+$/.test(url.pathname)), own.click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('at 200 % zoom Beranda fits and its panels are reached from the keyboard', async ({ browser }) => {
    // 640 × 400 at a scale of 2, the 200 % zoom of 1280 × 800 (EXC-53).
    const context = await contextAt(browser, WIDTHS[4]!);
    const page = await context.newPage();
    await signedIn(context, page);
    const watched = await watch(page);

    await page.goto(PAGE);
    await expect(page.getByRole('heading', { level: 1, name: 'Beranda' })).toBeVisible();
    const today = panel(page, 'Hari ini');
    await expect(today.getByText('Penjualan neto', { exact: true })).toBeVisible();
    await expect(panel(page, 'Perlu perhatian').getByRole('heading', { level: 2 })).toBeVisible();
    await checked(page);

    // "Rekap harian" is reached with Tab and opens the daily summary of today with Enter.
    const daily = today.getByRole('link', { name: 'Rekap harian' });
    await daily.focus();
    await expect(daily).toBeFocused();
    await Promise.all([page.waitForURL((url) => url.pathname === '/reports/daily'), page.keyboard.press('Enter')]);
    await expect(page.getByRole('heading', { level: 1, name: 'Rekap harian' })).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
