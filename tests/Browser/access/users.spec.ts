import AxeBuilder from '@axe-core/playwright';
import { expect as baseExpect, test, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, HOME, newPassword, resetPeople, SECURE_LOCAL_ORIGIN, signIn, unexpected, type Account } from '../support/access';
import { envelopeOf, injected, prepareCommands, refuse } from '../support/commands';
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
        const address = new URL(url);
        await theirs.goto(`${address.pathname}${address.hash}`);
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
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Cabut tautan' }).click();
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
    await expect(page.getByRole('button', { name: 'Tindakan lain' })).toHaveCount(0);

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

test('an idle form adopts the request of another tab, and returns to its draft when that request is resolved (OPEN_ITEMS T-34 (f))', async ({ browser }) => {
    // Review finding R-09: the other tab's request is held, so the page really
    // adopts it, and released; the form then sends its own request.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const who = person('Wati');

    await createAccount(page, who, hendra.password);
    await page.waitForURL((url) => /\/link$/.test(url.pathname));

    // The form lies idle, with a change, while another tab sends.
    await page.goto('/account/details');
    await page.getByLabel('Nama tampilan').fill('Hendra Lain');
    const save = page.getByRole('button', { name: 'Simpan perubahan' });
    await expect(save).not.toHaveAttribute('aria-disabled', 'true');

    const other = await context.newPage();
    const releases: (() => void)[] = [];
    const held = new Promise<void>((resolve) => {
        releases.push(resolve);
    });
    await other.route('**/commands/user.create', async (route) => {
        await held;
        await route.continue();
    });
    await createAccount(other, person('Yani'), null);

    // While it is out, the page shows its panel and blocks its own action.
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await expect(save).toHaveAttribute('aria-disabled', 'true');

    releases[0]?.();
    await other.waitForURL((url) => /\/link$/.test(url.pathname));
    await other.close();

    // Resolved elsewhere: the panel is gone and the draft is as it was.
    await expect(page.getByText('Status belum pasti')).toHaveCount(0);
    await expect(page.getByLabel('Nama tampilan')).toHaveValue('Hendra Lain');
    await expect(save).not.toHaveAttribute('aria-disabled', 'true');

    const sent = page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === '/commands/owner.update_details');
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), save.click()]);
    await sent;

    // Back to the name of every other test.
    await page.goto('/account/details');
    await page.getByLabel('Nama tampilan').fill('Hendra');
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), save.click()]);

    expect(unexpected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
    await context.close();
});

test('a page that adopted a committed request of another operation says what is true of it, and keeps its draft', async ({ browser }) => {
    // Review finding R-01: RC-35's answer is lost; on UXP-005 its status check
    // answers COMMITTED, which is not "Nama dan email disimpan." and not a
    // reason to leave the form.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);

    await page.route('**/commands/user.create', async (route) => {
        await route.fetch();
        await route.abort();
    });
    await createAccount(page, person('Lina'), hendra.password);
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await page.unroute('**/commands/user.create');

    await page.goto('/account/details');
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await page.getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByText('Sudah tersimpan, tetapi tautannya tidak bisa ditampilkan lagi.', { exact: false })).toBeVisible();
    await expect(page.getByText('Nama dan email disimpan.')).toHaveCount(0);
    expect(new URL(page.url()).pathname).toBe('/account/details');

    // The draft goes on with a request of its own.
    await page.getByRole('button', { name: 'Kembali ubah' }).click();
    await page.getByLabel('Nama tampilan').fill('Hendra Lain');
    await expect(page.getByRole('button', { name: 'Simpan perubahan' })).not.toHaveAttribute('aria-disabled', 'true');
    await page.getByLabel('Nama tampilan').fill('Hendra');

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

/** RC-35 commits and its answer never arrives: the page stays on the uncertain panel. Gives the request's intent. */
async function loseCreateAnswer(page: Page, who: { name: string; email: string }, password: string | null): Promise<string> {
    const intents: string[] = [];

    await page.route('**/commands/user.create', async (route) => {
        intents.push(envelopeOf(route.request()).sourceIntentId);
        await route.fetch();
        await route.abort();
    });
    await createAccount(page, who, password);
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await page.unroute('**/commands/user.create');

    return intents[0] ?? '';
}

/** The status check of the panel, which answers COMMITTED without the link. */
async function checkCommitted(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Cek status' }).click();
    await expect(page.getByText('Sudah tersimpan, tetapi tautannya tidak bisa ditampilkan lagi.', { exact: false })).toBeVisible();
}

/** Sends the final action without "Kembali ubah"; a request of a new intent has to leave. */
async function sendsNewRequest(page: Page, final: Locator, operation: string, adopted: string): Promise<void> {
    await expect(final).not.toHaveAttribute('aria-disabled', 'true');
    const sent = page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === `/commands/${operation}`);
    await final.click();
    const intent = envelopeOf(await sent).sourceIntentId;

    expect(adopted).not.toBe('');
    expect(intent).not.toBe(adopted);
}

// Review finding R-26: after the status check of an adopted request says COMMITTED,
// the block has ended (NC-13 (e)) and the final action of each form of the family
// sends a new request, with no "Kembali ubah" first.
test('UXP-603: a final action sends after an adopted request committed, and is gone after the own request committed', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const final = page.getByRole('button', { name: 'Simpan dan buat tautan' });

    // R-27, R-28: the page's own request committed: this draft is what was saved.
    await loseCreateAnswer(page, person('Lina'), hendra.password);
    await checkCommitted(page);
    await expect(page.getByRole('link', { name: 'Detail pengguna' })).toBeVisible();
    await expect(final).toHaveCount(0);
    await expect(page.getByLabel('Nama tampilan')).toHaveCount(0);

    const adopted = await loseCreateAnswer(page, person('Mira'), null);
    await page.goto(`${USERS}/new`);
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await checkCommitted(page);

    const who = person('Nina');
    await page.getByLabel('Nama tampilan').fill(who.name);
    await page.getByLabel(/^Email/).fill(who.email);
    await page.getByRole('checkbox', { name: /Kasir/ }).check();
    await sendsNewRequest(page, final, 'user.create', adopted);
    await page.waitForURL((url) => /\/link$/.test(url.pathname));
    await expect(page.getByText(`Tautan kata sandi pertama untuk ${who.name}`)).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('UXP-605: a final action sends after an adopted request committed', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const who = person('Oki');
    const renamed = person('Oka').name;

    const adopted = await loseCreateAnswer(page, who, hendra.password);
    await openFromList(page, who.name);
    const detail = new URL(page.url()).pathname;
    await page.goto(`${detail}/edit`);
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await checkCommitted(page);

    await page.getByLabel('Nama tampilan').fill(renamed);
    await sendsNewRequest(page, page.getByRole('button', { name: 'Simpan perubahan' }), 'user.update', adopted);
    await page.waitForURL((url) => url.pathname === detail);
    await expect(page.getByRole('main').getByText(renamed)).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

test('UXP-005: a final action sends after an adopted request committed', async ({ browser }) => {
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const save = page.getByRole('button', { name: 'Simpan perubahan' });

    const adopted = await loseCreateAnswer(page, person('Rani'), hendra.password);
    await page.goto('/account/details');
    await expect(page.getByText('Status belum pasti')).toBeVisible();
    await checkCommitted(page);

    await page.getByLabel('Nama tampilan').fill('Hendra Lain');
    await sendsNewRequest(page, save, 'owner.update_details', adopted);
    await page.waitForURL((url) => url.pathname === HOME);

    // Back to the name of every other test.
    await page.goto('/account/details');
    await page.getByLabel('Nama tampilan').fill('Hendra');
    await Promise.all([page.waitForURL((url) => url.pathname === HOME), save.click()]);

    expect(injected(watched.problems)).toEqual([]);
    await context.close();
});

// R-02: the rules of EXC-53 on UXP-604, in its states and dialogs, UXP-605 and UXP-005,
// at the widths of the pages above. R-05: on a phone the actions are in one menu.
for (const width of [WIDTHS[0], WIDTHS[4], WIDTHS[7]]) {
    test(`the account's pages meet the rules in each state at ${width?.name ?? ''}`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();
        const watched = await watch(page);
        const hendra = await owner(context, page);
        const who = person('Dian');
        const phone = width === WIDTHS[7];

        /** Opens the dialog of an action: from the page's button, or from the one menu on a phone. */
        const open = async (name: string): Promise<void> => {
            if (phone) {
                await page.getByRole('button', { name: 'Tindakan lain' }).click();
                await expect(page.getByRole('button', { name, exact: true })).toHaveCount(0);
                await checked(page);
                await page.getByRole('menuitem', { name }).click();
            } else {
                await page.getByRole('button', { name, exact: true }).click();
            }

            await expect(page.getByRole('dialog')).toBeVisible();
        };

        await createAccount(page, who, hendra.password);
        await page.waitForURL((url) => /\/link$/.test(url.pathname));
        await Promise.all([page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname)), page.getByRole('link', { name: 'Selesai' }).click()]);
        const detail = new URL(page.url()).pathname;

        // An active account with a link.
        await expect(page.getByRole('heading', { level: 1, name: 'Detail pengguna' })).toBeVisible();
        await checked(page);
        await open('Nonaktifkan');
        await checked(page);
        await page.getByRole('dialog').getByRole('button', { name: 'Batal' }).click();

        // UXP-605 and UXP-005.
        await page.goto(`${detail}/edit`);
        await expect(page.getByRole('heading', { level: 1, name: 'Ubah pengguna' })).toBeVisible();
        await checked(page);
        await page.goto('/account/details');
        await expect(page.getByRole('heading', { level: 1, name: 'Ubah nama dan email' })).toBeVisible();
        await checked(page);

        // A deactivated account.
        await page.goto(detail);
        await open('Nonaktifkan');
        await page.getByRole('dialog').getByRole('button', { name: 'Nonaktifkan' }).click();
        await expect(page.getByText('Akun dinonaktifkan.')).toBeVisible();
        await checked(page);
        await open('Anonimkan akun');
        await checked(page);
        await page.getByRole('dialog').getByLabel('Alasan').fill('Permintaan orang yang bersangkutan.');
        await page.getByRole('dialog').getByRole('button', { name: 'Anonimkan akun' }).click();
        await expect(page.getByText('Akun dianonimkan.')).toBeVisible();
        await checked(page);

        expect(unexpected(watched.problems).filter((problem) => !problem.includes('status of 423'))).toEqual([]);
        await context.close();
    });
}

test('the way back of a child page returns to the filters of the list (NC-10)', async ({ browser }) => {
    // Review finding R-04.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    await owner(context, page);

    await page.goto(USERS);
    await Promise.all([page.waitForURL((url) => url.search === '?role=owner'), page.getByRole('button', { name: 'Pemilik' }).click()]);
    await expect(page.getByRole('button', { name: 'Pemilik' })).toHaveAttribute('aria-pressed', 'true');
    await Promise.all([page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname)), page.getByRole('link', { name: 'Hendra' }).click()]);

    const back = page.getByRole('link', { name: 'Kembali ke Pengguna' });
    await expect(back).toHaveAttribute('href', `${USERS}?role=owner`);
    await Promise.all([page.waitForURL((url) => url.pathname === USERS && url.search === '?role=owner'), back.click()]);
    await expect(page.getByRole('button', { name: 'Pemilik' })).toHaveAttribute('aria-pressed', 'true');

    await context.close();
});

test('Enter never sends a final action, leaving a changed form asks first, a changed account shows its current values, and a second link in a minute waits', async ({ browser }) => {
    // Review finding R-10: UXA-10, UXS-45, UXS-17 and UXS-12 on these pages.
    const context = await contextAt(browser, WIDTHS[0]!);
    const page = await context.newPage();
    const watched = await watch(page);
    const hendra = await owner(context, page);
    const who = person('Rudi');

    // UXA-10: an activated action would ask for the password first.
    let sent = 0;
    await page.route('**/commands/**', async (route) => {
        sent += 1;
        await route.continue();
    });
    const confirmation = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });

    await page.goto(`${USERS}/new`);
    await page.getByLabel('Nama tampilan').fill(who.name);
    await page.getByLabel(/^Email/).fill(who.email);
    await page.getByRole('checkbox', { name: /Kasir/ }).check();
    await page.getByRole('button', { name: 'Simpan dan buat tautan' }).focus();
    await page.keyboard.press('Enter');
    await page.getByLabel('Nama tampilan').press('Enter');
    await page.waitForTimeout(500);
    await expect(confirmation).toHaveCount(0);
    expect(sent).toBe(0);

    await page.goto('/account/details');
    await page.getByLabel('Nama tampilan').fill('Hendra Lain');
    await page.getByRole('button', { name: 'Simpan perubahan' }).focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(500);
    await expect(confirmation).toHaveCount(0);
    expect(sent).toBe(0);

    // UXS-45: leaving a changed form asks, and "Tetap di halaman ini" stays.
    await page.getByRole('link', { name: 'Batal' }).click();
    await page.getByRole('button', { name: 'Tetap di halaman ini' }).click();
    expect(new URL(page.url()).pathname).toBe('/account/details');
    await expect(page.getByLabel('Nama tampilan')).toHaveValue('Hendra Lain');
    await page.unroute('**/commands/**');

    // The account, and UXS-12: a second link within the minute.
    await createAccount(page, who, hendra.password);
    await page.waitForURL((url) => /\/link$/.test(url.pathname));
    await Promise.all([page.waitForURL((url) => /\/settings\/users\/\d+$/.test(url.pathname)), page.getByRole('link', { name: 'Selesai' }).click()]);
    const detail = new URL(page.url()).pathname;
    await page.getByRole('button', { name: 'Buat ulang tautan kata sandi pertama' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Buat tautan' }).click();
    await expect(page.getByText('Terlalu banyak percobaan', { exact: false })).toBeVisible();
    await expect(page.locator('.bk-link-text')).toHaveCount(0);

    // UXS-17: another tab changes the account while the form is open.
    await page.goto(`${detail}/edit`);
    await page.getByLabel('Nama tampilan').fill(`${who.name} Dua`);
    const other = await context.newPage();
    await other.goto(`${detail}/edit`);
    await other.getByLabel('Nama tampilan').fill(`${who.name} Tiga`);
    await Promise.all([other.waitForURL((url) => url.pathname === detail), other.getByRole('button', { name: 'Simpan perubahan' }).click()]);
    await other.close();

    await page.getByRole('button', { name: 'Simpan perubahan' }).click();
    await expect(page.getByText('Data sudah berubah')).toBeVisible();
    await expect(page.getByRole('main').getByText(`${who.name} Tiga`)).toBeVisible();
    await expect(page.getByLabel('Nama tampilan')).toHaveValue(`${who.name} Dua`);
    await checked(page);

    // Reviewed, the next save is expected against the current version.
    await Promise.all([page.waitForURL((url) => url.pathname === detail), page.getByRole('button', { name: 'Simpan perubahan' }).click()]);
    await expect(page.getByText('Perubahan pengguna disimpan.')).toBeVisible();

    expect(injected(watched.problems)).toEqual([]);
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
