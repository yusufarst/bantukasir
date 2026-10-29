import { expect as baseExpect, test, type Browser, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn, type Account } from '../support/access';
import { COMMAND_SPECIMEN, prepareCommands, PROBE_ROUTE, REQUESTS_PAGE, storedReferences } from '../support/commands';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The block of NC-13 across devices (AC-009-04; TEST_STRATEGY TS-23;
// INTERACTION_CONTRACTS section 1.4; NAVIGATION_CONTRACTS NC-13 (a), (b) and
// (e); ADR-009 section 2; suites CMD01 and UX01; TASK-009 scope item 3): a
// request that reached the server and stays unresolved blocks its family in
// a second browser context of the same user and in the first one after its
// site data are cleared; the page of the family opens on that request's
// panel and offers no new final action; the Owner is never blocked by
// another user's request; a resolution on UXP-006 ends the block everywhere.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

async function signedIn(browser: Browser, account: Account): Promise<Page> {
    const context = await browser.newContext();
    const page = await context.newPage();

    await signIn(page, account);

    return page;
}

function main(page: Page) {
    return page.getByRole('main');
}

test('an unresolved request blocks its family on a second device and after the site data are cleared, never for the Owner', async ({ browser, page }) => {
    const { Agus: agus, Hendra: hendra } = await resetPeople(page.context(), ['Agus', 'Hendra']);
    await prepareCommands(page.context(), ['Agus', 'Hendra']);

    // Device A: the request reaches the server, which answers SERVICE_BUSY
    // and leaves its receipt PENDING.
    await signIn(page, agus);
    await page.goto(COMMAND_SPECIMEN);
    await page.route(PROBE_ROUTE, (route) => {
        const envelope = route.request().postDataJSON() as { payload: Record<string, unknown> };

        return route.continue({ postData: JSON.stringify({ ...envelope, payload: { ...envelope.payload, refuseWith: 'SERVICE_BUSY' } }) });
    });
    await main(page).getByRole('button', { name: 'Simpan' }).click();
    await expect(main(page).getByRole('alert').filter({ hasText: 'Status belum pasti' })).toBeVisible();

    // Device B, the same user: the page of the family opens on the panel of
    // that request — "Cek status" only, the payload being on device A — and
    // its final action is disabled with the reason and the link to UXP-006.
    const second = await signedIn(browser, agus);
    await second.goto(COMMAND_SPECIMEN);
    const adopted = main(second).getByRole('alert').filter({ hasText: 'Status belum pasti' });
    await expect(adopted).toBeVisible();
    await expect(adopted.getByRole('button')).toHaveText(['Cek status']);
    await expect(main(second).getByRole('button', { name: 'Simpan' })).toHaveAttribute('aria-disabled', 'true');
    await expect(main(second).getByText('Ada permintaan sejenis yang statusnya belum pasti. Cek statusnya dulu.')).toBeVisible();
    await expect(main(second).getByRole('link', { name: 'Buka Permintaan belum pasti' })).toHaveAttribute('href', REQUESTS_PAGE);
    expect(await storedReferences(second)).toEqual({});

    // The shell of device B: the banner and the account-menu item with the number.
    await expect(second.getByText('Ada permintaan yang statusnya belum pasti.', { exact: true })).toBeVisible();
    await second.getByRole('button', { name: 'Menu akun Agus' }).click();
    await expect(second.getByRole('menuitem', { name: 'Permintaan belum pasti (1)' })).toBeVisible();
    await second.keyboard.press('Escape');

    // Device A with its site data cleared: the receipt still blocks.
    await page.evaluate(() => window.localStorage.clear());
    await page.unroute(PROBE_ROUTE);
    await page.goto(COMMAND_SPECIMEN);
    await expect(main(page).getByRole('alert').filter({ hasText: 'Status belum pasti' })).toBeVisible();
    await expect(main(page).getByRole('button', { name: 'Simpan' })).toHaveAttribute('aria-disabled', 'true');

    // The Owner is never blocked by another user's request.
    const owner = await signedIn(browser, hendra);
    await owner.goto(COMMAND_SPECIMEN);
    await expect(main(owner).getByRole('heading', { level: 1, name: 'Catatan tinjauan' })).toBeVisible();
    await expect(main(owner).getByRole('button', { name: 'Simpan' })).toBeEnabled();
    await expect(main(owner).getByRole('alert')).toHaveCount(0);

    // Device B resolves it on UXP-006: the request reached the server and
    // never committed, so it is fenced NOT_COMMITTED and the block ends.
    await main(second).getByRole('link', { name: 'Buka Permintaan belum pasti' }).click();
    await expect(second).toHaveURL(new RegExp(`${REQUESTS_PAGE}$`));
    const card = main(second).getByRole('listitem').filter({ hasText: 'Catatan tinjauan · dikirim' });
    await expect(card).toContainText('Dikirim dari perangkat lain atau sebelum data peramban dihapus.');
    await card.getByRole('button', { name: 'Cek status' }).click();
    await expect(main(second).getByText('Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.').first()).toBeVisible();
    await expect(card).toHaveCount(0);

    await page.goto(COMMAND_SPECIMEN);
    await expect(main(page).getByRole('button', { name: 'Simpan' })).toBeEnabled();
    await expect(main(page).getByRole('alert')).toHaveCount(0);

    await second.context().close();
    await owner.context().close();
});
