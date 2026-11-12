import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Person } from '../support/access';
import { cashSession, type FixtureSession } from '../support/cash';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-205 "Sesi kas" and UXP-206 "Detail sesi kas", and the session of UXP-604 (ROUTE_CONTRACTS RP-205,
// RP-206; COPY_DECK sections 7.10, 7.11 and 11.4; INTERACTION_INVENTORY UXI-135 to UXI-139, UXI-148 and
// UXI-340; NAVIGATION_CONTRACTS UXP-205, UXP-206, UXP-604; UI_STATES UXS-03; BR-CASH-21; TASK-030, L5;
// review R-03 and R-04). The cashier lists her own sessions at every width, which the page says, filters
// them, pages both ways and comes back from a session to the same filter and page; her OPEN session
// shows its running figures with "Tutup sesi kas", which the Owner's view of it does not, and the Owner's
// page of her account leads to it. The Owner lists every session at every width with his filters "Laci
// kas" and "Penanggung jawab", and a filter without results clears. Every session is the fixture's,
// through the Actions, and each of hers is closed again, so that her home stays Buka sesi kas.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const SESSIONS = '/cash-sessions';

const CLOSE_PAGE = '/till/close-session';

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

/** A new OPEN session of the person through RC-02's Action, their earlier one closed through RC-03's. */
async function sessionOf(context: BrowserContext, person: Person): Promise<FixtureSession> {
    const { session } = await cashSession(context, person, { open: '0' });

    if (session === null) {
        throw new Error('The fixture opens a session.');
    }

    return session;
}

/** A session's row of the list (UXI-137), by its number. */
function row(page: Page, session: FixtureSession): Locator {
    return page.getByRole('main').getByRole('link', { name: session.number, exact: true });
}

test('the cashier lists her own sessions at every width, filters and pages them, and comes back from one to the same page (UXP-205, UXP-206, UXP-604; review R-03, R-04)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 8);

    for (const [index, width] of WIDTHS.entries()) {
        const first = index === 0;
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);
        const accounts = await resetPeople(context, ['Rina']);

        await prepareCommands(context, ['Rina']);

        // Two sessions of hers, closed again; the newer is listed first (BR-CASH-21).
        const older = first ? await sessionOf(context, 'Rina') : null;
        const newer = first ? await sessionOf(context, 'Rina') : null;
        await cashSession(context, 'Rina');

        // UXI-135 to UXI-138: her own sessions alone, which the page says; the rows become cards when narrow.
        await signIn(page, accounts.Rina);
        await page.goto(`${SESSIONS}?perPage=1`);
        await expect(page.getByRole('heading', { level: 1, name: 'Sesi kas' })).toBeVisible();
        await expect(page.getByText('Menampilkan sesi kas Anda sendiri.')).toBeVisible();
        await checked(page);

        if (older !== null && newer !== null) {
            // A filter (UXI-135) stands in the address with the page's size (NC-10).
            await page.getByLabel('Status', { exact: true }).selectOption({ label: 'Ditutup' });
            await expect(page).toHaveURL((url) => url.pathname === SESSIONS && url.searchParams.get('status') === 'CLOSED' && url.searchParams.get('perPage') === '1');
            await expect(row(page, newer)).toBeVisible();

            // UXI-138: the next page and back, and the next again.
            await page.getByRole('button', { name: 'Berikutnya' }).click();
            await expect(row(page, older)).toBeVisible();
            await page.getByRole('button', { name: 'Sebelumnya' }).click();
            await expect(row(page, newer)).toBeVisible();
            await page.getByRole('button', { name: 'Berikutnya' }).click();
            await expect(row(page, older)).toBeVisible();
            await expect(page).toHaveURL((url) => url.searchParams.has('after') && url.searchParams.get('status') === 'CLOSED' && url.searchParams.get('perPage') === '1');
            const list = new URL(page.url());

            // UXI-137 opens the session's report; "Kembali ke Sesi kas" (UXI-148) returns to the same filter and
            // page (review R-03).
            await Promise.all([page.waitForURL((url) => url.pathname === `${SESSIONS}/${String(older.id)}`), row(page, older).click()]);
            await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
            await expect(page.getByRole('main')).toContainText('Laporan sesi kas');
            await checked(page);
            const back = page.getByRole('link', { name: 'Kembali ke Sesi kas' });
            await expect(back).toHaveAttribute('href', `${list.pathname}${list.search}`);
            await Promise.all([page.waitForURL((url) => url.pathname === SESSIONS && url.search === list.search), back.click()]);
            await expect(row(page, older)).toBeVisible();
            await expect(page.getByLabel('Status', { exact: true })).toHaveValue('CLOSED');

            // Her OPEN session: its running figures and "Tutup sesi kas" for her (UXI-139).
            const open = await sessionOf(context, 'Rina');
            await page.goto(`${SESSIONS}/${String(open.id)}`);
            await expect(page.getByRole('heading', { level: 1, name: 'Detail sesi kas' })).toBeVisible();
            await expect(page.getByRole('main')).toContainText('Uang seharusnya di laci');
            await expect(page.getByRole('main').getByRole('link', { name: 'Tutup sesi kas', exact: true })).toHaveAttribute('href', CLOSE_PAGE);
            await checked(page);

            // The Owner sees it without the close, which is hers; his page of her account leads to it (UXI-340).
            const owner = await contextAt(browser, width);
            const ownerPage = await owner.newPage();
            const ownerWatched = await watch(ownerPage);
            const people = await resetPeople(owner, ['Hendra']);

            await signIn(ownerPage, people.Hendra);
            await ownerPage.goto(`/settings/users/${String(accounts.Rina.id)}`);
            await expect(ownerPage.getByRole('main')).toContainText('Sesi kas yang masih dibuka');
            await checked(ownerPage);
            await Promise.all([
                ownerPage.waitForURL((url) => url.pathname === `${SESSIONS}/${String(open.id)}`),
                ownerPage.getByRole('main').getByRole('link', { name: 'Detail sesi kas', exact: true }).click(),
            ]);
            await expect(ownerPage.getByRole('main')).toContainText('Uang seharusnya di laci');
            await expect(ownerPage.getByRole('main').getByRole('link', { name: 'Tutup sesi kas' })).toHaveCount(0);
            await checked(ownerPage);

            expect(injected(ownerWatched.problems), width.name).toEqual([]);
            await owner.close();
            await cashSession(context, 'Rina');
        }

        expect(injected(watched.problems), width.name).toEqual([]);
        await context.close();
    }
});

test('the Owner lists every session at every width with his filters, and a filter without results clears (UXP-205, UXS-03; review R-04)', async ({ browser }) => {
    test.setTimeout(ACCESS_TIMEOUT * 6);

    for (const [index, width] of WIDTHS.entries()) {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);
        const accounts = await resetPeople(context, ['Hendra']);

        // UXI-136: "Laci kas" and "Penanggung jawab" beside the status and the day; no note of an own scope.
        await signIn(page, accounts.Hendra);
        await page.goto(`${SESSIONS}?perPage=5`);
        await expect(page.getByRole('heading', { level: 1, name: 'Sesi kas' })).toBeVisible();
        await expect(page.getByLabel('Laci kas', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Penanggung jawab', { exact: true })).toBeVisible();
        await expect(page.getByText('Menampilkan sesi kas Anda sendiri.')).toHaveCount(0);
        await checked(page);

        if (index === 0) {
            // UXS-03: a day without sessions; "Hapus filter" clears the filters and keeps the page's size.
            await page.getByLabel('Tanggal', { exact: true }).fill('2001-01-01');
            await expect(page).toHaveURL((url) => url.searchParams.get('from') === '2001-01-01' && url.searchParams.get('to') === '2001-01-01');
            await expect(page.getByRole('main')).toContainText('Tidak ada yang cocok dengan pencarian atau filter ini.');
            await checked(page);
            await page.getByRole('button', { name: 'Hapus filter' }).click();
            await expect(page).toHaveURL((url) => url.pathname === SESSIONS && !url.searchParams.has('from') && url.searchParams.get('perPage') === '5');

            // His filter of a responsible user: her sessions alone, which the first test left.
            await page.getByLabel('Penanggung jawab', { exact: true }).selectOption({ label: 'Rina' });
            await expect(page).toHaveURL((url) => url.searchParams.has('responsible') && url.searchParams.get('perPage') === '5');
            const main = page.getByRole('main');
            await expect(main.getByRole('cell', { name: 'Rina', exact: true }).first()).toBeVisible();
            await expect(main.getByRole('cell', { name: /^(Hendra|Dewi)$/ })).toHaveCount(0);
        }

        expect(injected(watched.problems), width.name).toEqual([]);
        await context.close();
    }
});
