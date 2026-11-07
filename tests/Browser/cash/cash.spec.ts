import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, resetPeople, SECURE_LOCAL_ORIGIN, signIn, STOCK_HOME, type Person } from '../support/access';
import { injected, prepareCommands } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-609 "Laci kas", UXP-610 "Tambah laci kas" or "Ubah laci kas" and UXP-104 "Buka sesi kas" with
// KS-06's states `default`, `review` and `no-register` (AC-027-04, AC-027-06; COPY_DECK sections
// 7.4, 11.1, 11.9 and 11.10; NAVIGATION_CONTRACTS UXP-104, UXP-609, UXP-610; INTERACTION_INVENTORY
// UXI-002, UXI-094 to UXI-100, UXI-328 and UXI-367 to UXI-373; UI_STATES UXS-05, UXS-11, UXS-13 and
// UXS-48; WF-04, WF-30; suites UX01, UX02, SESSION02 and CONFIG01; TASK-027, L5). The Owner adds and
// changes a register with the password once; a code that another register holds is said at its
// field; the Owner opens his own session with Rp0 through the review, lands home with its number,
// and the register then shows the session and cannot be changed; the combined account sees the form
// at every width of KEY_SCREENS section 4 and 1440 px, and UXS-48 when no register is available.
// These tests sign in the people of Toko Contoh, so they run one at a time (playwright.config.ts).
// Every register gets a new random code, because the local database keeps its registers; the
// Owner's session is opened once per database, until TASK-030 delivers the close, so this file runs
// on a fresh database, as CI's is (TASK-027 evidence, finding F-3).

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const REGISTERS = '/settings/cash-registers';

const OPEN_SESSION = '/till/open-session';

function letters(length: number): string {
    return Array.from(crypto.getRandomValues(new Uint8Array(length)), (byte) => String.fromCharCode(97 + (byte % 26))).join('');
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

async function signedIn(context: BrowserContext, page: Page, person: Person): Promise<string> {
    const accounts = await resetPeople(context, [person]);

    await prepareCommands(context, [person]);
    await signIn(page, accounts[person]);

    return accounts[person].password;
}

/** A new register through UXP-610, with the password when the recent authentication has lapsed; the page is left on UXP-609. */
async function newRegister(page: Page, password: string | null): Promise<{ code: string; label: string }> {
    const register = { code: `L-${letters(8).toUpperCase()}`, label: `Laci ${letters(6)}` };

    await page.goto(`${REGISTERS}/new`);
    await page.getByLabel('Kode', { exact: true }).fill(register.code);
    await page.getByLabel('Label', { exact: true }).fill(register.label);

    if (password === null) {
        await Promise.all([page.waitForURL((url) => url.pathname === REGISTERS), page.getByRole('button', { name: 'Simpan', exact: true }).click()]);
    } else {
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
        await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
        await Promise.all([page.waitForURL((url) => url.pathname === REGISTERS), dialog.getByRole('button', { name: 'Konfirmasi' }).click()]);
    }

    await expect(page.getByText('Laci kas disimpan.')).toBeVisible();

    return register;
}

test('the Owner adds and changes a register from the settings, with the password once', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const password = await signedIn(context, page, 'Hendra');

    // UXI-328 on UXP-601, then UXP-609 and its "Tambah laci kas" (UXI-367).
    await page.goto('/settings');
    await expect(page.getByText('Laci kas yang dipakai untuk sesi kas.')).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === REGISTERS), page.getByRole('link', { name: /^Laci kas/ }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Laci kas' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Pengaturan' })).toHaveAttribute('href', '/settings');
    await Promise.all([page.waitForURL((url) => url.pathname === `${REGISTERS}/new`), page.getByRole('link', { name: 'Tambah laci kas' }).click()]);

    // UXP-610: the form, its reason while the code and the label are empty (UXS-13), Enter sending nothing (UXA-10).
    await expect(page.getByRole('heading', { level: 1, name: 'Tambah laci kas' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke Laci kas' })).toHaveAttribute('href', REGISTERS);
    await expect(page.getByText('Kode, label, dan status hanya bisa diubah saat tidak ada sesi kas yang dibuka di laci kas ini.')).toBeVisible();
    await expect(page.getByLabel('Aktif')).toBeChecked();
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Lengkapi kode dan label.')).toBeVisible();
    await checked(page);
    await page.getByLabel('Kode', { exact: true }).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // Saved with the password once (UXS-11), then UXP-609 with its message and the row.
    await page.goto(REGISTERS);
    const first = await newRegister(page, password);
    const row = page.getByRole('main').getByRole('row').filter({ hasText: first.code });
    await expect(row).toContainText(first.label);
    await expect(row).toContainText('Aktif');
    await expect(row).toContainText('Tidak ada');
    await checked(page);

    // A code that another register holds, in another letter case, is said at its field (UXS-05).
    await page.goto(`${REGISTERS}/new`);
    await page.getByLabel('Kode', { exact: true }).fill(first.code.toLowerCase());
    await page.getByLabel('Label', { exact: true }).fill('Laci kembar');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Kode ini sudah dipakai laci kas lain.').first()).toBeVisible();
    await expect(page.getByLabel('Kode', { exact: true })).toHaveValue(first.code.toLowerCase());
    expect(new URL(page.url()).pathname).toBe(`${REGISTERS}/new`);
    await checked(page);

    // "Ubah laci kas" names its register (UXI-368); nothing changed yet says so; a new label is saved.
    await page.goto(REGISTERS);
    await Promise.all([page.waitForURL((url) => url.pathname.endsWith('/edit')), page.getByRole('link', { name: `Ubah laci kas ${first.code} · ${first.label}` }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Ubah laci kas' })).toBeVisible();
    await expect(page.getByText('Belum ada perubahan.')).toBeVisible();
    // Outer spaces alone change nothing, since what is sent is trimmed (review finding R-04).
    await page.getByLabel('Label', { exact: true }).fill(`  ${first.label}  `);
    await expect(page.getByText('Belum ada perubahan.')).toBeVisible();
    const label = `Laci ${letters(6)}`;
    await page.getByLabel('Label', { exact: true }).fill(label);
    await Promise.all([page.waitForURL((url) => url.pathname === REGISTERS), page.getByRole('button', { name: 'Simpan', exact: true }).click()]);
    await expect(page.getByText('Laci kas disimpan.')).toBeVisible();
    await expect(page.getByRole('main').getByRole('row').filter({ hasText: first.code })).toContainText(label);

    expect(injected(watched.problems).filter((problem) => !problem.includes('status of 423') && !problem.includes('status of 422'))).toEqual([]);
    await context.close();
});

test('the registers are a table that becomes cards, at every width', async ({ browser }) => {
    for (const width of WIDTHS) {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Hendra');
        await page.goto(REGISTERS);
        await expect(page.getByRole('heading', { level: 1, name: 'Laci kas' })).toBeVisible();
        await checked(page);

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    }
});

test('the Owner opens his own session with Rp0 through the review, and the register then holds it', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const password = await signedIn(context, page, 'Hendra');
    await page.goto(REGISTERS);
    const register = await newRegister(page, password);
    const name = `${register.code} · ${register.label}`;

    // UXI-002 "Kasir" leads to Buka sesi kas while the Owner holds no session (KS-06 `default`).
    await Promise.all([page.waitForURL((url) => url.pathname === OPEN_SESSION), page.getByRole('navigation', { name: 'Menu utama' }).getByRole('link', { name: 'Kasir' }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Buka sesi kas' })).toBeVisible();
    await expect(page.getByText('Hitung uang di laci, lalu buka sesi kas atas nama Anda.')).toBeVisible();
    await expect(page.getByText('Jumlah uang di laci saat ini. Rp0 boleh.')).toBeVisible();
    await page.getByRole('button', { name: /^Tinjau/ }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByText('Pilih laci kas dan isi modal awal.')).toBeVisible();
    await checked(page);

    // The register and Rp0; Enter in the amount sends nothing (UXA-10).
    const choice = page.getByRole('radio', { name });

    if ((await choice.count()) > 0) {
        await choice.click();
    } else {
        await page.getByLabel('Laci kas', { exact: true }).selectOption({ label: name });
    }

    await page.getByLabel('Modal awal', { exact: true }).fill('0');
    await page.getByLabel('Modal awal', { exact: true }).press('Enter');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // KS-06 `review`: "Kembali ubah" first, the choice and the amount kept.
    await page.getByRole('button', { name: /^Tinjau/ }).click();
    const review = page.getByRole('dialog', { name: 'Tinjau buka sesi kas' });
    await expect(review).toContainText(`Laci kas ${name}, modal awal Rp0. Anda menjadi penanggung jawab sesi kas ini sampai Anda menutupnya.`);
    await expect(review.getByRole('button', { name: /^Kembali ubah/ })).toBeFocused();
    await checked(page);
    await review.getByRole('button', { name: /^Kembali ubah/ }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByLabel('Modal awal', { exact: true })).toHaveValue('0');

    // "Buka sesi" commits; the Owner's home until the till exists says the session's number.
    await page.getByRole('button', { name: /^Tinjau/ }).click();
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), page.getByRole('dialog', { name: 'Tinjau buka sesi kas' }).getByRole('button', { name: /^Buka sesi/ }).click()]);
    await expect(page.getByText(/^Sesi kas \d{8}-\d{6,} dibuka\.$/)).toBeVisible();

    // With a session, Buka sesi kas leads home (RP-104).
    await page.goto(OPEN_SESSION);
    expect(new URL(page.url()).pathname).toBe(HOME);

    // UXP-609: the session on its register, which cannot be changed now (UXS-13; BR-CASH-02).
    await page.goto(REGISTERS);
    const row = page.getByRole('main').getByRole('row').filter({ hasText: register.code });
    await expect(row).toContainText('Dibuka oleh Hendra');
    await expect(row.getByRole('button', { name: `Ubah laci kas ${name}` })).toHaveAttribute('aria-disabled', 'true');
    await expect(row).toContainText('Laci kas ini sedang dipakai sesi kas yang dibuka.');
    await checked(page);

    // UXP-602: the mark of the session (UXI-334).
    await page.goto('/settings/users');
    await expect(page.getByRole('main').getByRole('row').filter({ hasText: 'Hendra' })).toContainText('Memegang sesi kas');

    expect(injected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
    await context.close();
});

test('the combined account sees the form at every width, Batal leads to Stok, and no register available is UXS-48', async ({ browser }) => {
    for (const width of WIDTHS) {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await signedIn(context, page, 'Dewi');
        await page.goto(OPEN_SESSION);
        await expect(page.getByRole('heading', { level: 1, name: 'Buka sesi kas' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Batal' })).toHaveAttribute('href', STOCK_HOME);
        await checked(page);

        expect(injected(watched.problems)).toEqual([]);
        await context.close();
    }

    // KS-06 `no-register`: every register in use or inactive, answered so by the server (UXI-094).
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();

    await signedIn(context, page, 'Dewi');
    await page.route(`**${OPEN_SESSION}`, async (route) => {
        const response = await route.fetch();
        const body = (await response.text()).replace(/("|&quot;)registers\1:\[[^\]]*\]/, '$1registers$1:[]');

        await route.fulfill({ response, body });
    });
    await page.goto(OPEN_SESSION);
    await expect(page.getByText('Tidak ada laci kas yang tersedia')).toBeVisible();
    await expect(page.getByText('Untuk serah terima, kasir sebelumnya menutup sesinya dulu, lalu Anda membuka sesi baru.', { exact: false })).toBeVisible();
    await expect(page.getByRole('button', { name: /^Tinjau/ })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Batal' })).toHaveAttribute('href', STOCK_HOME);
    await checked(page);
    await context.close();
});
