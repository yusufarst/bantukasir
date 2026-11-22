import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-414 "Insiden rekonsiliasi" and UXP-415 "Detail insiden" (AC-036-03; COPY_DECK sections 9.15 and 9.16;
// NAVIGATION_CONTRACTS UXP-414, UXP-415; INTERACTION_INVENTORY UXI-017 and UXI-301 to UXI-305, UXI-223;
// UI_STATES UXS-04, UXS-11; INTERACTION_CONTRACTS section 1.5; WF-27; suites UX01, UX02, INV01, AUTH03;
// TASK-036, L5 RP-414 and RP-415): the Owner reaches the incidents from the sub-navigation of Stok, which
// counts the open ones; filters them by state; opens the incident, which says what did not match, by its label
// and never its stored code (TASK-051 F-1), and which product is blocked; reads the product's history and comes back to the incident; reopens its postings
// after the confirmation, which "Batal" closes with nothing sent, and the password; the incident then shows
// as reopened. Both pages fit 320 px. The incident is a fixture's (tests/Harness/Browser/FixturesController.php,
// `incident`), a new product each time, because the local database keeps its products. These tests sign in
// the people of Toko Contoh, so they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const LIST = '/stock/incidents';

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** A new incident of a new product, by the fixture. */
async function incident(context: BrowserContext): Promise<{ blockId: number; name: string; sku: string }> {
    const answer = await context.request.post('/testing/browser/incident', { headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    return (await answer.json()) as { blockId: number; name: string; sku: string };
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

test('the Owner finds the incident, reads what did not match and reopens its postings with the password', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const accounts = await resetPeople(context, ['Hendra']);
    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
    // Watched from the first page after the sign-in, whose Inertia location answer (409) the browser logs as a
    // failed load (tests/Browser/support/access.ts, `unexpected`): every answer of the incident's pages counts.
    const watched = await watch(page);
    const found = await incident(context);

    // UXI-017: "Insiden rekonsiliasi" in the sub-navigation of Stok, with the count of open incidents.
    await page.goto('/stock');
    const item = page.getByRole('navigation', { name: 'Submenu Stok' }).getByRole('link', { name: /^Insiden rekonsiliasi/ });
    await expect(item).toContainText(/\d/);
    await expect(item).toContainText('insiden perlu ditangani');
    await Promise.all([page.waitForURL((url) => url.pathname === LIST), item.click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Insiden rekonsiliasi' })).toBeVisible();

    // UXI-302: the newest first, with its state; UXI-301: the filter keeps one state.
    const row = page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: found.name });
    await expect(row).toContainText('Mutasi diblokir');
    await checked(page);
    await fitsNarrow(page);
    await page.getByRole('button', { name: 'Mutasi dibuka kembali' }).click();
    await page.waitForURL((url) => url.searchParams.get('status') === 'REOPENED');
    await expect(page.getByRole('main').getByText(found.name)).toHaveCount(0);
    await page.getByRole('button', { name: 'Mutasi dibuka kembali' }).click();
    await page.waitForURL((url) => !url.searchParams.has('status'));

    // UXP-415: what did not match, by the label that COPY_DECK section 11.15.2 approved for its detail code and
    // never the stored code (TASK-051 F-1, D-138), and the blocked product.
    await Promise.all([page.waitForURL((url) => url.pathname === `${LIST}/${String(found.blockId)}`), page.getByRole('main').getByRole('link', { name: found.name }).first().click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Detail insiden' })).toBeVisible();
    await expect(page.getByRole('main').getByText('Saldo stok', { exact: true })).toBeVisible();
    await expect(page.getByText('BALANCE_MISMATCH')).toHaveCount(0);
    await expect(page.getByText(`${found.name} · ${found.sku}`)).toBeVisible();
    await checked(page);
    await fitsNarrow(page);

    // UXI-303 and UXI-223: the product's history, and the way back to the incident. The page's own link, not
    // the sub-navigation's of the same name.
    const history = page.getByRole('main').getByRole('link', { name: 'Riwayat stok', exact: true }).and(page.locator('[href*="from_page=incident"]'));
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/history'), history.click()]);
    await Promise.all([page.waitForURL((url) => url.pathname === `${LIST}/${String(found.blockId)}`), page.getByRole('link', { name: 'Kembali ke Detail insiden' }).click()]);

    // UXI-304: the confirmation, which Enter never confirms and "Batal" closes with nothing sent.
    const reopen = page.getByRole('main').getByRole('button', { name: 'Buka kembali mutasi' });
    await reopen.click();
    const confirmation = page.getByRole('dialog', { name: `Buka kembali mutasi ${found.name}?` });
    await expect(confirmation.getByText('Lakukan ini hanya setelah fakta-faktanya diperiksa dan direkonsiliasi.', { exact: false })).toBeVisible();
    await expect(confirmation.getByRole('button', { name: 'Batal' })).toBeFocused();
    await checked(page);
    await confirmation.getByRole('button', { name: 'Batal' }).click();
    await expect(confirmation).toHaveCount(0);

    // Then the password (UXS-11), and the success (UXS-04): the incident shows as reopened.
    await reopen.click();
    await confirmation.getByRole('button', { name: 'Buka kembali mutasi' }).click();
    const password = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
    await expect(password).toBeVisible();
    await password.getByLabel('Kata sandi', { exact: true }).fill(accounts.Hendra.password);
    await password.getByRole('button', { name: 'Konfirmasi' }).click();
    await expect(page.getByText('Mutasi dibuka kembali.')).toBeVisible();
    await expect(page.getByRole('main').getByText('Mutasi dibuka kembali', { exact: true })).toBeVisible();
    await expect(page.getByRole('main').getByRole('button', { name: 'Buka kembali mutasi' })).toHaveCount(0);

    // UXI-305: back to the list.
    await Promise.all([page.waitForURL((url) => url.pathname === LIST), page.getByRole('link', { name: 'Kembali ke Insiden rekonsiliasi' }).click()]);
    await expect(page.getByRole('main').getByRole('table').getByRole('row').filter({ hasText: found.name })).toContainText('Mutasi dibuka kembali');

    expect(watched.problems).toEqual([]);
    await context.close();
});
