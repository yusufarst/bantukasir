import { expect as baseExpect, test, type BrowserContext, type Page, type Response } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { COMMAND_SPECIMEN, envelopeOf, injected, prepareCommands, storedReferences } from '../support/commands';
import { watch } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// A command under real maintenance mode (AC-014-04; RELEASE_PROCESS section 6 and
// RB-20 step 7; INTERACTION_CONTRACTS sections 1.3 and 2; UI_STATES UXS-14; TASK-014
// scope item 5), with the probe operation: the server answers before any route runs,
// so that nothing is left, and the client reads the answer as uncertain, never as a
// refusal, and resolves it after the window. The mode is the whole application's, so
// playwright.config.ts runs this file after every other test, alone.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

function xsrf(cookies: { name: string; value: string }[]): string {
    return decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '');
}

async function maintenance(context: BrowserContext, on: boolean): Promise<void> {
    const answer = await context.request.post('/testing/browser/maintenance', {
        data: { on },
        headers: { 'X-XSRF-TOKEN': xsrf(await context.cookies()), Accept: 'application/json' },
    });

    expect(answer.status(), await answer.text()).toBe(200);
}

function save(page: Page) {
    return page.getByRole('main').getByRole('button', { name: 'Simpan' });
}

function panel(page: Page) {
    return page.getByRole('main').getByRole('alert').filter({ hasText: 'Status belum pasti' });
}

function isProbeAnswer(response: Response): boolean {
    return response.url().endsWith('/commands/testing.probe');
}

async function opened(page: Page): Promise<void> {
    const context = page.context();
    const { Agus: agus } = await resetPeople(context, ['Agus']);

    await prepareCommands(context, ['Agus']);
    await signIn(page, agus);
    await page.goto(COMMAND_SPECIMEN);
    await expect(page.getByRole('heading', { level: 1, name: 'Catatan tinjauan' })).toBeVisible();
    await expect(save(page)).toBeEnabled();
}

test.afterEach(async ({ context }) => {
    await maintenance(context, false);
});

test('a command under maintenance mode is uncertain, leaves nothing, and is known not to have committed after the window', async ({ page, context }) => {
    const watched = await watch(page);
    await opened(page);

    await maintenance(context, true);
    const answered = page.waitForResponse(isProbeAnswer);
    await save(page).click();

    // The real answer, before any route: 503 with an empty body.
    const answer = await answered;
    expect(answer.status()).toBe(503);
    expect(await answer.json()).toEqual({});
    const intent = envelopeOf(answer.request()).sourceIntentId;

    // UXS-14, with its recovery, never a refusal; the reference is kept.
    await expect(panel(page)).toBeVisible();
    await expect(panel(page).getByRole('button')).toHaveText(['Kirim ulang', 'Cek status']);
    await expect(page.getByRole('main').getByRole('alert').filter({ hasText: 'Tidak disimpan' })).toHaveCount(0);
    expect(Object.values(await storedReferences(page)).join('\n')).toContain(intent);

    // The status check gets the same answer: no answer, and the state is unchanged.
    await panel(page).getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByRole('main').getByText('Cek status belum mendapat jawaban. Statusnya tetap belum pasti; cek lagi sebentar lagi.')).toBeVisible();
    await expect(panel(page)).toBeVisible();

    // After the window the server has no receipt of it, and nothing committed.
    await maintenance(context, false);
    const requests = await context.request.get('/data/command-requests', { headers: { Accept: 'application/json' } });
    expect(requests.status()).toBe(200);
    expect(JSON.stringify(await requests.json())).not.toContain(intent);

    await panel(page).getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByRole('main').getByText('Tidak tersimpan, dan tidak akan pernah tersimpan. Anda dapat mulai lagi.')).toBeVisible();
    await expect(panel(page)).toHaveCount(0);
    expect(await storedReferences(page)).toEqual({});
    expect(injected(watched.problems)).toEqual([]);
});

test('"Kirim ulang" after the window sends the same frozen request, which then commits', async ({ page, context }) => {
    await opened(page);

    await maintenance(context, true);
    const first = page.waitForResponse(isProbeAnswer);
    await save(page).click();
    const refused = await first;
    expect(refused.status()).toBe(503);
    await expect(panel(page)).toBeVisible();

    await maintenance(context, false);
    const second = page.waitForResponse(isProbeAnswer);
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), panel(page).getByRole('button', { name: 'Kirim ulang' }).click()]);
    const committed = await second;

    expect(committed.status()).toBe(201);
    expect(envelopeOf(committed.request()).sourceIntentId).toBe(envelopeOf(refused.request()).sourceIntentId);
    expect(envelopeOf(committed.request()).idempotencyKey).toBe(envelopeOf(refused.request()).idempotencyKey);
    expect(await storedReferences(page)).toEqual({});
});
