import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, SECURE_LOCAL_ORIGIN, signIn } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-611 "Status sistem" (AC-039-01, AC-039-02; COPY_DECK section 11.11; NAVIGATION_CONTRACTS UXP-611;
// INTERACTION_INVENTORY UXI-329, UXI-374 to UXI-376; UI_STATES UXS-01, UXS-22, UXS-43; OPERATIONS_RUNBOOK
// section 5; suites UX01, UX02, STATUS01; TASK-039, L5 RP-611 states): the Owner reaches the status from
// "Pengaturan"; an item that is not proven says "Belum Terverifikasi"; "Muat ulang" reads the status again
// and shows what passed and what failed; a read that fails says so with "Coba lagi" and shows no item;
// "Insiden rekonsiliasi" leads to the open incidents; "Kembali ke Pengaturan" leads back. The page fits
// 320 px. The evidence is a fixture's (tests/Harness/Browser/FixturesController.php, `status`), always
// the newest rows, because the local database keeps the earlier ones; the free space of the disk is the
// machine's, so its item may be either measured or "Belum Terverifikasi". These tests sign in the people
// of Toko Contoh, so they run one at a time (playwright.config.ts).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const PAGE = '/settings/system-status';

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** The newest evidence of the status, by the fixture. */
async function evidence(context: BrowserContext, state: 'unverified' | 'ok' | 'failed'): Promise<void> {
    const answer = await context.request.post('/testing/browser/status', { headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' }, data: { state } });

    expect(answer.status(), await answer.text()).toBe(200);
}

/** A new open incident, by the fixture of TASK-036. */
async function incident(context: BrowserContext): Promise<void> {
    const answer = await context.request.post('/testing/browser/incident', { headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

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

function item(page: Page, id: string) {
    return page.getByRole('main').locator(`[data-item="${id}"]`);
}

/** "Muat ulang" (UXI-374), and its read's answer. */
async function reload(page: Page): Promise<void> {
    await Promise.all([page.waitForResponse((response) => new URL(response.url()).pathname === PAGE), page.getByRole('button', { name: 'Muat ulang' }).click()]);
}

test('the Owner reads the system status, reloads it, sees a failed read and follows the incident link', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const accounts = await resetPeople(context, ['Hendra']);
    await prepareCommands(context, ['Hendra']);
    await signIn(page, accounts.Hendra);
    const watched = await watch(page);
    await evidence(context, 'unverified');

    // UXI-329: "Status sistem" on "Pengaturan".
    await page.goto('/settings');
    await Promise.all([page.waitForURL((url) => url.pathname === PAGE), page.getByRole('main').getByRole('link', { name: /Status sistem/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Status sistem' })).toBeVisible();
    await expect(page).toHaveTitle('Status sistem — Toko Contoh');

    // UXS-43: the backup's weekly re-verification failed, so it is not proven, with no passing look.
    await expect(item(page, 'backup')).toHaveAttribute('data-state', 'UNVERIFIED');
    await expect(item(page, 'backup')).toContainText('Belum Terverifikasi');
    await expect(item(page, 'backup')).not.toContainText('Berhasil');
    await expect(item(page, 'disk')).toContainText(/Tersisa .+ dari .+|Belum Terverifikasi/);
    for (const label of ['Cadangan data terakhir', 'Uji pemulihan terakhir', 'Ruang penyimpanan', 'Rekonsiliasi harian', 'Penyimpanan cadangan']) {
        await expect(page.getByRole('main').getByText(label, { exact: true })).toBeVisible();
    }

    await checked(page);
    await fitsNarrow(page);

    // UXI-374: read again, with every item proven except perhaps the disk.
    await evidence(context, 'ok');
    await reload(page);
    for (const id of ['backup', 'restore', 'reconciliation', 'backupStore']) {
        await expect(item(page, id)).toHaveAttribute('data-state', 'OK');
    }

    await expect(item(page, 'backup')).toContainText(/^Berhasil \d{2} \w+ \d{4}, \d{2}\.\d{2}\.\d{2}/);
    await expect(item(page, 'backup')).toContainText('menit yang lalu');
    await expect(item(page, 'backupStore')).toContainText('Terpakai 1,5 GB dari 10 GB');
    await checked(page);

    // The newest backup failed.
    await evidence(context, 'failed');
    await reload(page);
    await expect(item(page, 'backup')).toHaveAttribute('data-state', 'FAILED');
    await expect(item(page, 'backup')).toContainText(/^Gagal /);
    await checked(page);

    // UXS-22: a read that fails shows no item, then "Coba lagi" reads them again.
    await page.route(`**${PAGE}`, (route) => route.abort(), { times: 1 });
    await page.getByRole('button', { name: 'Muat ulang' }).click();
    await expect(page.getByText('Gagal memuat')).toBeVisible();
    await expect(page.getByRole('main').locator('[data-item]')).toHaveCount(0);
    await checked(page);
    await Promise.all([page.waitForResponse((response) => new URL(response.url()).pathname === PAGE), page.getByRole('button', { name: 'Coba lagi' }).click()]);
    await expect(page.getByRole('main').locator('[data-item]')).toHaveCount(5);

    // UXI-375: while an incident is open, its link leads to the incidents.
    await incident(context);
    await reload(page);
    const link = item(page, 'reconciliation').getByRole('link', { name: 'Insiden rekonsiliasi' });
    await expect(link).toBeVisible();
    await checked(page);
    await Promise.all([page.waitForURL((url) => url.pathname === '/stock/incidents'), link.click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Insiden rekonsiliasi' })).toBeVisible();

    // UXI-376: back to "Pengaturan".
    await page.goto(PAGE);
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings'), page.getByRole('link', { name: 'Kembali ke Pengaturan' }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Pengaturan' })).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});
