import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, newPassword, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected, type Account } from '../support/access';
import { injected, prepareCommands, refuse } from '../support/commands';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// UXP-005 and UXP-602 to UXP-606 (AC-011-05, AC-011-07; COPY_DECK sections 6.6 and 11.2
// to 11.6; NAVIGATION_CONTRACTS; suites UX01, UX02, LINK01; TASK-011, L5): the Owner
// creates a staff account and hands over its link, which exists only in page memory and
// is never shown again; the staff member sets the password with it; the Owner changes
// the account, issues and revokes links, deactivates, reactivates and anonymizes it;
// the Owner's own details; a real refusal at its field and a 401 on a first command page
// (OPEN_ITEMS T-34 (a)); an idle form returns to its draft when another tab's request
// resolves (T-34 (f)). Every other role is refused the pages. These tests sign in the
// people of Toko Contoh, so they run one at a time.

test.describe.configure({ timeout: ACCESS_TIMEOUT });
test.use(SECURE_LOCAL_ORIGIN);

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const USERS = '/settings/users';

/** A new person for each run: the local database keeps its accounts. */
function person(prefix: string): { name: string; email: string } {
    const suffix = Array.from(crypto.getRandomValues(new Uint8Array(4)), (byte) => byte.toString(16).padStart(2, '0')).join('');

    return { name: `${prefix} ${suffix}`, email: `${prefix.toLowerCase()}.${suffix}@contoh.test` };
}

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

/** The recent-authentication dialog, the first time in a sign-in session (UXS-11). */
async function confirmPassword(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Konfirmasi' }).click();
}

/** Fills UXP-603 and sends it, confirming the password when asked. */
async function createAccount(page: Page, who: { name: string; email: string }, password: string | null): Promise<void> {
    await page.goto(`${USERS}/new`);
    await page.getByLabel('Nama tampilan').fill(who.name);
    await page.getByLabel(/^Email/).fill(who.email);
    await page.getByRole('checkbox', { name: /Kasir/ }).check();
    await page.getByRole('button', { name: 'Simpan dan buat tautan' }).click();

    if (password !== null) {
        await confirmPassword(page, password);
    }
}

/** The account's detail page, from the list. */
async function openFromList(page: Page, name: string): Promise<void> {
    await page.goto(USERS);
    await Promise.all([page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname)), page.getByRole('link', { name }).click()]);
}

async function owner(context: BrowserContext, page: Page): Promise<Account> {
    const { Hendra: hendra } = await resetPeople(context, ['Hendra']);

    await prepareCommands(context, ['Hendra']);
    await signIn(page, hendra);

    return hendra;
}

// 1440 × 900, the 200 % zoom of 1280 × 800 and 320 × 640.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`the Owner creates a staff account and hands over its link, shown once, at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        await context.grantPermissions(['clipboard-read', 'clipboard-write']).catch(() => undefined);
        const page = await context.newPage();
        const watched = await watch(page);
        const hendra = await owner(context, page);
        const who = person('Budi');

        // UXI-326 from the settings, then UXP-602 with its button.
        await page.goto('/settings');
        await Promise.all([page.waitForURL((url) => url.pathname === USERS), page.getByRole('link', { name: /Pengguna/ }).click()]);
        await expect(page.getByRole('heading', { level: 1, name: 'Pengguna' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Kembali ke Pengaturan' })).toBeVisible();
        await checked(page);
        await Promise.all([page.waitForURL((url) => url.pathname === `${USERS}/new`), page.getByRole('link', { name: 'Tambah pengguna' }).click()]);

        // UXP-603: the final action says why it waits, and Enter never sends it.
        const final = page.getByRole('button', { name: 'Simpan dan buat tautan' });
        await expect(final).toHaveAttribute('aria-disabled', 'true');
        await expect(page.getByText('Lengkapi isian yang wajib dulu.')).toBeVisible();
        await checked(page);
        await createAccount(page, who, hendra.password);

        // UXP-606: the link, only from the answer.
        await page.waitForURL((url) => /\/settings\/users\/\d+\/link$/.test(url.pathname));
        await expect(page.getByRole('heading', { level: 1, name: 'Tautan kata sandi' })).toBeVisible();
        await expect(page.getByText(`Tautan kata sandi pertama untuk ${who.name}`)).toBeVisible();
        await expect(page.getByText(`Pengguna ${who.name} ditambahkan.`)).toBeVisible();
        await expect(page.getByText('Tautan ini hanya ditampilkan sekali.')).toBeVisible();
        await expect(page.getByText(`Serahkan tautan ini langsung kepada ${who.name}.`, { exact: false })).toBeVisible();
        await expect(page.getByRole('img', { name: `Kode QR tautan kata sandi untuk ${who.name}` })).toBeVisible();
        const url = (await page.locator('.bk-link-text').textContent()) ?? '';
        expect(url).toMatch(/\/set-password#u=\d+&t=/);
        await checked(page);

        // Never in the address, the history state, the storage or the page's props.
        const token = url.slice(url.indexOf('&t=') + 3);
        const held = await page.evaluate(() => ({
            address: window.location.href,
            history: JSON.stringify(window.history.state),
            local: JSON.stringify(window.localStorage),
            session: JSON.stringify(window.sessionStorage),
            page: document.querySelector('[data-page]')?.getAttribute('data-page') ?? '',
        }));
        expect(held.address).not.toContain(token);
        expect(held.history).not.toContain(token);
        expect(held.local).not.toContain(token);
        expect(held.session).not.toContain(token);
        expect(held.page).not.toContain(token);

        // A reload shows UXS-49, with the way back, and never the link.
        const linkPage = page.url();
        await page.reload();
        await expect(page.getByText('Tautan tidak ditampilkan lagi')).toBeVisible();
        await expect(page.locator('.bk-link-text')).toHaveCount(0);
        await checked(page);
        await Promise.all([page.waitForURL((address) => /\/settings\/users\/\d+$/.test(address.pathname)), page.getByRole('link', { name: 'Kembali ke Detail pengguna' }).click()]);

        // A new visit too.
        await page.goto(linkPage);
        await expect(page.getByText('Tautan tidak ditampilkan lagi')).toBeVisible();

        // The staff member sets the first password with it, signed out, elsewhere.
        const other = await contextAt(browser, WIDTHS[0]!);
        const theirs = await other.newPage();
        const password = newPassword();
        await theirs.goto(url);
        await expect.poll(() => new URL(theirs.url()).hash).toBe('');
        await theirs.getByLabel('Kata sandi baru', { exact: true }).fill(password);
        await theirs.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(password);
        await theirs.getByRole('button', { name: 'Simpan kata sandi' }).click();
        await expect(theirs.getByText('Kata sandi tersimpan.', { exact: false })).toBeVisible();
        await other.close();

        expect(unexpected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
        await context.close();
    });
}

test('the Owner changes an account, issues and revokes its links, deactivates, reactivates and anonymizes it', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const who = person('Sari');

    await createAccount(page, who, hendra.password);
    await page.waitForURL((url) => /\/link$/.test(url.pathname));
    await Promise.all([page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname)), page.getByRole('link', { name: 'Selesai' }).click()]);

    // UXP-604: a staff account without a password, its first link active.
    await expect(page.getByRole('heading', { level: 1, name: 'Detail pengguna' })).toBeVisible();
    await expect(page.getByText('Belum mengatur kata sandi')).toBeVisible();
    await expect(page.getByText(/Aktif sampai/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Buat ulang tautan kata sandi pertama' })).toBeVisible();
    await checked(page);

    // UXI-344: the dialog asks, Enter on its final action sends nothing, and the
    // command updates the page in place with its message.
    await page.getByRole('button', { name: 'Cabut tautan' }).click();
    const revoke = page.getByRole('dialog', { name: `Cabut tautan kata sandi ${who.name}?` });
    await expect(revoke).toBeVisible();
    await checked(page);
    await revoke.getByRole('button', { name: 'Cabut tautan' }).focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(500);
    await expect(revoke).toBeVisible();
    await revoke.getByRole('button', { name: 'Cabut tautan' }).click();
    await expect(page.getByText('Tautan dicabut.')).toBeVisible();
    await expect(page.getByText('Tidak ada tautan aktif')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cabut tautan' })).toHaveCount(0);

    // UXI-343: a new first link goes to UXP-606, shown once.
    await page.getByRole('button', { name: 'Buat ulang tautan kata sandi pertama' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Buat tautan' }).click();
    await page.waitForURL((url) => /\/link$/.test(url.pathname));
    await expect(page.locator('.bk-link-text')).toContainText('/set-password#u=');
    await page.goBack();
    await page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname));
    await page.goForward();
    await expect(page.getByText('Tautan tidak ditampilkan lagi')).toBeVisible();
    await expect(page.locator('.bk-link-text')).toHaveCount(0);
    await page.getByRole('link', { name: 'Kembali ke Detail pengguna' }).click();

    // UXP-605: the save waits for a change; success leads back with its message.
    await page.getByRole('link', { name: 'Ubah pengguna' }).click();
    await page.waitForURL((url) => /\/edit$/.test(url.pathname));
    const save = page.getByRole('button', { name: 'Simpan perubahan' });
    await expect(save).toHaveAttribute('aria-disabled', 'true');
    await checked(page);
    await page.getByLabel('Nama tampilan').fill(`${who.name} Baru`);
    await page.getByRole('checkbox', { name: /Admin Operasional/ }).check();
    await save.click();
    await page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname));
    await expect(page.getByText('Perubahan pengguna disimpan.')).toBeVisible();
    await expect(page.getByText('Kasir, Admin Operasional')).toBeVisible();

    // UXI-345, UXI-346 and UXI-347.
    await page.getByRole('button', { name: 'Nonaktifkan' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(page.getByText('Akun dinonaktifkan.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Aktifkan kembali' })).toBeVisible();
    await page.getByRole('button', { name: 'Aktifkan kembali' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Aktifkan kembali' }).click();
    await expect(page.getByText('Akun diaktifkan kembali.')).toBeVisible();
    await page.getByRole('button', { name: 'Nonaktifkan' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(page.getByText('Akun dinonaktifkan.')).toBeVisible();

    await page.getByRole('button', { name: 'Anonimkan akun' }).click();
    const anonymize = page.getByRole('dialog');
    const confirm = anonymize.getByRole('button', { name: 'Anonimkan akun' });
    await expect(confirm).toHaveAttribute('aria-disabled', 'true');
    await anonymize.getByLabel('Alasan').fill('Permintaan orang yang bersangkutan.');
    await confirm.click();
    await expect(page.getByText('Akun dianonimkan.')).toBeVisible();
    await expect(page.getByText('Tidak ada (akun dianonimkan)')).toBeVisible();
    await expect(page.getByRole('main').getByText(/Pengguna dianonimkan \d+/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Aktifkan kembali' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Ubah pengguna' })).toHaveCount(0);
    await checked(page);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('the Owner changes the Owner own details, and the Owner account has no other control', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);

    // UXI-046 on UXP-003, then UXP-005.
    await expect(page.getByText('Pemilik toko. Mengatur pengguna', { exact: false })).toBeVisible();
    await Promise.all([page.waitForURL((url) => url.pathname === '/account/details'), page.getByRole('link', { name: 'Ubah nama dan email' }).click()]);
    await expect(page.getByRole('heading', { level: 1, name: 'Ubah nama dan email' })).toBeVisible();
    const save = page.getByRole('button', { name: 'Simpan perubahan' });
    await expect(save).toHaveAttribute('aria-disabled', 'true');
    await expect(page.getByText('Belum ada perubahan.')).toBeVisible();
    await checked(page);

    await page.getByLabel('Nama tampilan').fill('Hendra Sementara');
    await save.click();
    await confirmPassword(page, hendra.password);
    await page.waitForURL((url) => url.pathname === HOME);
    await expect(page.getByText('Nama dan email disimpan.')).toBeVisible();
    await expect(page.getByRole('main').getByText('Hendra Sementara')).toBeVisible();

    // The Owner's own account on UXP-604 says why it has no controls (UXI-348).
    await openFromList(page, 'Hendra Sementara');
    await expect(page.getByText('Peran akun Pemilik tidak bisa diubah', { exact: false })).toBeVisible();

    for (const control of ['Nonaktifkan', 'Anonimkan akun', 'Ubah pengguna', 'Cabut tautan']) {
        await expect(page.getByRole('main').getByRole('button', { name: control })).toHaveCount(0);
        await expect(page.getByRole('main').getByRole('link', { name: control })).toHaveCount(0);
    }

    // Back to the name of every other test.
    await page.goto('/account/details');
    await page.getByLabel('Nama tampilan').fill('Hendra');
    await save.click();
    await page.waitForURL((url) => url.pathname === HOME);

    expect(unexpected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
    await context.close();
});

test('a real refusal shows at its field with its focused summary, and a 401 on a first command leaves for the sign-in page', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const who = person('Tono');

    // OPEN_ITEMS T-34 (a): an email that another account holds, refused by the server.
    await page.goto(`${USERS}/new`);
    await page.getByLabel('Nama tampilan').fill(who.name);
    await page.getByLabel(/^Email/).fill(hendra.email);
    await page.getByRole('checkbox', { name: /Kasir/ }).check();
    await page.getByRole('button', { name: 'Simpan dan buat tautan' }).click();
    await confirmPassword(page, hendra.password);
    const summary = page.locator('.bk-error-summary');
    await expect(summary).toBeFocused();
    await expect(summary.getByText('Email ini sudah dipakai akun lain.')).toBeVisible();
    await expect(page.locator('.bk-field .bk-error').getByText('Email ini sudah dipakai akun lain.')).toBeVisible();
    await checked(page);
    await summary.getByRole('link', { name: 'Email' }).click();
    await expect(page.getByLabel(/^Email/)).toBeFocused();

    // A 401 of the command: the sign-in session has ended; the page leaves.
    await page.getByRole('button', { name: 'Kembali ubah' }).click();
    await page.getByLabel(/^Email/).fill(who.email);
    await page.route('**/commands/user.create', (route) => refuse(route, 401, 'UNAUTHENTICATED'));
    await page.getByRole('button', { name: 'Simpan dan buat tautan' }).click();
    await page.waitForURL((url) => url.pathname === '/' || url.pathname === '/login' || url.pathname === HOME);

    expect(injected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
    await context.close();
});

test('an idle form returns to its draft when the request that another tab sent is resolved (OPEN_ITEMS T-34 (f))', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const who = person('Wati');

    await createAccount(page, who, hendra.password);
    await page.waitForURL((url) => /\/link$/.test(url.pathname));

    // The form lies idle, with a change, while another tab sends and is answered.
    await page.goto('/account/details');
    await page.getByLabel('Nama tampilan').fill('Hendra Lain');
    const save = page.getByRole('button', { name: 'Simpan perubahan' });
    await expect(save).not.toHaveAttribute('aria-disabled', 'true');

    const other = await context.newPage();
    await createAccount(other, person('Yani'), null);
    await other.waitForURL((url) => /\/link$/.test(url.pathname));
    await other.close();

    // Its panel, if it adopted that request for a moment, is gone, and the final
    // action sends this form's own request.
    await expect(page.getByText('Status belum pasti')).toHaveCount(0);
    await expect(save).not.toHaveAttribute('aria-disabled', 'true');
    await page.getByLabel('Nama tampilan').fill('Hendra');
    await expect(save).toHaveAttribute('aria-disabled', 'true');

    expect(unexpected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
    await context.close();
});

test('every other role has no way to the user pages and is refused them', async ({ page, context }) => {
    const { Rina: rina } = await resetPeople(context, ['Rina']);

    await signIn(page, rina);
    await expect(page.getByRole('link', { name: 'Ubah nama dan email' })).toHaveCount(0);

    for (const path of [USERS, `${USERS}/new`, '/account/details']) {
        const refused = await page.goto(path);

        expect(refused?.status()).toBe(403);
        await expect(page.getByRole('heading', { level: 1, name: 'Anda tidak memiliki akses' })).toBeVisible();
    }
});
